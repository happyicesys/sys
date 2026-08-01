<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApkReleaseResource;
use App\Jobs\PublishMqtt;
use App\Models\ApkRelease;
use App\Models\Vend;
use App\Services\OtaChannelResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use ZipArchive;

/**
 * ApkReleaseController — admin UI for the APK OTA channels.
 *
 * Lives under Machine Management as "APK OTA Updates". One page, one tab per
 * channel (see config/ota.php): upload a signed build, verify it, stage its
 * rollout, publish/pause it, and nudge that channel's fleet to check now.
 *
 * Every read and write is channel-scoped, so a vending release can never appear in
 * — or be published to — the smart-freezer fleet, and vice versa.
 *
 * This controller only touches apk_releases and reads vends. It never mutates
 * anything the legacy vending fleet depends on at runtime.
 */
class ApkReleaseController extends Controller
{
    public function __construct(private OtaChannelResolver $channels)
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $channel = $this->channels->normalise($request->query('channel'));

        $releases = ApkRelease::query()
            ->with('uploader')
            ->channel($channel)
            ->orderByDesc('version_code')
            ->get();

        // Flag the one row actually served as this channel's live manifest, so the
        // page can say "live" instead of making the operator infer it from status.
        $liveId = $releases->firstWhere('status', ApkRelease::STATUS_PUBLISHED)?->id;
        $releases->each(fn ($r) => $r->is_live = ($r->id === $liveId));

        return Inertia::render('ApkRelease/Index', [
            'channel' => $channel,
            'channels' => collect($this->channels->all())
                ->map(fn ($cfg, $key) => [
                    'key' => $key,
                    'label' => $cfg['label'] ?? $key,
                    'package_name' => $cfg['package_name'] ?? null,
                ])
                ->values(),
            // Bare array — the Vue page treats `releases` as a plain array, not {data:[]}.
            'releases' => ApkReleaseResource::collection($releases)->resolve(),
            'fleetVersions' => $this->fleetVersions($channel),
            'fleetCount' => $this->fleetQuery($channel)->count(),
        ]);
    }

    /**
     * Upload a new signed build. sha256 and size are computed from the uploaded
     * bytes here and never trusted from the client, so the on-device hash check is
     * anchored to exactly what mark1 stored.
     */
    public function storeRelease(Request $request)
    {
        $channel = $this->channels->normalise($request->input('channel'));

        $validated = $request->validate([
            'channel' => ['required', Rule::in($this->channels->keys())],
            'apk' => ['required', 'file', 'max:' . (int) config('ota.max_upload_kb', 262144)],
            // versionCode only has to be unique WITHIN its channel.
            'version_code' => [
                'required', 'integer', 'min:1',
                Rule::unique('apk_releases', 'version_code')->where(fn ($q) => $q->where('channel', $channel)),
            ],
            'version_name' => ['required', 'string', 'max:120'],
            'rollout_permille' => ['required', 'integer', 'min:0', 'max:1000'],
            'mandatory' => ['sometimes', 'boolean'],
            'min_supported_version_code' => ['nullable', 'integer', 'min:0'],
            'release_notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'version_code.unique' => 'That versionCode already exists in this channel.',
        ]);

        $file = $request->file('apk');

        if (! $this->looksLikeApk($file)) {
            return redirect()->back()
                ->withErrors(['apk' => 'That file is not a valid APK (no AndroidManifest.xml inside).'])
                ->withInput();
        }

        // Hash + size from the temp upload BEFORE moving it to storage.
        $sha256 = hash_file('sha256', $file->getRealPath());
        $sizeBytes = (int) $file->getSize();

        $folder = $this->channels->storageFolder($channel);
        $storedPath = $file->storePubliclyAs($folder, $this->fileName($channel, $validated));

        if ($storedPath === false) {
            return redirect()->back()->withErrors(['apk' => 'Upload failed — the file could not be stored.']);
        }

        // Copy-verify: a truncated PUT would otherwise be published with a hash the
        // device can never match, bricking the rollout silently.
        $storedSize = (int) Storage::size($storedPath);
        if ($storedSize !== $sizeBytes) {
            Storage::delete($storedPath);

            return redirect()->back()->withErrors([
                'apk' => "Upload incomplete ({$storedSize} of {$sizeBytes} bytes stored) — nothing was saved. Try again.",
            ]);
        }

        $release = ApkRelease::create([
            'channel' => $channel,
            'package_name' => $this->channels->packageName($channel),
            'version_code' => $validated['version_code'],
            'version_name' => $validated['version_name'],
            'file_url' => Storage::url($storedPath),
            'file_path' => $storedPath,
            'sha256' => $sha256,
            'size_bytes' => $sizeBytes,
            'mandatory' => $request->boolean('mandatory'),
            'min_supported_version_code' => $validated['min_supported_version_code'] ?? 0,
            'rollout_permille' => $validated['rollout_permille'],
            'status' => ApkRelease::STATUS_DRAFT,
            'release_notes' => $validated['release_notes'] ?? null,
            'uploaded_by' => $request->user()?->id,
        ]);

        return redirect()->back()->with(
            'success',
            "{$this->channels->label($channel)}: v{$release->version_name} (code {$release->version_code}) uploaded as draft."
        );
    }

    public function publish($id)
    {
        $release = ApkRelease::findOrFail($id);
        $release->update(['status' => ApkRelease::STATUS_PUBLISHED]);

        $live = ApkRelease::query()->liveManifest($release->channel)->first();
        $label = $this->channels->label($release->channel);

        // Publishing below the current live build is legal but a no-op for devices —
        // say so rather than letting it look like it took effect.
        if ($live && $live->id !== $release->id) {
            return redirect()->back()->with(
                'success',
                "{$label}: v{$release->version_name} published, but v{$live->version_name} (code {$live->version_code}) is still the live build — devices only ever take the highest published versionCode."
            );
        }

        return redirect()->back()->with(
            'success',
            "{$label}: v{$release->version_name} is now live to the fleet (rollout {$release->rollout_permille}‰)."
        );
    }

    public function unpublish($id)
    {
        $release = ApkRelease::findOrFail($id);
        $release->update(['status' => ApkRelease::STATUS_DRAFT]);

        return redirect()->back()->with(
            'success',
            "{$this->channels->label($release->channel)}: v{$release->version_name} unpublished — no longer offered."
        );
    }

    public function updateRollout(Request $request, $id)
    {
        $validated = $request->validate([
            'rollout_permille' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);

        $release = ApkRelease::findOrFail($id);
        $release->update(['rollout_permille' => $validated['rollout_permille']]);

        return redirect()->back()->with(
            'success',
            "Rollout for v{$release->version_name} set to {$release->rollout_permille}‰."
        );
    }

    public function toggleMandatory($id)
    {
        $release = ApkRelease::findOrFail($id);
        $release->update(['mandatory' => ! $release->mandatory]);

        return redirect()->back()->with(
            'success',
            "v{$release->version_name} mandatory flag " . ($release->mandatory ? 'ON' : 'OFF') . '.'
        );
    }

    public function destroy($id)
    {
        $release = ApkRelease::findOrFail($id);

        // Deleting the binary out from under a live manifest would hand every
        // polling device a 404 download. Force an explicit unpublish first.
        if ($release->status === ApkRelease::STATUS_PUBLISHED) {
            return redirect()->back()->withErrors([
                'release' => "v{$release->version_name} is published — unpublish it before deleting.",
            ]);
        }

        if ($release->file_path) {
            Storage::delete($release->file_path);
        }
        $release->delete();

        return redirect()->back()->with('success', "v{$release->version_name} deleted.");
    }

    /**
     * Nudge one channel's fleet to poll the manifest now instead of waiting for the
     * next scheduled check. Publishes an OTA_CHECK command using the same signed CSV
     * envelope as the other VMC/APK commands. The device still pulls, verifies the
     * hash and applies its own rollout gate — this only shortens the wait.
     */
    public function pushOtaCheck(Request $request)
    {
        $channel = $this->channels->normalise($request->input('channel'));

        $count = 0;
        $this->fleetQuery($channel)
            ->where('is_active', true)
            ->select(['id', 'code', 'private_key'])
            ->chunkById(200, function ($vends) use (&$count) {
                foreach ($vends as $vend) {
                    if (empty($vend->code)) {
                        continue;
                    }

                    PublishMqtt::dispatch('CM' . $vend->code, $this->otaCheckFrame($vend))->onQueue('high');
                    $count++;
                }
            });

        return redirect()->back()->with(
            'success',
            "OTA check pushed to {$count} machine(s) on {$this->channels->label($channel)}."
        );
    }

    /* ---------------------------------------------------------------- helpers */

    /** Base Vend query for one channel's fleet. */
    private function fleetQuery(string $channel)
    {
        return $this->channels->scopeFleet(Vend::query(), $channel);
    }

    /** How many machines on this channel report each APK versionCode. */
    private function fleetVersions(string $channel): array
    {
        return $this->fleetQuery($channel)
            ->selectRaw('apk_version_code, COUNT(*) as total')
            ->groupBy('apk_version_code')
            ->orderByRaw('apk_version_code IS NULL, apk_version_code DESC')
            ->get()
            ->map(fn ($row) => [
                'version_code' => $row->apk_version_code,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * Cheap structural check that the upload really is an APK: a ZIP container
     * holding an AndroidManifest.xml. Catches the wrong-file-picked mistake before
     * a bad binary is ever offered to a machine. Signature/versionCode verification
     * still happens on-device.
     */
    private function looksLikeApk(UploadedFile $file): bool
    {
        if (! class_exists(ZipArchive::class)) {
            return true; // ext-zip absent: fall back to the on-device checks
        }

        $zip = new ZipArchive();

        if ($zip->open($file->getRealPath()) !== true) {
            return false;
        }

        $hasManifest = $zip->locateName('AndroidManifest.xml') !== false;
        $zip->close();

        return $hasManifest;
    }

    /** Deterministic, human-readable stored filename. */
    private function fileName(string $channel, array $validated): string
    {
        $package = $this->channels->packageName($channel) ?: $channel;
        $slug = preg_replace('/[^A-Za-z0-9._-]/', '-', $package . '_' . $validated['version_code'] . '_' . $validated['version_name']);

        return trim($slug, '-') . '.apk';
    }

    /** Signed CSV MQTT envelope carrying the OTA_CHECK command. */
    private function otaCheckFrame(Vend $vend): string
    {
        $fid = 1;
        $content = base64_encode(json_encode([
            'Type' => 'OTA_CHECK',
            'time' => Carbon::now()->timestamp,
            'action' => '',
            'mid' => $vend->code,
        ]));
        $contentLength = strlen($content);
        $key = $vend->private_key ?: config('vend.private_key', '123456789110138A');
        $md5 = md5($fid . ',' . $contentLength . ',' . $content . $key);

        return $fid . ',' . $contentLength . ',' . $content . ',' . $md5;
    }
}

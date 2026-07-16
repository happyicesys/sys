<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApkReleaseResource;
use App\Jobs\PublishMqtt;
use App\Models\ApkRelease;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * SmartFreezerSettingController — admin UI for the Smart-Freezer APK OTA channel.
 *
 * Lives under the "Machine Management" side nav as "Smart Freezer Settings". Lets an
 * operator upload a signed APK build, verify it (sha256/size computed server-side),
 * stage its rollout, publish/pause it, and nudge the fleet to check for updates now.
 *
 * This controller only touches apk_releases + Smart-Freezer vends. It never mutates
 * anything the legacy vending fleet depends on.
 */
class SmartFreezerSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:read machine-settings')->only('index');
    }

    public function index(Request $request)
    {
        // Fleet version spread: how many Smart-Freezers are on each APK versionCode.
        $fleet = Vend::query()
            ->smart()
            ->selectRaw('apk_version_code, COUNT(*) as total')
            ->groupBy('apk_version_code')
            ->orderByRaw('apk_version_code IS NULL, apk_version_code DESC')
            ->get()
            ->map(fn ($row) => [
                'version_code' => $row->apk_version_code,
                'total' => (int) $row->total,
            ]);

        return Inertia::render('SmartFreezerSetting/Index', [
            'releases' => ApkReleaseResource::collection(
                ApkRelease::query()
                    ->with('uploader')
                    ->orderByDesc('version_code')
                    ->get()
            )->resolve(), // bare array — the Vue page treats `releases` as a plain array (not {data:[]})
            'fleetVersions' => $fleet,
            'smartFleetCount' => Vend::query()->smart()->count(),
        ]);
    }

    /**
     * Upload a new signed APK build. sha256 + size are computed from the uploaded
     * bytes here (never trusted from the client) so the on-device hash check is
     * anchored to exactly what mark1 stored.
     */
    public function storeRelease(Request $request)
    {
        $validated = $request->validate([
            'apk' => ['required', 'file', 'max:262144'], // 256 MB ceiling (also gate php.ini upload_max_filesize)
            'version_code' => ['required', 'integer', 'min:1', 'unique:apk_releases,version_code'],
            'version_name' => ['required', 'string', 'max:120'],
            'rollout_permille' => ['required', 'integer', 'min:0', 'max:1000'],
            'mandatory' => ['sometimes', 'boolean'],
            'min_supported_version_code' => ['nullable', 'integer', 'min:0'],
            'release_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $file = $request->file('apk');

        // Hash + size from the temp upload BEFORE moving it to storage.
        $sha256 = hash_file('sha256', $file->getRealPath());
        $sizeBytes = $file->getSize();

        $storedPath = $file->storePublicly('sys/vends/apk');
        $url = Storage::url($storedPath);

        $release = ApkRelease::create([
            'version_code' => $validated['version_code'],
            'version_name' => $validated['version_name'],
            'file_url' => $url,
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

        return redirect()->back()->with('success', "APK v{$release->version_name} (code {$release->version_code}) uploaded as draft.");
    }

    public function publish($id)
    {
        $release = ApkRelease::findOrFail($id);
        $release->update(['status' => ApkRelease::STATUS_PUBLISHED]);

        return redirect()->back()->with('success', "v{$release->version_name} is now published to the fleet (rollout {$release->rollout_permille}‰).");
    }

    public function unpublish($id)
    {
        $release = ApkRelease::findOrFail($id);
        $release->update(['status' => ApkRelease::STATUS_DRAFT]);

        return redirect()->back()->with('success', "v{$release->version_name} unpublished — no longer offered to the fleet.");
    }

    public function updateRollout(Request $request, $id)
    {
        $validated = $request->validate([
            'rollout_permille' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);

        $release = ApkRelease::findOrFail($id);
        $release->update(['rollout_permille' => $validated['rollout_permille']]);

        return redirect()->back()->with('success', "Rollout for v{$release->version_name} set to {$release->rollout_permille}‰.");
    }

    public function toggleMandatory($id)
    {
        $release = ApkRelease::findOrFail($id);
        $release->update(['mandatory' => ! $release->mandatory]);

        return redirect()->back()->with('success', "v{$release->version_name} mandatory flag " . ($release->mandatory ? 'ON' : 'OFF') . '.');
    }

    public function destroy($id)
    {
        $release = ApkRelease::findOrFail($id);

        if ($release->file_path) {
            Storage::delete($release->file_path);
        }
        $release->delete();

        return redirect()->back()->with('success', 'Release deleted.');
    }

    /**
     * Nudge the Smart-Freezer fleet to poll the OTA manifest now, instead of waiting
     * for the next ~6h poll. Publishes an OTA_CHECK MQTT command to every active,
     * MQTT-connected Smart-Freezer using the same signed CSV envelope as the other
     * VMC/APK commands (Type=OTA_CHECK). The device still pulls + verifies + gates —
     * this only shortens the wait.
     */
    public function pushOtaCheck()
    {
        $vends = Vend::query()
            ->smart()
            ->where('is_active', true)
            ->get();

        $count = 0;
        foreach ($vends as $vend) {
            $fid = 1;
            $content = base64_encode(json_encode([
                'Type' => 'OTA_CHECK',
                'time' => Carbon::now()->timestamp,
                'action' => '',
                'mid' => $vend->code,
            ]));
            $contentLength = strlen($content);
            $key = $vend && $vend->private_key ? $vend->private_key : '123456789110138A';
            $md5 = md5($fid . ',' . $contentLength . ',' . $content . $key);

            PublishMqtt::dispatch('CM' . $vend->code, $fid . ',' . $contentLength . ',' . $content . ',' . $md5)->onQueue('high');
            $count++;
        }

        return redirect()->back()->with('success', "OTA check pushed to {$count} Smart-Freezer(s).");
    }
}

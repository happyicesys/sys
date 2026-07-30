<?php

namespace App\Console\Commands;

use App\Models\RefundTicketAttachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One-time (rerunnable, resumable) migration of refund attachments from the
 * local disk to the configured cloud disk (config refund.attachments.disk,
 * normally 'digitaloceanspaces'). Copy-and-verify first; local files are only
 * deleted on an explicit --delete-local pass, and only after the remote copy
 * exists with a matching size. Files stay PRIVATE on Spaces (no visibility
 * option) — they are only ever served through the authed viewer route.
 *
 *   php artisan refund:attachments-to-spaces            # copy missing files
 *   php artisan refund:attachments-to-spaces --dry-run  # report only
 *   php artisan refund:attachments-to-spaces --delete-local   # free the droplet
 */
class MigrateRefundAttachmentsToSpaces extends Command
{
    protected $signature = 'refund:attachments-to-spaces {--dry-run} {--delete-local}';

    protected $description = 'Copy refund attachments to the configured cloud disk (verify sizes; optionally delete local copies)';

    public function handle(): int
    {
        $target = RefundTicketAttachment::storageDisk();

        if ($target === 'local') {
            $this->error('config refund.attachments.disk is still "local" — set REFUND_ATTACHMENT_DISK=digitaloceanspaces (and config:cache) first.');
            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $deleteLocal = (bool) $this->option('delete-local');

        $copied = $skipped = $deleted = $missing = $failed = 0;

        RefundTicketAttachment::query()->orderBy('id')->chunkById(100, function ($attachments) use ($target, $dry, $deleteLocal, &$copied, &$skipped, &$deleted, &$missing, &$failed) {
            foreach ($attachments as $a) {
                $onLocal = Storage::disk('local')->exists($a->path);
                $onTarget = Storage::disk($target)->exists($a->path);

                if (!$onLocal && !$onTarget) {
                    $this->warn("MISSING everywhere: #{$a->id} {$a->path}");
                    $missing++;
                    continue;
                }

                // Copy up when absent remotely (streamed — attachments may be 30MB videos).
                if ($onLocal && !$onTarget) {
                    if ($dry) {
                        $this->line("would copy: {$a->path}");
                        $copied++;
                    } else {
                        $stream = Storage::disk('local')->readStream($a->path);
                        if ($stream === false || $stream === null) {
                            $this->error("READ FAIL: {$a->path}");
                            $failed++;
                            continue;
                        }
                        try {
                            Storage::disk($target)->writeStream($a->path, $stream);
                        } finally {
                            if (is_resource($stream)) {
                                fclose($stream);
                            }
                        }
                        $onTarget = Storage::disk($target)->exists($a->path);
                        if (!$onTarget) {
                            $this->error("UPLOAD FAIL: {$a->path}");
                            $failed++;
                            continue;
                        }
                        $copied++;
                    }
                } else {
                    $skipped++;
                }

                // Verified delete of the local copy — only with the explicit flag,
                // only when the remote copy exists AND the sizes match.
                if ($deleteLocal && $onLocal && $onTarget) {
                    $localSize = Storage::disk('local')->size($a->path);
                    $remoteSize = Storage::disk($target)->size($a->path);
                    if ($localSize === $remoteSize) {
                        if ($dry) {
                            $this->line("would delete local: {$a->path}");
                        } else {
                            Storage::disk('local')->delete($a->path);
                        }
                        $deleted++;
                    } else {
                        $this->error("SIZE MISMATCH (kept local): {$a->path} local={$localSize} remote={$remoteSize}");
                        $failed++;
                    }
                }
            }
        });

        $this->info(($dry ? '[DRY RUN] ' : '') . "copied={$copied} already-there={$skipped} local-deleted={$deleted} missing={$missing} failed={$failed}");

        if ($failed > 0) {
            $this->error('Some files failed — rerun after checking; nothing failed was deleted.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

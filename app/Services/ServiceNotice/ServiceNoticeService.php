<?php

namespace App\Services\ServiceNotice;

use App\Models\Attachment;
use App\Models\OpsJob;
use App\Models\ServiceNotice;
use App\Models\ServiceNoticeItem;
use App\Models\User;
use App\Models\Vend;
use App\Services\OpsJobStops\StopCodeGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Every write to a service notice. Controllers validate and authorise; the
 * rules of the notice itself (what may be completed, what a verdict needs,
 * what deleting cleans up) live here.
 */
class ServiceNoticeService
{
    public const ATTACHMENT_DIR = 'sys/service-notices';

    public function __construct(private StopCodeGenerator $codes) {}

    /**
     * @param  string[]  $itemLines  one line = one thing to fix; blank lines are dropped
     */
    public function create(OpsJob $opsJob, Vend $vend, array $itemLines, ?string $remarks, int|float|null $sequence, User $by): ServiceNotice
    {
        $lines = self::cleanLines($itemLines);

        if ($lines === []) {
            throw ValidationException::withMessages([
                'items' => 'Enter at least one service item, 请至少输入一个维修项目',
            ]);
        }

        return DB::transaction(function () use ($opsJob, $vend, $lines, $remarks, $sequence, $by) {
            $notice = $this->codes->createWithNextCode(
                ServiceNotice::class,
                (int) $opsJob->operator_id,
                fn (int $code) => ServiceNotice::create([
                    'code' => $code,
                    'operator_id' => $opsJob->operator_id,
                    'ops_job_id' => $opsJob->id,
                    'vend_id' => $vend->id,
                    'customer_id' => $vend->customer_id,
                    'sequence' => $sequence,
                    'status' => ServiceNotice::STATUS_PENDING,
                    'remarks' => $remarks,
                    'created_by' => $by->id,
                    'updated_by' => $by->id,
                ]),
            );

            foreach ($lines as $index => $line) {
                $this->newItem($notice, $line, $index + 1, $by);
            }

            return $notice;
        });
    }

    public function addItem(ServiceNotice $notice, string $desc, User $by): ServiceNoticeItem
    {
        $this->assertOpen($notice);

        $next = ((int) $notice->items()->max('sequence')) + 1;

        return $this->newItem($notice, trim($desc), $next, $by);
    }

    /** @param  array{desc?:string, desc_before?:?string, desc_after?:?string}  $fields */
    public function updateItem(ServiceNoticeItem $item, array $fields, User $by): ServiceNoticeItem
    {
        $this->assertOpen($item->serviceNotice);

        $item->update($fields + ['updated_by' => $by->id]);

        return $item;
    }

    public function setItemStatus(ServiceNoticeItem $item, int $status, ?string $incompleteReason, User $by): ServiceNoticeItem
    {
        $this->assertOpen($item->serviceNotice);

        $reason = trim((string) $incompleteReason);

        // New vs cms: "could not finish" without saying why is useless to the office.
        if ($status === ServiceNoticeItem::STATUS_INCOMPLETE && $reason === '') {
            throw ValidationException::withMessages([
                'incomplete_reason' => 'Say why it could not be completed, 请说明未能完成的原因',
            ]);
        }

        $item->update([
            'status' => $status,
            'incomplete_reason' => $status === ServiceNoticeItem::STATUS_INCOMPLETE ? $reason : null,
            'status_changed_at' => now(),
            'status_changed_by' => $by->id,
            'updated_by' => $by->id,
        ]);

        return $item;
    }

    public function complete(ServiceNotice $notice, User $by): ServiceNotice
    {
        $this->assertOpen($notice);

        if ($notice->hasUnresolvedItems()) {
            throw ValidationException::withMessages([
                'status' => 'Not able to complete: every item needs a progress status, 无法完成这维修单，请在每个维修项目，提供状况进展',
            ]);
        }

        $notice->update([
            'status' => ServiceNotice::STATUS_COMPLETED,
            'completed_at' => now(),
            'completed_by' => $by->id,
            'updated_by' => $by->id,
        ]);

        return $notice;
    }

    public function undoComplete(ServiceNotice $notice, User $by): ServiceNotice
    {
        abort_unless($notice->isCompleted(), 422, 'Only a completed notice can be reopened.');

        $notice->update([
            'status' => ServiceNotice::STATUS_PENDING,
            'completed_at' => null,
            'completed_by' => null,
            'undo_completed_at' => now(),
            'undo_completed_by' => $by->id,
            'updated_by' => $by->id,
        ]);

        return $notice;
    }

    public function cancel(ServiceNotice $notice, User $by): ServiceNotice
    {
        $this->assertOpen($notice);

        $notice->update([
            'status' => ServiceNotice::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $by->id,
            'updated_by' => $by->id,
        ]);

        return $notice;
    }

    public function updateHeader(ServiceNotice $notice, array $fields, User $by): ServiceNotice
    {
        $notice->update($fields + ['updated_by' => $by->id]);

        return $notice;
    }

    public function storeAttachment(ServiceNoticeItem $item, int $slot, UploadedFile $file): Attachment
    {
        $this->assertOpen($item->serviceNotice);

        // storePublicly hashes the name: no same-minute collisions (the cms
        // bug), and the URL stays inside attachments.full_url's 255 chars.
        $path = $file->storePublicly(self::ATTACHMENT_DIR);

        return $item->attachments()->create([
            'full_url' => Storage::url($path),
            'local_url' => $path,
            'type' => $slot,
            'name' => mb_substr($file->getClientOriginalName(), 0, 255),
        ]);
    }

    public function deleteAttachment(Attachment $attachment): void
    {
        Storage::delete($attachment->local_url);
        $attachment->delete();
    }

    public function deleteItem(ServiceNoticeItem $item): void
    {
        DB::transaction(function () use ($item) {
            $item->attachments->each(fn (Attachment $a) => $this->deleteAttachment($a));
            $item->delete();
        });
    }

    public function delete(ServiceNotice $notice): void
    {
        DB::transaction(function () use ($notice) {
            $notice->items()->with('attachments')->get()->each(fn (ServiceNoticeItem $i) => $this->deleteItem($i));
            $notice->delete();
        });
    }

    /**
     * @param  string[]  $lines
     * @return string[]
     */
    public static function cleanLines(array $lines): array
    {
        return array_values(array_filter(array_map(
            fn ($line) => trim((string) $line),
            $lines,
        ), fn (string $line) => $line !== ''));
    }

    private function newItem(ServiceNotice $notice, string $desc, int $sequence, User $by): ServiceNoticeItem
    {
        return $notice->items()->create([
            'sequence' => $sequence,
            'status' => ServiceNoticeItem::STATUS_NEW,
            'desc' => $desc,
            'created_by' => $by->id,
            'updated_by' => $by->id,
        ]);
    }

    /** A completed or cancelled notice is history until someone reopens it. */
    private function assertOpen(ServiceNotice $notice): void
    {
        abort_unless($notice->isPending(), 422, 'This service notice is '.strtolower($notice->statusName()).' — reopen it first.');
    }
}

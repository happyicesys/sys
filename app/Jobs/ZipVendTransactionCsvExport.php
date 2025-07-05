<?php

namespace App\Jobs;

use App\Models\ExportJob;
use App\Models\ExportJobChunk;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ZipVendTransactionCsvExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $jobId;
    public $tries = 1;

    public function __construct($jobId)
    {
        $this->jobId = $jobId;
    }

    public function handle()
    {
        \Log::info('ZipVendTransactionCsvExport: Started', ['jobId' => $this->jobId]);

        $exportJob = ExportJob::find($this->jobId);
        if (!$exportJob) {
            \Log::error('ZipVendTransactionCsvExport: ExportJob not found', ['jobId' => $this->jobId]);
            return;
        }

        $chunks = ExportJobChunk::where('export_job_id', $this->jobId)
            ->where('status', 'completed')
            ->get();

        if ($chunks->isEmpty()) {
            \Log::error('ZipVendTransactionCsvExport: No completed chunks for zipping', ['jobId' => $this->jobId]);
            $exportJob->update([
                'status' => 'failed',
                'error_message' => 'No completed chunks found for zipping.'
            ]);
            return;
        }

        // Ensure unique filename to avoid conflicts
        $zipFileName = 'vend_transactions_' . now()->format('Ymd_His_u') . '.zip';
        $tmpFolder = storage_path('app/tmp');
        $localZipPath = "{$tmpFolder}/{$zipFileName}";

        if (!file_exists($tmpFolder)) {
            mkdir($tmpFolder, 0755, true);
            \Log::info('ZipVendTransactionCsvExport: Created tmp folder', ['path' => $tmpFolder]);
        }

        $zip = new ZipArchive();
        $result = $zip->open($localZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($result !== true) {
            \Log::error('ZipVendTransactionCsvExport: Failed to open zip', [
                'localZipPath' => $localZipPath,
                'result_code' => $result
            ]);
            $exportJob->update([
                'status' => 'failed',
                'error_message' => 'Unable to create zip file. ZipArchive open failed with code: ' . $result
            ]);
            return;
        }

        foreach ($chunks as $chunk) {
            $remotePath = "sys/exports/{$chunk->filename}";
            \Log::info('ZipVendTransactionCsvExport: Adding chunk to zip', [
                'jobId' => $this->jobId,
                'remotePath' => $remotePath,
                'filename_in_zip' => $chunk->filename
            ]);

            try {
                $content = Storage::disk('digitaloceanspaces')->get($remotePath);
                $zip->addFromString($chunk->filename, $content);
            } catch (\Throwable $e) {
                \Log::error('ZipVendTransactionCsvExport: Failed to add chunk to zip', [
                    'chunk' => $chunk->toArray(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        $zip->close();
        \Log::info('ZipVendTransactionCsvExport: Zip closed', ['localZipPath' => $localZipPath]);

        $spacesPath = "sys/exports/{$zipFileName}";
        try {
            Storage::disk('digitaloceanspaces')->put($spacesPath, fopen($localZipPath, 'r+'), [
                'visibility' => 'public',
            ]);
        } catch (\Throwable $e) {
            \Log::error('ZipVendTransactionCsvExport: Failed to upload zip', [
                'spacesPath' => $spacesPath,
                'error' => $e->getMessage()
            ]);
            $exportJob->update([
                'status' => 'failed',
                'error_message' => 'Failed to upload zip: ' . $e->getMessage()
            ]);
            unlink($localZipPath);
            return;
        }

        $url = Storage::disk('digitaloceanspaces')->url($spacesPath);
        \Log::info('ZipVendTransactionCsvExport: Uploaded zip', ['url' => $url]);

        try {
            $attachment = $exportJob->attachment()->create([
                'type' => 2,
                'file_name' => $zipFileName,
                'full_url' => $url,
                'local_url' => $spacesPath,
            ]);
            \Log::info('ZipVendTransactionCsvExport: Attachment created', ['attachment_id' => $attachment->id ?? null]);
        } catch (\Throwable $e) {
            \Log::error('ZipVendTransactionCsvExport: Attachment creation failed', [
                'error' => $e->getMessage()
            ]);
            $exportJob->update([
                'status' => 'failed',
                'error_message' => 'Failed to create attachment: ' . $e->getMessage()
            ]);
            unlink($localZipPath);
            return;
        }

        $exportJob->update([
            'status' => 'completed',
            'filename' => $zipFileName,
        ]);

        \Log::info('ZipVendTransactionCsvExport: Completed successfully', ['jobId' => $this->jobId]);

        unlink($localZipPath);
    }
}

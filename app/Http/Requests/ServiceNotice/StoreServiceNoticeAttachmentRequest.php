<?php

namespace App\Http\Requests\ServiceNotice;

use App\Models\ServiceNoticeItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceNoticeAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slot' => ['required', 'integer', Rule::in(ServiceNoticeItem::SLOTS)],
            // Same envelope as the ops-job item uploader: photo, video or PDF, 20 MB.
            'file' => 'required|file|max:20480|mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif,image/gif,video/mp4,video/quicktime,video/webm,application/pdf',
        ];
    }
}

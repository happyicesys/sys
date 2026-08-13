<?php

namespace App\Http\Controllers;

use App\Jobs\Vend\PushApkSettingSync;
use App\Models\ApkSetting;
use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    /**
     * If this attachment belongs to an apk_setting, its machines need to know:
     * the name drives the on-device filename (and therefore the play order in
     * "mixed" banner mode), and a delete must remove the file from the fleet.
     * No-op for every other owner type, so the shared endpoints are unchanged.
     */
    private function pushIfApkSettingMedia(?Attachment $attachment): void
    {
        if ($attachment && $attachment->modelable_type === ApkSetting::class) {
            PushApkSettingSync::schedule((int) $attachment->modelable_id);
        }
    }

    public function update(Request $request, $id)
    {
        $attachment = Attachment::findOrFail($id);
        // if($request->type and $attachment) {
        //     $removeSameType = $attachment->modelable->attachments()->where('type', $request->type)->first();

        //     if($removeSameType) {
        //         $removeSameType->update(['type' => null]);
        //     }
        // }
        $attachment->update($request->all());

        $this->pushIfApkSettingMedia($attachment);

        return redirect()->back();
    }

    public function delete($id)
    {
        // findOrFail: a double-click or a second tab deleting the same row
        // must 404, not fatal on a null deref.
        $attachment = Attachment::findOrFail($id);
        Storage::disk('public')->delete($attachment->local_url);

        $attachment->delete();

        // The in-memory model keeps its attributes after delete().
        $this->pushIfApkSettingMedia($attachment);

        return redirect()->back();
    }
}

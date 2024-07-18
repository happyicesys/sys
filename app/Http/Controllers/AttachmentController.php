<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function update(Request $request, $id)
    {
        $attachment = Attachment::find($id);
        // if($request->type and $attachment) {
        //     $removeSameType = $attachment->modelable->attachments()->where('type', $request->type)->first();

        //     if($removeSameType) {
        //         $removeSameType->update(['type' => null]);
        //     }
        // }
        $attachment->update($request->all());

        return redirect()->back();
    }

    public function delete($id)
    {
        $attachment = Attachment::find($id);
        $response = Storage::disk('public')->delete($attachment->local_url);
        // dd($attachment->local_url, $attachment->full_url, $response); // This will return false
        $attachment->delete();
        return redirect()->back();
    }
}

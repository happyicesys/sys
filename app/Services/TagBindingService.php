<?php

namespace App\Services;

class TagBindingService
{
    public static function sync($model, array $newTagIds)
    {
        // Get existing tag IDs
        $existingTagIds = $model->tagBindings()->pluck('tag_id')->toArray();

        // Determine IDs to delete
        $idsToDelete = array_diff($existingTagIds, $newTagIds);

        // Determine IDs to add
        $idsToAdd = array_diff($newTagIds, $existingTagIds);

        // Delete removed tags
        if (!empty($idsToDelete)) {
            $model->tagBindings()->whereIn('tag_id', $idsToDelete)->delete();
        }

        // Add new tags
        if (!empty($idsToAdd)) {
            foreach ($idsToAdd as $tagId) {
                $model->tagBindings()->create(['tag_id' => $tagId]);
            }
        }
    }
}

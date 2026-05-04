<?php

namespace App\Http\Controllers;

use App\Http\Resources\OpsJobTaskResource;
use App\Models\OpsJob;
use App\Models\OpsJobTask;
use App\Services\MapService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OpsJobTaskController extends Controller
{
    public function __construct(
        protected MapService $mapService,
    ) {}

    public function store(Request $request, int $opsJobId)
    {
        $opsJob = OpsJob::findOrFail($opsJobId);

        $data = $request->validate([
            'task_name'      => 'required|string|max:255',
            'address'        => 'required|string|max:500',
            'postcode'       => 'required|string|max:10',
            'ops_note'       => 'nullable|string',
            'ref_url'        => 'nullable|url|max:2048',
            'value'          => 'nullable|numeric|min:0',
            'qty'            => 'nullable|integer|min:0',
            'sequence'       => 'nullable|numeric|min:0.1',
        ]);

        $coords = $this->mapService->geocodePostcodeSG($data['postcode']);

        OpsJobTask::create([
            'ops_job_id'     => $opsJob->id,
            'sequence'       => $data['sequence'] ?? null,
            'task_name'      => $data['task_name'],
            'address'        => $data['address'],
            'postcode'       => $data['postcode'],
            'ops_note'       => $data['ops_note'] ?? null,
            'ref_url'        => $data['ref_url'] ?? null,
            // Pass dollar value — the model mutator handles × 100 → cents
            'value'          => $data['value'] ?? 0,
            'qty'            => $data['qty'] ?? null,
            'latitude'       => $coords['latitude'],
            'longitude'      => $coords['longitude'],
            'created_by'     => auth()->id(),
            'updated_by'     => auth()->id(),
        ]);

        // Return JSON — frontend uses axios (not Inertia), so redirect()->back()
        // causes axios to follow the redirect and receive the full Inertia HTML,
        // which can corrupt page state and make the task appear to vanish.
        return response()->json(['success' => true]);
    }

    public function update(Request $request, int $taskId)
    {
        $task = OpsJobTask::findOrFail($taskId);

        $data = $request->validate([
            'task_name'      => 'required|string|max:255',
            'address'        => 'required|string|max:500',
            'postcode'       => 'required|string|max:10',
            'ops_note'       => 'nullable|string',
            'ref_url'        => 'nullable|url|max:2048',
            'value'          => 'nullable|numeric|min:0',
            'qty'            => 'nullable|integer|min:0',
            'sequence'       => 'nullable|numeric|min:0.1',
        ]);

        // Re-geocode only if postcode changed or coords are missing
        $postcodeChanged = $data['postcode'] !== ($task->getRawOriginal('postcode') ?? $task->postcode);
        $coordsMissing   = $task->latitude === null || $task->longitude === null;

        $coords = ($postcodeChanged || $coordsMissing)
            ? $this->mapService->geocodePostcodeSG($data['postcode'])
            : ['latitude' => $task->latitude, 'longitude' => $task->longitude];

        $task->update([
            'sequence'       => $data['sequence'] ?? null,
            'task_name'      => $data['task_name'],
            'address'        => $data['address'],
            'postcode'       => $data['postcode'],
            'ops_note'       => $data['ops_note'] ?? null,
            'ref_url'        => $data['ref_url'] ?? null,
            // Pass dollar value — the model mutator handles × 100 → cents
            'value'          => $data['value'] ?? 0,
            'qty'            => $data['qty'] ?? null,
            'latitude'       => $coords['latitude'],
            'longitude'      => $coords['longitude'],
            'updated_by'     => auth()->id(),
        ]);

        return response()->json(['success' => true]);
    }

    public function updateSequence(Request $request, int $taskId)
    {
        $task = OpsJobTask::findOrFail($taskId);

        $data = $request->validate([
            'sequence' => 'nullable|numeric|min:0.1',
        ]);

        $task->update([
            'sequence'   => $data['sequence'],
            'updated_by' => auth()->id(),
        ]);

        return response()->json(['success' => true]);
    }

    public function updateStatus(int $taskId)
    {
        $task = OpsJobTask::findOrFail($taskId);

        switch ($task->status) {
            case OpsJobTask::STATUS_PENDING:
                $task->status     = OpsJobTask::STATUS_PICKED;
                $task->picked_at  = Carbon::now();
                $task->picked_by  = auth()->id();
                break;

            case OpsJobTask::STATUS_PICKED:
                $task->status        = OpsJobTask::STATUS_COMPLETED;
                $task->completed_at  = Carbon::now();
                $task->completed_by  = auth()->id();
                break;
        }

        $task->updated_by = auth()->id();
        $task->save();

        return response()->json([
            'success' => true,
            'task'    => new OpsJobTaskResource(
                $task->load('createdBy', 'pickedBy', 'completedBy')
            ),
        ]);
    }

    public function undoStatus(int $taskId)
    {
        $task = OpsJobTask::findOrFail($taskId);

        switch ($task->status) {
            case OpsJobTask::STATUS_PICKED:
                $task->status          = OpsJobTask::STATUS_PENDING;
                $task->undo_picked_at  = Carbon::now();
                $task->undo_picked_by  = auth()->id();
                $task->picked_at       = null;
                $task->picked_by       = null;
                break;

            case OpsJobTask::STATUS_COMPLETED:
                $task->status             = OpsJobTask::STATUS_PICKED;
                $task->undo_completed_at  = Carbon::now();
                $task->undo_completed_by  = auth()->id();
                $task->completed_at       = null;
                $task->completed_by       = null;
                break;
        }

        $task->updated_by = auth()->id();
        $task->save();

        return response()->json([
            'success' => true,
            'task'    => new OpsJobTaskResource(
                $task->load('createdBy', 'pickedBy', 'completedBy')
            ),
        ]);
    }

    public function destroy(int $taskId)
    {
        $task = OpsJobTask::findOrFail($taskId);
        $task->delete();

        return response()->json(['success' => true]);
    }
}

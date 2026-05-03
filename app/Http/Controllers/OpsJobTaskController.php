<?php

namespace App\Http\Controllers;

use App\Models\OpsJob;
use App\Models\OpsJobTask;
use App\Services\MapService;
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
            'sequence'       => 'nullable|integer|min:1',
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
            'sequence'       => 'nullable|integer|min:1',
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
            'sequence' => 'nullable|integer|min:1',
        ]);

        $task->update([
            'sequence'   => $data['sequence'],
            'updated_by' => auth()->id(),
        ]);

        return response()->json(['success' => true]);
    }

    public function destroy(int $taskId)
    {
        $task = OpsJobTask::findOrFail($taskId);
        $task->delete();

        return response()->json(['success' => true]);
    }
}

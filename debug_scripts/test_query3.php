<?php
$data = \Illuminate\Support\Facades\DB::table('ops_job_items')
    ->join('ops_job_item_channels', 'ops_job_items.id', '=', 'ops_job_item_channels.ops_job_item_id')
    ->where('ops_job_items.ops_job_id', 1468)
    ->select('ops_job_item_channels.id', 'ops_job_item_channels.capacity', 'ops_job_item_channels.qty')
    ->get();
echo json_encode($data);

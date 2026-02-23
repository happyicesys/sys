<?php
$vend = App\Models\Vend::where('code', '7006')->first();
$channel = App\Models\VendChannel::where('vend_id', $vend->id)->where('code', '25')->first();
var_dump($channel->error_rate_json);

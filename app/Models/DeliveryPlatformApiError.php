<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One failed Grab exchange. Written only by App\Support\Grab\GrabApiErrorRecorder.
 * direction: outgoing = our call to Grab; incoming = Grab's callback to us (or an
 * order Grab has that never reached us, action order_missed).
 */
class DeliveryPlatformApiError extends Model
{
    const UPDATED_AT = null;

    const DIRECTION_OUTGOING = 'outgoing';

    const DIRECTION_INCOMING = 'incoming';

    const KEEP_DAYS = 30;

    // Grab calls and callbacks by name, for the page and the history.
    const ACTIONS = [
        'oauth_token' => 'Get Grab login token',
        'menu_categories' => 'List menu categories',
        'menu_notify' => 'Tell Grab to pull the menu',
        'menu_update' => 'Update menu item',
        'menu_batch_update' => 'Batch update menu',
        'menu_trace' => 'Trace menu sync',
        'order_accept' => 'Accept / reject order',
        'order_list' => 'List orders',
        'order_edit' => 'Edit order',
        'order_ready' => 'Mark order ready',
        'order_delivery_state' => 'Update delivery state',
        'order_ready_time' => 'Update order ready time',
        'order_cancelable' => 'Check order cancelable',
        'order_cancel' => 'Cancel order',
        'store_pause' => 'Pause / resume store',
        'store_status' => 'Get store status',
        'store_hours' => 'Store hours',
        'campaign_create' => 'Create campaign',
        'campaign_list' => 'List campaigns',
        'campaign_update' => 'Update campaign',
        'campaign_delete' => 'Delete campaign',
        'grab_call' => 'Other Grab call',
        'callback_menu' => 'Grab pulls our menu',
        'callback_order_create' => 'Grab sends a new order',
        'callback_order_update' => 'Grab sends an order update',
        'callback_menu_sync' => 'Grab reports a menu sync',
        'callback' => 'Other Grab callback',
        'order_missed' => 'Order never reached mark1',
    ];

    protected $fillable = [
        'action',
        'delivery_platform_operator_id',
        'delivery_platform_ref_number_id',
        'direction',
        'http_method',
        'merchant_id',
        'message',
        'order_id',
        'path',
        'request_json',
        'response_json',
        'status',
    ];

    protected $casts = [
        'request_json' => 'json',
        'response_json' => 'json',
    ];

    public function deliveryPlatformRefNumber()
    {
        return $this->belongsTo(DeliveryPlatformRefNumber::class);
    }

    public function deliveryPlatformOperator()
    {
        return $this->belongsTo(DeliveryPlatformOperator::class);
    }
}

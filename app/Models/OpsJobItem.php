<?php

namespace App\Models;

use App\Traits\GetUserTimezone;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpsJobItem extends Model
{
    use GetUserTimezone, HasFactory;

    protected $fillable = [
        'acc_total_amount',
        'acc_total_cash_amount',
        'acc_total_cashless_amount',
        'acc_total_count',
        'acc_total_promo_amount',
        'cancelled_at',
        'cancelled_by',
        'cash_amount',
        'cashless_amount',
        'channels_json',
        'cms_transaction_at',
        'cms_transaction_by',
        'cms_transaction_id',
        'completed_at',
        'completed_by',
        'created_by',
        'customer_id',
        'flagged_at',
        'flagged_by',
        'frozen_at',
        'frozen_snapshot',
        'is_cash_collected',
        'is_ignore_limit',
        'is_inventory_adjusted',
        'notes',
        'ops_job_id',
        'previous_ops_job_item_id',
        'refillable_amount',
        'refillable_count',
        'remarks',
        'remarks_updated_at',
        'remarks_updated_by',
        'sequence',
        'status',
        // temporary
        'temp_cash_amount_from_vmc',
        'picked_at',
        'picked_by',
        'stock_action_type',
        'undo_completed_at',
        'undo_completed_by',
        'undo_flagged_at',
        'undo_flagged_by',
        'undo_picked_at',
        'undo_picked_by',
        'undo_verified_at',
        'undo_verified_by',
        'updated_by',
        'vend_id',
        'vend_channel_record_id',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'cms_transaction_at' => 'datetime',
        'flagged_at' => 'datetime',
        'frozen_at' => 'datetime',
        'frozen_snapshot' => 'array',
        'is_ignore_limit' => 'boolean',
        'is_inventory_adjusted' => 'boolean',
        'picked_at' => 'datetime',
        'remarks_updated_at' => 'datetime',
        'verified_at' => 'datetime',
        'undo_completed_at' => 'datetime',
        'undo_flagged_at' => 'datetime',
        'undo_picked_at' => 'datetime',
        'undo_verified_at' => 'datetime',
    ];

    // accessor and mutators
    // cash amount set x 100, get / 100
    public function cashAmount(): Attribute
    {
        return Attribute::make(
            // get: fn ($value) => $value != null ? $value / 100 : null,
            // set: fn ($value) => $value != null ? $value * 100 : null,
            get: fn($value) => $value / 100,
            set: fn($value) => $value * 100,
        );
    }

    // cashless amount set x 100, get / 100
    public function cashlessAmount(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value / 100,
            set: fn($value) => $value * 100,
        );
    }

    public function tempCashAmountFromVmc(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value / 100,
            set: fn($value) => $value * 100,
        );
    }

    // relationships
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'modelable')->oldest();
    }

    public function cmsTransactionBy()
    {
        return $this->belongsTo(User::class, 'cms_transaction_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function opsJob()
    {
        return $this->belongsTo(OpsJob::class);
    }

    public function opsJobItemChannels()
    {
        return $this->hasMany(OpsJobItemChannel::class);
    }

    public function pickedBy()
    {
        return $this->belongsTo(User::class, 'picked_by');
    }

    public function previousOpsJobItem()
    {
        return $this->belongsTo(OpsJobItem::class, 'previous_ops_job_item_id');
    }

    public function remarksUpdatedBy()
    {
        return $this->belongsTo(User::class, 'remarks_updated_by');
    }

    public function statusBy()
    {
        return $this->belongsTo(User::class, 'status_by');
    }

    public function undoCompletedBy()
    {
        return $this->belongsTo(User::class, 'undo_completed_by');
    }

    public function undoFlaggedBy()
    {
        return $this->belongsTo(User::class, 'undo_flagged_by');
    }

    public function undoPickedBy()
    {
        return $this->belongsTo(User::class, 'undo_picked_by');
    }

    public function undoVerifiedBy()
    {
        return $this->belongsTo(User::class, 'undo_verified_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class, 'vend_id');
    }

    public function vendChannelRecord()
    {
        return $this->belongsTo(VendChannelRecord::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /* ----------------------------------------------------------------------
     |  Freeze ("snapshot 10 min after stock-in")
     | ----------------------------------------------------------------------
     |  Once a machine has been stocked-in (status >= STATUS_DELIVERED) for
     |  10 minutes, the ops:freeze-stock-in command captures its displayed
     |  figures into frozen_snapshot and stamps frozen_at. From then on the
     |  row renders the stored snapshot instead of re-deriving from live data.
     |  Undoing the stock-in (status drops below STATUS_DELIVERED) clears both
     |  columns and the row goes live again.
     */

    public function isFrozen(): bool
    {
        return $this->frozen_at !== null;
    }

    /**
     * Release a freeze (used when stock-in is undone). Persists immediately.
     */
    public function clearFreeze(): void
    {
        if ($this->frozen_at === null && $this->frozen_snapshot === null) {
            return;
        }

        static::whereKey($this->getKey())->update([
            'frozen_at' => null,
            'frozen_snapshot' => null,
        ]);

        $this->setAttribute('frozen_at', null);
        $this->setAttribute('frozen_snapshot', null);
    }

    /**
     * Tally/error state — PHP port of opsJobItemChannelErrorCheck() in Edit.vue.
     * Returns 0 = All tally, 1 = Not tally (fixed), 2 = Not tally (havent fixed).
     * Requires opsJobItemChannels to be loaded.
     */
    public function computeTallyStatus(): int
    {
        if ((int) $this->status < (int) OpsJob::STATUS_DELIVERED) {
            return 0;
        }

        $status = 0;
        foreach ($this->opsJobItemChannels as $channel) {
            if ($channel->virtual_is_error == 1 && $channel->is_error_settle == 0) {
                $status = 2;
            } elseif ($channel->virtual_is_error == 1 && $channel->is_error_settle == 1 && $status < 2) {
                $status = 1;
            } elseif ($channel->virtual_is_error == 0 && $status < 1) {
                $status = 0;
            }
        }

        return $status;
    }

    /**
     * Coin float at freeze time — the machine's reported CoinCnt (raw, as the
     * frontend divides by the currency exponent itself). Null when unavailable.
     */
    public function coinFloatValue()
    {
        $params = $this->vend?->parameter_json;

        if (is_array($params) && isset($params['CoinCnt'])) {
            return $params['CoinCnt'];
        }

        return null;
    }

    /**
     * Resolved mapping labels shown for the item — current mapping name, the
     * upcoming mapping name, and the upcoming mapping's remarks text. These are
     * derived from the (live) vend mapping relations, which is why they must be
     * captured at freeze time. NOTE the two frontends resolve the upcoming
     * NAME and the upcoming REMARKS in opposite preference order, mirrored here:
     *   - name  (CustomerIndex): prefer productMapping.upcomingProductMapping
     *   - remarks (Edit.vue):     prefer vend.upcomingProductMapping
     */
    public function resolveMappingSnapshot(): array
    {
        $vend = $this->vend;

        if (!$vend) {
            return [
                'mapping_current_name' => null,
                'mapping_upcoming_name' => null,
                'mapping_remarks' => null,
            ];
        }

        $current = $vend->productMapping?->name;
        $viaCurrentUpcoming = $vend->productMapping?->upcomingProductMapping; // productMapping.upcomingProductMapping
        $directUpcoming = $vend->upcomingProductMapping;                       // vend.upcomingProductMapping

        $upcomingName = null;
        if ($viaCurrentUpcoming && $viaCurrentUpcoming->name !== 'N/A') {
            $upcomingName = $viaCurrentUpcoming->name;
        } elseif ($directUpcoming && $directUpcoming->name !== 'N/A') {
            $upcomingName = $directUpcoming->name;
        }

        $remarksSource = $directUpcoming ?: $viaCurrentUpcoming;

        return [
            'mapping_current_name' => $current,
            'mapping_upcoming_name' => $upcomingName,
            'mapping_remarks' => $remarksSource?->remarks,
        ];
    }

    /**
     * Build the snapshot of the fields we deliberately freeze 10 min after
     * stock-in: the channel-error/tally verdict, coin float, the stock action
     * badge, and the product-mapping labels (current/upcoming/remarks).
     *
     * Cash, amounts and counts are intentionally NOT frozen — they follow the
     * original live behaviour. Requires opsJobItemChannels + vend (with mapping
     * relations) eager-loaded; lazy-loads them otherwise.
     */
    public function buildFreezeSnapshot(): array
    {
        if (!$this->relationLoaded('opsJobItemChannels')) {
            $this->load('opsJobItemChannels');
        }
        if (!$this->relationLoaded('vend')) {
            $this->load([
                'vend:id,parameter_json,vend_channel_error_logs_json,product_mapping_id,upcoming_product_mapping_id',
                'vend.productMapping:id,name,upcoming_product_mapping_id',
                'vend.productMapping.upcomingProductMapping:id,name,remarks',
                'vend.upcomingProductMapping:id,name,remarks',
            ]);
        }

        $snapshot = [
            'stock_action_type' => $this->stock_action_type,
            'tally_status' => $this->computeTallyStatus(),
            'coin_float' => $this->coinFloatValue(),
            // Machine-reported channel error logs at the moment of freeze — these
            // are live telemetry that clears over time, so we keep the at-job copy.
            'channel_error_logs' => $this->vend?->vend_channel_error_logs_json ?? null,
            'frozen_schema_version' => 1,
        ];

        $snapshot += $this->resolveMappingSnapshot();

        return $snapshot;
    }
}

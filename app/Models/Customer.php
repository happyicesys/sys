<?php

namespace App\Models;

use App\Models\OpsJob;
use App\Models\Scopes\OperatorCustomerFilterScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    const DAYS_MAPPING = [
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
        6 => 'Sat',
        7 => 'Sun',
        8 => 'Holiday Eve',
    ];

    const FREQUENCY_PER_WEEK_STATUSES_MAPPING = [
        1 => 'Less Than 1 Time',
        2 => '1 Time',
        3 => 'More Than 1 Time',
    ];

    const RUNNING_NUMBER_INIT = 20000;

    // Customer lifecycle status. Stored on customers.status_id (integer).
    // STATUS_POTENTIAL was added when the Customer/Edit "Is Active?" Yes/No
    // field was replaced by a 5-value "Status" dropdown. The legacy is_active
    // boolean is now a derived mirror: is_active = (status_id === STATUS_ACTIVE),
    // kept in sync on save so existing infra reading is_active keeps working.
    const STATUS_POTENTIAL = 5;
    const STATUS_NEW = 4;
    const STATUS_PENDING = 3;
    const STATUS_ACTIVE = 2;
    const STATUS_INACTIVE = 1;

    // "Pending" was relabelled to "Removed" (the status now means the site has
    // been removed and commission stops after its Removed Date). The integer
    // value (3) is UNCHANGED so no data migration is needed; this alias just
    // lets new code read STATUS_REMOVED instead of the legacy STATUS_PENDING.
    const STATUS_REMOVED = self::STATUS_PENDING;

    const ADDRESS_TYPE_BILLING = 1;
    const ADDRESS_TYPE_DELIVERY = 2;

    const FILE_TYPE_ATTACHMENT = 1;
    const FILE_TYPE_PHOTO = 2;
    const FILE_TYPE_CONTRACT = 3;

    // Order here drives the dropdown order in the UI (Customer/Edit,
    // Customer/Index filter, etc.): Potential, New, Active, Pending, Inactive.
    const STATUSES_MAPPING = [
        self::STATUS_POTENTIAL => 'Potential',
        self::STATUS_NEW => 'New',
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_PENDING => 'Removed',
        self::STATUS_INACTIVE => 'Inactive',
    ];

    // Statuses that carry a user-entered effective date captured via the
    // Edit page's status-change prompt: Active → active_date, Removed →
    // removed_date. (Inactive auto-stamps termination_date; the rest carry no
    // date.) Drives the Status History log + the date-prompt modal.
    const STATUS_DATE_FIELDS = [
        self::STATUS_ACTIVE => 'active_date',
        self::STATUS_REMOVED => 'removed_date',
    ];

    // Notice Period dropdown options for the Placement Contract Detail
    // block on Customer/Edit.vue. Stored as the exact label string in
    // `contract_notice_period` (varchar 16) — see migration
    // 2026_05_13_000000_change_contract_notice_period_to_string.
    // Order here drives the dropdown order in the UI.
    const NOTICE_PERIOD_OPTIONS = [
        '1 wk',
        '2 wk',
        '3 wk',
        '1 mth',
        '1.5 mth',
        '2 mth',
        '3 mth',
        'NO need',
        'Cant ETerm',
    ];

    // Location grading rubric — shown on Customer/Edit.vue under the
    // "Placement Contract Detail" section. Selections are stored in the
    // three location_grading_* columns (char(1), values A/B/C/null).
    // Edit values here when the rubric wording changes; no migration needed
    // (the columns just hold the picked option code).
    const LOCATION_GRADING_CATEGORIES = [
        'placement' => [
            'label' => 'Machine placement & removal',
            'options' => [
                'A' => 'Smooth surface, 1 person can perform',
                'B' => 'Smooth surface, need 2 persons to perform',
                'C' => 'Not smooth surface, need min 2 persons to perform',
            ],
        ],
        'access' => [
            'label' => 'Easy access & refill',
            'options' => [
                'A' => 'Low/Free parking and easy access',
                'B' => 'Low/Free parking, but need to pre-apply entry',
                'C' => 'No proper parking space; need guide to go in',
            ],
        ],
        'flexibility' => [
            'label' => 'Flexible to terminate / or replace with another machine later',
            'options' => [
                'A' => 'Less than 1 week',
                'B' => 'Less than 2 weeks',
                'C' => '2 weeks and above',
            ],
        ],
    ];

    protected static function booted()
    {
        static::addGlobalScope(new OperatorCustomerFilterScope);
    }

    protected $casts = [
        'account_manager_json' => 'json',
        'begin_date' => 'datetime',
        // Site lifecycle dates (drive the Summary commission window).
        'active_date' => 'date',
        'removed_date' => 'date',
        'contract_auto_renewal' => 'boolean',
        'is_external_subsidize' => 'boolean',
        'is_gst_registered' => 'boolean',
        'is_billing_same_as_delivery' => 'boolean',
        'contract_from' => 'date',
        'contract_until' => 'date',
        'contract_detail_updated_at' => 'datetime',
        // Customer Notes audit timestamp — cast so the Resource can call
        // ->toDateTimeString() and the Vue side gets a parseable string
        // (otherwise `optional()` silently swallows the call on a raw
        // DB string and the audit line renders "Invalid date").
        'notes_updated_at' => 'datetime',
        // Same reasoning as notes_updated_at — cast so the Resource can call
        // ->toDateTimeString() and the Vue side gets a parseable string.
        'loc_fee_remarks_updated_at' => 'datetime',
        'cms_invoice_history' => 'json',
        'person_json' => 'json',
        'last_invoice_date' => 'datetime',
        'next_invoice_date' => 'datetime',
        'snap_parameter_json' => 'json',
        'snap_vend_channels_json' => 'json',
        'snap_vend_channel_error_logs_json' => 'json',
        'snap_vend_status_json' => 'json',
        'termination_date' => 'datetime',
        'totals_json' => 'json',
        // json declaration for vend
        'acb_vmc_pa_json' => 'json',
        'acb_status_json' => 'json',
        'apk_ver_json' => 'json',
        'begin_date' => 'datetime',
        'is_active' => 'boolean',
        'is_report_email_enabled' => 'boolean',
        'is_restricted_access' => 'boolean',
        'last_updated_at' => 'datetime',
        'mqtt_last_updated_at' => 'datetime',
        'parameter_json' => 'json',
        'preferred_visit_days_json' => 'json',
        'statistics1_json' => 'json',
        'temp_updated_at' => 'datetime',
        'termination_date' => 'datetime',
        'vend_channel_error_logs_json' => 'json',
        'vend_channels_json' => 'json',
        'vend_channel_totals_json' => 'json',
        'vend_criteria_score_json' => 'json',
        'vend_criteria_weightage_json' => 'json',
        'vend_transaction_totals_json' => 'json',
    ];

    protected $fillable = [
        'account_manager_json',
        'begin_date',
        'category_id',
        'cms_invoice_history',
        'code',
        'created_at',
        'person_json',
        'first_transaction_id',
        'frequency_per_week_status',
        'name',
        // Site-level contact — stored directly on the customers table, distinct
        // from the polymorphic billing Contact relation. See migration
        // 2026_06_01_000000_add_site_contact_to_customers.
        'site_contact_person',
        'site_phone_number',
        'site_alt_phone_number',
        // Free-text remarks for the delivery address. See migration
        // 2026_06_01_000001_add_address_remarks_to_customers.
        'address_remarks',
        // Read-only CMS mirror scalars (see migration
        // 2026_05_27_000000_add_cms_mirror_fields_to_customers and the
        // UpdateCustomerCmsFields job). Only written by the CMS sync,
        // never edited in mark1.
        'company_remark',
        'site_name',
        'cost_rate',
        'payterm',
        // "Payment To" tracking — sys-only (who Location Fees are paid to).
        // See migration 2026_05_27_010000_add_payment_to_and_gst_registered.
        'payment_to',
        'is_gst_registered',
        'is_billing_same_as_delivery',
        'bank_id',
        'bank_account_name',
        'bank_account_number',
        'is_active',
        'is_restricted_access',
        'last_invoice_date',
        'location_type_id',
        'next_invoice_date',
        'next_invoice_driver_id',
        'operator_id',
        'ops_note',
        // Last-edited audit pair for ops_note. Mirrors the
        // notes_updated_at / notes_updated_by pair used by the Customer
        // Note column on the Customer Summary and Vend/CustomerIndex pages.
        'ops_note_updated_at',
        'ops_note_updated_by',
        // for cms person id
        'person_id',
        'power_socket_key_number',
        'preferred_visit_days_json',
        'profile_id',
        'selling_price_type',
        'snap_parameter_json',
        'snap_vend_channels_json',
        'snap_vend_channel_error_logs_json',
        'snap_vend_status_json',
        'status_id',
        'termination_date',
        // Site lifecycle dates — active_date / removed_date drive the Summary
        // commission window; termination_date above is the auto Inactive Date.
        'active_date',
        'removed_date',
        'totals_json',
        'virtual_customer_code',
        'virtual_customer_prefix',
        'zone_id',
        // Site grouping — links co-located sites into one cluster so they can
        // "travel together" on listing/report pages. See migration
        // 2026_06_29_000000_create_customer_groups_table.
        'customer_group_id',
        'contract_commission_type',
        'contract_commission_value',
        'contract_commission_value2',
        'contract_ps_term',
        // External Subsidize — toggle + optional dollar amount. See migration
        // 2026_05_25_000000_add_external_subsidize_to_customers.
        'is_external_subsidize',
        'external_subsidize_amount',
        'contract_from',
        'contract_until',
        'contract_auto_renewal',
        'contract_notice_period',
        'contract_remarks',
        'contract_detail_updated_at',
        'contract_detail_updated_by',
        // Customer-level free-text notes shown on the Customer Summary
        // page (Customer Tag column). Parked here — not on the monthly
        // CustomerPeriodSummary row — so the note carries over no matter
        // which period filter is applied. See migration
        // 2026_05_14_090000_add_notes_to_customers.
        'notes',
        'notes_updated_at',
        'notes_updated_by',
        // Dedicated free-text "Remarks for Loc Fees" — one per Site, parked
        // on the customer record (like notes) so it carries across any period
        // filter on the Summary page. Standalone from the general Site Note;
        // no unread/mention tracking. See migration
        // 2026_06_20_000000_add_loc_fee_remarks_to_customers.
        'loc_fee_remarks',
        'loc_fee_remarks_updated_at',
        'loc_fee_remarks_updated_by',
        // Performance Report email opt-in (see migration
        // 2026_05_09_000000_add_report_email_to_customers).
        'report_email',
        'is_report_email_enabled',
        // Location grading — A/B/C selections per category. See
        // LOCATION_GRADING_CATEGORIES const above for the rubric.
        'location_grading_placement',
        'location_grading_access',
        'location_grading_flexibility',
    ];

    // mutator
    public function getRefIDAttribute()
    {
        return $this->id + self::RUNNING_NUMBER_INIT;
    }

    // relationships
    public function addresses()
    {
        return $this->morphMany(Address::class, 'modelable');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'modelable')->where('type', 1)->orderBy('sequence');
    }

    public function contracts()
    {
        return $this->morphMany(Attachment::class, 'modelable')->where('type', self::FILE_TYPE_CONTRACT)->latest();
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function billingAddress()
    {
        // return $this->morphOne(Address::class, 'modelable')->ofMany('type', 'min');
        return $this->morphOne(Address::class, 'modelable')->where('type', 1);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function contact()
    {
        return $this->morphOne(Contact::class, 'modelable');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customerVendBindings()
    {
        return $this->hasMany(CustomerVendBinding::class)->orderBy('created_at');
    }

    public function customerGroup()
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    /**
     * Other Sites in the same group (excludes self). Empty when ungrouped.
     */
    public function siblings()
    {
        return $this->hasMany(Customer::class, 'customer_group_id', 'customer_group_id')
            ->whereNotNull('customer_group_id')
            ->where('id', '<>', $this->id);
    }

    public function deliveryAddress()
    {
        // return $this->morphOne(Address::class, 'modelable')->ofMany('type', 'max');
        return $this->morphOne(Address::class, 'modelable')->where('type', 2);
    }

    public function handledBy()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function firstTransaction()
    {
        return $this->belongsTo(Transaction::class, 'first_transaction_id');
    }

    public function lastOpsJobItem()
    {
        return $this->hasOne(OpsJobItem::class)
            ->whereHas('opsJob', function ($query) {
                $query->where('date', '<=', Carbon::today()->endOfDay());
            })
            ->where('status', '>=', OpsJob::STATUS_DELIVERED)
            ->where('status', '<>', OpsJob::STATUS_CANCELLED)
            ->latest();
    }

    public function lastSecondOpsJobItem()
    {
        return $this->hasOne(OpsJobItem::class)
            ->whereHas('opsJob', function ($query) {
                $query->where('date', '<=', Carbon::today()->endOfDay());
            })
            ->where('status', '>=', OpsJob::STATUS_DELIVERED)
            ->where('status', '<>', OpsJob::STATUS_CANCELLED)
            ->latest()    // Order by the latest date
            ->skip(1)     // Skip the most recent (latest) entry
            ->take(1);    // Take the second-to-last entry
    }


    public function nextOpsJobItem()
    {
        return $this->hasOne(OpsJobItem::class)
            ->whereHas('opsJob', function ($query) {
                $query->where('date', '>=', Carbon::today()->startOfDay());
            })
            ->where('status', '<', OpsJob::STATUS_DELIVERED)
            ->where('status', '<>', OpsJob::STATUS_CANCELLED)
            ->oldest();
    }

    public function locationType()
    {
        return $this->belongsTo(LocationType::class);
    }

    public function nextInvoiceDriver()
    {
        return $this->belongsTo(User::class, 'next_invoice_driver_id');
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function photos()
    {
        return $this->morphMany(Attachment::class, 'modelable')->where('type', 2)->orderBy('created_at');
    }

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function tagBindings()
    {
        return $this->hasMany(TagBinding::class);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function vend()
    {
        return $this->hasOne(Vend::class)->latest('begin_date')->latest('created_at');
    }

    public function vends()
    {
        return $this->hasMany(Vend::class);
    }

    public function vendRecords()
    {
        return $this->hasMany(VendRecord::class);
    }

    public function vendTransactions()
    {
        return $this->hasMany(VendTransaction::class);
    }

    public function contractDetailUpdatedBy()
    {
        return $this->belongsTo(User::class, 'contract_detail_updated_by');
    }

    /**
     * The single PENDING future contract change for this customer, if any.
     * Only one pending row is ever allowed (enforced in the controller), so
     * hasOne is correct. Applied / cancelled rows are excluded.
     */
    public function scheduledContract()
    {
        return $this->hasOne(CustomerScheduledContract::class)
            ->where('status', CustomerScheduledContract::STATUS_PENDING)
            ->latest('id');
    }

    public function notesUpdatedBy()
    {
        return $this->belongsTo(User::class, 'notes_updated_by');
    }

    public function locFeeRemarksUpdatedBy()
    {
        return $this->belongsTo(User::class, 'loc_fee_remarks_updated_by');
    }

    /**
     * Settlement ledger ("Payment History") for this site — what we owe the
     * site owner and the payments/waivers made against it. Chronological.
     * See App\Models\CustomerSettlement.
     */
    public function settlements()
    {
        return $this->hasMany(CustomerSettlement::class);
    }

    /**
     * Append-only Site status change history (newest first). Powers the
     * "Status History" popup on the Edit page.
     */
    public function statusLogs()
    {
        return $this->hasMany(CustomerStatusLog::class)->latest('id');
    }

    public function opsNoteUpdatedBy()
    {
        return $this->belongsTo(User::class, 'ops_note_updated_by');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    // for the use of cleanCustomerSeeder before deprecate
    public function vendBindings()
    {
        return $this->hasMany(VendBinding::class);
    }

    public function latestVendBinding()
    {
        return $this->hasOne(VendBinding::class)->where('is_active', true)->latest('begin_date');
    }

    // vend_transactions with date range
    public function daysVendTransactions($from = 0, $to = 0)
    {
        return $this->vendTransactions()
            // ->isSuccessful()
            ->where('transaction_datetime', '>=', Carbon::today()->subDays($from)->startOfDay())
            ->where('transaction_datetime', '<=', Carbon::today()->subDays($to)->endOfDay());
    }

    // vend_records with customer begin date and operational end date. The end
    // is the Removed Date (when the site stopped operating) if set, else the
    // auto Inactive Date (termination_date), else today.
    public function lifetimeVendRecords()
    {
        $end = $this->removed_date
            ?? $this->termination_date
            ?? Carbon::today();

        // Floor the lower bound at the app-wide reporting floor so lifetime
        // sums/averages never include pre-genesis data, even if begin_date is
        // older. See CustomerController::summaryFloorDate().
        $floor = Carbon::parse(\App\Http\Controllers\CustomerController::summaryFloorDate())->startOfDay();
        $start = Carbon::parse($this->begin_date)->startOfDay();
        if ($start->lt($floor)) {
            $start = $floor;
        }

        return $this->vendRecords()
            ->where('date', '>=', $start)
            ->where('date', '<=', Carbon::parse($end)->endOfDay());
    }

    // vend_records with date range
    public function daysVendRecords($from = 0, $to = 0)
    {
        return $this->vendRecords()
            ->where('date', '>=', Carbon::today()->subDays($from)->startOfDay())
            ->where('date', '<=', Carbon::today()->subDays($to)->endOfDay());
    }

    // scopes
    public function scopeFilterIndex($query, $request)
    {
        return $query->when($request->categories, function ($query, $search) {
            $query->whereHas('categories', function ($query) use ($search) {
                $query->whereIn('id', $search);
            });
        })
            ->when($request->categoryGroups, fn($query, $input) => $query->whereHas('category.categoryGroup', function ($query) use ($input) {
                $query->whereIn('id', $input);
            }))
            ->when($request->code, fn($query, $input) => $query->where('code', 'LIKE', '%' . $input . '%'))
            ->when($request->created_in, fn($query, $input) => $query->whereDate('created_at', '>=', Carbon::createFromFormat('m-Y', $input)->startOfMonth())->whereDate('created_at', '<=', Carbon::createFromFormat('m-Y', $input)->endOfMonth()))
            ->when($request->customer, function ($query, $search) {
                $search = trim((string) $search);

                // The "Site" box matches the Site Name, the virtual code/prefix,
                // AND the displayed Site ID (ref_id = customers.id +
                // RUNNING_NUMBER_INIT). Parse a leading numeric token as the Site
                // ID and treat the rest as a name fragment, so all of these work:
                //   "24310"        → the site with ref_id 24310
                //   "24310 Waterc" → ref_id 24310 AND name contains "Waterc"
                //   "Waterc"       → name contains "Waterc" (unchanged)
                $idPart = null;
                $namePart = $search;
                if (preg_match('/^(\d+)\s*(.*)$/', $search, $m)) {
                    $idPart = (int) $m[1];
                    $namePart = trim($m[2]);
                }

                $query->where(function ($query) use ($search, $idPart, $namePart) {
                    $query->where('customers.virtual_customer_prefix', 'LIKE', "{$search}%")
                        ->orWhere('customers.virtual_customer_code', 'LIKE', "{$search}%")
                        ->orWhere('customers.name', 'LIKE', "%{$search}%");

                    // Only treat the number as a Site ID when it's in the ref_id
                    // range (>= RUNNING_NUMBER_INIT); a small number like "35" is
                    // left to the name match above (e.g. "Blk 35").
                    if ($idPart !== null && $idPart >= self::RUNNING_NUMBER_INIT) {
                        $realId = $idPart - self::RUNNING_NUMBER_INIT;
                        if ($namePart !== '') {
                            $query->orWhere(function ($q) use ($realId, $namePart) {
                                $q->where('customers.id', $realId)
                                    ->where('customers.name', 'LIKE', "%{$namePart}%");
                            });
                        } else {
                            $query->orWhere('customers.id', $realId);
                        }
                    }
                });
            })
            ->when($request->settlement_ref, function ($query, $search) {
                // Hidden Summary filter: match a site by a settlement ledger
                // reference (e.g. LF-000351 / OB-000095 / PMT-000564),
                // regardless of entry type. Exact, case-insensitive match on
                // the full reference so it pinpoints the one owning site.
                $ref = strtoupper(trim((string) $search));
                $query->whereExists(function ($q) use ($ref) {
                    $q->selectRaw('1')
                        ->from('customer_settlements')
                        ->whereColumn('customer_settlements.customer_id', 'customers.id')
                        ->whereRaw('UPPER(customer_settlements.reference_no) = ?', [$ref]);
                });
            })
            ->when($request->billing_company, function ($query, $search) {
                // Billing Company filter — mirrors the Summary page's displayed
                // value: contact.company (the Edit form's "Bill From" field) with
                // a fallback to the legacy CMS-mirrored company_remark for sites
                // never edited in mark1. Match a site if EITHER source contains
                // the search term, so results line up with what the column shows.
                $query->where(function ($query) use ($search) {
                    $query->whereHas('contact', function ($q) use ($search) {
                        $q->where('company', 'LIKE', "%{$search}%");
                    })
                        ->orWhere('customers.company_remark', 'LIKE', "%{$search}%");
                });
            })
            ->when($request->frequency_per_week_status, function ($query, $search) {
                if ($search != 'all') {
                    $query->whereIn('frequency_per_week_status', $search);
                }
            })
            ->when($request->is_active, function ($query, $search) use ($request) {
                if ($search != 'all') {
                    $query->where('customers.is_active', filter_var($search, FILTER_VALIDATE_BOOLEAN));
                }
            })
            ->when($request->is_binded_vend, function ($query, $search) {
                if ($search != 'all') {
                    $searchBoolean = filter_var($search, FILTER_VALIDATE_BOOLEAN);
                    if ($searchBoolean)
                        $query->whereHas('vend');
                    else {
                        $query->doesntHave('vend');
                    }
                }
            })
            ->when($request->is_cms, function ($query, $search) {
                if ($search != 'all') {
                    $searchBoolean = filter_var($search, FILTER_VALIDATE_BOOLEAN);
                    if ($searchBoolean)
                        $query->whereNotNull('person_id');
                    else {
                        $query->whereNull('person_id');
                    }
                }
            })
            // Contract Attachment? filter — Yes/No on whether the site has ever
            // had any contract attachment uploaded (contracts() = Attachment
            // type=3). "all"/empty = no filter. Listing page has no period
            // concept, so this is a plain has/has-not-any check.
            ->when($request->contract_attachment, function ($query, $search) {
                if ($search != 'all') {
                    if (filter_var($search, FILTER_VALIDATE_BOOLEAN)) {
                        $query->whereHas('contracts');
                    } else {
                        $query->whereDoesntHave('contracts');
                    }
                }
            })
            ->when($request->handled_by, fn($query, $input) => $query->where('handled_by', $input))
            ->when($request->location_types, function ($query, $search) {
                if (!in_array('all', $search)) {
                    $query->whereIn('location_type_id', $search);
                }
            })
            ->when($request->operator_id, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('customers.operator_id', $search);
                }
            })
            ->when($request->operators, function ($query, $search) {
                if (!in_array('all', $search)) {
                    $query->whereIn('customers.operator_id', $search);
                }
            })
            ->when($request->preferredDays, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    foreach ($search as $day) {
                        $subQuery->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(customers.preferred_visit_days_json, '$.\"$day\"')) = 'true'");
                    }
                });
            })
            ->when($request->price_template_id, fn($query, $input) => $query->where('price_template_id', $input))
            ->when($request->profile_id, fn($query, $input) => $query->where('profile_id', $input))
            ->when($request->ref_id, function ($query, $search) {
                $query->where('customers.id', '=', ($search - 20000));
            })
            ->when($request->selling_price_type, fn($query, $input) => $query->where('selling_price_type', $input))
            ->when($request->status, function ($query, $input) {
                // Site Status filter — now multi-select. Accepts an array of
                // status_ids OR a single scalar (backwards compatible). 'all'
                // (or an empty selection) means no constraint. Selecting one or
                // more concrete ids narrows to those via whereIn.
                $vals = array_values(array_filter(
                    is_array($input) ? $input : [$input],
                    fn ($v) => $v !== null && $v !== '' && (string) $v !== 'all'
                ));
                if (!empty($vals)) {
                    $query->whereIn('status_id', $vals);
                }
            })
            ->when($request->tags, function ($query, $search) {
                // Tags filter (multi-select). Keep customers bound to ANY of
                // the selected tag ids — mirrors the whereIn semantics used by
                // the other multi-selects here (location_types, operators).
                // Empty array is falsy, so no selection = no filter ("all").
                $tagIds = array_values(array_filter(
                    is_array($search) ? $search : [$search],
                    fn ($v) => $v !== null && $v !== '' && $v !== 'all'
                ));
                if (!empty($tagIds)) {
                    $query->whereHas('tagBindings', function ($q) use ($tagIds) {
                        $q->whereIn('tag_id', $tagIds);
                    });
                }
            })
            ->when($request->vend_code, function ($query, $search) {
                $query->whereIn(
                    'customers.id',
                    Vend::where('code', 'LIKE', '%' . $search . '%')
                        ->pluck('customer_id')
                );
            })
            ->when($request->vend_model_id, function ($query, $search) {
                if ($search != 'all') {
                    $query->whereHas('vend', function ($query) use ($search) {
                        $query->where('vend_model_id', $search);
                    });
                }
            })
            ->when($request->vendConfigs, function ($query, $search) {
                if (!in_array('all', $search)) {
                    $query->whereHas('vend', function ($query) use ($search) {
                        $query->whereIn('vend_config_id', $search);
                    });
                }
            })
            ->when($request->vendPrefixes, function ($query, $search) {
                $query->whereHas('vend', function ($query) use ($search) {
                    if (in_array('single-ud', $search)) {
                        $search = array_unique(array_merge($search, [56, 57, 58, 60, 63, 64, 76, 83]));
                        unset($search[array_search('single-ud', $search)]);
                    }
                    $query->whereIn('vend_prefix_id', $search);
                });
            })
            ->when($request->zones, function ($query, $search) {
                if (!in_array('all', $search)) {
                    $query->whereIn('zone_id', $search);
                }
            })
            ->when($request->sortKey, function ($query, $search) use ($request) {
                // Check if the sortKey involves a JSON field
                if (strpos($search, '->')) {
                    $inputSearch = explode("->", $search);
                    if (
                        $search === 'vend_transaction_totals_json->vend_records_amount_latest' or
                        $search === 'vend_transaction_totals_json->vend_records_amount_average_day' or
                        $search === 'vend_transaction_totals_json->vend_records_thirty_days_amount_average' or
                        $search === 'vend_transaction_totals_json->vend_records_thirty_days_amount' or
                        $search === 'vend_transaction_totals_json->thirty_days_gross_profit'
                    ) {
                        $query->orderByRaw('(CAST(json_unquote(json_extract(`' . 'totals_json' . '`, "$.' . $inputSearch[1] . '")) AS DECIMAL(10,2))) ' . (filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'));
                    } else {
                        $query->orderByRaw('LENGTH(json_unquote(json_extract(`' . $inputSearch[0] . '`, "$.' . $inputSearch[1] . '")))' . (filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'));
                    }

                    $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                } else {
                    // Handle sorting for non-JSON fields
                    $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                }
            });

    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Resources\CountryResource;
use App\Http\Resources\DeliveryPlatformResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\PaymentGatewayResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\VendResource;
use App\Models\AlertEmailItem;
use App\Models\Country;
use App\Models\Customer;
use App\Models\DeliveryPlatform;
use App\Models\DeliveryPlatformOperator;
use App\Models\Operator;
use App\Models\OperatorPaymentGateway;
use App\Models\OperatorVend;
use App\Models\PaymentGateway;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vend;
use App\Traits\HasFilter;
use Carbon\Carbon;
use DateTimeZone;
use DB;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\AutoEncoder;
use Intervention\Image\Laravel\Facades\Image;
use Inertia\Inertia;

class OperatorController extends Controller
{
    use HasFilter;

    public function __construct()
    {
        $this->middleware(['permission:read operators']);
        $this->middleware(['permission:create operators'])->only(['create', 'store']);
        $this->middleware(['permission:delete operators'])->only(['delete']);
    }

    public function index(Request $request)
    {
        $timezones = DateTimeZone::listIdentifiers();
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        $authUser = auth()->user();
        $authOperatorId = $authUser->operator_id;
        $isOperatorRestricted = $authOperatorId && $authOperatorId != 1;

        return Inertia::render('Operator/Index', [
            // 'countries' => CountryResource::collection(Country::orderBy('sequence')->orderBy('name')->get()),
            // 'deliveryPlatformOperatorTypes' => [
            //     DeliveryPlatformOperator::TYPE_SANDBOX,
            //     DeliveryPlatformOperator::TYPE_PRODUCTION
            // ],
            'operators' => OperatorResource::collection(
                Operator::with([
                    'address:id,postcode',
                    // 'deliveryPlatformOperators.deliveryPlatform',
                    'country:id,name,code,currency_name,currency_symbol',
                    'vends:id,code,customer_id,is_active',
                    'vends.customer:id,code,name,person_id,virtual_customer_code,virtual_customer_prefix',
                ])
                    ->when($request->name, function ($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($isOperatorRestricted, function ($query) use ($authOperatorId) {
                        $query->where('id', $authOperatorId);
                    })
                    ->when($sortKey, function ($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
            // 'operatorPaymentGatewayTypes' => [
            //     OperatorPaymentGateway::TYPE_SANDBOX,
            //     OperatorPaymentGateway::TYPE_PRODUCTION
            // ],
            // 'timezones' => $timezones,
            // 'countryDeliveryPlatforms' =>
            //     DeliveryPlatformResource::collection(
            //         DeliveryPlatform::with(['country'])
            //         ->when($request->country_id, function($query, $search) {
            //             $query->where('country_id', $search);
            //         })
            //         ->orderBy('name')
            //         ->get()
            //     )
            // ,
            // 'countryPaymentGateways' =>
            //     PaymentGatewayResource::collection(
            //         PaymentGateway::with(['country'])
            //         ->when($request->country_id, function($query, $search) {
            //             $query->where('country_id', $search);
            //         })
            //         ->orderBy('name')
            //         ->get()
            //     )
            // ,
            // 'unbindedVends' => fn () =>
            //     VendResource::collection(
            //         Vend::with([
            //             'customer:id,code,name'
            //         ])->whereNotIn('id', function($query) use ($request) {
            //             $query->select('vend_id')

            //                 ->where('operator_id', $request->operator_id);
            //         })
            //         ->orderBy('code')
            //         ->get()
            //     )
            // ,
            // 'operator' => fn() => OperatorResource::make(
            //     Operator::with([
            //         'address',
            //         'address.country',
            //         'country',
            //         'deliveryPlatformOperators.deliveryPlatform',
            //         'operatorPaymentGateways.paymentGateway',
            //         'vends',
            //         'vends.customer',
            //     ])
            //     ->when($request->name, function($query, $search) {
            //         $query->where('name', 'LIKE', "%{$search}%");
            //     })
            //     ->when($request->id, function($query, $search) {
            //         $query->where('id', $search);
            //     })
            //     ->first()
            // )
        ]);
    }

    public function create(Request $request)
    {
        $timezones = DateTimeZone::listIdentifiers();

        return Inertia::render('Operator/Create', [
            'countries' => CountryResource::collection(
                Country::query()
                    ->orderBy('sequence')
                    ->orderBy('name')
                    ->get()
            ),
            'timezones' => $timezones,
        ]);
    }

    public function edit(Request $request, $id)
    {
        $authUser = auth()->user();
        if ($authUser->operator_id && $authUser->operator_id != 1 && $authUser->operator_id != $id) {
            abort(403);
        }

        $request->merge(['is_active_vend' => isset($request->is_active_vend) ? $request->is_active_vend : 'true']);

        $operator = Operator::query()
            ->with([
                'address',
                'address.country',
                'country',
                'vends' => function ($query) use ($request) {
                    $query
                        ->when($request->is_active_vend, function ($query, $search) {
                            if ($search != 'all') {
                                $query->where('is_active', filter_var($search, FILTER_VALIDATE_BOOLEAN));
                            }
                        })
                        ->when($request->vend_code, function ($query, $search) {
                            $query->where('code', 'LIKE', '%' . $search . '%');
                        });
                    $query->select('id', 'code', 'name', 'customer_id', 'operator_id');
                },
                'vends.customer' => function ($query) use ($request) {
                    $query
                        ->when($request->prefix_code, function ($query, $search) {
                            $query->where(function ($query) use ($search) {
                                $query->where('virtual_customer_prefix', 'LIKE', '%' . $search . '%')
                                    ->orWhere('virtual_customer_code', 'LIKE', '%' . $search . '%');
                            });
                        })
                        ->when($request->name, function ($query, $search) {
                            $query->where('name', 'LIKE', '%' . $search . '%');
                        });
                    $query->select('id', 'code', 'name', 'virtual_customer_code', 'virtual_customer_prefix', 'operator_id', 'person_id', 'is_active');
                },
                'deliveryPlatformOperators.deliveryPlatform',
                'operatorCallbacks',
                'operatorPaymentGateways.paymentGateway',
                'logo',
            ])
            ->find($id);
        $timezones = DateTimeZone::listIdentifiers();
        $setting = Setting::query()->first();
        $logoOverrideOperatorIds = collect($setting?->allow_overwrite_logo_operator_ids_array ?? [])
            ->map(fn($value) => (int) $value)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $operatorCanOverrideLogo = $operator ? in_array($operator->id, $logoOverrideOperatorIds, true) : false;

        return Inertia::render('Operator/Edit', [
            'countries' => CountryResource::collection(
                Country::query()
                    ->orderBy('sequence')
                    ->orderBy('name')
                    ->get()
            ),
            'countryDeliveryPlatforms' =>
                DeliveryPlatformResource::collection(
                    DeliveryPlatform::with(['country'])
                        ->when($request->country_id, function ($query, $search) {
                            $query->where('country_id', $search);
                        })
                        ->orderBy('name')
                        ->get()
                )
            ,
            'countryPaymentGateways' =>
                PaymentGatewayResource::collection(
                    PaymentGateway::with(['country'])
                        ->orderBy('name')
                        ->get()
                )
            ,
            'deliveryPlatformOperatorTypes' => [
                DeliveryPlatformOperator::TYPE_SANDBOX,
                DeliveryPlatformOperator::TYPE_PRODUCTION
            ],
            'emailUserOptions' => UserResource::collection(
                User::query()
                    ->select('id', 'name', 'email', 'operator_id', 'is_active')
                    ->where('is_active', true)
                    ->where(function ($query) {
                        $operatorId = auth()->user()->operator_id;
                        $isHappyIce = $operatorId == 1;
                        if (!$isHappyIce && $operatorId) {
                            // Include users from current operator OR superuser group (operator_id = 1)
                            $query->where(function ($q) use ($operatorId) {
                                $q->whereHas('operator', function ($oq) use ($operatorId) {
                                    $oq->where('id', $operatorId);
                                })
                                    ->orWhere('operator_id', 1);
                            });
                        }
                        // If superuser, show all active users by default
                    })
                    ->orderBy('name')
                    ->get()
                    ->map(function ($user) {
                        $user->email = $user->email ?: 'no email';
                        return $user;
                    })
            ),
            'operatorPaymentGatewayTypes' => [
                OperatorPaymentGateway::TYPE_SANDBOX,
                OperatorPaymentGateway::TYPE_PRODUCTION
            ],
            'operator' => OperatorResource::make(
                $operator
            ),
            'operatorCanOverrideLogo' => $operatorCanOverrideLogo,
            'timezones' => $timezones,
            'type' => 'update',
        ]);
    }

    public function deleteDeliveryPlatformOperator($id)
    {
        $deliveryPlatformOperator = DeliveryPlatformOperator::findOrFail($id);
        $operatorId = $deliveryPlatformOperator->operator_id;

        $deliveryPlatformOperator->delete();

        return redirect()->route('operators.edit', [$operatorId]);
    }

    public function deleteOperatorPaymentGateway($id)
    {
        $operatorPaymentGateway = OperatorPaymentGateway::findOrFail($id);
        $operatorId = $operatorPaymentGateway->operator_id;

        $operatorPaymentGateway->delete();

        return redirect()->route('operators.edit', [$operatorId]);
    }

    public function deleteOperatorVend($id)
    {
        $operatorVend = OperatorVend::findOrFail($id);
        $operatorId = $operatorVend->operator_id;

        $operatorVend->delete();

        return redirect()->route('operators.edit', [$operatorId]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'country_id' => 'required',
            'name' => 'required',
            'timezone' => 'required',
            'email_recipients' => ['nullable', 'array'],
            'email_recipients.*.email' => ['required', 'email'],
            'email_recipients.*.label' => ['nullable', 'string', 'max:255'],
        ]);

        if (!$request->has('gst_vat_rate') or $request->gst_vat_rate == null) {
            $request->merge(['gst_vat_rate' => 0]);
        }
        $operator = Operator::create(
            collect($request->all())->except('email_recipients')->toArray()
        );

        // Save recipients into json column
        $operator->email_recipients_json = collect($request->input('email_recipients', []))
            ->map(fn($r) => [
                'email' => strtolower(trim($r['email'] ?? '')),
                'label' => trim($r['label'] ?? ''),
            ])
            ->filter(fn($r) => !empty($r['email']))
            ->unique('email')
            ->values()
            ->all();
        $operator->save();

        // after $operator->save();
        $customEmails = collect($operator->email_recipients_json['customs'] ?? [])->pluck('email');
        $userEmails = User::whereIn('id', $operator->email_recipients_json['user_ids'] ?? [])
            ->whereNotNull('email')
            ->pluck('email');
        // dd($userEmails);
        $this->syncAlertEmailItemsGeneric($operator, $userEmails->merge($customEmails));


        // return redirect()->route('operators');
        return redirect()->route('operators.edit', [$operator->id]);
    }

    public function storeDeliveryPlatformOperator(Request $request, $operatorId)
    {
        $operator = Operator::findOrFail($operatorId);
        $operator->deliveryPlatformOperators()->create($request->all());

        return redirect()->route('operators.edit', [$operatorId]);
    }

    public function storeOperatorPaymentGateway(Request $request, $operatorId)
    {
        // dd($request->all());
        $operator = Operator::findOrFail($operatorId);
        $operator->operatorPaymentGateways()->create($request->all());

        return redirect()->route('operators.edit', [$operatorId]);
    }

    public function update(Request $request, $operatorId)
    {
        $authUser = auth()->user();
        if ($authUser->operator_id && $authUser->operator_id != 1 && $authUser->operator_id != $operatorId) {
            abort(403);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email_user_ids' => ['nullable', 'array'],
            'email_user_ids.*' => ['integer', 'exists:users,id'],
            'email_customs' => ['nullable', 'array'],
            'email_customs.*.email' => ['required', 'email'],
            'email_customs.*.label' => ['nullable', 'string', 'max:255'],
            'transaction_callback_url' => ['nullable', 'url', 'max:255'],
            'alert_callback_url' => ['nullable', 'url', 'max:255'],
        ]);

        if ($request->hasFile('logo')) {
            $request->validate([
                'logo' => ['image', 'max:400'],
            ]);
        }

        $operator = Operator::findOrFail($operatorId);
        $operator->load('logo');

        if ($request->boolean('logo_remove')) {
            $this->deleteOperatorLogo($operator);
        }

        if ($request->hasFile('logo')) {
            $this->deleteOperatorLogo($operator);

            $uploadedLogo = $request->file('logo');
            $image = Image::read($uploadedLogo)
                ->scaleDown(400, 400, fn($constraint) => $constraint->upsize());

            $extension = $uploadedLogo->getClientOriginalExtension() ?: 'png';
            $filename = Str::uuid() . '.' . strtolower($extension);
            $relativePath = 'sys/operators/logos/' . $filename;

            $disk = $this->logoStorageDisk();
            Storage::disk($disk)->put(
                $relativePath,
                (string) $image->encode(new AutoEncoder()),
                ['visibility' => 'public']
            );

            $publicUrl = Storage::disk($disk)->url($relativePath);

            $operator->logo()->updateOrCreate(
                ['type' => Operator::LOGO_ATTACHMENT_TYPE],
                [
                    'local_url' => $relativePath,
                    'full_url' => $publicUrl,
                    'is_active' => true,
                ]
            );
        }

        // Update the rest of the fields
        $payload = collect($request->all())
            ->except(['email_user_ids', 'email_customs', 'logo', 'logo_remove', 'transaction_callback_url', 'alert_callback_url'])
            ->toArray();
        $operator->update($payload);

        // Normalize the JSON we keep for the UI
        $userIds = collect($request->input('email_user_ids', []))
            ->map(fn($v) => (int) $v)->filter()->values();

        $customs = collect($request->input('email_customs', []))
            ->map(fn($r) => [
                'email' => strtolower(trim($r['email'] ?? '')),
                'label' => trim($r['label'] ?? ''),
            ])
            ->filter(fn($r) => !empty($r['email']))
            ->unique('email')
            ->values();

        $operator->email_recipients_json = [
            'user_ids' => $userIds->all(),
            'customs' => $customs->all(),
        ];
        $operator->save();

        // Flatten to email items (with user_id when available) for alert_email_items
        $userEmailItems = User::whereIn('id', $userIds)
            ->whereNotNull('email')
            ->get(['id', 'email'])
            ->map(fn($u) => [
                'email' => strtolower(trim((string) $u->email)),
                'user_id' => (int) $u->id,
            ]);

        $customEmailItems = $customs->pluck('email')
            ->map(fn($e) => [
                'email' => strtolower(trim((string) $e)),
                'user_id' => null,
            ]);

        $items = $userEmailItems->merge($customEmailItems);

        $this->syncAlertEmailItemsGeneric($operator, $items);

        // Handle callbacks
        $txnUrl = $request->input('transaction_callback_url');
        if ($txnUrl) {
            $operator->operatorCallbacks()->updateOrCreate(
                ['code' => 'transaction_upload'],
                ['url' => $txnUrl, 'format' => 'json']
            );
        } else {
            $operator->operatorCallbacks()->where('code', 'transaction_upload')->delete();
        }

        $alertUrl = $request->input('alert_callback_url');
        $alertCodes = ['channel_error_alert', 'vend_offline_alert', 'vend_power_restored_alert'];
        if ($alertUrl) {
            foreach ($alertCodes as $code) {
                $operator->operatorCallbacks()->updateOrCreate(
                    ['code' => $code],
                    ['url' => $alertUrl, 'format' => 'json']
                );
            }
        } else {
            $operator->operatorCallbacks()->whereIn('code', $alertCodes)->delete();
        }

        return redirect()->route('operators.edit', [$operatorId]);
    }



    public function delete($operatorId)
    {
        $operator = Operator::findOrFail($operatorId);
        $operator->delete();

        return redirect()->route('operators');
    }

    public function bindCustomer(Request $request)
    {
        $customer = Customer::findOrFail($request->customer_id);
        $customer->operator_id = $request->operator_id;
        $customer->save();

        return redirect()->route('operators.edit', [$request->operator_id]);
    }

    public function bindVend(Request $request)
    {
        $vend = Vend::findOrFail($request->vend_id);
        $vend->update(['operator_id' => $request->operator_id]);

        if ($vend->customer) {
            $vend->customer()->update(['operator_id' => $request->operator_id]);
        }

        return redirect()->route('operators.edit', [$request->operator_id]);
    }

    public function unbindCustomer(Request $request)
    {
        $customer = Customer::findOrFail($request->customer_id);
        $customer->operator_id = null;
        $customer->save();

        return redirect()->route('operators.edit', [$request->operator_id]);
    }

    public function unbindVend(Request $request)
    {
        $operator = Operator::findOrFail($request->operator_id);
        $vend = Vend::findOrFail($request->vend_id);
        $vend->update(['operator_id' => null]);

        return redirect()->route('operators.edit', [$request->operator_id]);
    }

    public function bindDeliveryPlatform(Request $request, $id)
    {
        $operator = Operator::findOrFail($id);
        // dd($request->all());
        $createdDeliveryPlatformOperator = $operator->deliveryPlatformOperators()->create($request->all());
        if ($request->has('oauth_client_id') and $request->has('oauth_client_secret')) {
            $createdDeliveryPlatformOperator->externalOauthToken()->updateOrCreate([
                'client_id' => $request->oauth_client_id,
                'client_secret' => $request->oauth_client_secret,
            ], [
                'granted_type' => $createdDeliveryPlatformOperator->deliveryPlatform->default_granted_type,
                'scopes' => $createdDeliveryPlatformOperator->deliveryPlatform->default_scopes,
            ]);
        }
    }

    public function unbindDeliveryPlatform($paymentGatewayOperatorId)
    {
        $paymentGatewayOperator = DeliveryPlatformOperator::findOrFail($paymentGatewayOperatorId);
        if ($paymentGatewayOperator->externalOauthToken()->exists()) {
            $paymentGatewayOperator->externalOauthToken()->delete();
        }
        $paymentGatewayOperator->delete();
    }

    protected function deleteOperatorLogo(Operator $operator): void
    {
        if (!$operator->relationLoaded('logo')) {
            $operator->load('logo');
        }

        $logo = $operator->logo;
        if (!$logo) {
            return;
        }

        $disk = $this->logoStorageDisk();

        if ($logo->local_url && Storage::disk($disk)->exists($logo->local_url)) {
            Storage::disk($disk)->delete($logo->local_url);
        }

        $logo->delete();
        $operator->unsetRelation('logo');
    }

    protected function logoStorageDisk(): string
    {
        $default = config('filesystems.default', 'public');

        return $default === 'local' ? 'public' : $default;
    }

    protected function syncAlertEmailItemsGeneric(?Operator $operator, Collection $emails, array $flags = []): void
    {
        $defaults = [
            'is_active' => true,
            'is_send_channel_error_log' => true,
            'is_send_offline_notification' => true,
            'is_send_power_restored_notification' => true,
            'is_send_transaction_no_entry_notification' => true,
        ];
        $flags = array_replace($defaults, $flags);

        DB::transaction(function () use ($operator, $emails, $flags) {
            $q = AlertEmailItem::query();
            $operator ? $q->where('operator_id', $operator->id)
                : $q->whereNull('operator_id');
            $q->delete();

            // Coerce to items with email + optional user_id
            $items = $emails
                ->map(function ($e) {
                    if (is_array($e)) {
                        $email = strtolower(trim((string) ($e['email'] ?? '')));
                        $userId = isset($e['user_id']) && is_numeric($e['user_id']) ? (int) $e['user_id'] : null;
                        return ['email' => $email, 'user_id' => $userId];
                    }
                    $email = strtolower(trim((string) $e));
                    return ['email' => $email, 'user_id' => null];
                })
                ->filter(fn($it) => $it['email'] !== '')
                ->unique('email')
                ->values();

            if ($items->isEmpty())
                return;

            $now = now();
            $rows = $items->map(fn($it) => [
                'operator_id' => $operator?->id,
                'user_id' => $it['user_id'],
                'email' => $it['email'],
                'is_active' => $flags['is_active'],
                'is_send_channel_error_log' => $flags['is_send_channel_error_log'],
                'is_send_offline_notification' => $flags['is_send_offline_notification'],
                'is_send_power_restored_notification' => $flags['is_send_power_restored_notification'],
                'is_send_transaction_no_entry_notification' => $flags['is_send_transaction_no_entry_notification'],
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            AlertEmailItem::insert($rows);
        });
    }

    public function storeOperatorCallback(Request $request, $operatorId)
    {
        $request->validate([
            'code' => 'required|string|max:255',
            'url' => 'required|url',
            'format' => 'nullable|string|in:json,xml,form',
            'description' => 'nullable|string|max:255',
        ]);

        $operator = Operator::findOrFail($operatorId);
        $operator->operatorCallbacks()->create($request->all());

        return redirect()->route('operators.edit', [$operatorId]);
    }

    public function deleteOperatorCallback($id)
    {
        $callback = \App\Models\OperatorCallback::findOrFail($id);
        $operatorId = $callback->operator_id;
        $callback->delete();

        return redirect()->route('operators.edit', [$operatorId]);
    }
}

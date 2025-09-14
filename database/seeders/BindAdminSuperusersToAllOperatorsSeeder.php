<?php

namespace Database\Seeders;

use App\Models\AlertEmailItem;
use App\Models\Operator;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BindAdminSuperusersToAllOperatorsSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Find all active admins under operator_id = 1 with a valid email
        $superusers = User::query()
            ->role('admin')
            ->where('operator_id', 1)
            ->where('is_active', true)
            ->whereNotNull('email')
            ->get(['id', 'email'])
            ->map(function ($u) {
                return [
                    'user_id' => (int) $u->id,
                    'email'   => strtolower(trim((string) $u->email)),
                ];
            })
            ->filter(fn ($it) => filter_var($it['email'], FILTER_VALIDATE_EMAIL))
            ->unique('email')
            ->values();

        if ($superusers->isEmpty()) {
            return; // nothing to do
        }

        $operatorIds = Operator::query()->pluck('id');
        if ($operatorIds->isEmpty()) {
            return; // no operators
        }

        $now = now();

        DB::transaction(function () use ($operatorIds, $superusers, $now) {
            $superuserIds = $superusers->pluck('user_id')->unique()->values();
            foreach ($operatorIds as $operatorId) {
                // Avoid duplicates: gather existing emails for this operator
                $existing = AlertEmailItem::query()
                    ->where('operator_id', $operatorId)
                    ->pluck('email')
                    ->map(fn ($e) => strtolower(trim((string) $e)))
                    ->all();
                $existingSet = array_flip($existing);

                $rows = [];
                foreach ($superusers as $it) {
                    $email = $it['email'];
                    if (isset($existingSet[$email])) {
                        continue; // already bound for this operator
                    }
                    $rows[] = [
                        'operator_id' => $operatorId,
                        'user_id'     => $it['user_id'],
                        'email'       => $email,
                        'is_active'   => true,
                        'is_send_channel_error_log'    => true,
                        'is_send_offline_notification' => true,
                        'is_send_power_restored_notification' => true,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }

                if (!empty($rows)) {
                    AlertEmailItem::insert($rows);
                }

                // Also sync operator.email_recipients_json to include these user_ids
                $operator = Operator::find($operatorId);
                if ($operator) {
                    $json = is_array($operator->email_recipients_json) ? $operator->email_recipients_json : [];

                    $existingUserIds = collect(data_get($json, 'user_ids', []))
                        ->map(fn($v) => (int) $v)
                        ->filter()
                        ->unique()
                        ->values();

                    $customs = collect(data_get($json, 'customs', []))
                        ->map(function ($r) {
                            return [
                                'email' => strtolower(trim((string) data_get($r, 'email', ''))),
                                'label' => trim((string) data_get($r, 'label', '')),
                            ];
                        })
                        ->filter(fn($r) => !empty($r['email']))
                        ->unique('email')
                        ->values();

                    $mergedUserIds = $existingUserIds->merge($superuserIds)->unique()->values()->all();

                    $operator->email_recipients_json = [
                        'user_ids' => $mergedUserIds,
                        'customs'  => $customs->all(),
                    ];
                    $operator->save();
                }
            }
        });
    }
}

<?php

namespace App\Services\Access;

use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Services\UserLogger;
use App\Support\ProductAccess;
use App\Support\VendAccess;
use App\Support\VendCode;
use Illuminate\Support\Facades\DB;

/**
 * The machine level of a product-limited user, as User edit shows and sets it
 * (Brian, 2026-10-27: "based on Access Product, show which machines will be
 * shown to the Product Owner, then 2 buttons: Show (default), No Show").
 *
 * The list is LIVE — the active machines carrying any of the user's products
 * right now (an active channel with capacity, ProductAccess::vendHasChannelSql's
 * definition) — plus every machine already switched to No Show, so a hidden
 * machine that stopped carrying them can still be switched back. The only
 * writer of `user_hidden_vends`; VendAccess reads it.
 */
class UserMachineVisibility
{
    /**
     * @return array<int, array{id: int, code: string, site: ?string, products: array<int, string>, is_active: bool, is_shown: bool, carries: bool}>|null
     *                                                                                                                                                    null when the user is not limited to a product list
     */
    public function listFor(User $user): ?array
    {
        if ($user->product_access_mode !== ProductAccess::MODE_LIST) {
            return null;
        }

        $productIds = ProductAccess::forUser($user) ?? [];
        $hidden = $this->hiddenIds($user);

        if ($productIds === [] && $hidden === []) {
            return [];
        }

        $carrying = DB::table('vend_channels')
            ->join('products', 'products.id', '=', 'vend_channels.product_id')
            ->whereIn('vend_channels.product_id', $productIds ?: [0])
            ->where('vend_channels.is_active', 1)
            ->where('vend_channels.capacity', '>', 0)
            ->select('vend_channels.vend_id', 'products.code')
            ->distinct()
            ->get()
            ->groupBy('vend_id')
            ->map(fn ($rows) => $rows->pluck('code')->sort()->values()->all());

        $query = DB::table('vends')
            ->leftJoin('customers', 'customers.id', '=', 'vends.customer_id')
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->whereIn('vends.id', $carrying->keys()->all() ?: [0])->where('vends.is_active', true))
                ->orWhereIn('vends.id', $hidden ?: [0]))
            ->selectRaw(VendCode::sqlLabel().' as code_label')
            ->addSelect('vends.id', 'vends.code', 'vends.is_active', 'customers.name as site_name');

        // The machines this user could see at all: their own operator's, or every
        // operator's for HappyIce — and never more than the editor may see.
        if ((int) $user->operator_id !== OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID) {
            $query->where('vends.operator_id', $user->operator_id);
        }
        if ($editorOperatorId = OperatorVendFilterScope::viewerOperatorId()) {
            $query->where('vends.operator_id', $editorOperatorId);
        }

        return $query->orderBy('vends.code')->get()
            ->map(fn ($vend) => [
                'id' => (int) $vend->id,
                'code' => $vend->code_label,
                'site' => $vend->site_name,
                'products' => $carrying->get($vend->id, []),
                'is_active' => (bool) $vend->is_active,
                'is_shown' => ! in_array((int) $vend->id, $hidden, true),
                'carries' => $carrying->has($vend->id),
            ])
            ->values()
            ->all();
    }

    /** Show (true) or No Show (false) one machine for this user. */
    public function set(User $user, int $vendId, bool $show, ?User $by = null): void
    {
        $before = $this->hiddenIds($user);

        if ($show) {
            DB::table('user_hidden_vends')->where('user_id', $user->id)->where('vend_id', $vendId)->delete();
        } else {
            DB::table('user_hidden_vends')->insertOrIgnore([
                'user_id' => $user->id,
                'vend_id' => $vendId,
                'created_by' => $by?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // A pivot write fires no model event, so the audit row is written by hand
        // (a synthetic column, as the Machine Stickers picker does).
        UserLogger::recordChanges($user, ['hidden_vend_ids' => [$before, $this->hiddenIds($user)]]);

        VendAccess::flush($user->id);
    }

    /** @return array<int, int> */
    private function hiddenIds(User $user): array
    {
        return DB::table('user_hidden_vends')->where('user_id', $user->id)
            ->pluck('vend_id')->map(fn ($id) => (int) $id)->all();
    }
}

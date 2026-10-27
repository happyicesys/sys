<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Resolves VendAccess once per user for one request or job (scoped in
 * AppServiceProvider): global scopes ask on every query.
 */
class VendAccessResolver
{
    /** @var array<int, VendAccess> */
    private array $memo = [];

    public function for(?User $user): VendAccess
    {
        if (! $user || ! $user->getKey()) {
            return new VendAccess(VendAccess::ALL);
        }

        return $this->memo[(int) $user->getKey()] ??= $this->resolve($user);
    }

    public function flush(?int $userId = null): void
    {
        if ($userId === null) {
            $this->memo = [];

            return;
        }

        unset($this->memo[$userId]);
    }

    private function resolve(User $user): VendAccess
    {
        if ($user->product_access_mode === ProductAccess::MODE_LIST) {
            $hidden = DB::table('user_hidden_vends')
                ->where('user_id', $user->getKey())
                ->pluck('vend_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            return $hidden === []
                ? new VendAccess(VendAccess::ALL)
                : new VendAccess(VendAccess::EXCEPT, $hidden);
        }

        // Through the relation, as every reader did before: Vend's operator scope
        // applies for the signed-in viewer, so a bound machine of another operator
        // drops out (and a list that empties that way means "all", as it always did).
        $bound = $user->vends()->pluck('vends.id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        return $bound === []
            ? new VendAccess(VendAccess::ALL)
            : new VendAccess(VendAccess::ONLY, $bound);
    }
}

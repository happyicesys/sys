<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Models\UserPageView;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Messenger-style unread tracking for the free-text Site Note (customers.notes,
 * shown on /customers/summary) and product Remarks (products.remarks, shown on
 * /products/availability).
 *
 * "Unread" for a user = a row whose note/remark was last touched by SOMEONE
 * ELSE after a reference timestamp:
 *   - sidebar badge  → reference = last_viewed_at  (resets on page visit)
 *   - "Unread" button → reference = unread_since    (survives in-page searches)
 *
 * Operator scoping mirrors HasFilter::filterOperator — operator_id 1 (Happy
 * Ice / HIPL HQ) sees every operator; everyone else is scoped to their own
 * operator_id so they're never notified about sites/products they can't see.
 */
class NoteNotificationService
{
    public const PAGE_SUMMARY = 'customers.summary';
    public const PAGE_AVAILABILITY = 'products.availability';

    /** page_key => sidebar href, so the Vue layout can map badges to menu links. */
    public const PAGE_HREFS = [
        self::PAGE_SUMMARY => '/customers/summary',
        self::PAGE_AVAILABILITY => '/products/availability',
    ];

    /** Cached once per process: is the tracking table migrated yet? */
    protected static ?bool $tableReady = null;

    /**
     * Guard so a deploy that lands before `php artisan migrate` can't 500 every
     * page — the badges simply stay hidden until the table exists.
     */
    protected function ready(): bool
    {
        if (self::$tableReady === null) {
            try {
                self::$tableReady = Schema::hasTable('user_page_views');
            } catch (\Throwable $e) {
                self::$tableReady = false;
            }
        }

        return self::$tableReady;
    }

    /**
     * Record a fresh page arrival: slide the unread window forward and reset
     * last_viewed_at to now (so the sidebar badge clears). Call ONLY on a
     * genuine page load, never on in-page filter re-searches.
     */
    public function markViewed(User $user, string $pageKey): void
    {
        if (!$this->ready()) {
            return;
        }

        $pv = UserPageView::firstOrNew([
            'user_id' => $user->id,
            'page_key' => $pageKey,
        ]);

        $now = Carbon::now();
        // First-ever visit establishes the baseline with an empty window, so
        // the user isn't retroactively flooded with everything ever written.
        $pv->unread_since = $pv->exists ? ($pv->last_viewed_at ?? $now) : $now;
        $pv->last_viewed_at = $now;
        $pv->save();
    }

    /** Per-page sidebar badge counts keyed by href (0 when never visited). */
    public function badgeCounts(User $user): array
    {
        if (!$this->ready()) {
            return [
                self::PAGE_HREFS[self::PAGE_SUMMARY] => 0,
                self::PAGE_HREFS[self::PAGE_AVAILABILITY] => 0,
            ];
        }

        $views = UserPageView::where('user_id', $user->id)
            ->whereIn('page_key', array_keys(self::PAGE_HREFS))
            ->get()
            ->keyBy('page_key');

        return [
            self::PAGE_HREFS[self::PAGE_SUMMARY] => $this->customerUnreadCount(
                $user,
                $views[self::PAGE_SUMMARY]->last_viewed_at ?? null
            ),
            self::PAGE_HREFS[self::PAGE_AVAILABILITY] => $this->productUnreadCount(
                $user,
                $views[self::PAGE_AVAILABILITY]->last_viewed_at ?? null
            ),
        ];
    }

    /**
     * Per-page @mention sidebar badge counts keyed by href (separate from the
     * general unread badge). A mention is "unread" if the note/remark mentions
     * the user AND was last touched by someone else AFTER last_viewed_at — so
     * opening the page clears it (markViewed slides last_viewed_at to now).
     *
     * Unlike the general unread badge, a NULL last_viewed_at (page never
     * visited) counts ALL current mentions rather than 0: being explicitly
     * tagged should surface on first login, and there's no flooding risk.
     */
    public function mentionBadgeCounts(User $user): array
    {
        if (!$this->ready()) {
            return [
                self::PAGE_HREFS[self::PAGE_SUMMARY] => 0,
                self::PAGE_HREFS[self::PAGE_AVAILABILITY] => 0,
            ];
        }

        $views = UserPageView::where('user_id', $user->id)
            ->whereIn('page_key', array_keys(self::PAGE_HREFS))
            ->get()
            ->keyBy('page_key');

        return [
            self::PAGE_HREFS[self::PAGE_SUMMARY] => $this->customerMentionUnreadCount(
                $user,
                $views[self::PAGE_SUMMARY]->last_viewed_at ?? null
            ),
            self::PAGE_HREFS[self::PAGE_AVAILABILITY] => $this->productMentionUnreadCount(
                $user,
                $views[self::PAGE_AVAILABILITY]->last_viewed_at ?? null
            ),
        ];
    }

    /** Start of the current "Unread" button window for a page (nullable). */
    public function unreadSince(User $user, string $pageKey): ?Carbon
    {
        if (!$this->ready()) {
            return null;
        }

        return UserPageView::where('user_id', $user->id)
            ->where('page_key', $pageKey)
            ->value('unread_since');
    }

    // --- Customers (Site Note) -------------------------------------------

    public function customerUnreadCount(User $user, ?Carbon $since): int
    {
        if (!$since) {
            return 0;
        }
        return $this->customerUnreadQuery($user, $since)->count();
    }

    /** IDs of customers with an unread Site Note since $since (empty if null). */
    public function customerUnreadIds(User $user, ?Carbon $since): array
    {
        if (!$since) {
            return [];
        }
        return $this->customerUnreadQuery($user, $since)
            ->pluck('customers.id')->all();
    }

    protected function customerUnreadQuery(User $user, Carbon $since)
    {
        $query = Customer::query()
            ->whereNotNull('notes_updated_at')
            ->where('notes_updated_at', '>', $since)
            ->where(function ($w) use ($user) {
                $w->whereNull('notes_updated_by')
                    ->orWhere('notes_updated_by', '!=', $user->id);
            });

        $this->scopeOperator($query, $user, 'customers');

        return $query;
    }

    // --- Products (Remarks) ----------------------------------------------

    public function productUnreadCount(User $user, ?Carbon $since): int
    {
        if (!$since) {
            return 0;
        }
        return $this->productUnreadQuery($user, $since)->count();
    }

    /** IDs of products with an unread Remark since $since (empty if null). */
    public function productUnreadIds(User $user, ?Carbon $since): array
    {
        if (!$since) {
            return [];
        }
        return $this->productUnreadQuery($user, $since)
            ->pluck('products.id')->all();
    }

    protected function productUnreadQuery(User $user, Carbon $since)
    {
        $query = Product::query()
            ->whereNotNull('remarks_updated_at')
            ->where('remarks_updated_at', '>', $since)
            ->where(function ($w) use ($user) {
                $w->whereNull('remarks_updated_by')
                    ->orWhere('remarks_updated_by', '!=', $user->id);
            });

        $this->scopeOperator($query, $user, 'products');

        return $query;
    }

    // --- Mentions (Phase 2 autocomplete) ---------------------------------

    /**
     * Active users in the SAME operator as $user (excluding themselves) for the
     * @-mention dropdown. Strictly operator-scoped per the agreed design — users
     * from other operators are never mentionable.
     */
    public function mentionableUsers(User $user): array
    {
        return User::query()
            ->where('is_active', true)
            ->where('operator_id', $user->operator_id)
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get(['id', 'name', 'alias'])
            ->toArray();
    }

    // --- Mentions: sites where THIS user is @-mentioned in the Site Note --
    //
    // Mentions live as plain "@token" text inside customers.notes (no join
    // table). The token MentionTextarea inserts is the user's alias when set,
    // otherwise their name (see selectUser() in MentionTextarea.vue), so we
    // match BOTH. Matching is word-boundary aware so a short alias like "@B"
    // can't match "@Brian": the token must be bounded by a non-alphanumeric/
    // underscore char (or string start/end) on each side.

    /** Distinct @mention tokens for $user (alias + name), trimmed, non-empty. */
    protected function mentionTokens(User $user): array
    {
        return collect([$user->alias ?? null, $user->name ?? null])
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function customerMentionedCount(User $user): int
    {
        $query = $this->customerMentionedQuery($user);

        return $query ? $query->count() : 0;
    }

    /** IDs of customers whose Site Note @-mentions $user (empty if none). */
    public function customerMentionedIds(User $user): array
    {
        $query = $this->customerMentionedQuery($user);

        return $query ? $query->pluck('customers.id')->all() : [];
    }

    /** Null when the user has no usable mention token (nothing to match). */
    protected function customerMentionedQuery(User $user)
    {
        $tokens = $this->mentionTokens($user);
        if (empty($tokens)) {
            return null;
        }

        $query = Customer::query()->whereNotNull('notes');
        $query->where(function ($w) use ($tokens) {
            foreach ($tokens as $token) {
                // Escape every non-alphanumeric, non-space char so regex
                // metacharacters in a name/alias (., (), -, +, …) are literal.
                $escaped = preg_replace('/[^A-Za-z0-9 ]/', '\\\\$0', $token);
                $w->orWhereRaw(
                    'notes REGEXP ?',
                    ['(^|[^[:alnum:]_])@' . $escaped . '([^[:alnum:]_]|$)']
                );
            }
        });

        $this->scopeOperator($query, $user, 'customers');

        return $query;
    }

    /**
     * Sidebar badge count: customers that @-mention $user and changed (by
     * someone else) since $since. NULL $since = all current mentions.
     */
    public function customerMentionUnreadCount(User $user, ?Carbon $since): int
    {
        $query = $this->customerMentionedQuery($user);
        if (!$query) {
            return 0;
        }

        $query->where(function ($w) use ($user) {
            $w->whereNull('notes_updated_by')
                ->orWhere('notes_updated_by', '!=', $user->id);
        });
        if ($since) {
            $query->where('notes_updated_at', '>', $since);
        }

        return $query->count();
    }

    public function productMentionedCount(User $user): int
    {
        $query = $this->productMentionedQuery($user);

        return $query ? $query->count() : 0;
    }

    /** IDs of products whose Remarks @-mention $user (empty if none). */
    public function productMentionedIds(User $user): array
    {
        $query = $this->productMentionedQuery($user);

        return $query ? $query->pluck('products.id')->all() : [];
    }

    /** Null when the user has no usable mention token (nothing to match). */
    protected function productMentionedQuery(User $user)
    {
        $tokens = $this->mentionTokens($user);
        if (empty($tokens)) {
            return null;
        }

        $query = Product::query()->whereNotNull('remarks');
        $query->where(function ($w) use ($tokens) {
            foreach ($tokens as $token) {
                $escaped = preg_replace('/[^A-Za-z0-9 ]/', '\\\\$0', $token);
                $w->orWhereRaw(
                    'remarks REGEXP ?',
                    ['(^|[^[:alnum:]_])@' . $escaped . '([^[:alnum:]_]|$)']
                );
            }
        });

        $this->scopeOperator($query, $user, 'products');

        return $query;
    }

    /**
     * Sidebar badge count: products that @-mention $user and changed (by
     * someone else) since $since. NULL $since = all current mentions.
     */
    public function productMentionUnreadCount(User $user, ?Carbon $since): int
    {
        $query = $this->productMentionedQuery($user);
        if (!$query) {
            return 0;
        }

        $query->where(function ($w) use ($user) {
            $w->whereNull('remarks_updated_by')
                ->orWhere('remarks_updated_by', '!=', $user->id);
        });
        if ($since) {
            $query->where('remarks_updated_at', '>', $since);
        }

        return $query->count();
    }

    // --- Shared ----------------------------------------------------------

    /**
     * Restrict to the user's operator unless they are Happy Ice / HQ
     * (operator_id 1), who see all operators. Mirrors filterOperator().
     */
    protected function scopeOperator($query, User $user, string $table): void
    {
        $operatorId = $user->operator_id;
        if ($operatorId && (int) $operatorId !== 1) {
            $query->where($table . '.operator_id', $operatorId);
        }
    }
}

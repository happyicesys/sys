<?php

namespace App\Services\SmartFreezer\Claude;

/**
 * "Zijia counts, Claude names" — what the two AIs together would do with a T05 hold. SHADOW ONLY: nothing reads it
 * to charge, release or move stock.
 *
 * Why (Brian, 2026-11-15, on the 19 sessions a person checked): Zijia's number of units was right 17 times and
 * Claude's 11, Claude's misses almost all too few — customers often take two or three of the same flavour in one grab
 * — while Zijia's misses are mostly the wrong flavour. So the units come from Zijia and only WHICH products from Claude:
 *
 *  - Claude saw something unusual (a put-back, a swap, a hidden pack, goods moved or put in, a pack opened) or
 *    something not on the freezer → a person;
 *  - Zijia's count equals the paid order product by product → settle (today's rule);
 *  - Zijia's total is not the paid total → a person;
 *  - Claude's products are not exactly the paid products → a person;
 *  - one product paid for → settle (the number is Zijia's, the product Claude's and the receipt's);
 *  - several products: settle only when Claude's count equals the paid order product by product, because a split
 *    between two prices is something neither has then confirmed.
 */
final class CombinedCount
{
    public const SETTLE = 'settle';

    public const PERSON = 'person';

    /**
     * @param  array<int, int>  $paid  product id => units paid for
     * @param  array<int|string, int>|null  $zijia  product id (or "code:…") => units Zijia counted, null = no answer
     * @param  array<int, int>|null  $claude  product id => net units Claude counted, null = no answer
     * @param  list<array{kind: string}>  $unusual  what Claude reported as unusual
     * @return array{outcome: string, reason: string}|null null when it cannot be judged (an AI or the sale missing)
     */
    public static function judge(array $paid, ?array $zijia, ?array $claude, array $unusual = [], int $claudeUnknown = 0, int $claudeReturned = 0): ?array
    {
        $paid = self::clean($paid);
        if ($paid === [] || $zijia === null || $claude === null) {
            return null;
        }
        $zijia = self::clean($zijia);
        $claude = self::clean($claude);

        if ($unusual !== [] || $claudeReturned > 0) {
            return self::person(__('Claude saw something unusual'));
        }
        if ($claudeUnknown > 0) {
            return self::person(__('Claude saw something that is not on this freezer'));
        }
        if (self::same($zijia, $paid)) {
            return ['outcome' => self::SETTLE, 'reason' => __('Zijia matched the paid order')];
        }
        $zijiaTotal = array_sum($zijia);
        $paidTotal = array_sum($paid);
        if ($zijiaTotal !== $paidTotal) {
            return self::person(__('Zijia counted :n, :paid paid for', ['n' => $zijiaTotal, 'paid' => $paidTotal]));
        }
        $claudeSet = array_keys($claude);
        $paidSet = array_keys($paid);
        sort($claudeSet);
        sort($paidSet);
        if ($claudeSet !== $paidSet) {
            return self::person(__('Claude saw other products than those paid for'));
        }
        if (count($paid) === 1) {
            return ['outcome' => self::SETTLE, 'reason' => __('Zijia\'s count and Claude\'s product agree with the paid order')];
        }
        if (self::same($claude, $paid)) {
            return ['outcome' => self::SETTLE, 'reason' => __('Claude matched the paid order and Zijia\'s total agrees')];
        }

        return self::person(__('Several products: the units per product are not confirmed'));
    }

    /** @return array{outcome: string, reason: string} */
    private static function person(string $reason): array
    {
        return ['outcome' => self::PERSON, 'reason' => $reason];
    }

    /**
     * @param  array<int|string, int|string>  $units
     * @return array<int|string, int>
     */
    private static function clean(array $units): array
    {
        $units = array_filter(array_map('intval', $units), fn (int $n) => $n > 0);
        ksort($units);

        return $units;
    }

    /**
     * @param  array<int|string, int>  $a
     * @param  array<int|string, int>  $b
     */
    private static function same(array $a, array $b): bool
    {
        return $a == $b;
    }
}

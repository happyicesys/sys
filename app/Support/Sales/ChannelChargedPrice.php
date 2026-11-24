<?php

namespace App\Support\Sales;

use App\Models\Vend;
use App\Services\Product\MachinePriceDelivery;
use Illuminate\Support\Facades\DB;

/**
 * What the machine's screen charged for one unit on a channel, when the sale carries no price of its own for that
 * line (Brian, 2026-11-24, IDN 10027 order 26112112553410027: a 60,000 basket of Tropical Sunrise + Watermelon Berry
 * stored both lines at 32,000, the VMC board price, while the machine sells at the Site's RP2 — 28,000 for Watermelon
 * Berry since 2026-11-11).
 *
 * The vending TRADE carries ONE `Price` for a basket and none per line, so each line is priced by mark1. A machine on
 * Machine Price charges its board price (`vend_channels.amount`). A machine that follows the Site's pricing charges the
 * `server_price` `/thumbnails` served it, when that is above 0 — the APK falls back to the board price otherwise
 * (`clsSlotPara.getPrice`). So for such a machine, per channel code:
 *   1. the price the machine itself last loaded (`vends.price_menu_prices_json`, MachinePriceDelivery::recordFetch —
 *      what it holds now, even when a newer price has not reached it yet);
 *   2. a channel missing from that record (no fetch since tracking began 2026-11-11, or a code added since): the
 *      price `/thumbnails` would serve now (MachinePriceDelivery::expected).
 * A null / 0 price there means the machine charges its board price, so the channel is left out and the caller keeps
 * its board-price fallback.
 */
final class ChannelChargedPrice
{
    /**
     * Server prices the machine charges, keyed by channel code (string). Empty for a machine on Machine Price.
     *
     * @return array<string, int>
     */
    public static function serverPrices(Vend $vend): array
    {
        // A vend loaded with a few columns may lack the switch; read it rather than take "not set" as Machine Price.
        $follows = array_key_exists('is_using_server_price', $vend->getAttributes())
            ? $vend->usesServerPrice()
            : (bool) DB::table('vends')->where('id', $vend->id)->value('is_using_server_price');
        if (! $follows) {
            return [];
        }

        $loaded = self::loadedPrices($vend);
        $expected = null;

        $out = [];
        $codes = $vend->vendChannels->pluck('code')->map(fn ($c) => (string) (int) $c)->all();
        foreach (array_unique(array_merge($codes, array_keys($loaded))) as $code) {
            if (array_key_exists($code, $loaded)) {
                $price = $loaded[$code];
            } else {
                $expected ??= app(MachinePriceDelivery::class)->expected($vend);
                $price = $expected[$code] ?? null;
            }

            if ($price !== null && (int) $price > 0) {
                $out[$code] = (int) $price;
            }
        }

        return $out;
    }

    /** @return array<string, int|null> */
    private static function loadedPrices(Vend $vend): array
    {
        $raw = array_key_exists('price_menu_prices_json', $vend->getAttributes())
            ? $vend->getAttributes()['price_menu_prices_json']
            : DB::table('vends')->where('id', $vend->id)->value('price_menu_prices_json');

        $decoded = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : null);
        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $code => $price) {
            if (is_numeric($code)) {
                $out[(string) (int) $code] = is_numeric($price) ? (int) $price : null;
            }
        }

        return $out;
    }
}

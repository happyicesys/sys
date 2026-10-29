<?php

use App\Models\PaymentMerchant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Brian, 2026-10-29: the design team's newer "Ace Credits & Apex Credits" artwork (Dropbox
 * payment_gateways/PNG "payment gateway_ace credit1.jpg", converted to a 625x625 PNG like the other logos).
 *
 * It goes up under a NEW name, so a board that loaded its menu earlier keeps a working link to the old
 * picture (left on Spaces) and any image cache keyed by URL cannot keep serving the old one. The Eloquent
 * save clears the machine menu cache (OptionCacheBuster); boards pick the new URL at their next app start.
 *
 * Nothing changes unless the merchant still points at one of the earlier Ace & Apex logos, so a re-run or a
 * fresh test DB does nothing. A failed upload leaves the working URL in place and is logged.
 */
return new class extends Migration
{
    private const PUBLIC_URL = 'https://sys.happyice.com.sg/img/payment-merchant/mindef-ace-apex.png';

    private const OLD_PATH = 'payment-merchant/mindef-ace-apex.png';

    private const PATH = 'payment-merchant/mindef-ace-apex-v2.png';

    public function up(): void
    {
        $disk = Storage::disk();
        $merchant = PaymentMerchant::whereIn('image_url', [self::PUBLIC_URL, $disk->url(self::OLD_PATH)])->first();
        $source = database_path('data/payment-merchant/mindef-ace-apex-v2.png');

        if (! $merchant || ! is_file($source)) {
            return;
        }

        if (! $disk->put(self::PATH, file_get_contents($source), 'public') || ! $disk->exists(self::PATH)) {
            Log::warning('Ace & Apex logo v2: upload to the default disk failed; merchant keeps its current URL', ['path' => self::PATH]);

            return;
        }

        $merchant->update(['image_url' => $disk->url(self::PATH)]);
    }

    public function down(): void
    {
        $disk = Storage::disk();
        PaymentMerchant::where('image_url', $disk->url(self::PATH))->update(['image_url' => $disk->url(self::OLD_PATH)]);
    }
};

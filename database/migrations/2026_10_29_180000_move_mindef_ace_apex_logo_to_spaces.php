<?php

use App\Models\PaymentMerchant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * The "Ace Credits & Apex Credits" logo (2026-10-29) was first served from the app's public/ folder.
 * Every other payment logo lives on DO Spaces under `payment-merchant/` (the default disk on prod), so
 * this puts it there too and points the merchant at it. The Eloquent save clears the machine menu cache
 * (OptionCacheBuster), so the next board to load its menu gets the new URL.
 *
 * Nothing changes unless the merchant still points at the public/ copy, so a re-run, or a fresh test
 * DB, does nothing. A failed upload leaves the working public/ URL in place and is logged.
 */
return new class extends Migration
{
    private const OLD_URL = 'https://sys.happyice.com.sg/img/payment-merchant/mindef-ace-apex.png';

    private const PATH = 'payment-merchant/mindef-ace-apex.png';

    public function up(): void
    {
        $merchant = PaymentMerchant::where('image_url', self::OLD_URL)->first();
        $source = public_path('img/payment-merchant/mindef-ace-apex.png');

        if (! $merchant || ! is_file($source)) {
            return;
        }

        $disk = Storage::disk();
        if (! $disk->put(self::PATH, file_get_contents($source), 'public') || ! $disk->exists(self::PATH)) {
            Log::warning('Ace & Apex logo: upload to the default disk failed; merchant keeps its public/ URL', ['path' => self::PATH]);

            return;
        }

        $merchant->update(['image_url' => $disk->url(self::PATH)]);
    }

    public function down(): void
    {
        PaymentMerchant::where('image_url', Storage::disk()->url(self::PATH))->update(['image_url' => self::OLD_URL]);
    }
};

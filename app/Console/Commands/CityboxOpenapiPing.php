<?php

namespace App\Console\Commands;

use App\Exceptions\CityboxApiException;
use App\Services\Citybox\OpenapiClient;
use Illuminate\Console\Command;

/**
 * Day-one credential + signing check against the live CityBox-Openapi.
 *
 * Proves, in order: config present → get_access_token accepts our app_id +
 * MD5 sign (i.e. the secret and the sign concatenation are right) → box_list
 * returns OUR merchant's devices. Optionally dumps one device's live stock.
 *
 * Never prints the secret or the token.
 */
class CityboxOpenapiPing extends Command
{
    protected $signature = 'citybox:openapi-ping {--device= : Also pull this device_id\'s live products}';

    protected $description = 'Validate CityBox-Openapi credentials, signing and device visibility';

    public function handle(OpenapiClient $client): int
    {
        try {
            $token = $client->accessToken();
            $this->info(sprintf('1/2 access_token OK (%d chars) — app_id + secret + signing accepted.', strlen($token)));

            $boxes = $client->boxList();
        } catch (CityboxApiException $e) {
            $this->error($e->getMessage());
            $this->line('  → app_id有误  : app_id not registered / typo');
            $this->line('  → 签名/sign 错误: secret wrong, or sign concatenation variant — check OpenapiSigner');
            $this->line('  → 无权限       : app_id not authorised for this endpoint (their side)');

            return self::FAILURE;
        }

        $this->info(sprintf('2/2 box_list OK — %d device(s) visible to this app_id.', count($boxes)));
        foreach (array_slice($boxes, 0, 10) as $box) {
            $this->line(sprintf(
                '  %-16s %-24s type=%-10s %s / %s',
                $box['equipment_id'] ?? '?',
                $box['name'] ?? '?',
                $box['type'] ?? '?',
                $box['equipment_status_str'] ?? $box['status'] ?? '?',
                $box['equipment_online_status_str'] ?? $box['equipment_online_status'] ?? '?',
            ));
        }
        if (count($boxes) > 10) {
            $this->line('  … '.(count($boxes) - 10).' more');
        }
        if (! $boxes) {
            $this->warn('  No devices — either none assigned to this app_id yet, or wrong environment (test vs prod).');
        }

        if ($deviceId = $this->option('device')) {
            try {
                $body = $client->deviceProduct($deviceId);
            } catch (CityboxApiException $e) {
                $this->error("device_product({$deviceId}): ".$e->getMessage());

                return self::FAILURE;
            }
            $goods = $body['goods'] ?? [];
            $this->info(sprintf('device_product(%s): %d product row(s)', $deviceId, count($goods)));
            foreach ($goods as $g) {
                $this->line(sprintf(
                    '  [%s] %-30s qty=%-4s price=%-6s %s %s',
                    $g['product_id'] ?? '?',
                    $g['name'] ?? '?',
                    $g['quantity'] ?? '?',
                    $g['price'] ?? '?',
                    $g['volume'] ?? '',
                    $g['unit'] ?? '',
                ));
            }
        }

        return self::SUCCESS;
    }
}

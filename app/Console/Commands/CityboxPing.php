<?php

namespace App\Console\Commands;

use App\Exceptions\CityboxApiException;
use App\Services\Citybox\CityboxClient;
use Illuminate\Console\Command;

/**
 * One-shot connectivity + credential check against the live Citybox API.
 * Run this the day Citybox hands over the real domain + api_secret, before
 * flipping CITYBOX_ENABLED anywhere that matters.
 */
class CityboxPing extends Command
{
    protected $signature = 'citybox:ping {--equipment_id= : Also pull one device\'s product stock}';

    protected $description = 'Validate Citybox API connectivity, credentials and signing';

    public function handle(CityboxClient $client): int
    {
        try {
            $data = $client->getEquipment(page: 1, limit: 1);
        } catch (CityboxApiException $e) {
            $this->error($e->getMessage());
            if ($e->apiCode === 401) {
                $this->line('  → 401 means the signature was rejected: verify CITYBOX_API_SECRET,');
                $this->line('    and confirm MD5 vs HMAC-SHA256 with Citybox (their doc contradicts itself).');
            }
            if ($e->apiCode === 402) {
                $this->line('  → 402 means clock skew: check this server\'s NTP sync.');
            }

            return self::FAILURE;
        }

        $total = $data['total'] ?? 'unknown';
        $first = $data['list'][0] ?? null;
        $this->info("OK — signing accepted. Equipment total: {$total}");
        if ($first) {
            $this->line(sprintf(
                '  first device: %s "%s" (status: %s, online: %s)',
                $first['equipment_id'] ?? '?',
                $first['equipment_name'] ?? '?',
                $first['equipment_status_str'] ?? $first['equipment_status'] ?? '?',
                $first['equipment_online_status_str'] ?? $first['equipment_online_status'] ?? '?',
            ));
        }

        if ($equipmentId = $this->option('equipment_id')) {
            $products = $client->getEquipmentProducts($equipmentId);
            $this->info(sprintf('Products on %s: %s rows', $equipmentId, $products['total'] ?? 0));
            foreach (array_slice($products['list'] ?? [], 0, 5) as $row) {
                $this->line(sprintf(
                    '  %s — stock %s, pre_qty %s',
                    $row['product_name'] ?? '?',
                    $row['stock'] ?? '?',
                    $row['pre_qty'] ?? '?',
                ));
            }
        }

        return self::SUCCESS;
    }
}

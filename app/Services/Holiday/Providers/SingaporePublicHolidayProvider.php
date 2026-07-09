<?php

namespace App\Services\Holiday\Providers;

use App\Contracts\Holiday\HolidayProvider;
use App\Services\Holiday\DTO\PublicHolidayData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Singapore public holidays from the Ministry of Manpower consolidated dataset
 * on data.gov.sg (CKAN datastore_search API).
 *
 * Response shape:
 *   { success: true, result: {
 *       fields:  [ {id:"date"}, {id:"day"}, {id:"holiday"}, {id:"_id"} ],
 *       records: [ { _id, date:"2026-01-01", day:"Thursday", holiday:"New Year's Day" } ],
 *       total:   104
 *   } }
 *
 * Notes: `day`/`holiday` values occasionally carry stray whitespace/newlines and
 * curly apostrophes; we normalize them. Paginated via limit/offset against total.
 */
class SingaporePublicHolidayProvider implements HolidayProvider
{
    /**
     * @param  array{endpoint:string, resource_id:string, timezone?:string}  $config
     */
    public function __construct(
        protected array $config,
    ) {
    }

    public function key(): string
    {
        return 'sg';
    }

    public function region(): string
    {
        return 'Singapore';
    }

    /**
     * @return PublicHolidayData[]
     */
    public function fetchPublicHolidays(): array
    {
        $endpoint = $this->config['endpoint'] ?? '';
        $resourceId = $this->config['resource_id'] ?? '';

        if ($endpoint === '' || $resourceId === '') {
            throw new RuntimeException('Holiday provider [sg] is missing endpoint/resource_id config.');
        }

        $holidays = [];
        $limit = 100;
        $offset = 0;
        $guard = 0; // hard stop so a bad `total` can never loop forever

        do {
            $response = Http::timeout(15)->retry(2, 500)->acceptJson()->get($endpoint, [
                'resource_id' => $resourceId,
                'limit'       => $limit,
                'offset'      => $offset,
            ]);

            if (! $response->successful()) {
                throw new RuntimeException("Public holiday API returned HTTP {$response->status()}.");
            }

            $json = $response->json();

            if (! is_array($json) || ($json['success'] ?? false) !== true || ! isset($json['result'])) {
                throw new RuntimeException('Public holiday API returned an unexpected payload.');
            }

            $result = $json['result'];
            $records = $result['records'] ?? [];
            $total = (int) ($result['total'] ?? 0);

            foreach ($records as $record) {
                $rawDate = trim((string) ($record['date'] ?? ''));
                $name = $this->cleanName((string) ($record['holiday'] ?? ''));

                if ($rawDate === '' || $name === '') {
                    continue;
                }

                try {
                    $date = Carbon::parse($rawDate)->toDateString();
                } catch (Throwable $e) {
                    continue; // skip unparseable dates rather than failing the whole run
                }

                $holidays[] = new PublicHolidayData($date, $name);
            }

            $offset += $limit;
            $guard++;
        } while ($offset < $total && $guard < 50);

        return $holidays;
    }

    /**
     * Collapse whitespace/newlines and normalize curly apostrophes to straight.
     */
    protected function cleanName(string $name): string
    {
        $name = str_replace(["\u{2019}", "\u{2018}"], "'", $name);
        $name = preg_replace('/\s+/u', ' ', $name);

        return trim((string) $name);
    }
}

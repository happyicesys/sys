<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class WeatherService
{
    /**
     * Get historical weather icon for a specific date and location.
     *
     * @param Carbon $date
     * @param float $lat
     * @param float $lng
     * @return string|null
     */
    /**
     * Get historical weather icons for a date range.
     * Returns array [ 'YYYY-MM-DD' => 'icon_code' ]
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param float $lat
     * @param float $lng
     * @return array
     */
    /**
     * Get historical weather icons for a date range.
     * Returns array [ 'YYYY-MM-DD' => 'icon_code' ]
     */
    public function getDailyWeatherForRange(Carbon $startDate, Carbon $endDate, float $lat, float $lng)
    {
        // 1. Fetch existing records from DB
        $existingRecords = \App\Models\DailyWeatherHistory::query()
            ->where('latitude', $lat)
            ->where('longitude', $lng)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->keyBy('date');

        $result = [];
        $missingDates = [];
        $currentDate = $startDate->copy();

        // 2. Identify missing dates and build result for existing ones
        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            if ($record = $existingRecords->get($dateStr)) {
                $result[$dateStr] = $record->icon_code;
            } else {
                $missingDates[] = $dateStr;
            }
            $currentDate->addDay();
        }

        // 3. If everything is cached in DB, return
        if (empty($missingDates)) {
            return $result;
        }

        // 4. Fetch missing data from API
        // We fetch the whole range again from API to keep logic simple,
        // or we could try to fetch chunks. For simplicity + latency, fetching full requested range
        // or just the missing chunks is okay. Since the user usually requests a month,
        // and if some days are missing (e.g. today), we might as well query the API for the whole window
        // effectively, but simpler to just query the full range if gaps exist, or query specifically.
        // Let's query the specific range of missing dates. Ideally we find the min/max of missing dates.

        $apiStart = Carbon::parse(min($missingDates));
        $apiEnd = Carbon::parse(max($missingDates));

        try {
            // Logic to choose API
            $ninetyDaysAgo = Carbon::today()->subDays(90);
            $useForecast = $apiStart->gte($ninetyDaysAgo);

            $baseUrl = $useForecast
                ? 'https://api.open-meteo.com/v1/forecast'
                : 'https://archive-api.open-meteo.com/v1/archive';

            $response = Http::get($baseUrl, [
                'latitude' => $lat,
                'longitude' => $lng,
                'start_date' => $apiStart->format('Y-m-d'),
                'end_date' => $apiEnd->format('Y-m-d'),
                'daily' => 'weathercode',
                'timezone' => 'auto',
            ]);

            if ($response->successful()) {
                $json = $response->json();
                if (isset($json['daily']['time']) && isset($json['daily']['weathercode'])) {
                    foreach ($json['daily']['time'] as $index => $dateStr) {
                        // Only process if this date is actually missing and needed
                        if (in_array($dateStr, $missingDates)) {
                            $code = $json['daily']['weathercode'][$index] ?? null;
                            if ($code !== null) {
                                $icon = $this->mapWmoToIcon($code);

                                // Store to DB
                                \App\Models\DailyWeatherHistory::updateOrCreate(
                                    [
                                        'date' => $dateStr,
                                        'latitude' => $lat,
                                        'longitude' => $lng,
                                    ],
                                    [
                                        'weather_code' => $code,
                                        'icon_code' => $icon,
                                    ]
                                );

                                $result[$dateStr] = $icon;
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Fallback: return what we have
        }

        return $result;
    }

    /**
     * Map WMO Weather Codes to OpenWeatherMap Icon IDs.
     */
    private function mapWmoToIcon($wmoCode)
    {
        return match (true) {
            // Sunny: Clear sky, Mainly clear
            $wmoCode === 0 || $wmoCode === 1 => '01d',

                // Cloudy: Partly cloudy, Overcast, Fog
            ($wmoCode >= 2 && $wmoCode <= 3) || ($wmoCode >= 45 && $wmoCode <= 48) => '03d',

            // Rainy: Drizzle, Rain, Snow, Showers, Thunderstorm
            default => '09d',
        };
    }
}

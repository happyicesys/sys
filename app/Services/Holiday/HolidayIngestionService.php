<?php

namespace App\Services\Holiday;

use App\Contracts\Holiday\HolidayProvider;
use App\Models\Holiday;
use App\Services\Holiday\DTO\PublicHolidayData;

/**
 * Persists fetched public holidays into the shared `holidays` table.
 *
 * Idempotent, and scoped to the sync's own rows: a public holiday is matched on
 * its exact single-day (date_from == date_to == date). If a row already exists
 * for that date it is overwritten (name/type/source); otherwise a new row is
 * inserted. Manually-entered multi-day ranges never collide (their date_to
 * differs), so hand-entered holidays are preserved.
 */
class HolidayIngestionService
{
    /**
     * @param  PublicHolidayData[]  $holidays
     * @return array{total:int, created:int, updated:int}
     */
    public function ingestPublic(HolidayProvider $provider, array $holidays): array
    {
        $created = 0;
        $updated = 0;

        foreach ($holidays as $holiday) {
            $model = Holiday::firstOrNew([
                'date_from' => $holiday->date,
                'date_to'   => $holiday->date,
            ]);

            $existed = $model->exists;

            $model->name = $holiday->name;
            $model->type = 'public';
            $model->source = 'api';
            $model->save();

            $existed ? $updated++ : $created++;
        }

        return [
            'total'   => count($holidays),
            'created' => $created,
            'updated' => $updated,
        ];
    }
}

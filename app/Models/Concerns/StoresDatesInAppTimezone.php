<?php

namespace App\Models\Concerns;

use Illuminate\Support\Carbon;

/**
 * A date string that names its own timezone ("2023-10-21T16:00:00.000000Z", "…+08:00") is converted to the app
 * timezone before it is stored.
 *
 * Laravel serializes a date cast as UTC ISO-8601, and the Site and Machine edit pages post the whole record back
 * as they received it. Eloquent's fromDateTime() keeps the WALL time of a parsed string and drops its zone, so the
 * UTC "16:00Z" of a Singapore midnight was stored as 16:00 Singapore — every save moved begin_date /
 * termination_date / last_invoice_date 8 hours back, a whole day every second or third save (2026-10-19: Site 5735's
 * termination date 10-08 → 10-07 in two saves; 1,954 such saves since the audit log began).
 *
 * Strings only: a Carbon built in code, a plain "Y-m-d" or "Y-m-d H:i:s" string are stored exactly as before.
 */
trait StoresDatesInAppTimezone
{
    public function fromDateTime($value)
    {
        if (is_string($value) && preg_match('/\d{2}:\d{2}(:\d{2}(\.\d+)?)?\s*(Z|[+-]\d{2}:?\d{2})$/i', trim($value))) {
            $value = Carbon::parse(trim($value))->setTimezone(config('app.timezone'));
        }

        return parent::fromDateTime($value);
    }
}

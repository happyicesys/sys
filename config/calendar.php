<?php

/*
|--------------------------------------------------------------------------
| Calendar & cohort rules for the daily sales-analysis report facts
|--------------------------------------------------------------------------
|
| Drives DimensionRebuilder (dim_calendar + dim_site_cohort). Everything the
| morning report used to hardcode — school-term ranges, the Zoo/Sentosa/Masjid
| site matching, the record floor — lives here so it is tunable without code.
|
| Region-agnostic by construction: only the SG deployment has school terms +
| a rainfall provider, so a non-SG instance can simply leave 'school_terms'
| empty and every date resolves to not-in-term.
*/

return [

    /*
    | Company daily-sales record floor. fact_daytype_record only considers days
    | on/after this date ("assume our system calculation only takes place on").
    | Matches the reporting.floor_date convention. Extend/override via env.
    */
    'record_floor' => env('REPORT_RECORD_FLOOR', '2026-04-01'),

    /*
    | How far forward dim_calendar is pre-materialised past "today" each run, so
    | the report can look up buckets for near-future dates. holiday_days already
    | extends to 2026-12-31; this just bounds the generated calendar rows.
    */
    'calendar_forward_days' => 200,
    'calendar_floor'        => env('REPORT_CALENDAR_FLOOR', '2023-01-01'),

    /*
    | MOE school-term ranges (school in session), inclusive [start, end].
    | Source: MOE "School Terms and Holidays for 2026" press release.
    |   is_school_term      = date falls within any range for its year.
    |   is_madrasah_active  = is_school_term AND the date is a weekend
    |                         (madrasah weekend classes during term).
    | Add a "YYYY" => [...] block each year. A year with no block → never term.
    */
    'school_terms' => [
        '2026' => [
            ['2026-01-02', '2026-03-13'], // Term 1
            ['2026-03-23', '2026-05-29'], // Term 2
            ['2026-06-29', '2026-09-04'], // Term 3
            ['2026-09-14', '2026-11-20'], // Term 4
        ],
    ],

    /*
    | Site cohort classification. Resolved once/night per active site into
    | dim_site_cohort. Precedence (first match wins):
    |   1. name / site_name matches a 'mosque_name_patterns' token
    |      → mosque_madrasah (a mosque under a generic "Religious" location
    |      type; the name is the stronger signal).
    |   2. location_types.name (lowercased) mapped via 'location_type_cohort'.
    |   3. name / site_name matches a cohort in 'name_patterns'.
    |   4. 'default_cohort'.
    | Valid cohorts: tourist_leisure, mosque_madrasah, weekday_routine,
    | school_linked, other.
    */
    'cohorts' => [

        'default_cohort' => 'other',

        'mosque_name_patterns' => ['masjid', 'mosque', 'madrasah', 'surau'],

        // location_types.name (lowercased, trimmed) => cohort. Primary signal;
        // mirrors the actual SG location_types rows. Residential/community types
        // (condo, hdb, cc & rc) are intentionally omitted → default 'other'.
        'location_type_cohort' => [
            'zoo'                         => 'tourist_leisure',
            'sentosa'                     => 'tourist_leisure',
            'recreational'                => 'tourist_leisure',
            'shopping mall'               => 'tourist_leisure',
            'hotel/ hostel/ co-living'    => 'tourist_leisure',
            'religious'                   => 'mosque_madrasah',
            'secondary school'            => 'school_linked',
            'int. school & teritary'      => 'school_linked',
            'childcare or tuition center' => 'school_linked',
            'government agency'           => 'weekday_routine',
            'corporate office'            => 'weekday_routine',
            'commercial/ industrial'      => 'weekday_routine',
            'factory/ warehouse'          => 'weekday_routine',
            'construction sites'          => 'weekday_routine',
            'worker domitory'             => 'weekday_routine',
            'hospital'                    => 'weekday_routine',
            'f&b food court'              => 'weekday_routine',
        ],

        // Fallback token matching on name/site_name when location_type is
        // null or unmapped.
        'name_patterns' => [
            'tourist_leisure' => ['zoo', 'sentosa', 'bird paradise', 'river wonder', 'gardens by the bay', 'jewel', 'wildlife', 'aquarium', 'universal', 'cable car', 'mandai', 'safari'],
            'school_linked'   => ['school', 'primary', 'secondary', 'junior college', 'polytechnic', 'institute', 'kindergarten', 'childcare', 'campus', 'university'],
            'mosque_madrasah' => ['masjid', 'mosque', 'madrasah', 'surau'],
        ],
    ],
];

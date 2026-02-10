# Logic Change: Rising Trends Alert Metadata

## Problem
The dashboard shows "Rising lowest T1 and T2 (Last 24hrs vs Last 48hrs)" but only displays the value and timestamp for the "Last 24hrs". The user wanted to see the "Last 48hrs" value (the previous period's minimum) and its timestamp as well.

## Solution

1.  **Backend (`app/Jobs/DetectTempTrends.php`)**:
    *   Updated `analyzeTrend` to calculate the timestamp of the minimum temperature in the previous period (`prev_min`).
    *   Added a private helper method `findMinTimestamp` to duplicate the logic for finding the timestamp of a specific temperature value within a time window.
    *   Stored `prev_min_timestamp` in the `meta_data` of the `VendSmartAlert`.

2.  **Service (`app/Services/MachineHealthDashboardService.php`)**:
    *   Updated `getSmartAlerts` to retrieve `prev_min_timestamp` from `meta_data` and map it to `first_min_temp_at` in the API response.

3.  **Frontend (`resources/js/Pages/Report/MachineHealth/Index.vue`)**:
    *   Updated the "Preventive maintenance / Temp raise alert" section (specifically the rising trend alerts) to display "Last 48hrs lowest T1/T2" along with its value and timestamp.

## Verification
-   Run `DetectTempTrends` job.
-   Check `vend_smart_alerts` table `meta_data` column for `prev_min_timestamp`.
-   Reload the Machine Health Dashboard.
-   Verify that Rising Trends alerts show both "Last 24hrs lowest" and "Last 48hrs lowest" blocks.

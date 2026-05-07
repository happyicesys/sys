-- =============================================================================
-- (1) Alert on Lost of Connectivity or Electricity
-- List of triggered alerts since 1 Apr 2026, with matched dismissal time.
--
-- Data source: machine_health_histories
--   trigger:   event = 'machine_health_alert',           alert_type = 'connectivity'
--   dismissal: event = 'machine_health_alert_dismissed', alert_type = 'connectivity'
-- Matched on vend_id + bucket; dismissal must occur at/after the trigger.
-- =============================================================================

SELECT
    mh.id                                             AS alert_id,
    v.code                                            AS machine_code,
    vp.name                                           AS vend_prefix,
    c.name                                            AS customer_name,
    o.name                                            AS operator_name,
    mh.bucket                                         AS bucket,
    ROUND(
        COALESCE(
            JSON_UNQUOTE(JSON_EXTRACT(d.context, '$.lapse_hours')) + 0,
            JSON_UNQUOTE(JSON_EXTRACT(mh.context, '$.hours_offline')) + 0
        ),
        2
    )                                                 AS hours_offline,
    mh.occurred_at                                    AS triggered_at,
    d.occurred_at                                     AS dismissed_at,
    GREATEST(
        COALESCE(v.mqtt_last_updated_at,            '1970-01-01'),
        COALESCE(v.last_updated_at,                 '1970-01-01'),
        COALESCE(v.last_vend_transaction_at,        '1970-01-01'),
        COALESCE(v.offline_restart_count_datetime,  '1970-01-01')
    )                                                 AS last_contact_at,
    CASE WHEN d.id IS NULL THEN 'ACTIVE' ELSE 'DISMISSED' END AS status
FROM machine_health_histories mh
INNER JOIN vends         v  ON v.id = mh.vend_id AND v.is_testing = 0
LEFT  JOIN vend_prefixes vp ON vp.id = v.vend_prefix_id
LEFT  JOIN customers     c  ON c.id  = v.customer_id
LEFT  JOIN operators     o  ON o.id  = v.operator_id
LEFT  JOIN machine_health_histories d
       ON d.id = (
           SELECT d2.id
           FROM machine_health_histories d2
           WHERE d2.vend_id     = mh.vend_id
             AND d2.event       = 'machine_health_alert_dismissed'
             AND d2.alert_type  = 'connectivity'
             AND d2.bucket      = mh.bucket
             AND d2.occurred_at >= mh.occurred_at
           ORDER BY d2.occurred_at ASC
           LIMIT 1
       )
WHERE mh.event       = 'machine_health_alert'
  AND mh.alert_type  = 'connectivity'
  AND mh.occurred_at >= '2026-04-01 00:00:00'
ORDER BY mh.occurred_at DESC;

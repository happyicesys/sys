// Shared vend / machine telemetry thresholds.
//
// Keep these values as the single source of truth — any UI that styles a
// machine reading based on a threshold (e.g. red vs. green coin float pill)
// must import from here so all surfaces (Vend pages, OpsJob, Reports,
// Settings, Machine Health) flip color at the same value.

// CoinCnt is the raw coin-float count reported by the machine's parameter_json.
// A reading at or below this is considered "low" (rendered red); above is "healthy" (green).
export const COIN_FLOAT_LOW_THRESHOLD = 3000;

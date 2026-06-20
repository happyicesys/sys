#!/usr/bin/env bash
#
# ONE-TIME full-history reconcile of every vend_transactions-derived rollup.
#
# Heals vend_records + gp_metrics + vend_product_records for any drifted day,
# then cascades a totals_json resync + unlocked Site-Summary recompute. This
# clears the backlog that predates the nightly/weekly reconcile windows
# (e.g. the Nov-2024 vend_records hole), so the machine page, Site Summary
# and Sales Transactions page all tally.
#
# Run ON THE APP HOST (the DB is local to it). A queue worker MUST be running
# — heals dispatch to the 'low' queue. Safe to delete this file afterward.
#
# Usage:
#   ./reconcile_backfill.sh dry     # report drift only, writes nothing (do this first)
#   ./reconcile_backfill.sh heal    # actually heal, year by year
#   ./reconcile_backfill.sh verify  # re-check the known-affected months after healing
#
set -euo pipefail
cd "$(dirname "$0")"

MODE="${1:-dry}"
START_YEAR=2023

# yesterday, GNU (Linux) or BSD (macOS) date
END=$(date -d 'yesterday' +%Y-%m-%d 2>/dev/null || date -v-1d +%Y-%m-%d)
THIS_YEAR=$(date +%Y)

run_reconcile() {
  local flag="$1"
  for Y in $(seq "$START_YEAR" "$THIS_YEAR"); do
    local from="$Y-01-01"
    local to="$Y-12-31"
    [[ "$from" > "$END" ]] && break
    [[ "$to"   > "$END" ]] && to="$END"
    echo "=== reconcile $from .. $to  (${flag:-heal}) ==="
    php artisan reconcile:sales-rollups --from="$from" --to="$to" --queue=low $flag
  done
}

case "$MODE" in
  dry)
    run_reconcile "--dry-run"
    echo
    echo "Dry-run complete. Review drifted days above, then: ./reconcile_backfill.sh heal"
    ;;
  heal)
    run_reconcile ""
    echo
    echo "Heal dispatched. Let the low queue drain (php artisan queue:work --queue=low),"
    echo "then run: ./reconcile_backfill.sh verify"
    ;;
  verify)
    # The 5 months our analysis flagged as drifted for machine 1184's site.
    for M in 2024-09 2024-11 2024-12 2025-01 2025-02; do
      echo "=== validate $M (vend 1184) ==="
      php artisan customer-summary:validate-sales --month="$M" --vend-code=1184 --show-matches || true
    done
    echo
    echo "Any LOCKED + MISMATCH rows must be fixed manually (unlock -> customer-summary:compute -> re-lock)."
    ;;
  *)
    echo "Usage: $0 {dry|heal|verify}" >&2
    exit 1
    ;;
esac

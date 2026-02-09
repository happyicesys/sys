#!/bin/bash

# Job Optimization Verification Script
# This script helps verify that the optimizations are working

echo "========================================="
echo "Job Optimization Verification"
echo "========================================="
echo ""

# Check if migration was applied
echo "1. Checking if optimization migration was applied..."
php artisan migrate:status | grep "add_indexes_for_job_optimization"
echo ""

# Check indexes on vend_transactions
echo "2. Checking indexes on vend_transactions table..."
php artisan tinker --execute="
\$indexes = DB::select('SHOW INDEX FROM vend_transactions WHERE Key_name LIKE \"idx_%\"');
foreach (\$indexes as \$idx) {
    echo \$idx->Key_name . ' - ' . \$idx->Column_name . PHP_EOL;
}
"
echo ""

# Check indexes on vend_records
echo "3. Checking indexes on vend_records table..."
php artisan tinker --execute="
\$indexes = DB::select('SHOW INDEX FROM vend_records WHERE Key_name LIKE \"idx_%\"');
foreach (\$indexes as \$idx) {
    echo \$idx->Key_name . ' - ' . \$idx->Column_name . PHP_EOL;
}
"
echo ""

# Test a sample job execution time
echo "4. Testing SyncVendTransactionTotalsJson job performance..."
echo "   (This will dispatch a test job - check queue dashboard for results)"
php artisan tinker --execute="
\$vend = App\Models\Vend::where('is_active', true)->first();
if (\$vend) {
    echo 'Dispatching job for Vend ID: ' . \$vend->id . PHP_EOL;
    App\Jobs\Vend\SyncVendTransactionTotalsJson::dispatch(\$vend);
    echo 'Job dispatched! Check queue dashboard for execution time.' . PHP_EOL;
} else {
    echo 'No active vend found for testing.' . PHP_EOL;
}
"
echo ""

echo "========================================="
echo "Verification Complete!"
echo "========================================="
echo ""
echo "Next steps:"
echo "1. Monitor the queue dashboard for job execution times"
echo "2. Verify jobs are completing successfully"
echo "3. Check that runtimes are reduced (target: <10s for SyncVendTransactionTotalsJson)"
echo ""

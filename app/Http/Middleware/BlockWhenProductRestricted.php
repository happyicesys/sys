<?php

namespace App\Http\Middleware;

use App\Support\ProductAccess;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Blocks pages whose numbers CANNOT be honestly product-filtered.
 *
 * Some tables are pre-aggregated ACROSS products, so there is no way to answer
 * "how much of this belongs to product aaa":
 *
 *   vend_records                - grain is date x machine; total_amount /
 *                                 revenue / gross_profit are summed over every
 *                                 product in the machine.
 *   customer_period_summaries,
 *   customer_settlements,
 *   commission_settlements      - whole-SITE money, and the location-fee rates
 *                                 are tiered on TOTAL site sales, so a
 *                                 per-product share is not even well defined.
 *   fact_sales_hourly,
 *   fact_site_daily,
 *   fact_daytype_record         - grain is date x site, no product dimension.
 *
 * Leaving these unfiltered would expose exactly what the restriction exists to
 * prevent: the other party's full machine / site revenue. So a product-
 * restricted viewer is shown an explanation instead of a page of numbers that
 * are not theirs.
 *
 * Unrestricted users (the overwhelming majority - anyone whose
 * product_access_mode is 'all', which is the default for every existing row)
 * are completely unaffected.
 */
class BlockWhenProductRestricted
{
    public function handle(Request $request, Closure $next)
    {
        if (! ProductAccess::isRestricted()) {
            return $next($request);
        }

        $message = 'This page reports money aggregated across all products in a machine or site, '
            . 'so it cannot be limited to the products you have access to. '
            . 'Use the Sales Transactions page, which is product-aware.';

        // NOT for Inertia visits: its axios client sets X-Requested-With on every
        // page navigation, so without the inertia() guard this JSON branch wins
        // every in-app click and the client shows its raw "unexpected response"
        // modal instead of the page below.
        if (! $request->inertia() && ($request->expectsJson() || $request->isXmlHttpRequest())) {
            return response()->json(['message' => $message], 403);
        }

        return Inertia::render('Error/ProductRestricted', [
            'message' => $message,
        ])->toResponse($request)->setStatusCode(403);
    }
}

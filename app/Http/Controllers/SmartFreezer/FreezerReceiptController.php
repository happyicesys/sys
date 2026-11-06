<?php

namespace App\Http\Controllers\SmartFreezer;

use App\Http\Controllers\Controller;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\Vend;
use App\Services\SmartFreezer\FreezerReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The public page behind the freezer's thank-you QR: `/r/{code}/{sessionRef}?s=<signature>`.
 * No login — the signature is the whole authorisation, and a wrong one is a plain 404 so a guessed
 * link learns nothing. The page refreshes itself from `/state` until the invoice is final.
 */
class FreezerReceiptController extends Controller
{
    public function __construct(private readonly FreezerReceipt $receipts) {}

    public function show(Request $request, string $code, string $ref): Response
    {
        $vend = $this->authorised($request, $code, $ref);
        $receipt = $this->receipts->state($vend, $ref);

        // `auto` marks the page's own reload when the invoice changes: the same viewer, not a new open.
        $device = FreezerReceipt::viewerDevice($request->userAgent());
        if ($device !== null && $request->isMethod('GET')) {
            $this->receipts->recordView($vend, $ref, $receipt['status'], $device, isNewOpen: ! $request->boolean('auto'));
        }

        return response()
            ->view('freezer-receipt', [
                'receipt' => $receipt,
                'stateUrl' => url('/r/'.$vend->code.'/'.$ref.'/state').'?s='.urlencode((string) $request->query('s')),
            ])
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function state(Request $request, string $code, string $ref): JsonResponse
    {
        $vend = $this->authorised($request, $code, $ref);
        $state = $this->receipts->state($vend, $ref);

        return response()
            ->json(['status' => $state['status'], 'version' => md5(json_encode($state))])
            ->header('Cache-Control', 'no-store');
    }

    private function authorised(Request $request, string $code, string $ref): Vend
    {
        $vend = Vend::withoutGlobalScope(OperatorVendFilterScope::class)->bareCode($code)->first();
        abort_unless(
            $vend !== null && $vend->isSmartFreezer()
                && $this->receipts->verify($vend, $ref, (string) $request->query('s', '')),
            404,
        );

        return $vend;
    }
}

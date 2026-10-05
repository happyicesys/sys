<?php

namespace App\Http\Controllers\SmartFreezer;

use App\Http\Controllers\Controller;
use App\Models\ZijiaSkuNotification;
use App\Services\SmartFreezer\Zijia\ZijiaAlgorithmClient;
use App\Services\SmartFreezer\Zijia\ZijiaSkuApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/smart-freezer/zijia/sku/notify — Zijia tells us a product was approved, rejected or
 * withdrawn in their portal (requested 2026-10-05), so its barcode reaches the AI's goodsList at
 * once instead of on the 3-minute library crawl (`smart-freezer:zijia-barcode-sync`, kept as the
 * backstop).
 *
 * Authenticated like the algorithm result callback: the envelope's MD5 signature with our
 * appSecret, under the same `callback_verification` mode (`enforce` in prod: an unsigned or
 * mis-signed push changes nothing). Every push is kept whole in `zijia_sku_notifications`, refused
 * ones too, because the payload shape is not final. Answers like the result callback:
 * `{"status":200,"body":"SUCCESS"}`.
 */
class ZijiaSkuNotifyController extends Controller
{
    public function __construct(
        private readonly ZijiaAlgorithmClient $client,
        private readonly ZijiaSkuApprovalService $approvals,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $raw = $request->getContent();
        $envelope = json_decode($raw, true, 512, JSON_BIGINT_AS_STRING);
        if (! is_array($envelope)) {
            $envelope = $request->all();
        }
        $verified = $this->client->isConfigured() && isset($envelope['sign']) && $this->client->signer()->verify($envelope);

        $biz = $envelope['bizContent'] ?? $envelope;
        $biz = is_string($biz) ? json_decode($biz, true) : $biz;
        $biz = is_array($biz) ? $biz : [];

        if (! $verified && config('smart_freezer.zijia.algorithm.callback_verification') === 'enforce') {
            ZijiaSkuNotification::query()->create(['raw_body' => $raw, 'payload' => $envelope, 'verified' => false, 'outcome' => 'refused_unsigned']);
            Log::warning('zijia sku notify refused: signature', ['ip' => $request->ip()]);

            return $this->reply(500, 'sign verification failed');
        }

        $result = $this->approvals->apply($biz);
        ZijiaSkuNotification::query()->create($result + ['raw_body' => $raw, 'payload' => $envelope, 'verified' => $verified]);
        Log::info('zijia sku notify', $result + ['verified' => $verified]);

        return $this->reply(200, 'SUCCESS');
    }

    private function reply(int $status, string $body): JsonResponse
    {
        return response()->json(['status' => $status, 'body' => $body]);
    }
}

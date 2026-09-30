<?php

namespace App\Http\Controllers\SmartFreezer;

use App\Http\Controllers\Controller;
use App\Services\SmartFreezer\FreezerRecognitionService;
use App\Services\SmartFreezer\Zijia\RecognitionResult;
use App\Services\SmartFreezer\Zijia\ZijiaAlgorithmClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * POST /api/smart-freezer/zijia/algorithm/notify — the algorithm's result for a door session
 * (`cabinet.algorithm.order.result`, 智佳算法服务接口文档 §6). Its URL is the `notifyUrl` mark1
 * sends with every recognition.
 *
 * Authenticated by the envelope's MD5 signature, not a token — signed with OUR appSecret, proven by
 * the first live callbacks (2026-09-30, both `callback_verified = 1`). Config `callback_verification`:
 * `enforce` refuses an unsigned or mis-signed callback (prod since 2026-09-30); `log` stores the
 * verdict and processes anyway, but an unverified result can neither overwrite a verified one nor
 * create a row for a trade mark1 never sent (FreezerRecognitionService::complete).
 *
 * Their contract: answer `{"status":200,"body":"SUCCESS"}`, or status 500 with a reason.
 */
class ZijiaAlgorithmNotifyController extends Controller
{
    public function __construct(
        private readonly ZijiaAlgorithmClient $client,
        private readonly FreezerRecognitionService $recognitions,
    ) {}

    public function store(Request $request): JsonResponse
    {
        // Verify what was SENT: decode the raw body keeping big numbers as the strings they arrived
        // as, so a long id is signed-over exactly as they signed it. A form post falls back to the
        // parsed input.
        $envelope = json_decode($request->getContent(), true, 512, JSON_BIGINT_AS_STRING);
        if (! is_array($envelope)) {
            $envelope = $request->all();
        }
        if (isset($envelope['bizContent']) && ! is_string($envelope['bizContent'])) {
            // Signed as a string on their side; as an object it can never verify. Say so, once per call.
            Log::warning('zijia algorithm callback carries bizContent as an object; its signature cannot verify');
        }
        $verified = $this->client->isConfigured() && $this->client->signer()->verify($envelope);

        if (! $verified && config('smart_freezer.zijia.algorithm.callback_verification') === 'enforce') {
            Log::warning('zijia algorithm callback refused: signature', ['ip' => $request->ip()]);

            return $this->reply(500, 'sign verification failed');
        }

        if (($envelope['method'] ?? null) !== ZijiaAlgorithmClient::METHOD_RESULT) {
            Log::warning('zijia algorithm callback with an unexpected method', ['method' => $envelope['method'] ?? null]);

            return $this->reply(500, 'unsupported method');
        }

        $biz = $envelope['bizContent'] ?? null;
        $biz = is_string($biz) ? json_decode($biz, true) : $biz;
        try {
            $result = RecognitionResult::fromBizContent(is_array($biz) ? $biz : []);
        } catch (InvalidArgumentException $e) {
            Log::warning('zijia algorithm callback unreadable', ['error' => $e->getMessage()]);

            return $this->reply(500, $e->getMessage());
        }

        $recognition = $this->recognitions->complete($result, $verified, $envelope);
        Log::info('zijia algorithm result', [
            'recognition' => $recognition?->id, 'trade_id' => $result->tradeId, 'order_status' => $result->orderStatus,
            'verified' => $verified, 'verdict' => $recognition?->verdict,
        ]);

        return $this->reply(200, 'SUCCESS');
    }

    private function reply(int $status, string $body): JsonResponse
    {
        return response()->json(['status' => $status, 'body' => $body]);
    }
}

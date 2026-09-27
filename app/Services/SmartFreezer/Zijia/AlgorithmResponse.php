<?php

namespace App\Services\SmartFreezer\Zijia;

/**
 * What the algorithm service answered to a call we made (§3.3): `code` 0 is success, anything
 * else — including a transport failure, which never reached them — is not. Never throws: the
 * caller records the outcome on the recognition either way.
 */
final class AlgorithmResponse
{
    /** Local code for "the request never got an answer" (timeout, DNS, TLS). */
    public const TRANSPORT_FAILURE = -1;

    public function __construct(
        public readonly int $code,
        public readonly ?string $message,
        public readonly ?string $requestId,
        public readonly ?int $remainIdentifyTime,
        public readonly array $raw,
    ) {}

    public static function fromBody(array $body): self
    {
        // requestId is a 19-digit Long: kept as a string so JSON floats never round it.
        $requestId = $body['requestId'] ?? null;

        return new self(
            code: (int) ($body['code'] ?? 500),
            message: isset($body['msg']) ? (string) $body['msg'] : null,
            requestId: $requestId === null ? null : (string) $requestId,
            remainIdentifyTime: isset($body['remainIdentifyTime']) ? (int) $body['remainIdentifyTime'] : null,
            raw: $body,
        );
    }

    public static function transportFailure(string $message): self
    {
        return new self(self::TRANSPORT_FAILURE, $message, null, null, []);
    }

    public function ok(): bool
    {
        return $this->code === 0;
    }
}

<?php

namespace App\Services\SmartFreezer\Zijia;

/**
 * What one video push from Zijia says, lifted out of a payload whose exact shape is not agreed yet.
 *
 * Deliberately tolerant: every field is looked for under the names a Chinese IoT platform tends to
 * use, anywhere in the body. When their contract is final, tighten THIS class — the controller and
 * everything downstream only ever see the typed fields.
 *
 *  - imei:      the modem IMEI Brian asked them to attach (2026-09-27). It is what the freezer's
 *               own status snapshot reports as `identity.imei`, so it resolves the machine even
 *               when the order number is missing.
 *  - deviceNo:  their device number (2009 reports "JS093016"; 50001 reports its IMEI here too).
 *  - tradeId:   THEIR id for the door session — echoed back in the algorithm's result. Our own
 *               ref stands in only when they send none.
 *  - sessionRef: OUR txnRef "SF-<vendCode>-<epoch>-<seq>", which the APK hands their
 *               orderOpenDoor as `orderNo`; found under any key, or inside any string.
 *
 * Each field is chosen by KEY PRIORITY (the order of its key list), never by where it happens to
 * sit in the payload — a body listing `orderNo` before `tradeId` must still yield their tradeId.
 * `orderNo` itself is deliberately not a trade key: in our spec it carries OUR ref.
 */
final class ZijiaVideoPush
{
    public const SESSION_REF_PATTERN = '/\bSF-(\d+)-\d+-\d+\b/';

    private const IMEI_KEYS = ['imei', 'deviceimei', 'device_imei', 'modemimei'];

    // No bare `sn`: a push may list goods, and a goods serial must never pass for the device.
    private const DEVICE_NO_KEYS = ['deviceno', 'device_no', 'deviceid', 'device_id', 'devicesn', 'device_sn', 'equipmentid', 'equipment_id', 'equipmentno'];

    private const TRADE_KEYS = ['tradeid', 'trade_id', 'outtradeno', 'out_trade_no', 'orderid', 'order_id'];

    private const DURATION_KEYS = ['videoduration', 'video_duration', 'duration'];

    private const DOOR_KEYS = ['doorid', 'door_id', 'door'];

    private const VIDEO_EXTENSIONS = '/\.(mp4|mov|m4v|flv|avi|mkv|ts|m3u8|h264|webm)(\?|$)/i';

    /** @param  list<string>  $videoUrls */
    public function __construct(
        public readonly ?string $imei,
        public readonly ?string $deviceNo,
        public readonly ?string $tradeId,
        public readonly ?string $sessionRef,
        public readonly array $videoUrls,
        public readonly int $videoDuration,
        public readonly int $doorId,
    ) {}

    /** @param  array<string, mixed>  $payload */
    public static function fromPayload(array $payload): self
    {
        $fields = ['imei' => self::IMEI_KEYS, 'deviceNo' => self::DEVICE_NO_KEYS, 'tradeId' => self::TRADE_KEYS,
            'duration' => self::DURATION_KEYS, 'door' => self::DOOR_KEYS];
        $seen = [];     // field => key => first value seen under that key
        $strings = [];
        array_walk_recursive($payload, function ($value, $key) use ($fields, &$seen, &$strings) {
            if (! is_scalar($value) || ($value = trim((string) $value)) === '') {
                return;
            }
            $strings[] = $value;
            $key = is_string($key) ? strtolower($key) : '';
            foreach ($fields as $field => $keys) {
                if (in_array($key, $keys, true)) {
                    $seen[$field][$key] ??= mb_substr($value, 0, 128);
                }
            }
        });
        // Highest-priority key wins, whatever order the payload listed them in.
        $found = [];
        foreach ($fields as $field => $keys) {
            foreach ($keys as $key) {
                if (isset($seen[$field][$key])) {
                    $found[$field] = $seen[$field][$key];
                    break;
                }
            }
        }

        $sessionRef = null;
        foreach ($strings as $s) {
            if (preg_match(self::SESSION_REF_PATTERN, $s, $m)) {
                $sessionRef = $m[0];
                break;
            }
        }

        $urls = array_values(array_unique(array_filter($strings, fn ($s) => preg_match('#^https?://#i', $s) === 1)));
        // A push may also carry a cover image or a callback link; when some URLs are clearly
        // videos, those are the videos. When none carry an extension, send them all.
        $videos = array_values(array_filter($urls, fn ($u) => preg_match(self::VIDEO_EXTENSIONS, $u) === 1));

        return new self(
            imei: $found['imei'] ?? null,
            deviceNo: $found['deviceNo'] ?? null,
            tradeId: $found['tradeId'] ?? $sessionRef,
            sessionRef: $sessionRef,
            videoUrls: $videos !== [] ? $videos : $urls,
            videoDuration: isset($found['duration']) && is_numeric($found['duration']) ? max(0, (int) round((float) $found['duration'])) : 0,
            doorId: isset($found['door']) && ctype_digit($found['door']) ? max(1, (int) $found['door']) : 1,
        );
    }

    /** The vend code inside our own session ref, when there is one. */
    public function sessionVendCode(): ?string
    {
        return $this->sessionRef !== null && preg_match(self::SESSION_REF_PATTERN, $this->sessionRef, $m) ? $m[1] : null;
    }

    /**
     * The identifier to STORE for the push: the IMEI when present. (What the algorithm is sent as
     * `deviceId` is decided in FreezerRecognitionService::submit — their device number first.)
     */
    public function deviceIdentifier(): ?string
    {
        return $this->imei ?? $this->deviceNo;
    }
}

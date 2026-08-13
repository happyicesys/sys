<?php

namespace Tests\Feature;

use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The vend-data ack format, staged restore (ACK_FIX_PLAN.md in apk/mark1-apk).
 *
 * The APK parses the ack as "<fid>,<len>,<base64>". Since 2025-07-05 the
 * endpoint has JSON-encoded it — '"17,4,MQ=="' with literal quotes — which no
 * APK can parse, so every machine treats every POST as unacknowledged and
 * re-uploads its trades (~47% of sales traffic measured as duplicates).
 *
 * The fix is gated per vend code via config('app.raw_ack_vends') so the fleet
 * changes one machine at a time. These tests pin BOTH sides of the gate: the
 * staged machine gets the raw pre-2025 bytes, and everyone else keeps today's
 * behaviour byte-for-byte until the rollout deliberately widens.
 */
class VendDataAckFormatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The endpoint fans out jobs (vend last-updated, vend_data logging,
        // temp trends...) that are irrelevant to the ack format.
        Queue::fake();
    }

    /** A minimal, valid frame: t/g/f/m plus base64({"Type":"P"}) — a poll. */
    private function postFrame(int $vendCode, ?int $fid = 99999)
    {
        $params = [
            't' => 5,
            'g' => 20,
            'm' => $vendCode,
            'p' => base64_encode(json_encode(['Type' => 'P'])),
        ];
        if ($fid !== null) {
            $params['f'] = $fid;
        }

        return $this->post('/api/v1/vend-data', $params);
    }

    public function test_staged_vend_gets_the_raw_ack_the_apk_can_parse(): void
    {
        Vend::forceCreate(['code' => 2031]);
        config(['app.raw_ack_vends' => [2031]]);

        $response = $this->postFrame(2031);

        $response->assertOk();
        // Exact bytes, no quotes: fid, declared length 4, base64("1").
        $this->assertSame('99999,4,MQ==', $response->getContent());
    }

    public function test_unstaged_vend_keeps_the_current_json_ack_byte_for_byte(): void
    {
        Vend::forceCreate(['code' => 5555]);
        config(['app.raw_ack_vends' => [2031]]);

        $response = $this->postFrame(5555);

        $response->assertOk();
        // Today's (broken-for-the-APK) shape, preserved exactly so nothing
        // changes for machines outside the staged list.
        $this->assertSame('"99999,4,MQ=="', $response->getContent());
    }

    public function test_wildcard_enables_the_raw_ack_for_any_vend(): void
    {
        Vend::forceCreate(['code' => 7777]);
        config(['app.raw_ack_vends' => ['*']]);

        $response = $this->postFrame(7777);

        $response->assertOk();
        $this->assertSame('99999,4,MQ==', $response->getContent());
    }

    public function test_frame_without_fid_stays_on_the_json_path_for_staged_vends(): void
    {
        Vend::forceCreate(['code' => 2031]);
        config(['app.raw_ack_vends' => [2031]]);

        $response = $this->postFrame(2031, null);

        $response->assertOk();
        // $response === true when 'f' is absent; the is_string guard keeps it
        // JSON-encoded ('true') for staged and unstaged vends alike.
        $this->assertSame('true', $response->getContent());
    }

    public function test_env_parsing_supports_codes_and_wildcard(): void
    {
        $parse = function (string $env) {
            return array_values(array_filter(array_map(
                function ($code) {
                    $code = trim($code);

                    return $code === '*' ? '*' : (int) $code;
                },
                explode(',', $env)
            )));
        };

        $this->assertSame([2031], $parse('2031'));
        $this->assertSame([2031, 2004], $parse('2031, 2004'));
        $this->assertSame(['*'], $parse('*'));
        $this->assertSame([], $parse(''));
    }
}

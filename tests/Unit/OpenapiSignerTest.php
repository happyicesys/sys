<?php

namespace Tests\Unit;

use App\Services\Citybox\OpenapiSigner;
use PHPUnit\Framework\TestCase;

class OpenapiSignerTest extends TestCase
{
    // ── base string construction (their get_access_token PHP sample) ───────

    public function test_base_string_sorts_keys_and_appends_trailing_ampersand(): void
    {
        $base = OpenapiSigner::baseString([
            'timestamp' => 1754467200,
            'app_id' => '24567672332',
        ], true);

        $this->assertSame('app_id=24567672332&timestamp=1754467200&', $base);
    }

    public function test_base_string_without_trailing_ampersand_variant(): void
    {
        $base = OpenapiSigner::baseString(['c' => 'd', 'a' => 'b', 'e' => 'f'], false);

        // The push doc's own worked example shape: a=b&c=d&e=f
        $this->assertSame('a=b&c=d&e=f', $base);
    }

    public function test_arrays_are_flattened_with_bare_join_not_json(): void
    {
        // Their sample: if(is_array($v)) $v = join('', $v);
        $base = OpenapiSigner::baseString(['ids' => ['1', '2', '3']], true);

        $this->assertSame('ids=123&', $base);
    }

    public function test_sign_excludes_the_sign_param_and_skips_nulls(): void
    {
        $withSign = OpenapiSigner::sign(['a' => 'b', 'sign' => 'junk', 'x' => null], 's3cret');
        $without = OpenapiSigner::sign(['a' => 'b'], 's3cret');

        $this->assertSame($without, $withSign);
        $this->assertSame(md5('a=b&s3cret'), $without);
    }

    // ── webhook verification: both concat variants accepted ────────────────

    public function test_verify_accepts_trailing_ampersand_signature(): void
    {
        $params = ['app_id' => '20221009', 'data' => '{"open_log_id":"7563"}'];
        $params['sign'] = md5(OpenapiSigner::baseString($params, true).'key1');

        $this->assertSame(OpenapiSigner::VARIANT_TRAILING, OpenapiSigner::verify($params, 'key1'));
    }

    public function test_verify_accepts_no_trailing_ampersand_signature(): void
    {
        $params = ['app_id' => '20221009', 'data' => '{"open_log_id":"7563"}'];
        $params['sign'] = md5(OpenapiSigner::baseString($params, false).'key1');

        $this->assertSame(OpenapiSigner::VARIANT_NO_TRAILING, OpenapiSigner::verify($params, 'key1'));
    }

    public function test_verify_rejects_wrong_secret_missing_sign_and_tampered_data(): void
    {
        $params = ['app_id' => '20221009', 'data' => '{"open_log_id":"7563"}'];
        $params['sign'] = md5(OpenapiSigner::baseString($params, true).'key1');

        $this->assertNull(OpenapiSigner::verify($params, 'other-key'));
        $this->assertNull(OpenapiSigner::verify(['app_id' => 'x'], 'key1'));

        $tampered = array_merge($params, ['data' => '{"open_log_id":"9999"}']);
        $this->assertNull(OpenapiSigner::verify($tampered, 'key1'));
    }
}

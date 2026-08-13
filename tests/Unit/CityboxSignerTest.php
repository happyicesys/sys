<?php

namespace Tests\Unit;

use App\Services\Citybox\CityboxSigner;
use PHPUnit\Framework\TestCase;

/**
 * Proves the signature construction against the WORKED EXAMPLE in the supplier
 * doc (citybox/apiThredDetail_2026-08-12.md, 签名示例). No app container needed.
 */
class CityboxSignerTest extends TestCase
{
    private const DOC_SECRET = 'cb_thred_detail_s3cReT_2026'; // sample from their doc — never a real credential

    public function test_base_string_matches_supplier_doc_worked_example(): void
    {
        // Params given deliberately out of order — sorting is the signer's job.
        $params = [
            'timestamp' => 1754467200,
            'order_id' => 12345,
            'nonce' => 'xK9mP2qL7nR4wE8y',
            'status' => 1,
        ];

        $this->assertSame(
            'nonce=xK9mP2qL7nR4wE8y&order_id=12345&status=1&timestamp=1754467200',
            CityboxSigner::baseString($params),
        );
    }

    public function test_sign_matches_supplier_doc_worked_example(): void
    {
        $params = [
            'timestamp' => 1754467200,
            'nonce' => 'xK9mP2qL7nR4wE8y',
            'order_id' => 12345,
            'status' => 1,
        ];

        $expected = md5('nonce=xK9mP2qL7nR4wE8y&order_id=12345&status=1&timestamp=1754467200'.self::DOC_SECRET);

        $this->assertSame($expected, CityboxSigner::sign($params, self::DOC_SECRET));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', CityboxSigner::sign($params, self::DOC_SECRET));
    }

    public function test_sign_param_is_excluded_from_the_base_string(): void
    {
        $withSign = ['b' => 2, 'a' => 1, 'sign' => 'should-be-ignored'];
        $withoutSign = ['a' => 1, 'b' => 2];

        $this->assertSame(
            CityboxSigner::baseString($withoutSign),
            CityboxSigner::baseString($withSign),
        );
    }

    public function test_null_params_are_skipped(): void
    {
        $this->assertSame('a=1', CityboxSigner::baseString(['a' => 1, 'b' => null]));
    }

    public function test_scalar_list_is_sorted_then_json_encoded(): void
    {
        // Doc: 数组参数先对元素排序，再 JSON 编码 — e.g. ids=["1","2","3"]
        $this->assertSame(
            'ids=["1","2","3"]',
            CityboxSigner::baseString(['ids' => ['3', '1', '2']]),
        );
    }

    public function test_object_list_passes_through_json_encoded_without_reordering(): void
    {
        // Element order of object arrays (editEqiupmentProduct's `list`) is
        // ambiguous in the doc, so it is preserved as built by the caller.
        $list = [
            ['equipment_id' => '08DFF2CEB11', 'product_id' => '9315', 'stock' => 10],
        ];

        $this->assertSame(
            'list='.json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CityboxSigner::baseString(['list' => $list]),
        );
    }
}

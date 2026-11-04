<?php

namespace Tests\Unit;

use App\Models\Customer;
use Tests\TestCase;

class CustomerCodeTest extends TestCase
{
    public function test_the_text_null_null_is_never_kept_as_a_site_code(): void
    {
        $customer = new Customer(['code' => 'null (null)']);

        $this->assertNull($customer->code);
    }

    public function test_real_site_codes_are_kept(): void
    {
        $this->assertSame('16839 (UFA)', (new Customer(['code' => '16839 (UFA)']))->code);
        $this->assertNull((new Customer(['code' => null]))->code);
    }
}

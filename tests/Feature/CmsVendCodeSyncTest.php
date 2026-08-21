<?php

namespace Tests\Feature;

use App\Jobs\SyncVendCodeVendPrefixCMS;
use App\Models\Customer;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CmsVendCodeSyncTest extends TestCase
{
    use RefreshDatabase;

    private function makeBoundVend(): Vend
    {
        $customer = Customer::create([
            'name' => 'XO Fitness First - Bugis Junction',
            'code' => 16902,
            'operator_id' => 1,
            'status_id' => Customer::STATUS_ACTIVE,
            'person_id' => 9513,
        ]);

        return Vend::create([
            'code' => 4752,
            'is_active' => 1,
            'customer_id' => $customer->id,
        ]);
    }

    public function test_nightly_sync_queues_nothing_without_cms_url(): void
    {
        config(['app.cms_url' => null]);
        Queue::fake();
        $this->makeBoundVend();

        $this->artisan('sync:all-cms-vend-code-vend-prefix')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_nightly_sync_queues_a_push_per_bound_vend(): void
    {
        config(['app.cms_url' => 'https://cms.test']);
        Queue::fake();
        $this->makeBoundVend();

        $this->artisan('sync:all-cms-vend-code-vend-prefix')->assertSuccessful();

        Queue::assertPushed(SyncVendCodeVendPrefixCMS::class, 1);
    }

    public function test_push_job_calls_cms_vendcode_endpoint(): void
    {
        config(['app.cms_url' => 'https://cms.test']);
        Http::fake();
        $vend = $this->makeBoundVend();

        (new SyncVendCodeVendPrefixCMS($vend))->handle();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://cms.test/api/sys/person/9513/vendcode/4752';
        });
    }

    public function test_push_job_is_a_no_op_without_cms_url(): void
    {
        config(['app.cms_url' => null]);
        Http::fake();
        $vend = $this->makeBoundVend();

        (new SyncVendCodeVendPrefixCMS($vend))->handle();

        Http::assertNothingSent();
    }
}

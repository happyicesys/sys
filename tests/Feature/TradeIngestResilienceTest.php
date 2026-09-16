<?php

namespace Tests\Feature;

use App\Jobs\Vend\CreateVendTransaction;
use App\Models\Operator;
use App\Models\PaymentMethod;
use App\Models\Vend;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Services\Sales\LateTradeTracker;
use App\Services\VendTransactionService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

/**
 * Audit M3-02 / M1-07 (2026-09-16). A TRADE that fails to persist must not
 * vanish: VendTransactionService::create() no longer catches the ingest
 * transaction, CreateVendTransaction retries anything that is not a duplicate
 * key and lets Horizon record it in failed_jobs, while a re-sent TRADE (1062 on
 * uniq_order_id_vend_id — 31 a day on prod) is still swallowed quietly at info.
 * The session tuning around the transaction is scoped to the call.
 */
class TradeIngestResilienceTest extends TestCase
{
    use RefreshDatabase;

    private Vend $vend;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-16 10:00:00');
        Bus::fake();

        $operator = Operator::create(['code' => 'OP1', 'name' => 'Op', 'timezone' => 'Asia/Singapore']);
        $this->vend = Vend::create(['code' => '9101', 'operator_id' => $operator->id, 'is_active' => 1]);
        PaymentMethod::forceCreate(['code' => 0, 'name' => 'Cash']);
        VendChannelError::firstOrCreate(['code' => 0], ['desc' => 'No Malfunction (0)']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function frame(array $overrides = []): array
    {
        return array_merge([
            'ORDRID' => 'R-'.uniqid(), 'PAY_TYPE' => 0, 'TIME' => '2026-09-16 09:59:50', 'SErr' => 0, 'SId' => 11, 'Price' => 200, 'TXN_SRC' => 0,
        ], $overrides);
    }

    /** A QueryException shaped like the driver's, with the MySQL error number in errorInfo[1]. */
    private function queryException(int $mysqlErrno, string $message): QueryException
    {
        $pdo = new \PDOException('SQLSTATE[HY000]: '.$message);
        $pdo->errorInfo = ['HY000', $mysqlErrno, $message];

        return new QueryException('mysql', 'insert into `vend_transactions` (...)', [], $pdo);
    }

    public function test_an_exception_inside_the_ingest_transaction_propagates_and_rolls_the_sale_back(): void
    {
        // Anything the ingest transaction throws (here from the dirty-day note
        // that runs after the insert, inside the same transaction) must reach the
        // caller — the old catch (\Exception) turned it into a one-line log.
        $this->mock(LateTradeTracker::class)
            ->shouldReceive('noteLanded')
            ->andThrow($this->queryException(1048, "Column 'vend_channel_id' cannot be null"));

        try {
            app(VendTransactionService::class)->create($this->vend->fresh(), $this->frame(['ORDRID' => 'R1']));
            $this->fail('create() swallowed the QueryException');
        } catch (QueryException $e) {
            $this->assertSame(1048, $e->errorInfo[1]);
        }

        $this->assertSame(0, VendTransaction::withoutGlobalScopes()->where('vend_id', $this->vend->id)->count(), 'rolled back, not half-written');
    }

    public function test_a_healthy_frame_still_lands(): void
    {
        app(VendTransactionService::class)->create($this->vend->fresh(), $this->frame(['ORDRID' => 'R2']));

        $row = VendTransaction::withoutGlobalScopes()->where('vend_id', $this->vend->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('R2', $row->order_id);
        $this->assertSame(200, (int) $row->amount);
    }

    public function test_the_job_rethrows_a_non_duplicate_query_exception_so_horizon_retries_it(): void
    {
        $job = new CreateVendTransaction($this->frame(['ORDRID' => 'R3']), $this->vend);
        $this->assertSame(3, $job->tries, 'bounded retries, then failed_jobs');
        $this->assertSame([10, 60], $job->backoff);

        $service = Mockery::mock(VendTransactionService::class);
        $service->shouldReceive('create')->once()->andThrow($this->queryException(1205, 'Lock wait timeout exceeded; try restarting transaction'));
        Log::spy();

        try {
            $job->handle($service);
            $this->fail('a lock-wait timeout must fail the job');
        } catch (QueryException $e) {
            $this->assertSame(1205, $e->errorInfo[1]);
        }

        Log::shouldNotHaveReceived('info');
    }

    public function test_a_duplicate_key_is_swallowed_quietly_without_an_error_log(): void
    {
        Log::spy();

        foreach ([1062, 1586] as $errno) { // ER_DUP_ENTRY, ER_DUP_ENTRY_WITH_KEY_NAME
            $service = Mockery::mock(VendTransactionService::class);
            $service->shouldReceive('create')->once()->andThrow($this->queryException($errno, "Duplicate entry 'R4-".$this->vend->id."' for key 'uniq_order_id_vend_id'"));

            (new CreateVendTransaction($this->frame(['ORDRID' => 'R4']), $this->vend))->handle($service); // no throw: the job succeeds, nothing retries
        }

        Log::shouldHaveReceived('info')->twice()->withArgs(fn ($message, $context) => str_contains($message, 'duplicate order ignored') && $context['order_id'] === 'R4');
        Log::shouldNotHaveReceived('error');
        Log::shouldNotHaveReceived('warning');
    }

    public function test_the_http_ingest_acks_the_frame_and_queues_the_job_regardless_of_the_service(): void
    {
        // The endpoint never calls create() itself: it queues CreateVendTransaction
        // (Bus::fake() in setUp holds it) and answers the APK at once. A throwing
        // service is the job's problem (retry, then failed_jobs), never the ack's.
        $this->mock(VendTransactionService::class)
            ->shouldReceive('create')
            ->andThrow($this->queryException(1205, 'Lock wait timeout exceeded; try restarting transaction'));

        $response = $this->post('/api/v1/vend-data', [
            'f' => 77, 't' => 5, 'g' => 20, 'm' => 9101,
            'p' => base64_encode(json_encode(array_merge(['Type' => 'TRADE'], $this->frame(['ORDRID' => 'R5'])))),
        ]);

        $response->assertOk();
        $this->assertSame('"77,4,MQ=="', $response->getContent(), 'the JSON-wrapped ack every non-staged vend gets');
        Bus::assertDispatched(CreateVendTransaction::class, 1);
    }

    /**
     * M1-07: the lock-wait timeout and isolation level are SESSION variables on
     * the worker's persistent connection. They are set for the ingest and
     * restored afterwards, on success and on failure. Needs transaction level
     * 0, so this runs on a probe copy of the connection outside the
     * RefreshDatabase wrapper; nothing is written there.
     */
    public function test_session_tuning_is_scoped_to_the_ingest_and_restored_afterwards(): void
    {
        config(['database.connections.probe' => config('database.connections.mysql')]);
        $default = config('database.default');
        config(['database.default' => 'probe']);

        try {
            $this->assertSame(0, DB::transactionLevel());
            DB::statement('SET SESSION innodb_lock_wait_timeout = 37');
            DB::statement("SET SESSION transaction_isolation = 'REPEATABLE-READ'");

            $probe = fn () => DB::selectOne('SELECT @@session.innodb_lock_wait_timeout AS lock_wait, @@session.transaction_isolation AS isolation');
            $service = app(VendTransactionService::class);
            $run = fn (callable $ingest) => $this->withIngestSession($ingest);

            $seen = $run->call($service, fn () => $probe());
            $this->assertSame(5, (int) $seen->lock_wait, 'tight lock wait inside the ingest');
            $this->assertSame('READ-COMMITTED', $seen->isolation);

            $after = $probe();
            $this->assertSame(37, (int) $after->lock_wait, 'restored — must not leak to the next job on this worker');
            $this->assertSame('REPEATABLE-READ', $after->isolation);

            try {
                $run->call($service, function () {
                    throw new \RuntimeException('ingest failed');
                });
                $this->fail('the ingest exception must propagate');
            } catch (\RuntimeException) {
            }
            $afterFailure = $probe();
            $this->assertSame(37, (int) $afterFailure->lock_wait, 'restored on failure too');
            $this->assertSame('REPEATABLE-READ', $afterFailure->isolation);
        } finally {
            config(['database.default' => $default]);
            DB::purge('probe');
        }
    }
}

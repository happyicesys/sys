<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\UserLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the app-wide user-action audit log (App\Services\UserLogger).
 */
class UserLogTest extends TestCase
{
    use RefreshDatabase;

    private function actingWebUser(): User
    {
        $user = User::factory()->create(['name' => 'Audit Tester']);
        Auth::guard('web')->setUser($user); // simulate an authenticated web session

        return $user;
    }

    public function test_create_update_delete_by_web_user_is_logged(): void
    {
        $user = $this->actingWebUser();

        // CREATE
        $product = Product::create(['code' => 'AUD1', 'name' => 'Audit Cola']);

        $created = DB::table('user_logs')->where('event', 'created')
            ->where('auditable_type', Product::class)->where('auditable_id', $product->id)->first();
        $this->assertNotNull($created, 'create should be logged');
        $this->assertSame($user->id, (int) $created->user_id);
        $this->assertSame('Audit Tester', $created->user_name);

        // UPDATE — diff should capture old -> new for name only
        $product->update(['name' => 'Audit Pepsi']);
        $updated = DB::table('user_logs')->where('event', 'updated')
            ->where('auditable_id', $product->id)->latest('id')->first();
        $this->assertNotNull($updated, 'update should be logged');
        $changes = json_decode($updated->changes, true);
        $this->assertArrayHasKey('name', $changes);
        $this->assertSame(['Audit Cola', 'Audit Pepsi'], $changes['name']);

        // DELETE
        $id = $product->id;
        $product->delete();
        $this->assertDatabaseHas('user_logs', [
            'event' => 'deleted', 'auditable_type' => Product::class, 'auditable_id' => $id,
        ]);
    }

    public function test_no_op_update_writes_no_row(): void
    {
        $this->actingWebUser();
        $product = Product::create(['code' => 'AUD2', 'name' => 'Same']);
        DB::table('user_logs')->delete();

        $product->update(['name' => 'Same']); // nothing actually changes

        $this->assertSame(0, DB::table('user_logs')->where('event', 'updated')->count());
    }

    public function test_writes_without_a_web_user_are_not_logged(): void
    {
        // No Auth::guard('web') user set — mirrors cron / queue / machine ingestion.
        Auth::guard('web')->logout();

        Product::create(['code' => 'AUD3', 'name' => 'Machine Made']);

        $this->assertSame(0, DB::table('user_logs')->count(),
            'actor-less writes (cron/queue/machine) must not be logged');
    }

    public function test_denied_models_are_not_logged(): void
    {
        config(['userlog.deny' => ['Product']]);
        $this->actingWebUser();

        Product::create(['code' => 'AUD4', 'name' => 'Denied']);

        $this->assertSame(0, DB::table('user_logs')->count(),
            'models on the deny-list must be skipped');
    }

    public function test_history_endpoint_returns_keyset_pages(): void
    {
        $user = User::factory()->create();
        $this->actingWebUser();

        $product = Product::create(['code' => 'AUD5', 'name' => 'Paged']);
        for ($i = 0; $i < 13; $i++) {
            $product->update(['name' => 'Paged '.$i]);
        }

        // Page 1: newest 10 + a cursor.
        $res = $this->actingAs($user)->getJson('/user-logs?type=Product&id='.$product->id);
        $res->assertOk()->assertJsonCount(10, 'data');
        $before = $res->json('next_before');
        $this->assertNotNull($before);

        // Page 2: older rows via the cursor.
        $res2 = $this->actingAs($user)->getJson('/user-logs?type=Product&id='.$product->id.'&before='.$before);
        $res2->assertOk();
        $this->assertLessThanOrEqual(10, count($res2->json('data')));
    }

    public function test_record_changes_logs_a_pivot_edit_eloquent_events_cannot_see(): void
    {
        $user = $this->actingWebUser();
        $product = Product::create(['code' => 'AUD6', 'name' => 'Pivot']);
        DB::table('user_logs')->delete();

        // Shape a belongsToMany sync() would leave behind, which fires no model
        // event on the parent (the Machine Stickers picker on Setting/Edit).
        UserLogger::recordChanges($product, ['sticker_ids' => [[1], [1, 2]]]);

        $row = DB::table('user_logs')->where('auditable_id', $product->id)->latest('id')->first();
        $this->assertNotNull($row, 'a manual change must be logged');
        $this->assertSame('updated', $row->event);
        $this->assertSame($user->id, (int) $row->user_id);
        $this->assertSame([[1], [1, 2]], json_decode($row->changes, true)['sticker_ids']);
    }

    public function test_record_changes_is_skipped_without_a_web_user(): void
    {
        Auth::guard('web')->logout();
        $product = Product::create(['code' => 'AUD7', 'name' => 'No actor']);
        DB::table('user_logs')->delete();

        UserLogger::recordChanges($product, ['sticker_ids' => [[], [1]]]);

        $this->assertSame(0, DB::table('user_logs')->count(),
            'actor-less manual changes must not be logged');
    }
}

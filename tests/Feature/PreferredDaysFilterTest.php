<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * AUDIT_2026-09-15 M2-02: the "Preferred Day(s)" filter interpolated each
 * request value straight into a whereRaw (four copies). Now one helper,
 * Customer::wherePreferredDays(), allow-lists against Customer::DAYS_MAPPING
 * and binds the JSON path.
 */
class PreferredDaysFilterTest extends TestCase
{
    use RefreshDatabase;

    private const INJECTION = "mon') OR 1=1 --";

    private function site(string $name, array $days): Customer
    {
        return Customer::create([
            'name' => $name,
            'code' => crc32($name) % 100000,
            'operator_id' => 1,
            'status_id' => Customer::STATUS_ACTIVE,
            'preferred_visit_days_json' => $days,
        ]);
    }

    public function test_the_injection_string_never_reaches_the_sql(): void
    {
        $query = Customer::wherePreferredDays(Customer::withoutGlobalScopes(), [self::INJECTION, '2']);

        $sql = $query->toSql();

        $this->assertStringContainsString('JSON_EXTRACT(customers.preferred_visit_days_json, ?)', $sql);
        $this->assertStringNotContainsString('OR 1=1', $sql);
        $this->assertStringNotContainsString('mon', $sql);
        $this->assertSame(['$."2"'], $query->getBindings());
    }

    public function test_a_list_with_no_valid_day_applies_no_filter_and_no_raw_sql(): void
    {
        foreach ([[self::INJECTION], ['all'], ['9'], ['0'], [''], [['nested']], 'string'] as $days) {
            $query = Customer::wherePreferredDays(Customer::withoutGlobalScopes(), $days);

            $this->assertStringNotContainsString('preferred_visit_days_json', $query->toSql());
            $this->assertSame([], $query->getBindings());
        }
    }

    public function test_the_filter_index_scope_binds_the_request_value(): void
    {
        $request = Request::create('/customers', 'GET', ['preferredDays' => [self::INJECTION, '3']]);

        $query = Customer::withoutGlobalScopes()->filterIndex($request);

        $this->assertStringNotContainsString('OR 1=1', $query->toSql());
        $this->assertContains('$."3"', $query->getBindings());
    }

    public function test_a_raw_query_builder_joined_to_customers_gets_the_same_predicate(): void
    {
        $query = DB::table('vends')->join('customers', 'customers.id', '=', 'vends.customer_id');

        Customer::wherePreferredDays($query, ['1', self::INJECTION]);

        $this->assertStringContainsString("JSON_UNQUOTE(JSON_EXTRACT(customers.preferred_visit_days_json, ?)) = 'true'", $query->toSql());
        $this->assertStringNotContainsString('OR 1=1', $query->toSql());
        $this->assertSame(['$."1"'], $query->getBindings());
    }

    public function test_a_legitimate_day_matches_only_sites_with_that_day_true(): void
    {
        $monday = $this->site('Monday site', ['1' => true, '2' => false]);
        $tuesday = $this->site('Tuesday site', ['1' => false, '2' => true]);
        $this->site('No days site', ['1' => false, '2' => false]);

        $ids = fn (array $days) => Customer::wherePreferredDays(Customer::withoutGlobalScopes(), $days)
            ->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertSame([$monday->id], $ids(['1']));
        $this->assertSame([$tuesday->id], $ids([2]));
        $this->assertSame([$monday->id, $tuesday->id], $ids(['1', '2']));
        // The injection string alongside a real day is simply dropped.
        $this->assertSame([$monday->id], $ids(['1', self::INJECTION]));
    }

    public function test_no_inline_copy_of_the_json_path_predicate_survives_outside_the_helper(): void
    {
        $offenders = [];

        foreach (Finder::create()->files()->in(base_path('app'))->name('*.php') as $file) {
            if ($file->getRelativePathname() === 'Models/Customer.php') {
                continue;
            }
            if (str_contains($file->getContents(), 'preferred_visit_days_json, \'$.')) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offenders, 'Use Customer::wherePreferredDays() instead of an inline JSON path.');
    }
}

<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Support\SiteSearch;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Coverage for the shared "Site" filter predicate. Every Site box in mark1
 * funnels through SiteSearch, so this pins down what a term matches — in
 * particular that the displayed Site ID (customers.id + RUNNING_NUMBER_INIT)
 * is filterable, without a small number like "35" being mistaken for one.
 *
 * SQL only — no DB round-trip.
 */
class SiteSearchTest extends TestCase
{
    private function sql(string $term, ?string $alias = null, array $extra = []): string
    {
        $search = SiteSearch::for($term);

        if ($alias !== null) {
            $search = $search->on($alias);
        }

        if ($extra !== []) {
            $search = $search->alsoMatching(...$extra);
        }

        return $search->applyTo(DB::table('customers'))->toSql();
    }

    private function bindings(string $term): array
    {
        return SiteSearch::for($term)->applyTo(DB::table('customers'))->getBindings();
    }

    public function test_a_blank_term_is_a_no_op(): void
    {
        foreach (['', '   ', null] as $term) {
            $query = SiteSearch::for($term)->applyTo(DB::table('customers'));

            $this->assertSame([], $query->getBindings());
            $this->assertStringNotContainsString('where', $query->toSql());
        }
    }

    public function test_it_matches_name_prefix_and_cms_code(): void
    {
        $sql = $this->sql('Waterc');

        $this->assertStringContainsString('`customers`.`virtual_customer_prefix` LIKE ?', $sql);
        $this->assertStringContainsString('`customers`.`code` LIKE ?', $sql);
        $this->assertStringContainsString('`customers`.`name` LIKE ?', $sql);
        $this->assertContains('%Waterc%', $this->bindings('Waterc'));
    }

    public function test_a_non_numeric_term_never_touches_the_integer_virtual_code_column(): void
    {
        // virtual_customer_code is an INT column: comparing it to a non-numeric
        // term forces MySQL to cast every row and can never match anyway.
        $this->assertStringNotContainsString('virtual_customer_code', $this->sql('Waterc'));
        $this->assertStringContainsString('virtual_customer_code', $this->sql('10025'));
    }

    public function test_it_matches_the_displayed_site_id(): void
    {
        $refId = Customer::RUNNING_NUMBER_INIT + 4310;

        $this->assertStringContainsString('`customers`.`id` = ?', $this->sql((string) $refId));
        $this->assertContains(4310, $this->bindings((string) $refId));
    }

    public function test_a_site_id_can_be_combined_with_a_name_fragment(): void
    {
        $term = (Customer::RUNNING_NUMBER_INIT + 4310).' Waterc';

        $this->assertStringContainsString('`customers`.`id` = ?', $this->sql($term));
        $this->assertContains(4310, $this->bindings($term));
        $this->assertContains('%Waterc%', $this->bindings($term));
    }

    public function test_a_number_below_the_site_id_range_stays_a_name_fragment(): void
    {
        // "Blk 35" must still be findable by typing 35.
        $this->assertStringNotContainsString('`customers`.`id` = ?', $this->sql('35'));
        $this->assertContains('%35%', $this->bindings('35'));

        // RUNNING_NUMBER_INIT itself maps to customers.id 0, which never exists.
        $this->assertStringNotContainsString(
            '`customers`.`id` = ?',
            $this->sql((string) Customer::RUNNING_NUMBER_INIT)
        );
    }

    public function test_it_understands_the_prefix_code_shorthand(): void
    {
        $bindings = $this->bindings('LK-10025');

        $this->assertContains('LK', $bindings);
        $this->assertContains('10025%', $bindings);
    }

    public function test_it_can_be_qualified_against_an_alias(): void
    {
        $sql = $this->sql((string) (Customer::RUNNING_NUMBER_INIT + 4310), 'c');

        $this->assertStringContainsString('`c`.`name` LIKE ?', $sql);
        $this->assertStringContainsString('`c`.`id` = ?', $sql);
        $this->assertStringNotContainsString('`customers`.`name`', $sql);
    }

    public function test_it_can_match_extra_joined_columns(): void
    {
        $sql = $this->sql('LK', null, ['vend_prefixes.name']);

        $this->assertStringContainsString('`vend_prefixes`.`name` LIKE ?', $sql);
    }

    public function test_customer_id_translates_a_displayed_site_id(): void
    {
        $this->assertSame(4310, SiteSearch::customerId((string) (Customer::RUNNING_NUMBER_INIT + 4310)));
        $this->assertNull(SiteSearch::customerId((string) Customer::RUNNING_NUMBER_INIT));
        $this->assertNull(SiteSearch::customerId('35'));
        $this->assertNull(SiteSearch::customerId('abc'));
        $this->assertNull(SiteSearch::customerId(null));
    }
}

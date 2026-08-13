<?php

namespace Tests\Unit;

use App\Models\ProductMapping;
use App\Models\Vend;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Verifies the machine-type ↔ mapping compatibility gate every vend-binding
 * path runs through (Setting create/edit, promote/replace changeovers, and —
 * as a non-throwing skip — the OpsJob mapping advance). No database required.
 *
 * Run: php artisan test --filter=VendMappingMachineTypeGuardTest
 */
class VendMappingMachineTypeGuardTest extends TestCase
{
    private function mapping(?string $machineType, string $name = 'Menu A'): ProductMapping
    {
        $mapping = new ProductMapping;
        $mapping->name = $name;
        $mapping->machine_type = $machineType;

        return $mapping;
    }

    public function test_null_mapping_is_always_compatible(): void
    {
        // Unbinding (product_mapping_id = null) must never be blocked.
        $this->assertTrue(Vend::mappingMatchesMachineType(null, Vend::MACHINE_TYPE_SMART_FREEZER));
    }

    public function test_na_placeholder_is_machine_agnostic(): void
    {
        $na = $this->mapping(Vend::MACHINE_TYPE_VENDING_MACHINE, 'N/A');

        $this->assertTrue(Vend::mappingMatchesMachineType($na, Vend::MACHINE_TYPE_SMART_FREEZER));
        $this->assertTrue(Vend::mappingMatchesMachineType($na, Vend::MACHINE_TYPE_SMART_CHILLER));
    }

    public function test_legacy_nulls_default_to_vending_machine_on_both_sides(): void
    {
        // Pre-migration rows carry NULL machine_type; both sides mean "vending machine".
        $this->assertTrue(Vend::mappingMatchesMachineType($this->mapping(null), null));
        $this->assertTrue(Vend::mappingMatchesMachineType($this->mapping(null), Vend::MACHINE_TYPE_VENDING_MACHINE));
        $this->assertTrue(Vend::mappingMatchesMachineType($this->mapping(Vend::MACHINE_TYPE_VENDING_MACHINE), null));
    }

    public function test_matching_kinds_are_compatible(): void
    {
        foreach (array_keys(Vend::MACHINE_TYPE_MAPPINGS) as $type) {
            $this->assertTrue(Vend::mappingMatchesMachineType($this->mapping($type), $type));
        }
    }

    public function test_cross_kind_binds_are_rejected(): void
    {
        // The BKX10 case: vending planogram on a smart freezer → every SKU "Not Available".
        $this->assertFalse(Vend::mappingMatchesMachineType(
            $this->mapping(Vend::MACHINE_TYPE_VENDING_MACHINE),
            Vend::MACHINE_TYPE_SMART_FREEZER
        ));
        $this->assertFalse(Vend::mappingMatchesMachineType(
            $this->mapping(Vend::MACHINE_TYPE_SMART_FREEZER),
            Vend::MACHINE_TYPE_VENDING_MACHINE
        ));
        $this->assertFalse(Vend::mappingMatchesMachineType(
            $this->mapping(Vend::MACHINE_TYPE_SMART_CHILLER),
            Vend::MACHINE_TYPE_SMART_FREEZER
        ));
    }

    public function test_assert_throws_validation_error_keyed_on_field(): void
    {
        try {
            Vend::assertMappingMatchesMachineType(
                $this->mapping(Vend::MACHINE_TYPE_VENDING_MACHINE, 'ICE CREAM 33'),
                Vend::MACHINE_TYPE_SMART_FREEZER,
                'product_mapping_id',
                'Product Mapping'
            );
            $this->fail('Expected ValidationException for a cross-kind bind.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('product_mapping_id', $e->errors());
            $message = $e->errors()['product_mapping_id'][0];
            $this->assertStringContainsString('ICE CREAM 33', $message);
            $this->assertStringContainsString('Vending Machine', $message);
            $this->assertStringContainsString('Smart Freezer', $message);
        }
    }

    public function test_assert_passes_silently_on_match(): void
    {
        Vend::assertMappingMatchesMachineType(
            $this->mapping(Vend::MACHINE_TYPE_SMART_FREEZER),
            Vend::MACHINE_TYPE_SMART_FREEZER,
            'product_mapping_id',
            'Product Mapping'
        );

        $this->assertTrue(true);
    }
}

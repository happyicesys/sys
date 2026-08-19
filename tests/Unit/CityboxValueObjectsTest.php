<?php

namespace Tests\Unit;

use App\Enums\Citybox\DeviceOpsStatus;
use App\Enums\Citybox\DeviceState;
use App\Enums\Citybox\DeviceType;
use App\Services\Citybox\DTO\ChillerCatalogItem;
use App\Services\Citybox\DTO\ChillerDevice;
use App\Services\Citybox\DTO\ChillerStockLine;
use App\Services\Citybox\DTO\StockCount;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The VOs are the ONE place their type drift is absorbed. Fixtures are the
 * literal live rows (2026-08-17/19), including the variants we actually saw.
 */
class CityboxValueObjectsTest extends TestCase
{
    public function test_device_parses_live_row_and_tolerates_status_as_string_or_int(): void
    {
        $asString = ChillerDevice::fromApi([
            'equipment_id' => 'ICB26F9TCKW2', 'name' => 'Singapore8', 'status' => '1', 'type' => 'visual-8',
            'heartbeat_last_recovery' => '2026-08-15 10:33:56', 'heartbeat_last_offline' => '2026-08-15 13:45:28',
            'equipment_online_status' => 2, 'equipment_online_status_str' => '离线', 'equipment_status_str' => '启运',
        ]);
        $asInt = ChillerDevice::fromApi(['equipment_id' => 'X', 'name' => '#1', 'status' => 1, 'type' => 'visual-2', 'equipment_online_status' => 1]);

        $this->assertSame(DeviceOpsStatus::Running, $asString->opsStatus);
        $this->assertSame(DeviceOpsStatus::Running, $asInt->opsStatus);
        $this->assertFalse($asString->online);
        $this->assertTrue($asInt->online);
        $this->assertSame(DeviceType::Visual8, $asString->type);
        $this->assertSame('2026-08-15 10:33:56', $asString->heartbeatRecovery?->toDateTimeString());
        $this->assertNull($asInt->heartbeatRecovery);
    }

    public function test_device_unknown_type_and_status_never_throw(): void
    {
        $d = ChillerDevice::fromApi(['equipment_id' => 'X', 'name' => 'n', 'type' => 'visual-99', 'status' => 'weird']);
        $this->assertSame(DeviceType::Unknown, $d->type);
        $this->assertNull($d->opsStatus);
        $this->assertSame(5, $d->type->layerCount());
    }

    public function test_stock_line_parses_live_row_with_numeric_string_ids_and_cent_prices(): void
    {
        $l = ChillerStockLine::fromApi([
            'thumbnailPic' => 'https://cdn.icitybox.cn/images/2026-08-09/1786258331_989.png',
            'quantity' => '1', 'name' => 'Kang Shi Fu, Oolong Tea Drink Peach Flavor 康师傅,蜜桃乌龙茶',
            'product_id' => '90340', 'price' => '0.10', 'active_price' => '0.10',
            'volume' => '500ml', 'unit' => 'Bottle', 'class_id' => '8', 'class_name' => '饮料', 'layer' => '1',
        ]);

        $this->assertSame(90340, $l->cityboxProductId);   // int, not "90340"
        $this->assertSame(1, $l->quantity);
        $this->assertSame(1, $l->layer);
        $this->assertSame(10, $l->priceCents);
        $this->assertSame(10, $l->activePriceCents);
        $this->assertSame(10, $l->effectivePriceCents());
        $this->assertSame(8, $l->classId);
    }

    public function test_par_line_has_no_active_price_and_effective_falls_back_to_list(): void
    {
        $l = ChillerStockLine::fromApi(['product_id' => '89925', 'name' => 'Cocacola', 'quantity' => '4', 'price' => '0.10', 'layer' => 1]);
        $this->assertNull($l->activePriceCents);
        $this->assertSame(10, $l->effectivePriceCents());
        $this->assertSame(4, $l->quantity); // par
    }

    public function test_catalog_item_treats_zero_sku_code_as_absent(): void
    {
        $c = ChillerCatalogItem::fromApi([
            'id' => '89925', 'product_id' => '0', 'product_name' => 'Cocacola',
            'img_url' => 'https://cdn/a.png', 'vision_img' => 'https://cdn/v1.png', 'vision_img2' => '', 'vision_img3' => null,
        ]);
        $this->assertSame(89925, $c->cityboxProductId);
        $this->assertNull($c->skuCode);
        $this->assertSame(['https://cdn/v1.png'], $c->visionImgs);
    }

    public function test_device_state_maps_observed_codes_and_unknown_to_other(): void
    {
        $this->assertSame(DeviceState::Free, DeviceState::fromApi('FREE'));
        $this->assertSame(DeviceState::Opening, DeviceState::fromApi('OPENING'));
        $this->assertSame(DeviceState::NotFound, DeviceState::fromApi('NOT_FOUND'));
        $this->assertSame(DeviceState::Other, DeviceState::fromApi('SOMETHING_NEW'));
        $this->assertTrue(DeviceState::Free->canOpenDoor());
        $this->assertFalse(DeviceState::Opening->canOpenDoor());
    }

    public function test_stock_count_validates_and_shapes_wire_rows(): void
    {
        $c = StockCount::of(['90338' => 0, 90340 => '5']);
        $this->assertSame([['product_id' => 90338, 'reality_stock' => 0], ['product_id' => 90340, 'reality_stock' => 5]], $c->toApiRows());

        $this->expectException(InvalidArgumentException::class);
        StockCount::of([90340 => -1]);
    }

    public function test_stock_count_rejects_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        StockCount::of([]);
    }
}

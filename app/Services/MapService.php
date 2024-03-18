<?php

namespace App\Services;
use App\Models\Map;
use App\Models\Maps\Google;
use App\Models\Maps\Onemap;
use Carbon\Carbon;

class MapService
{
    public function getMapService(string $name): Map
    {
        $map = Map::where('name', $name)->first();

        if ($map) {
            return $map;
        }

        switch ($name) {
            case 'onemap':
                return new Onemap();
            case 'google':
                return new Google();
            default:
                throw new \Exception('Map service not found');
        }
    }

    public function getMapServiceEndpoint(string $name): string
    {
        return $this->getMapService($name)->getEndpoint();
    }

    public function getMapServiceAddressParams(string $name): array
    {
        return $this->getMapService($name)->getAddressParams();
    }
}
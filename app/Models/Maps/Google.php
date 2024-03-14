<?php

namespace App\Models\Maps;

use App\Interfaces\MapInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Google extends Map
{
    use HasFactory;

    public function getEndpoint(): string
    {
        return 'https://maps.googleapis.com/maps/api/geocode/json';
    }

    public function getAddressParams(): array
    {
        return [
            'address' => 'address',
            'key' => 'key',
        ];
    }
}

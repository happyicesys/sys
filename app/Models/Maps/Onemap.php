<?php

namespace App\Models\Maps;

use App\Interfaces\MapInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Onemap extends Map implements MapInterface
{
    use HasFactory;

    public function getEndpoint(): string
    {
        return 'https://www.onemap.gov.sg/api/common/elastic/search';
    }

    public function getAddressParams(): array
    {
        return [
            'searchVal' => 'searchVal',
            'returnGeom' => 'Y',
            'getAddrDetails' => 'Y',
            'pageNum' => 1,
        ];
    }
}

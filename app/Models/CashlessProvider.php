<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashlessProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'remarks',
    ];

    // relationships
    public function cashlessTerminals()
    {
        return $this->hasMany(CashlessTerminal::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExternalOauthToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'access_token',
        'client_id',
        'client_secret',
        'granted_type',
        'expired_at',
        'scopes',
        'token_type',
    ];

    protected $casts = [
        'expired_at' => 'datetime'
    ];

    public function modelable()
    {
        return $this->morphTo();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryPlatform extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'country_id',
        'default_access_method',
        'default_granted_type',
        'default_scopes',
        'payment_method_id',
        'remarks',
        'slug',
        'field1_name',
        'field2_name',
        'field3_name',
        'field4_name',
    ];

    // relationships
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function deliveryPlatformOperators()
    {
        return $this->hasMany(DeliveryPlatformOperator::class);
    }

    public function deliveryPlatformOrders()
    {
        return $this->hasMany(DeliveryPlatformOrder::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    // shared method for delivery platforms
    // response params
    public function getResponse($response, $method)
    {
        $message = '';

        switch($response->status()) {
            case 200:
                $message = 'OK';
                break;
            case 204:
                $message = 'No Content';
                break;
            case 400:
                $message = 'Bad Request';
                break;
            case 401:
                $message = 'Unauthorized';
                break;
            case 403:
                $message = 'Forbidden';
                break;
            case 404:
                $message = 'Not Found';
                break;
            case 409:
                $message = 'Conflict';
                break;
            case 500:
                $message = 'Internal Server Error';
                break;
            case 503:
                $message = 'Service Unavailable';
                break;
        }

        $finalResponse =  [
            'success' => $response->successful(),
            'code' => $response->status(),
            'message' => $method.' '.$message,
            'data' => $response->json(),
        ];

        return $finalResponse;
    }
}

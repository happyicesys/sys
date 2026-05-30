<?php

namespace App\Services;

use App\Http\Resources\BankResource;
use App\Http\Resources\CategoryGroupResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CountryResource;
use App\Http\Resources\DeliveryPlatformResource;
use App\Http\Resources\LocationTypeResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\PaymentMethodResource;
use App\Http\Resources\PriceTemplateResource;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\TagResource;
use App\Http\Resources\UomResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\VendChannelErrorResource;
use App\Http\Resources\VendContractResource;
use App\Http\Resources\VendModelResource;
use App\Http\Resources\VendPrefixResource;
use App\Http\Resources\ZoneResource;
use App\Models\Bank;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Country;
use App\Models\DeliveryPlatform;
use App\Models\LocationType;
use App\Models\Operator;
use App\Models\PaymentMethod;
use App\Models\PriceTemplate;
use App\Models\Profile;
use App\Models\Tag;
use App\Models\Uom;
use App\Models\User;
use App\Models\VendChannelError;
use App\Models\VendContract;
use App\Models\VendModel;
use App\Models\VendPrefix;
use App\Models\Zone;

/**
 * Centralized service for loading dropdown options
 *
 * This service provides a single source of truth for all dropdown/select options
 * used across controllers. It implements request-level caching to avoid duplicate
 * queries within the same request.
 */
class OptionsService
{
    /**
     * Request-level cache for loaded options
     */
    protected array $loaded = [];

    /**
     * Get operator options
     */
    public function operators()
    {
        return $this->loadOnce('operators', function () {
            return OperatorResource::collection(
                Operator::orderBy('name')->get()
            );
        });
    }

    /**
     * Get bank options (active only) for the Customer "Bank Details" dropdown.
     */
    public function banks()
    {
        return $this->loadOnce('banks', function () {
            return BankResource::collection(
                Bank::where('is_active', true)->orderBy('name')->get()
            );
        });
    }

    /**
     * Get location type options
     */
    public function locationTypes()
    {
        return $this->loadOnce('locationTypes', function () {
            return LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
            );
        });
    }

    /**
     * Get categories for a specific class
     */
    public function categories(string $className)
    {
        $key = "categories_{$className}";
        return $this->loadOnce($key, function () use ($className) {
            return CategoryResource::collection(
                Category::where('classname', $className)
                    ->orderBy('sequence')
                    ->get()
            );
        });
    }

    /**
     * Get category groups for a specific class
     */
    public function categoryGroups(string $className)
    {
        $key = "categoryGroups_{$className}";
        return $this->loadOnce($key, function () use ($className) {
            return CategoryGroupResource::collection(
                CategoryGroup::whereHas('categories', function ($query) use ($className) {
                    $query->where('classname', $className);
                })->orderBy('name')->get()
            );
        });
    }

    /**
     * Get tags for a specific class
     */
    public function tags(string $className)
    {
        $key = "tags_{$className}";
        return $this->loadOnce($key, function () use ($className) {
            return TagResource::collection(
                Tag::where('classname', $className)
                    ->orderBy('name')
                    ->get()
            );
        });
    }

    /**
     * Get vend model options
     */
    public function vendModels()
    {
        return $this->loadOnce('vendModels', function () {
            return VendModelResource::collection(
                VendModel::orderBy('name')->get()
            );
        });
    }

    /**
     * Get vend prefix options
     */
    public function vendPrefixes()
    {
        return $this->loadOnce('vendPrefixes', function () {
            return VendPrefixResource::collection(
                VendPrefix::orderBy('name')->get()
            );
        });
    }

    /**
     * Get zone options
     */
    public function zones()
    {
        return $this->loadOnce('zones', function () {
            return ZoneResource::collection(
                Zone::orderBy('sequence')
                    ->orderBy('name')
                    ->get()
            );
        });
    }

    /**
     * Get user options (drivers, supervisors, etc.)
     *
     * @param array $roles Optional array of role names to filter by
     */
    public function users(array $roles = [])
    {
        $key = 'users_' . implode('_', $roles);
        return $this->loadOnce($key, function () use ($roles) {
            $query = User::query();

            if (!empty($roles)) {
                $query->whereHas('roles', function ($q) use ($roles) {
                    $q->whereIn('name', $roles);
                });
            }

            return UserResource::collection(
                $query->orderBy('name')->get()
            );
        });
    }

    /**
     * Get price template options
     */
    public function priceTemplates()
    {
        return $this->loadOnce('priceTemplates', function () {
            return PriceTemplateResource::collection(
                PriceTemplate::orderBy('name')->get()
            );
        });
    }

    /**
     * Get profile options
     */
    public function profiles()
    {
        return $this->loadOnce('profiles', function () {
            return ProfileResource::collection(
                Profile::orderBy('name')->get()
            );
        });
    }

    /**
     * Get vend channel error options
     */
    public function vendChannelErrors()
    {
        return $this->loadOnce('vendChannelErrors', function () {
            return VendChannelErrorResource::collection(
                VendChannelError::orderBy('code')->get()
            );
        });
    }

    /**
     * Get vend contract options
     */
    public function vendContracts()
    {
        return $this->loadOnce('vendContracts', function () {
            return VendContractResource::collection(
                VendContract::orderBy('name')->get()
            );
        });
    }

    /**
     * Get payment method options
     */
    public function paymentMethods()
    {
        return $this->loadOnce('paymentMethods', function () {
            return PaymentMethodResource::collection(
                PaymentMethod::orderBy('name')->get()
            );
        });
    }

    /**
     * Get delivery platform options
     */
    public function deliveryPlatforms()
    {
        return $this->loadOnce('deliveryPlatforms', function () {
            return DeliveryPlatformResource::collection(
                DeliveryPlatform::orderBy('name')->get()
            );
        });
    }

    /**
     * Get country options
     */
    public function countries()
    {
        return $this->loadOnce('countries', function () {
            return CountryResource::collection(
                Country::orderBy('sequence')
                    ->orderBy('name')
                    ->get()
            );
        });
    }

    /**
     * Get UOM (Unit of Measure) options
     */
    public function uoms()
    {
        return $this->loadOnce('uoms', function () {
            return UomResource::collection(
                Uom::orderBy('sequence')->get()
            );
        });
    }

    /**
     * Load once pattern - caches result for current request
     *
     * This prevents duplicate queries within the same request while
     * avoiding cross-request caching issues.
     */
    protected function loadOnce(string $key, callable $loader)
    {
        if (!isset($this->loaded[$key])) {
            $this->loaded[$key] = $loader();
        }

        return $this->loaded[$key];
    }

    /**
     * Get multiple options at once
     *
     * @param array $optionNames Array of method names to call
     * @return array Associative array of option name => data
     */
    public function getMultiple(array $optionNames): array
    {
        $result = [];

        foreach ($optionNames as $name) {
            if (method_exists($this, $name)) {
                $result[$name] = $this->$name();
            }
        }

        return $result;
    }
}

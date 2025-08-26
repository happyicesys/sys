<?php

namespace App\Models\DeliveryPlatforms;

use App\Models\DeliveryPlatform;
use App\Models\DeliveryPlatformOperator;
use App\Models\DeliveryProductMappingVend;
use App\Interfaces\DeliveryPlatformInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

class Grab extends DeliveryPlatform implements DeliveryPlatformInterface
{
    use HasFactory;

    const CAMPAIGN_TYPE_DOLLAR = 'net';
    const CAMPAIGN_TYPE_PERCENT = 'percentage';
    const CAMPAIGN_TYPE_BUNDLE_SAME_FIXED = 'bundleSameFixPrice';
    const CAMPAIGN_TYPE_BUNDLE_DIFF_FIXED = 'bundleDiffFixPrice';
    const CAMPAIGN_TYPE_BUNDLE_SAME_DOLLAR = 'bundleSameNet';
    const CAMPAIGN_TYPE_BUNDLE_DIFF_DOLLAR = 'bundleDiffNet';
    const CAMPAIGN_TYPE_BUNDLE_SAME_PERCENT = 'bundleSamePercentage';
    const CAMPAIGN_TYPE_BUNDLE_DIFF_PERCENT = 'bundleDiffPercentage';
    const CAMPAIGN_TYPE_DELIVERY = 'delivery';
    const CAMPAIGN_TYPE_FREEITEM = 'freeItem';

    const CAMPAIGN_SCOPE_ITEM = 'items';
    const CAMPAIGN_SCOPE_CATEGORY = 'category';
    const CAMPAIGN_SCOPE_ORDER = 'order';

    const CAMPAIGN_SCOPE_MAPPING = [
        self::CAMPAIGN_SCOPE_ITEM => 'Item',
        self::CAMPAIGN_SCOPE_CATEGORY => 'Category',
        self::CAMPAIGN_SCOPE_ORDER => 'Order',
    ];

    const CAMPAIGN_BUNDLE_MAPPING = [
        [
            'id' => self::CAMPAIGN_TYPE_BUNDLE_SAME_FIXED,
            'name' => 'Bundle Same Items Fixed Price',
            'is_same' => true,
            'type' => 'absolute',
            'phrase_1' => 'Buy ',
            'phrase_2' => ' Same Items for $',
            'phrase_3' => ''
        ],
        [
            'id' => self::CAMPAIGN_TYPE_BUNDLE_DIFF_FIXED,
            'name' => 'Bundle Different Items Fixed Price',
            'is_same' => false,
            'type' => 'absolute',
            'phrase_1' => 'Any ',
            'phrase_2' => ' Mixed Items for $',
            'phrase_3' => ''
        ],
        [
            'id' => self::CAMPAIGN_TYPE_BUNDLE_SAME_DOLLAR,
            'name' => 'Bundle Same Items Dollar Off',
            'is_same' => true,
            'type' => 'value_off',
            'phrase_1' => 'Buy ',
            'phrase_2' => ' Same Items Get $',
            'phrase_3' => 'off'
        ],
        [
            'id' => self::CAMPAIGN_TYPE_BUNDLE_DIFF_DOLLAR,
            'name' => 'Bundle Different Items Dollar Off',
            'is_same' => false,
            'type' => 'value_off',
            'phrase_1' => 'Any ',
            'phrase_2' => ' Mixed Items Get $',
            'phrase_3' => 'off'
        ],
        [
            'id' => self::CAMPAIGN_TYPE_BUNDLE_SAME_PERCENT,
            'name' => 'Bundle Same Items Percent Off',
            'is_same' => true,
            'type' => 'percentage',
            'phrase_1' => 'Buy ',
            'phrase_2' => ' Same Items Get ',
            'phrase_3' => '% off'
        ],
        [
            'id' => self::CAMPAIGN_TYPE_BUNDLE_DIFF_PERCENT,
            'name' => 'Bundle Different Items Percent Off',
            'is_same' => false,
            'type' => 'percentage',
            'phrase_1' => 'Any ',
            'phrase_2' => ' Mixed Items Get ',
            'phrase_3' => '% off'
        ],
    ];

    const CAMPAIGN_MAPPINGS = [
        [
            'id' => self::CAMPAIGN_TYPE_BUNDLE_SAME_FIXED,
            'name' => 'Bundle Same Items Fixed Price',
            'cap' => false,
            'qty' => true,
            'promo_value' => true,
            'phrase1' => 'Buy ',
            'phrase2' => ' Same Items for $',
            'phrase3' => '',
            'scope' => [
                [
                    'id' => self::CAMPAIGN_SCOPE_ITEM,
                    'name' => 'Item',
                    'isProduct' => true,
                    'isCategory' => false,
                    'singleObjectOnly' => true,
                    'eaterType' => [
                        [
                            'id' => 'all',
                            'name' => 'All',
                        ],
                    ],
                    'minBasketAmount' => false,
                ]
            ]
        ],
        [
            'id' => self::CAMPAIGN_TYPE_BUNDLE_DIFF_FIXED,
            'name' => 'Bundle Different Items Fixed Price',
            'cap' => false,
            'qty' => true,
            'promo_value' => true,
            'phrase1' => 'Any ',
            'phrase2' => ' Mixed Items for $',
            'phrase3' => '',
            'scope' => [
                [
                    'id' => self::CAMPAIGN_SCOPE_ITEM,
                    'name' => 'Item',
                    'isProduct' => true,
                    'isCategory' => false,
                    'singleObjectOnly' => false,
                    'eaterType' => [
                        [
                            'id' => 'all',
                            'name' => 'All',
                        ],
                    ],
                    'minBasketAmount' => false,
                ]
            ]
        ],
        [
            'id' => self::CAMPAIGN_TYPE_BUNDLE_SAME_DOLLAR,
            'name' => 'Bundle Same Items Dollar Off',
            'cap' => false,
            'qty' => true,
            'promo_value' => true,
            'phrase1' => 'Buy ',
            'phrase2' => ' Same Items Get $',
            'phrase3' => 'off',
            'scope' => [
                [
                    'id' => self::CAMPAIGN_SCOPE_ITEM,
                    'name' => 'Item',
                    'isProduct' => true,
                    'isCategory' => false,
                    'singleObjectOnly' => true,
                    'eaterType' => [
                        [
                            'id' => 'all',
                            'name' => 'All',
                        ],
                    ],
                    'minBasketAmount' => false,
                ]
            ]
        ],
        [
            'id' => self::CAMPAIGN_TYPE_BUNDLE_DIFF_DOLLAR,
            'name' => 'Bundle Different Items Dollar Off',
            'cap' => false,
            'qty' => true,
            'promo_value' => true,
            'phrase1' => 'Any ',
            'phrase2' => ' Mixed Items Get $',
            'phrase3' => 'off',
            'scope' => [
                [
                    'id' => self::CAMPAIGN_SCOPE_ITEM,
                    'name' => 'Item',
                    'isProduct' => true,
                    'isCategory' => false,
                    'singleObjectOnly' => false,
                    'eaterType' => [
                        [
                            'id' => 'all',
                            'name' => 'All',
                        ],
                    ],
                    'minBasketAmount' => false,
                ]
            ]
        ],
        [
            'id' => self::CAMPAIGN_TYPE_BUNDLE_SAME_PERCENT,
            'name' => 'Bundle Same Items Percent Off',
            'cap' => false,
            'qty' => true,
            'promo_value' => true,
            'phrase1' => 'Buy ',
            'phrase2' => ' Same Items Get ',
            'phrase3' => '% off',
            'scope' => [
                [
                    'id' => self::CAMPAIGN_SCOPE_ITEM,
                    'name' => 'Item',
                    'isProduct' => true,
                    'isCategory' => false,
                    'singleObjectOnly' => true,
                    'eaterType' => [
                        [
                            'id' => 'all',
                            'name' => 'All',
                        ],
                    ],
                    'minBasketAmount' => false,
                ]
            ]
        ],
        [
            'id' => self::CAMPAIGN_TYPE_BUNDLE_DIFF_PERCENT,
            'name' => 'Bundle Different Items Percent Off',
            'cap' => false,
            'qty' => true,
            'promo_value' => true,
            'phrase1' => 'Any ',
            'phrase2' => ' Mixed Items Get ',
            'phrase3' => '% off',
            'scope' => [
                [
                    'id' => self::CAMPAIGN_SCOPE_ITEM,
                    'name' => 'Item',
                    'isProduct' => true,
                    'isCategory' => false,
                    'singleObjectOnly' => false,
                    'eaterType' => [
                        [
                            'id' => 'all',
                            'name' => 'All',
                        ],
                    ],
                    'minBasketAmount' => false,
                ]
            ]
        ],
        [
            'id' => self::CAMPAIGN_TYPE_DOLLAR,
            'name' => 'Dollar Off',
            'cap' => false,
            'qty' => false,
            'promo_value' => true,
            'phrase1' => '$',
            'phrase2' => ' off',
            'phrase3' => ' for selected ',
            'scope' => [
                [
                    'id' => self::CAMPAIGN_SCOPE_ITEM,
                    'name' => 'Item',
                    'isProduct' => true,
                    'isCategory' => false,
                    'singleObjectOnly' => false,
                    'eaterType' => [
                        [
                            'id' => 'all',
                            'name' => 'All',
                        ],
                    ],
                    'minBasketAmount' => false,
                ],
                [
                    'id' => self::CAMPAIGN_SCOPE_CATEGORY,
                    'name' => 'Category',
                    'isProduct' => false,
                    'isCategory' => true,
                    'singleObjectOnly' => false,
                    'eaterType' => [
                        [
                            'id' => 'all',
                            'name' => 'All',
                        ],
                    ],
                    'minBasketAmount' => false,
                ],
                [
                    'id' => self::CAMPAIGN_SCOPE_ORDER,
                    'name' => 'Order',
                    'isProduct' => false,
                    'isCategory' => false,
                    'singleObjectOnly' => false,
                    'eaterType' => [
                        [
                            'id' => 'all',
                            'name' => 'All',
                        ],
                        [
                            'id' => 'new',
                            'name' => 'New',
                        ],
                    ],
                    'minBasketAmount' => true,
                ],
            ]
        ],
        [
            'id' => self::CAMPAIGN_TYPE_PERCENT,
            'name' => 'Percent Off',
            'cap' => true,
            'qty' => false,
            'promo_value' => true,
            'phrase1' => '',
            'phrase2' => '% off up to $',
            'phrase3' => ' for selected ',
            'scope' => [
                [
                    'id' => self::CAMPAIGN_SCOPE_ITEM,
                    'name' => 'Item',
                    'isProduct' => true,
                    'isCategory' => false,
                    'singleObjectOnly' => false,
                    'eaterType' => [
                        [
                            'id' => 'all',
                            'name' => 'All',
                        ],
                    ],
                    'minBasketAmount' => false,
                ],
                [
                    'id' => self::CAMPAIGN_SCOPE_CATEGORY,
                    'name' => 'Category',
                    'isProduct' => false,
                    'isCategory' => true,
                    'singleObjectOnly' => false,
                    'eaterType' => [
                        [
                            'id' => 'all',
                            'name' => 'All',
                        ],
                    ],
                    'minBasketAmount' => false,
                ],
                [
                    'id' => self::CAMPAIGN_SCOPE_ORDER,
                    'name' => 'Order',
                    'isProduct' => false,
                    'isCategory' => false,
                    'singleObjectOnly' => false,
                    'eaterType' => [
                        [
                            'id' => 'all',
                            'name' => 'All',
                        ],
                        [
                            'id' => 'new',
                            'name' => 'New',
                        ],
                    ],
                    'minBasketAmount' => true,
                ],
            ]
        ],
        [
            'id' => self::CAMPAIGN_TYPE_DELIVERY,
            'name' => 'Delivery Fee Off',
            'cap' => false,
            'qty' => false,
            'promo_value' => true,
            'phrase1' => '$',
            'phrase2' => ' delivery off',
            'scope' => [
                [
                    'id' => self::CAMPAIGN_SCOPE_ORDER,
                    'name' => 'Order',
                    'isProduct' => false,
                    'isCategory' => false,
                    'singleObjectOnly' => false,
                    'eaterType' => [
                        [
                            'id' => 'all',
                            'name' => 'All',
                        ],
                        [
                            'id' => 'new',
                            'name' => 'New',
                        ],
                    ],
                    'minBasketAmount' => true,
                ],
            ]
        ],
        [
            'id' => self::CAMPAIGN_TYPE_FREEITEM,
            'name' => 'Free Item',
            'cap' => false,
            'qty' => false,
            'promo_value' => false,
            'phrase1' => 'Free Item',
            'phrase2' => '',
            'scope' => [
                [
                    'id' => self::CAMPAIGN_SCOPE_ITEM,
                    'name' => 'Item',
                    'isProduct' => true,
                    'isCategory' => false,
                    'singleObjectOnly' => true,
                    'eaterType' => [
                        [
                            'id' => 'all',
                            'name' => 'All',
                        ],
                    ],
                    'minBasketAmount' => true,
                ],
            ]
        ],
    ];

    const DEFAULT_CATEGORIES = [
        "categories" => [
            [
                "id" => "SGITEDP20200707065055013739",
                "name" => "Ice Cream & Dessert",
                "subCategories" => [
                    [
                        "id" => "SGITEDP20200707065104013951",
                        "name" => "Ice Cream Pints"
                    ],
                    [
                        "id" => "SGITEDP20220505051119018481",
                        "name" => "Frozen Desserts"
                    ],
                    [
                        "id" => "SGITEDP20200707065112014691",
                        "name" => "Ice Cream Sticks"
                    ],
                    [
                        "id" => "SGITEDP20200717085806012030",
                        "name" => "Puffs"
                    ],
                    [
                        "id" => "SGITEDP20200717094052016939",
                        "name" => "Jelly & Pudding"
                    ],
                    [
                        "id" => "SGITEDP20200707065119015075",
                        "name" => "Frozen Yoghurt"
                    ],
                    [
                        "id" => "SGITEDP20200707065124017190",
                        "name" => "Asian Dessert"
                    ],
                    [
                        "id" => "SGITEDP20200707062332016541",
                        "name" => "Desserts (Others)"
                    ],
                    [
                        "id" => "SGITEDP20200717085757018518",
                        "name" => "Ice Cream"
                    ],
                    [
                        "id" => "SGITEDP20230228090646016005",
                        "name" => "Chilled Desserts"
                    ],
                    [
                        "id" => "SGITEDP20230228090706018087",
                        "name" => "Ice Cream Bundles"
                    ],
                    [
                        "id" => "SGITEDP20230228090718019047",
                        "name" => "B&J Pints Selection"
                    ],
                    [
                        "id" => "SGITEDP20230228090729011304",
                        "name" => "Magnum Selection"
                    ],
                    [
                        "id" => "SGITEDP20230228090740016500",
                        "name" => "Cornetto Selection"
                    ],
                    [
                        "id" => "SGITEDP20230518072748019786",
                        "name" => "Ice Cream Cake"
                    ],
                    [
                        "id" => "SGITEDP20230623055805016330",
                        "name" => "Scoops"
                    ],
                    [
                        "id" => "SGITEDP20230705011657012553",
                        "name" => "Premium Collection (Ice Cream & Dessert)"
                    ],
                    [
                        "id" => "SGITEDP20230705011729015795",
                        "name" => "Classic Collection (Ice Cream & Dessert)"
                    ],
                    [
                        "id" => "SGITEDP20230810063658010418",
                        "name" => "Signatures (Ice Cream)"
                    ],
                    [
                        "id" => "SGITEDP20230810063723018198",
                        "name" => "Signatures (Dessert)"
                    ],
                    [
                        "id" => "SGITEDP20230817031819018092",
                        "name" => "Soft Serve & Sundae"
                    ],
                    [
                        "id" => "SGITEDP20230913063042011476",
                        "name" => "Halal Flavours"
                    ],
                    [
                        "id" => "SGITEDP20230913063055017798",
                        "name" => "Sorbet"
                    ],
                    [
                        "id" => "SGITEDP20230914032002015735",
                        "name" => "Bundles (Ice Cream & Dessert)"
                    ],
                    [
                        "id" => "SGITEDP20230914032013018920",
                        "name" => "Ice Cream Pies"
                    ],
                    [
                        "id" => "SGITEDP20230915043005016934",
                        "name" => "Seasonal Collection (Ice Cream & Dessert)"
                    ],
                    [
                        "id" => "SGITEDP20230915043117014032",
                        "name" => "Mini Ice Cream Cups"
                    ]
                ]
            ]
        ]
    ];

    const START_TIME = '00:00';
    const END_TIME = '23:59';
    const MAX_END_DATETIME = '9999-12-31 23:59:59';

    const PAYMENT_METHOD_GRABMART = 209;

    const PAYMENT_TYPE_CASH = 'CASH';
    const PAYMENT_TYPE_CASHLESS = 'CASHLESS';

    const STATE_NEW = 'NEW';
    const STATE_PENDING = 'PENDING';
    const STATE_ACCEPTED = 'ACCEPTED';
    const STATE_DRIVER_ALLOCATED = 'DRIVER_ALLOCATED';
    const STATE_DRIVER_ARRIVED = 'DRIVER_ARRIVED';
    const STATE_COLLECTED = 'COLLECTED';
    const STATE_DELIVERED = 'DELIVERED';
    const STATE_CANCELLED = 'CANCELLED';
    const STATE_FAILED = 'FAILED';

    const STATUS_AVAILABLE = 'AVAILABLE';
    const STATUS_UNAVAILABLE = 'UNAVAILABLE';
    const STATUS_UNAVAILABLE_TODAY = 'UNAVAILABLETODAY';
    const STATUS_HIDE = 'HIDE';

    const STATUS_MAPPING = [
        1 => 'AVAILABLE',
        0 => 'UNAVAILABLE',
    ];

    public static $main_endpoint = 'https://api.grab.com';
    public static $mart_partner_endpoint = 'https://partner-api.grab.com/grabmart';
    public static $mart_partner_sandbox_endpoint = 'https://partner-api.grab.com/grabmart-sandbox';
    public static $food_partner_endpoint = 'https://partner-api.grab.com/grabfood';
    public static $food_partner_sandbox_endpoint = 'https://partner-api.grab.com/grabfood-sandbox';

    protected $deliveryPlatformOperator;

    public function __construct(DeliveryPlatformOperator $deliveryPlatformOperator)
    {
        $this->deliveryPlatformOperator = $deliveryPlatformOperator;
    }

    public function getDefaultCategories()
    {
        return self::DEFAULT_CATEGORIES;
    }

    // retrieve and fill in oauth params from delivery platform
    public function getIncomingOauthParams($params = [])
    {
        return [
            'access_token' => $params['access_token'],
            'expired_at' => $params['expires_in'],
            'token_type' => $params['token_type'],
        ];
    }

    // retrieve and fill in oauth params from own web service to delivery platform
    private function getOutgoingOauthParams($params = [])
    {
        return [
            'access_token' => $params['access_token'],
            'token_type' => $params['type'],
            'expires_in' => $params['expired_at'],
        ];
    }

    // Get Grab OAuth token
    public function getGrabOAuthToken()
    {
        if(!$this->deliveryPlatformOperator->externalOauthToken()->exists()) {
            throw new \Exception('Oauth Client ID and Secret Not Found: ');
        }

        $response = Http::withHeaders($this->getHeaders())
        ->post($this->getMainEndpoint() . '/grabid/v1/oauth2/token', [
            'client_id' => $this->deliveryPlatformOperator->externalOauthToken->client_id,
            'client_secret' => $this->deliveryPlatformOperator->externalOauthToken->client_secret,
            'grant_type' => $this->deliveryPlatformOperator->externalOauthToken->granted_type,
            // 'scope' => $this->getScope(),
            'scope' => $this->deliveryPlatformOperator->externalOauthToken->scopes,
        ]);

        return $this->getResponse($response, 'getGrabOAuthToken');

        throw new \Exception('Get OAuthToken Failed: ' . $response->body());
    }

    // interface compulsory for delivery platform
    public function getOauthToken()
    {
        return $this->getGrabOAuthToken();
    }

    // List Mart Categories
    public function listMartCategories()
    {
        $this->verifyOauthAccessToken();
        $response = Http::withHeaders($this->getHeaders())
        ->get($this->getPartnerEndpoint() . '/partner/v1/menu/categories', [
            'countryCode' => $this->deliveryPlatformOperator->operator->country->code,
        ]);

        return $this->getResponse($response, 'listMartCategories');

        throw new \Exception('Get Mart Categories Failed: ' . $response->body());
    }

    // Notify Grab of updated menu
    public function notifyUpdatedMenu($merchantIdParam = [])
    {
        $this->verifyOauthAccessToken();

        $response = Http::withHeaders($this->getHeaders())
        ->post($this->getPartnerEndpoint() . '/partner/v1/merchant/menu/notification', $merchantIdParam);

        return $this->getResponse($response, 'notifyUpdatedMenu');

        throw new \Exception('Notify Updated Menu Failed: ' . $response->body());
    }

    // Update menu record
    public function updateMenuRecord($singleProductParam = [])
    {
        $this->verifyOauthAccessToken();

        if(!$singleProductParam) {
            throw new \Exception('No Single Product Param for Update Menu Record ');
        }

        $response = Http::withHeaders($this->getHeaders())
        ->put($this->getPartnerEndpoint() . '/partner/v1/menu', $singleProductParam);

        return $this->getResponse($response, 'updateMenuRecord');

        throw new \Exception('Update Single Menu Failed: ' . $response->body());
    }


    // Batch Update Menu
    public function batchUpdateMenu()
    {
        $this->verifyOauthAccessToken();

        $response = Http::withHeaders($this->getHeaders())
        ->put($this->getPartnerEndpoint() . '/partner/v1/batch/menu', [
            'merchantID' => $this->merchantId,
            'field' => 'ITEM',
            'menuEntities' => [
                [
                    'id' => 'item_id',
                    'name' => 'item_name',
                    'description' => 'item_description',
                    'price' => [
                        'amount' => 100,
                        'currency' => 'SGD',
                    ],
                    'availableStatus' => 'AVAILABLE',
                    'categories' => [
                        [
                            'id' => 'category_id',
                            'name' => 'category_name',
                            'availableStatus' => 'AVAILABLE',
                        ],
                    ],
                    'images' => [
                        [
                            'url' => 'https://www.google.com/images/branding/googlelogo/1x/googlelogo_color_272x92dp.png',
                            'type' => 'PRIMARY',
                        ],
                    ],
                ],
            ],
        ]);

        return $this->getResponse($response, 'batchUpdateMenu');

        throw new \Exception('Batch Update Menu Failed: ' . $response->body());
    }

    // Manually accept/reject orders
    public function manuallyAcceptRejectOrder($orderId, $isAccept = true)
    {
        $this->verifyOauthAccessToken();

        $response = Http::withHeaders($this->getHeaders())
        ->post($this->getPartnerEndpoint() . '/partner/v1/order/prepare', [
            'orderID' => $orderId,
            'toState' => $isAccept ? 'ACCEPTED' : 'REJECTED',
        ]);

        return $this->getResponse($response, 'manuallyAcceptRejectOrder');

        throw new \Exception('Manualy Accept Reject Order Failed: ' . $response->body());
    }

    // List orders
    public function listOrders($date, $pageNumber = 1, $filters)
    {
        $this->verifyOauthAccessToken();

        $response = Http::withHeaders($this->getHeaders([
            'merchantID' => $this->merchantId,
            'date' => $date ? $date : Carbon::today()->toDateString(),
            'page' => $pageNumber,
        ]))
        ->post($this->getPartnerEndpoint() . '/partner/v1/order/prepare', $filters);

        return $this->getResponse($response, 'listOrders');

        throw new \Exception('List Orders Failed: ' . $response->body());
    }

    // Edit order
    public function editOrder($orderId, $items = [])
    {
        $this->verifyOauthAccessToken();

        if(!$items) {
            throw new \Exception('Items Arr Not Found for Edit Order ');
        }

        $response = Http::withHeaders($this->getHeaders())
        ->put($this->getPartnerEndpoint() . '/partner/v1/order/prepare/'. $orderId, [
            'orderID' => $orderId,
            'items' => $items,
            'onlyRecalculate' => config('app.env') === 'local' ? true : false,
        ]);

        return $this->getResponse($response, 'editOrder');

        throw new \Exception('Edit Order Failed: ' . $response->body());
    }

    // Mark order as ready
    public function markOrderReady($orderId)
    {
        $this->verifyOauthAccessToken();

        $response = Http::withHeaders($this->getHeaders())
        ->post($this->getPartnerEndpoint() . '/partner/v1/orders/mark', [
            'orderID' => $orderId,
            'markStatus' => 1
        ]);

        return $this->getResponse($response, 'markOrderReady');

        throw new \Exception('Mark Order Ready Failed: ' . $response->body());
    }

    // Update delivery state
    public function updateDeliveryState($orderId, $fromState, $toState)
    {
        $this->verifyOauthAccessToken();

        if(!$state) {
            throw new \Exception('State Not Found for Update Delivery State ');
        }

        $response = Http::withHeaders($this->getHeaders())
        ->post($this->getPartnerEndpoint() . '/partner/v1/order/delivery', [
            'orderID' => $orderId,
            'fromState' => $fromState,
            'toState' => $toState,
        ]);

        return $this->getResponse($response, 'updateDeliveryState');

        throw new \Exception('Update Delivery State Failed: ' . $response->body());
    }

    // Update order ready time
    public function updateOrderReadyTime($orderId, $newOrderReadyTime)
    {
        $this->verifyOauthAccessToken();

        $response = Http::withHeaders($this->getHeaders())
        ->put($this->getPartnerEndpoint() . '/partner/v1/order/readytime', [
            'orderID' => $orderId,
            'newOrderReadyTime' => $newOrderReadyTime ? $newOrderReadyTime : Carbon::now()->toDateTimeString(),
        ]);

        return $this->getResponse($response, 'updateOrderReadyTime');

        throw new \Exception('Update Order Ready Time Failed: ' . $response->body());
    }

    // Check order cancelable
    public function checkOrderCancelable($orderId, $merchantID)
    {
        $this->verifyOauthAccessToken();

        $response = Http::withHeaders($this->getHeaders())
        ->get($this->getPartnerEndpoint() . '/partner/v1/order/cancelable', [
            'orderID' => $orderId,
            'merchantID' => $merchantID,
        ]);

        return $this->getResponse($response, 'checkOrderCancelable');

        throw new \Exception('Check Order Cancelable Failed: ' . $response->body());
    }

    // Cancel an order
    public function cancelOrder($orderID, $merchantID, $cancelCode)
    {
        $this->verifyOauthAccessToken();

        $response = Http::withHeaders($this->getHeaders())
        ->put($this->getPartnerEndpoint() . '/partner/v1/order/cancel', [
            'orderID' => $orderID,
            'merchantID' => $merchantID,
            'cancelCode' => $cancelCode,
        ]);

        return $this->getResponse($response, 'cancelOrder');

        throw new \Exception('Cancel Order Failed: ' . $response->body());
    }

    // Pause store
    public function pauseStore($merchantID, $isPause = true, $duration = '24h')
    {
        $this->verifyOauthAccessToken();

        $response = Http::withHeaders($this->getHeaders())
        ->put($this->getPartnerEndpoint() . '/partner/v1/merchant/pause', [
            'merchantID' => $merchantID,
            'isPause' => $isPause,
            'duration' => $duration,
        ]);

        return $this->getResponse($response, 'pauseOrder');

        throw new \Exception('Pause Order Failed: ' . $response->body());
    }

    // create campaign
    public function createCampaign($campaignParams = [])
    {
        $this->verifyOauthAccessToken();

        if(!$campaignParams) {
            throw new \Exception('Campaign Param Not Found for Create Campaign ');
        }

        $response = Http::withHeaders($this->getHeaders())
        ->post($this->getPartnerEndpoint() . '/partner/v1/campaigns', $campaignParams);

        return $this->getResponse($response, 'createCampaign');

        throw new \Exception('Create Campaign Failed: ' . $response->body());
    }

    // create campaign
    public function deleteCampaign($platformRefID)
    {
        $this->verifyOauthAccessToken();

        if(!$platformRefID) {
            throw new \Exception('Campaign ID Not Found for Delete Campaign ');
        }

        $response = Http::withHeaders($this->getHeaders([
            'campaignId' => $platformRefID,
        ]))
        ->delete($this->getPartnerEndpoint() . '/partner/v1/campaigns/'. $platformRefID);

        return $this->getResponse($response, 'deleteCampaign');

        throw new \Exception('Delete Campaign Failed: ' . $response->body());
    }

    // update campaign
    public function updateCampaign($platformRefID, $campaignParams = [])
    {
        $this->verifyOauthAccessToken();

        if(!$platformRefID) {
            throw new \Exception('Campaign ID Not Found for Update Campaign ');
        }

        $response = Http::withHeaders($this->getHeaders([
            'campaignId' => $platformRefID,
        ]))
        ->put($this->getPartnerEndpoint() . '/partner/v1/campaigns/'. $platformRefID, $campaignParams);

        return $this->getResponse($response, 'updateCampaign');

        throw new \Exception('Update Campaign Failed: ' . $response->body());
    }

    // init default headers for grab
    private function getHeaders($params = [])
    {
        $defaultHeaders = array(
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->deliveryPlatformOperator->externalOauthToken->access_token,
        );

        $headers = array_merge($defaultHeaders, $params);

        return $headers;
    }

    private function getMainEndpoint()
    {
        return self::$main_endpoint;
    }

    private function getPartnerEndpoint()
    {
        if($this->deliveryPlatformOperator->externalOauthToken->scopes === 'mart.partner_api') {
            if($this->deliveryPlatformOperator->type === 'sandbox') {
                $endpoint = self::$mart_partner_sandbox_endpoint;
            }else {
                $endpoint = self::$mart_partner_endpoint;
            }
        }else if($this->deliveryPlatformOperator->externalOauthToken->scopes === 'food.partner_api') {
            if($this->deliveryPlatformOperator->type === 'sandbox') {
                $endpoint = self::$food_partner_sandbox_endpoint;
            }else {
                $endpoint = self::$food_partner_endpoint;
            }
        }

        return $endpoint;
    }

    private function verifyOauthAccessToken()
    {
        if(!$this->deliveryPlatformOperator->externalOauthToken()->exists() or !$this->deliveryPlatformOperator->externalOauthToken->access_token) {
            throw new \Exception('Oauth Client ID, Secret, Access Key Not Available');
        }

        return true;
    }
}

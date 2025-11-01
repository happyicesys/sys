<?php

namespace App\Http\Resources;

use DateTimeZone;
use Illuminate\Http\Resources\Json\JsonResource;

class OperatorResource extends JsonResource
{
    public function toArray($request)
    {
        $emailRecipientsJson = $this->email_recipients_json ?? [];

        // Safe timezone mapping
        $tzList  = DateTimeZone::listIdentifiers();
        $tzName  = $this->timezone;
        $tzIndex = null;
        if ($tzName !== null) {
            $found = array_search($tzName, $tzList, true);
            $tzIndex = ($found !== false) ? $found : null;
        }

        return [
            'id'          => $this->id,
            'code'        => $this->code,
            'name'        => $this->name,
            'full_name'   => $this->code . ' - ' . $this->name,
            'remarks'     => $this->remarks,
            'gst_vat_rate'=> $this->gst_vat_rate,
            'is_active'   => (bool) $this->is_active,

            'timezone' => [
                'id'   => $tzIndex,
                'name' => $tzName,
            ],

            'country'    => CountryResource::make($this->whenLoaded('country')),
            'country_id' => CountryResource::make($this->whenLoaded('country')),

            'address' => AddressResource::make($this->whenLoaded('address')),

            'customers'                => CustomerResource::collection($this->whenLoaded('customers')),
            'vends'                    => VendResource::collection($this->whenLoaded('vends')),
            'deliveryPlatformOperators'=> DeliveryPlatformOperatorResource::collection($this->whenLoaded('deliveryPlatformOperators')),
            'operatorPaymentGateways'  => OperatorPaymentGatewayResource::collection($this->whenLoaded('operatorPaymentGateways')),

            // Current UI field (single multiselect backing)
            'email_recipients' => $emailRecipientsJson,

            'logo' => AttachmentResource::make($this->whenLoaded('logo')),
            'logo_url' => $this->logo?->full_url,

            // Back-compat fields; will be [] when json is a flat string array
            'email_user_ids' => data_get($emailRecipientsJson, 'user_ids', []),
            'email_customs'  => data_get($emailRecipientsJson, 'customs', []),
        ];
    }
}

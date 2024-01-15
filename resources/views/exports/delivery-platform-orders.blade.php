@inject('deliveryPlatformOrderModel', 'App\Models\DeliveryPlatformOrder')
<table>
  <thead>
  <tr>
      <th>#</th>
      <th>Order Time</th>
      <th>Platform</th>
      <th>Order ID</th>
      <th>Short Order ID</th>
      <th>Status</th>
      <th>C to D (minutes)</th>
      <th>Vend ID</th>
      <th>Customer</th>
      <th>Transaction Order ID</th>
      <th>Driver Phone Number</th>
      <th>Campaign</th>
      <th>Campaign Total</th>
      <th>Subtotal</th>
      <th>Product Code</th>
      <th>Product Name</th>
      <th>Qty</th>
      <th>Channel</th>
      <th>Error</th>
  </tr>
  </thead>
  <tbody>
  @foreach($deliveryPlatformOrders as $deliveryPlatformOrderIndex => $deliveryPlatformOrder)
      <tr>
          <td>
            {{ $deliveryPlatformOrderIndex + 1 }}
          </td>
          <td>
            {{ $deliveryPlatformOrder->order_created_at }}
          </td>
          <td>
            {{ $deliveryPlatformOrder->deliveryPlatform->name }}
            <br>
            ({{ $deliveryPlatformOrder->deliveryPlatformOperator->type }})
          </td>
          <td>
            {{ $deliveryPlatformOrder->order_id }}
          </td>
          <td>
            {{ $deliveryPlatformOrder->short_order_id }}
          </td>
          <td>
            {{ $deliveryPlatformOrderModel::STATUS_MAPPING[$deliveryPlatformOrder->status] }}
          </td>
          <td>
            {{ $deliveryPlatformOrder->last_mile_timediff_mins }}
          </td>
          <td>
            {{ $deliveryPlatformOrder->vend_code }}
          </td>
          <td>
            @if($deliveryPlatformOrder->deliveryProductMappingVend and
            $deliveryPlatformOrder->deliveryProductMappingVend->vend and
            $deliveryPlatformOrder->deliveryProductMappingVend->vend->latestVendBinding and
            $deliveryPlatformOrder->deliveryProductMappingVend->vend->latestVendBinding->customer)
              {{ $deliveryPlatformOrder->deliveryProductMappingVend->vend->latestVendBinding->customer->name }}
              <br>
              {{ $deliveryPlatformOrder->deliveryProductMappingVend->vend->latestVendBinding->customer->phone }}
            <span>
            @else
              {{ $deliveryPlatformOrder->deliveryProductMappingVend->vend->name }}
            </span>
            @endif
          </td>
          <td>
            {{ $deliveryPlatformOrder->vend_transaction_order_id }}
          </td>
          <td>
            {{ $deliveryPlatformOrder->driver_phone_number }}
          </td>
          <td>
            @if($deliveryPlatformOrder->campaign_json)
              @foreach($deliveryPlatformOrder->campaign_json as $campaign)
              <span>
                {{ $campaign['name'] }}
              </span>
              @endforeach
            @endif
          </td>
          <td>
            {{ $deliveryPlatformOrder->promo_amount }}
          </td>
          <td>
            {{ $deliveryPlatformOrder->subtotal_amount }}
          </td>
      </tr>
  @endforeach
  </tbody>
</table>
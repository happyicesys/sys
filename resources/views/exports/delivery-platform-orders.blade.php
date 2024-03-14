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
      <th>Customer Code</th>
      <th>Customer Name</th>
      <th>Transaction Order ID</th>
      <th>Driver Phone Number</th>
      <th>Campaign</th>
      <th>Campaign Total</th>
      <th>Subtotal</th>
      <th>Product Code</th>
      <th>Product Name</th>
      <th>Qty</th>
      <th>Channel</th>
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
            $deliveryPlatformOrder->deliveryProductMappingVend->vend->customer)
              {{ $deliveryPlatformOrder->deliveryProductMappingVend->vend->customer->code }}
            @endif
          </td>
          <td>
            @if($deliveryPlatformOrder->deliveryProductMappingVend and
            $deliveryPlatformOrder->deliveryProductMappingVend->vend and
            $deliveryPlatformOrder->deliveryProductMappingVend->vend->customer)
              {{ $deliveryPlatformOrder->deliveryProductMappingVend->vend->customer->name }}
            @else
              {{ $deliveryPlatformOrder->deliveryProductMappingVend->vend->name }}
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
                {{ $campaign['name'] }}
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
      @if($deliveryPlatformOrder->deliveryPlatformOrderItems)
        @foreach($deliveryPlatformOrder->deliveryPlatformOrderItems as $deliveryPlatformOrderItemIndex => $deliveryPlatformOrderItem)
          <tr>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td>
                {{ $deliveryPlatformOrderItem->product->code }}
              </td>
              <td>
                {{ $deliveryPlatformOrderItem->product->name }}
              </td>
              <td>
                {{ $deliveryPlatformOrderItem->orderItemVendChannels[0]->qty }}
              </td>
              <td>
                {{ $deliveryPlatformOrderItem->orderItemVendChannels[0]->vend_channel_code }}
              </td>
          </tr>
        @endforeach
      @endif
  @endforeach
  </tbody>
</table>
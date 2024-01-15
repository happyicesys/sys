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
  </tr>
  </thead>
  <tbody>
  @foreach($deliveryPlatformOrders as $deliveryPlatformOrderIndex => $deliveryPlatformOrder)
      <tr>
          <td>
            {{ $deliveryPlatformOrderIndex + 1 }}
          </td>
          <td>
            {{ $deliveryPlatformOrder->order_created_at->format('Y-m-d H:i:s') }}
          </td>
          <td>
            {{ $deliveryPlatformOrder->deliveryPlatform->name }}
            <br>
            ({{ $deliveryPlatformOrder->deliveryPlatformOperator->type }})
          </td>
          <td>
            {{ $deliveryPlatformOrderModel::STATUS_MAPPING[$deliveryPlatformOrder->status] }}
          </td>
          <td>
            {{ $deliveryPlatformOrder->last_mile_timediff_mins }}
          </td>

      </tr>
  @endforeach
  </tbody>
</table>
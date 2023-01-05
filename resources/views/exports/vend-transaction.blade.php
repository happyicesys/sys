<table>
  <thead>
  <tr>
      <th>#</th>
      <th>Order ID</th>
      <th>Transaction Datetime</th>
      <th>Vend ID</th>
      <th>Customer Name</th>
      <th>Channel</th>
      <th>Product</th>
      <th>Amount</th>
      <th>Payment Method</th>
      <th>Error</th>
  </tr>
  </thead>
  <tbody>
  @foreach($vendTransactions as $index => $vendTransaction)
      <tr>
          <td>
            {{ $index + 1 }}
          </td>
          <td>
            {!! "&nbsp;" !!}
            {{ $vendTransaction->order_id }}
          </td>
          <td>
            {{ $vendTransaction->transaction_datetime }}
          </td>
          <td>
            {{ $vendTransaction->vend->code }}
          </td>
          <td>
            {{ $vendTransaction->vend->latestVendBinding ? $vendTransaction->vend->latestVendBinding->customer->code :
            $vendTransaction->vend->name }}<br>
            {{ $vendTransaction->vend->latestVendBinding ? $vendTransaction->vend->latestVendBinding->customer->name : '' }}
          </td>
          <td>
            {{ $vendTransaction->vendChannel->code }}
          </td>
          <td>
            {{ $vendTransaction->product ? $vendTransaction->product->code.' - '.$vendTransaction->product->name : '' }}
          </td>
          <td>
            {{ $vendTransaction->amount/ 100 }}
          </td>
          <td>
            {{ $vendTransaction->paymentMethod->name }}
          </td>
          <td>
            {{ $vendTransaction->vendChannelError ? $vendTransaction->vendChannelError->code : '' }}
          </td>
      </tr>
  @endforeach
  </tbody>
</table>
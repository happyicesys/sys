<table>
  <thead>
  <tr>
      <th>#</th>
      <th>Order ID</th>
      <th>Transaction Datetime</th>
      <th>Vend ID</th>
      <th>Customer Name</th>
      <th>Channel</th>
      <th>Product ID</th>
      <th>Product Name</th>
      <th>Price Type</th>
      <th>Amount</th>
      <th>Sales (before GST)</th>
      <th>Unit Cost</th>
      <th>Payment Method</th>
      <th>Error</th>
      <th>Location Type</th>
  </tr>
  </thead>
  <tbody>
    {{-- @dd($vendTransactions->toArray()); --}}
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
            {{$vendTransaction->customer && $vendTransaction->customer->person_id ? $vendTransaction->customer->virtual_customer_prefix . '-' .$vendTransaction->customer->virtual_customer_code : ''}}
            <br>
            {{$vendTransaction->customer ? $vendTransaction->customer->name : ''}}

          </td>
          <td>
            {{ $vendTransaction->vend_channel_code }}
          </td>
          <td>
            {{
                $vendTransaction->product ? $vendTransaction->product->code : ''
            }}
          </td>
          <td>
            {{
                $vendTransaction->product ? $vendTransaction->product->name : ''
            }}
          </td>
          <td>
            {{ $vendTransaction->vendChannel && $vendTransaction->vendChannel->amount === $vendTransaction->amount ? 'P1' : ($vendTransaction->vendChannel && $vendTransaction->vendChannel->amount2 === $vendTransaction->amount ? 'P2' : '') }}
          </td>
          <td>
            {{ isset($vendTransaction->amount) ? $vendTransaction->amount/ 100 : 0 }}
          </td>
          <td>
            {{ isset($vendTransaction->revenue) ? $vendTransaction->revenue/ 100 : 0 }}
          </td>
          <td>
            {{ isset($vendTransaction->unit_cost) ? $vendTransaction->unit_cost/ 100 : 0 }}
          </td>
          <td>
            {{ $vendTransaction->paymentMethod->name }}
          </td>
          <td>
            {{ $vendTransaction->vend_transaction_json &&
                  isset($vendTransaction->vend_transaction_json['SErr']) ?
                  $vendTransaction->vend_transaction_json['SErr'] :
                  $vendTransaction->vend_channel_error_code }}
          </td>
          <td>
            {{ $vendTransaction->location_type_json ?
                $vendTransaction->location_type_json['name'] :
                '' }}
          </td>
      </tr>

      @if($vendTransaction->vendTransactionItems)
        @foreach($vendTransaction->vendTransactionItems as $vendTransactionItem)
        <tr>
          <td colspan="5"></td>
          <td>
            {{ $vendTransactionItem->vend_channel_code }}
          </td>
          <td>
            {{
                $vendTransactionItem->product ?
                $vendTransactionItem->product->code : ''
            }}
          </td>
          <td>
            {{
                $vendTransactionItem->product ?
                $vendTransactionItem->product->name : ''
            }}
          </td>
        </tr>
        @endforeach
      @endif
  @endforeach
  </tbody>
</table>
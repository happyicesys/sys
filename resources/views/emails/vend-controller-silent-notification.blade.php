@extends('mail')

@section('content')
  <div class="bg-white shadow overflow-hidden sm:rounded-lg">
    <div class="px-4 py-5 sm:px-6">
      <h3 class="text-lg leading-6 font-medium text-gray-900">
        <a href="{{$baseUrl}}/vends/customers?operators[]=all&codes={{$vend->code}}"
          style="color: #1a0dab; text-decoration: underline;">
          #ID: {{ $vend->codeLabel() }} ({{$vendPrefixName}})
        </a>
      </h3>
      <h3 class="text-lg leading-6 font-medium text-gray-900">
        @if($vend and $vend->customer)
          <span>
            - {{$vend->customer->name}}
          </span>
        @endif
      </h3>

      <h3 class="text-md font-bold text-gray-900 mt-4">
        @if($state === \App\Support\ControllerLink::STATE_SILENT)
          (1B) Alert on Controller Not Responding
        @else
          (1B) Alert on Machine Data Not Arriving
        @endif
      </h3>
      <p class="text-sm text-gray-500">
        {{ $label }}
      </p>
      <p class="text-sm text-gray-700 mt-2">
        @if($state === \App\Support\ControllerLink::STATE_SILENT)
          The machine's screen is still connected, but its controller has stopped reporting. There is no temperature reading and no sale can be reported, so the freezer may be warming unseen.
        @else
          Only the machine's MQTT link is up. Temperature readings and sales are not arriving. This may be an internet problem or the controller, and the system cannot tell which.
        @endif
      </p>
      <p class="text-xs text-gray-400">
        Last Controller Data: {{ optional($vend->temp_updated_at)->format('Y-m-d H:i:s') ?? 'N/A' }}
        @if($vend->temp !== null)
          ({{ number_format($vend->temp / 10, 1) }}°C)
        @endif
      </p>

      <div class="mt-4 border-t border-gray-200"></div>

      <div class="mt-4">
        <p class="text-sm text-gray-500">Handling methods:</p>
        <div class="mt-2 text-sm text-gray-700 space-y-2">
          <div>i) Contact location personnel to check whether the machine is cold and has power</div>
          <div>ii) Arrange a technician to check the controller board, its power supply and the serial cable</div>
          <div>iii) If the freezer may have warmed, check the stock before it is sold</div>
        </div>
      </div>
      <p class="mt-4 max-w-2xl text-xs text-gray-400">
        Timestamp: {{ $now->format('Y-m-d H:i:s') }}
      </p>
    </div>
  </div>
@endsection

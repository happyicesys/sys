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
        (1C) Alert on Temperature Probe Error ({{ $probes }})
      </h3>
      <p class="text-sm text-gray-500">
        {{ $label }}
      </p>
      <p class="text-sm text-gray-700 mt-2">
        The machine is online and its controller is reporting, but the {{ $probes }} temperature probe, which was reading normally, now sends an error instead of a temperature. The temperature alerts cannot see this probe until it is fixed.
      </p>
      <p class="text-xs text-gray-400">
        Probe error since: {{ optional($vend->temp_probe_error_since)->format('Y-m-d H:i:s') ?? 'N/A' }}
        @if($vend->temp !== null)
          · Last T1 reading: {{ number_format($vend->temp / 10, 1) }}°C
        @endif
        @if($t2 !== null)
          · T2 now: {{ number_format($t2, 1) }}°C
        @endif
      </p>

      <div class="mt-4 border-t border-gray-200"></div>

      <div class="mt-4">
        <p class="text-sm text-gray-500">Handling methods:</p>
        <div class="mt-2 text-sm text-gray-700 space-y-2">
          <div>i) Check the other probe's reading on the temperature graph to see whether the machine is still cold</div>
          <div>ii) Arrange a technician to check the probe, its plug and its cable, and replace the probe if needed</div>
          <div>iii) If the machine may have warmed, check the stock before it is sold</div>
        </div>
      </div>
      <p class="mt-4 max-w-2xl text-xs text-gray-400">
        Timestamp: {{ $now->format('Y-m-d H:i:s') }}
      </p>
    </div>
  </div>
@endsection

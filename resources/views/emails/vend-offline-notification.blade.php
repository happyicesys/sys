@extends('mail')

@section('content')
  <!-- This example requires Tailwind CSS v2.0+ -->
  <div class="bg-white shadow overflow-hidden sm:rounded-lg">
    <div class="px-4 py-5 sm:px-6">
      <h3 class="text-lg leading-6 font-medium text-gray-900">
        <a href="{{$baseUrl}}/vends/customers?operators[]=all&codes={{$vend->code}}"
          style="color: #1a0dab; text-decoration: underline;">
          #ID: {{$vend->code}} ({{$vendPrefixName}})
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
        (1) Alert on Lost of Connectivity or Electricity
      </h3>
      <p class="text-sm text-gray-500">
        {{ $label }}
      </p>
      <p class="text-xs text-gray-400">
        Last Detected Time: {{ optional($vend->last_updated_at)->format('Y-m-d H:i:s') ?? 'N/A' }}
      </p>

      <div class="mt-4 border-t border-gray-200"></div>

      <div class="mt-4">
        <p class="text-sm text-gray-500">Handling methods:</p>
        <div class="mt-2 text-sm text-gray-700 space-y-2">
          <div>i) Cross-check with other source of connectivity device (eg: cashless device; cctv)</div>
          <div>ii) Contact location personnel to confirm is power-trip happen on machine</div>
          <div>iii) If after > 8hrs, still lost connectivity/electricity, arrange technician to go onsite check</div>
        </div>
      </div>
      <p class="mt-4 max-w-2xl text-xs text-gray-400">
        Timestamp: {{ $now->format('Y-m-d H:i:s') }}
      </p>
    </div>
  </div>
@endsection
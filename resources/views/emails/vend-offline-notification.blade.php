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
            {{$vend->customer->code}} - {{$vend->customer->name}}
          </span>
        @endif
      </h3>
      <p class="mt-1 max-w-2xl text-sm text-gray-500">
        Last Detected Time ({{ optional($vend->last_updated_at)->format('y-m-d h:ia') ?? 'N/A' }})
      </p>

      <div class="mt-6 border-t border-gray-200 pt-6">
        <h3 class="text-md font-bold text-gray-900">(1) Alert on Lost of Connectivity or Electricity</h3>

        <div class="mt-4">
          <h4 class="text-sm font-semibold text-gray-700">Handling Method:</h4>
          <ul class="mt-2 list-none text-sm text-gray-700 space-y-2">
            <li>i) Cross-check with other source of connectivity device (eg: cashless device; cctv)</li>
            <li>ii) Contact location personnel to confirm is power-trip happen on machine</li>
            <li>iii) If after > 8hrs, still lost connectivity/electricity, arrange technician to go onsite check</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
@endsection
@extends('mail')

@section('content')
    <!-- This example requires Tailwind CSS v2.0+ -->
    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">
                <a href="{{$baseUrl}}/vends/customers?operators[]=all&codes={{$vend->code}}"
                    style="color: #1a0dab; text-decoration: underline;">
                    #ID: {{$vend->code}} ({{$vendPrefixName}})
                </a> - <span class="text-red-600 font-bold">{{ $title }}</span>
            </h3>
            <h3 class="text-lg leading-6 font-medium text-gray-900">
                @if($vend and $vend->customer)
                    <span>
                        {{$vend->customer->code}} - {{$vend->customer->name}}
                    </span>
                @endif
            </h3>
            <div class="mt-4 border-t border-gray-200 pt-4">
                <h4 class="text-sm font-medium text-gray-500">Possible causes:</h4>
                <ul class="mt-2 list-disc pl-5 text-sm text-gray-700 space-y-1">
                    @if($alertType === \App\Models\VendSmartAlert::TYPE_T2_BELOW_MINUS_25)
                        <li>Fan not function</li>
                        <li>Temp probe malfunction</li>
                    @elseif($alertType === \App\Models\VendSmartAlert::TYPE_TEMPS_ABOVE_0)
                        <li>Freezer door not close tight</li>
                        <li>Open freezer door >15mins</li>
                        <li>Fan not function</li>
                        <li class="text-xs text-gray-500 italic mt-1">(Alert dismissed once temp below 0°C)</li>
                    @elseif($alertType === \App\Models\VendSmartAlert::TYPE_TEMPS_ABOVE_MINUS_8)
                        <li>Freezer door not close tight</li>
                        <li>Open freezer door >15mins</li>
                        <li>Comp not function</li>
                        <li class="text-xs text-gray-500 italic mt-1">(Alert dismissed once temp below -8°C)</li>
                    @elseif($alertType === \App\Models\VendSmartAlert::TYPE_NOT_REACH_MINUS_18)
                        <li>Freezer door not close tight</li>
                        <li>Open freezer door >15mins</li>
                        <li>Many purchases occur</li>
                        <li class="text-xs text-gray-500 italic mt-1">(Alert dismissed once temp below -18°C)</li>
                    @else
                        <li>Unknown error</li>
                    @endif
                </ul>
            </div>
            <p class="mt-4 max-w-2xl text-sm text-gray-500">
                Duration: {{ $label }}
            </p>
            <p class="mt-1 max-w-2xl text-xs text-gray-400">
                Timestamp: {{ $now->format('Y-m-d H:i:s') }}
            </p>
        </div>
    </div>
@endsection
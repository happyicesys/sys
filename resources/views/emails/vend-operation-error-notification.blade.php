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
            <h3 class="text-md font-bold text-gray-900 mt-4">
                2.1 Operation Error / Critical Parts Failure
            </h3>
            <h3 class="text-lg leading-6 font-medium text-red-600 font-bold">
                {{ $title }}
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
                    @if($alertType === \App\Models\VendSmartAlert::TYPE_T1_HIGHER_THAN_T2)
                        <li>Possible component issue:</li>
                        <li>i) Fan not function</li>
                        <li>ii) Temp probe malfunction</li>
                    @elseif($alertType === \App\Models\VendSmartAlert::TYPE_COMP_FAN_OFF)
                        <li>Possible component issue:</li>
                        <li>i) Freezer unit being turned off</li>
                        <li>ii) Comp & or fan, fail to start after defrost or resting</li>
                        <li>iii) Comp is working, but Fan not turning</li>
                    @elseif($alertType === \App\Models\VendSmartAlert::TYPE_TEMPS_ABOVE_0)
                        <li>Possible component issue:</li>
                        <li>i) Freezer unit being turned off</li>
                        <li>ii) Comp & or fan, fail to start after forced defrost</li>
                        <li><br>Possible Operation issue:</li>
                        <li>iii) Freezer door not close tight</li>
                        <li>iv) Open freezer door >15mins</li>
                        <li class="text-xs text-gray-500 italic mt-1">(Alert dismissed once temp below 0°C)</li>
                    @elseif($alertType === \App\Models\VendSmartAlert::TYPE_TEMPS_ABOVE_MINUS_8)
                        <li>Possible Operation issue:</li>
                        <li>i) Freezer door not close tight</li>
                        <li>ii) Open freezer door >15mins</li>
                        <li class="text-xs text-gray-500 italic mt-1">(Alert dismissed once temp below -8°C)</li>
                    @elseif($alertType === \App\Models\VendSmartAlert::TYPE_NOT_REACH_MINUS_18)
                        <li>Possible Operation issue:</li>
                        <li>i) Freezer door not close tight</li>
                        <li>ii) Open freezer door >15mins</li>
                        <li>iii) Many purchases occur</li>
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
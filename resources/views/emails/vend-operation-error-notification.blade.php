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
                (2) Operation Error / Critical Parts Failure
            </h3>
            <h3 class="text-lg leading-6 font-medium text-gray-900 font-bold mt-4">
                {{ $title }}
            </h3>
            <p class="text-sm text-gray-500">
                {{ $label }}
            </p>
            <div class="mt-4 border-t border-gray-200"></div>
            <div class="mt-4">
                <div class="mt-2 text-sm text-gray-700 space-y-1">
                    @if($alertType === \App\Models\VendSmartAlert::TYPE_T1_HIGHER_THAN_T2)
                        <div class="font-bold">Possible component issue:</div>
                        <div>i) Fan not function</div>
                        <div>ii) Temp probe malfunction</div>
                    @elseif($alertType === \App\Models\VendSmartAlert::TYPE_COMP_FAN_OFF)
                        <div class="font-bold">Possible component issue:</div>
                        <div>i) Freezer unit being turned off</div>
                        <div>ii) Comp & or fan, fail to start after defrost or resting</div>
                        <div>iii) Comp is working, but Fan not turning</div>
                    @elseif($alertType === \App\Models\VendSmartAlert::TYPE_TEMPS_ABOVE_0)
                        <div class="font-bold">Possible component issue:</div>
                        <div>i) Freezer unit being turned off</div>
                        <div>ii) Comp & or fan, fail to start after forced defrost</div>
                        <div class="font-bold mt-2">Possible Operation issue:</div>
                        <div>iii) Freezer door not close tight</div>
                        <div>iv) Open freezer door >15mins</div>
                        <div class="text-xs text-gray-500 italic mt-1">(Alert dismissed once temp below 0°C)</div>
                    @elseif($alertType === \App\Models\VendSmartAlert::TYPE_TEMPS_ABOVE_MINUS_8)
                        <div class="font-bold">Possible Operation issue:</div>
                        <div>i) Freezer door not close tight</div>
                        <div>ii) Open freezer door >15mins</div>
                        <div class="text-xs text-gray-500 italic mt-1">(Alert dismissed once temp below -8°C)</div>
                    @elseif($alertType === \App\Models\VendSmartAlert::TYPE_NOT_REACH_MINUS_18)
                        <div class="font-bold">Possible Operation issue:</div>
                        <div>i) Freezer door not close tight</div>
                        <div>ii) Open freezer door >15mins</div>
                        <div>iii) Many purchases occur</div>
                        <div class="text-xs text-gray-500 italic mt-1">(Alert dismissed once temp below -18°C)</div>
                    @else
                        <div>Unknown error</div>
                    @endif
                </div>
            </div>
            <p class="mt-1 max-w-2xl text-xs text-gray-400">
                Timestamp: {{ $now->format('Y-m-d H:i:s') }}
            </p>
        </div>
    </div>
@endsection
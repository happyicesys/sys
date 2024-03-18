@extends('mail')

@section('content')
<!-- This example requires Tailwind CSS v2.0+ -->
<div class="bg-white shadow overflow-hidden sm:rounded-lg">
    <div class="px-4 py-5 sm:px-6">
      <h3 class="text-lg leading-6 font-medium text-gray-900">
        <span>
          #ID: {{$vend->code}}
        </span>
      </h3>
      <h3 class="text-lg leading-6 font-medium text-gray-900">
        @if($vend and $vend->customer)
          <span>
            {{$vend->customer->code}} - {{$vend->customer->name}}
          </span>
        @endif
      </h3>
      <p class="mt-1 max-w-2xl text-sm text-gray-500">Last Detected Time ({{$vend->last_updated_at->format('y-m-d h:ia')}})</p>
    </div>
  </div>
@endsection

@extends('mail')

@section('content')
<!-- This example requires Tailwind CSS v2.0+ -->
<div class="bg-white shadow overflow-hidden sm:rounded-lg">
    <div class="px-4 py-5 sm:px-6">
      <h3 class="text-lg leading-6 font-medium text-gray-900">VM Channel Error Logs</h3>
      <p class="mt-1 max-w-2xl text-sm text-gray-500">Time Range ({{\Carbon\Carbon::parse($now)->subHours($intervalHours)->format('y-m-d h:ia')}} to {{\Carbon\Carbon::parse($now)->format('y-m-d h:ia')}})</p>
    </div>
    <!-- This example requires Tailwind CSS v2.0+ -->
    <div class="px-4 sm:px-6 lg:px-8">
      <div class="mt-8 flex flex-col">
        <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
          <div class="inline-block min-w-full py-2 align-middle">
            <div class="shadow-sm ring-1 ring-black ring-opacity-5">
              <table class="min-w-full border-separate" style="border-spacing: 0">
                <thead class="bg-gray-50">
                  <tr>
                    <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                      #
                    </th>
                    <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                      VM Code
                    </th>
                    <th scope="col" class="sticky top-0 z-10 hidden border-b border-gray-300 bg-gray-50 bg-opacity-75 px-3 py-3.5 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:table-cell">
                      Channel Code
                    </th>
                    <th scope="col" class="sticky top-0 z-10 hidden border-b border-gray-300 bg-gray-50 bg-opacity-75 px-3 py-3.5 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter lg:table-cell">
                      Error Code
                    </th>
                    <th scope="col" class="sticky top-0 z-10 hidden border-b border-gray-300 bg-gray-50 bg-opacity-75 px-3 py-3.5 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter lg:table-cell">
                      Error Desc
                    </th>
                    <th scope="col" class="sticky top-0 z-10 hidden border-b border-gray-300 bg-gray-50 bg-opacity-75 px-3 py-3.5 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter lg:table-cell">
                      Created At
                    </th>
                  </tr>
                </thead>
                @php
                    $counter = 0;
                @endphp
                <tbody class="bg-white">
                  @foreach($vendChannelErrorLogs as $vendChannelErrorLog)
                    @php
                        $counter += 1;
                    @endphp
                    <tr>
                      <td class="whitespace-nowrap border-b border-gray-200 py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 lg:pl-8">
                        {{$counter}}
                      </td>
                      <td class="whitespace-nowrap border-b border-gray-200 py-4 pl-4 pr-3 text-right text-sm font-medium text-gray-900 sm:pl-6 lg:pl-8">
                        {{$vendChannelErrorLog->vendChannel->vend->code}}
                      </td>
                      <td class="whitespace-nowrap border-b border-gray-200 py-4 pl-4 pr-3 text-center text-sm font-medium text-gray-900 sm:pl-6 lg:pl-8">
                        {{$vendChannelErrorLog->vendChannel->code}}
                      </td>
                      <td class="whitespace-nowrap border-b border-gray-200 px-3 py-4 text-sm text-center text-gray-500 hidden lg:table-cell">
                        {{$vendChannelErrorLog->vendChannelError->code }}
                      </td>
                      <td class="whitespace-nowrap border-b border-gray-200 px-3 py-4 text-sm text-gray-500 hidden lg:table-cell">
                        {{$vendChannelErrorLog->vendChannelError->desc }}
                      </td>
                      <td class="whitespace-nowrap border-b border-gray-200 px-3 py-4 text-sm text-gray-500 hidden lg:table-cell">
                        {{$vendChannelErrorLog->created_at->format('ymd h:ia')}}
                      </td>
                    </tr>
                  @endforeach
                  <!-- More people... -->
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
@endsection

@extends('mail')

@section('content')
<div class="bg-white shadow overflow-hidden sm:rounded-lg">
    <div class="px-4 py-5 sm:px-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900">
            Machines Without Transactions
        </h3>
        <p class="mt-1 text-sm text-gray-500">
            Generated at {{ $generatedAt->format('Y-m-d H:i') }}
        </p>
        @if ($operator)
            <p class="mt-1 text-sm text-gray-500">
                Operator: {{ $operator->code }} - {{ $operator->name }}
            </p>
        @endif
    </div>

    <div class="border-t border-gray-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                            Machine
                        </th>
                        <th scope="col" class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                            Customer
                        </th>
                        <th scope="col" class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                            Threshold (hrs)
                        </th>
                        <th scope="col" class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                            Hours Since Last Transaction
                        </th>
                        <th scope="col" class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                            Last Transaction Time
                        </th>
                        <th scope="col" class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                            Link
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($vends as $item)
                        <tr class="align-top">
                            <td class="px-4 py-2 text-sm text-gray-900">
                                <div>
                                    <strong>{{ $item['code'] ?? '-' }}</strong>
                                    @if (!empty($item['vend_prefix_name']))
                                        <span class="text-xs text-gray-500">({{ $item['vend_prefix_name'] }})</span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500">
                                    ID: {{ $item['id'] ?? '-' }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ $item['name'] ?? '' }}
                                </div>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-900">
                                @if (!empty($item['customer']))
                                    {{ $item['customer']['code'] ?? '' }} - {{ $item['customer']['name'] ?? '' }}
                                @else
                                    <span class="text-gray-500">N/A</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-900">
                                {{ $item['threshold_hours'] ?? 'N/A' }}
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-900">
                                @if (array_key_exists('hours_since_last_transaction', $item) && $item['hours_since_last_transaction'] !== null)
                                    {{ number_format($item['hours_since_last_transaction'], 2) }}
                                @else
                                    <span class="text-gray-500">No records</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-900">
                                @if (!empty($item['last_transaction_at']))
                                    {{ \Carbon\Carbon::parse($item['last_transaction_at'])->format('Y-m-d H:i') }}
                                @else
                                    <span class="text-gray-500">N/A</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-sm text-blue-600">
                                @if (!empty($item['code']))
                                    <a href="{{ $baseUrl }}/vends/customers?codes={{ $item['code'] }}" style="color: #1a0dab; text-decoration: underline;">
                                        View
                                    </a>
                                @else
                                    <span class="text-gray-500">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-4 text-center text-sm text-gray-500">
                                No machines met the criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

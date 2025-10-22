@extends('mail')

@section('content')
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width: 720px; margin: 0 auto; font-family: Arial, sans-serif; color: #1a202c;">
    <tr>
        <td style="padding: 24px;">
            <h2 style="margin: 0 0 12px; font-size: 20px; font-weight: 600; color: #0f172a;">
                Machines Without Transactions
            </h2>
            <p style="margin: 0 0 6px; font-size: 14px; color: #475569;">
                Generated at {{ $generatedAt->format('Y-m-d H:i') }}
            </p>
            @if ($operator)
                <p style="margin: 0 0 16px; font-size: 14px; color: #475569;">
                    Operator: <strong>{{ $operator->code }}</strong> - {{ $operator->name }}
                </p>
            @endif

            <table cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; width: 100%; border: 1px solid #e2e8f0; font-size: 14px;">
                <thead>
                    <tr style="background-color: #f8fafc;">
                        <th align="left" style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #475569;">
                            Machine
                        </th>
                        <th align="left" style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #475569;">
                            Customer
                        </th>
                        <th align="center" style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #475569;">
                            Threshold (hrs)
                        </th>
                        <th align="center" style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #475569;">
                            Hours Since Last Transaction
                        </th>
                        <th align="center" style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #475569;">
                            Last Transaction Time
                        </th>
                        <th align="center" style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #475569;">
                            Link
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vends as $index => $item)
                        <tr style="{{ $index % 2 === 0 ? 'background-color: #ffffff;' : 'background-color: #f8fafc;' }}">
                            <td valign="top" style="padding: 12px; border-bottom: 1px solid #e2e8f0; color: #0f172a;">
                                <div style="font-weight: 600;">
                                    {{ $item['code'] ?? '-' }}
                                    @if (!empty($item['vend_prefix_name']))
                                        <span style="font-size: 12px; color: #64748b;">({{ $item['vend_prefix_name'] }})</span>
                                    @endif
                                </div>
                            </td>
                            <td valign="top" style="padding: 12px; border-bottom: 1px solid #e2e8f0; color: #0f172a;">
                                @if (!empty($item['customer']))
                                    <div style="font-weight: 600;">
                                        {{ $item['customer']['code'] ?? '' }}
                                    </div>
                                    <div style="font-size: 12px; color: #334155; margin-top: 4px;">
                                        {{ $item['customer']['name'] ?? '' }}
                                    </div>
                                @else
                                    <span style="color: #94a3b8;">N/A</span>
                                @endif
                            </td>
                            <td align="center" valign="top" style="padding: 12px; border-bottom: 1px solid #e2e8f0; color: #0f172a;">
                                {{ $item['threshold_hours'] ?? '—' }}
                            </td>
                            <td align="center" valign="top" style="padding: 12px; border-bottom: 1px solid #e2e8f0; color: #0f172a;">
                                @if (array_key_exists('hours_since_last_transaction', $item) && $item['hours_since_last_transaction'] !== null)
                                    {{ number_format($item['hours_since_last_transaction'], 2) }}
                                @else
                                    <span style="color: #94a3b8;">No records</span>
                                @endif
                            </td>
                            <td align="center" valign="top" style="padding: 12px; border-bottom: 1px solid #e2e8f0; color: #0f172a;">
                                @if (!empty($item['last_transaction_at']))
                                    {{ \Carbon\Carbon::parse($item['last_transaction_at'])->format('Y-m-d H:i') }}
                                @else
                                    <span style="color: #94a3b8;">N/A</span>
                                @endif
                            </td>
                            <td align="center" valign="top" style="padding: 12px; border-bottom: 1px solid #e2e8f0;">
                                @if (!empty($item['code']))
                                    <a href="{{ $baseUrl }}/vends/customers?codes={{ $item['code'] }}" style="color: #1d4ed8; text-decoration: none; font-weight: 600;">
                                        View
                                    </a>
                                @else
                                    <span style="color: #94a3b8;">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" align="center" style="padding: 18px; border-bottom: 1px solid #e2e8f0; color: #64748b;">
                                No machines met the criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </td>
    </tr>
</table>
@endsection

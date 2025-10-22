@extends('mail')

@section('content')
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width: 720px; margin: 0 auto; font-family: Arial, sans-serif; color: #1a202c;">
    <tr>
        <td style="padding: 24px;">
            <h2 style="margin: 0 0 12px; font-size: 20px; font-weight: 600; color: #0f172a;">
                VM Channel Error Logs
            </h2>
            <p style="margin: 0 0 16px; font-size: 14px; color: #475569;">
                Time Range ({{ $now->copy()->subHours($intervalHours)->format('Y-m-d h:ia') }} to {{ $now->format('Y-m-d h:ia') }})
            </p>

            <table cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; border: 1px solid #e2e8f0; font-size: 14px;">
                <thead>
                    <tr style="background-color: #f8fafc;">
                        <th align="center" style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #475569; width: 50px;">
                            #
                        </th>
                        <th align="left" style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #475569;">
                            VM Code
                        </th>
                        <th align="center" style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #475569;">
                            Channel Code
                        </th>
                        <th align="center" style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #475569;">
                            Error Code
                        </th>
                        <th align="left" style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #475569;">
                            Customer
                        </th>
                        <th align="center" style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #475569;">
                            Logged At
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vendChannelErrorLogs as $log)
                        @php
                            $vendChannel = $log->vendChannel;
                            $vend = $vendChannel?->vend;
                            $customer = $vend?->customer;
                        @endphp
                        <tr style="{{ $loop->odd ? 'background-color: #ffffff;' : 'background-color: #f8fafc;' }}">
                            <td align="center" valign="top" style="padding: 12px; border-bottom: 1px solid #e2e8f0; font-weight: 600; color: #0f172a;">
                                {{ $loop->iteration }}
                            </td>
                            <td valign="top" style="padding: 12px; border-bottom: 1px solid #e2e8f0; color: #0f172a;">
                                <div style="font-weight: 600;">
                                    {{ $vend->code ?? '—' }}
                                </div>
                                @if (!empty($vend?->name))
                                    <div style="margin-top: 4px; font-size: 12px; color: #334155;">
                                        {{ $vend->name }}
                                    </div>
                                @endif
                            </td>
                            <td align="center" valign="top" style="padding: 12px; border-bottom: 1px solid #e2e8f0; color: #0f172a;">
                                {{ $vendChannel->code ?? '—' }}
                            </td>
                            <td align="center" valign="top" style="padding: 12px; border-bottom: 1px solid #e2e8f0; color: #0f172a;">
                                {{ $log->vendChannelError->code ?? '—' }}
                            </td>
                            <td valign="top" style="padding: 12px; border-bottom: 1px solid #e2e8f0; color: #0f172a;">
                                @if ($customer)
                                    <div style="font-weight: 600;">
                                        {{ $customer->code ?? '' }}
                                    </div>
                                    <div style="margin-top: 4px; font-size: 12px; color: #334155;">
                                        {{ $customer->name ?? '' }}
                                    </div>
                                @else
                                    <span style="color: #94a3b8;">N/A</span>
                                @endif
                            </td>
                            <td align="center" valign="top" style="padding: 12px; border-bottom: 1px solid #e2e8f0; color: #0f172a;">
                                {{ optional($log->created_at)->format('Y-m-d h:ia') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" align="center" style="padding: 18px; border-bottom: 1px solid #e2e8f0; color: #64748b;">
                                No channel errors detected for this window.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </td>
    </tr>
</table>
@endsection

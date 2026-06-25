<?php
    // Money helper — cents → "S$1,234.56" using the operator's currency.
    $money = function ($cents) use ($symbol, $divisor) {
        return $symbol . number_format(abs((int) $cents) / $divisor, 2);
    };
    $outstanding = (int) $ledger['outstanding_cents'];
    $isCredit = $outstanding < 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Statement of Account — {{ $ledger['site_label'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #1f2937; margin: 0; padding: 32px; font-size: 13px; line-height: 1.45;
        }
        .toolbar {
            position: sticky; top: 0; display: flex; gap: 8px; justify-content: flex-end;
            margin-bottom: 16px;
        }
        .toolbar button {
            font-size: 12px; padding: 8px 14px; border-radius: 8px; border: 1px solid #d1d5db;
            background: #4f46e5; color: #fff; cursor: pointer;
        }
        .toolbar button.secondary { background: #fff; color: #374151; }
        @media print { .toolbar { display: none; } body { padding: 0; } }

        .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111827; padding-bottom: 14px; margin-bottom: 20px; }
        .company { font-size: 18px; font-weight: 700; }
        .doc-title { font-size: 13px; letter-spacing: .12em; text-transform: uppercase; color: #6b7280; margin-top: 2px; }
        .meta { text-align: right; font-size: 12px; color: #4b5563; }
        .meta .site { font-weight: 600; color: #111827; font-size: 13px; }

        .summary { display: flex; justify-content: space-between; align-items: center;
            border: 1px solid {{ $isCredit ? '#bae6fd' : ($outstanding > 0 ? '#fecdd3' : '#bbf7d0') }};
            background: {{ $isCredit ? '#f0f9ff' : ($outstanding > 0 ? '#fff1f2' : '#f0fdf4') }};
            border-radius: 12px; padding: 16px 20px; margin-bottom: 22px; }
        .summary .label { font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: #6b7280; }
        .summary .amount { font-size: 26px; font-weight: 700; margin-top: 2px;
            color: {{ $isCredit ? '#0284c7' : ($outstanding > 0 ? '#e11d48' : '#16a34a') }}; }
        .summary .status { font-size: 12px; font-weight: 600;
            color: {{ $isCredit ? '#0369a1' : ($outstanding > 0 ? '#be123c' : '#15803d') }}; }
        .summary .since { font-size: 11px; color: #9ca3af; margin-top: 4px; }

        table { width: 100%; border-collapse: collapse; }
        thead th { text-align: left; font-size: 10px; letter-spacing: .06em; text-transform: uppercase;
            color: #6b7280; border-bottom: 2px solid #e5e7eb; padding: 8px 10px; }
        tbody td { padding: 9px 10px; border-bottom: 1px solid #f0f1f3; vertical-align: top; }
        th.num, td.num { text-align: right; white-space: nowrap; }
        .ref { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; color: #9ca3af; }
        .type { display: inline-block; font-size: 10px; font-weight: 600; padding: 1px 7px; border-radius: 999px; background: #eef2ff; color: #4338ca; }
        .desc { font-weight: 600; }
        .rmk { color: #9ca3af; font-size: 11px; margin-top: 2px; }
        .credit-amt { color: #16a34a; }
        .muted { color: #d1d5db; }
        .cr { color: #0284c7; font-size: 10px; font-weight: 600; }
        tfoot td { padding: 12px 10px; border-top: 2px solid #d1d5db; font-weight: 700; font-size: 14px; }
        tfoot .total { color: {{ $isCredit ? '#0284c7' : ($outstanding > 0 ? '#e11d48' : '#16a34a') }}; }
        .legend { margin-top: 18px; font-size: 11px; color: #9ca3af; display: flex; justify-content: space-between; }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">Print / Save as PDF</button>
        <button class="secondary" onclick="window.close()">Close</button>
    </div>

    <div class="head">
        <div>
            <div class="company">{{ $company }}</div>
            <div class="doc-title">Statement of Account — Location Fees</div>
        </div>
        <div class="meta">
            <div class="site">{{ $ledger['site_label'] }}</div>
            <div>Generated {{ $generatedAt->format('d M Y, h:i A') }}</div>
            @if ($ledger['since_date'])
                <div>Tracked since {{ \Carbon\Carbon::parse($ledger['since_date'])->format('d M Y') }}</div>
            @endif
        </div>
    </div>

    <div class="summary">
        <div>
            <div class="label">{{ $isCredit ? 'Credit Balance' : 'Current Outstanding' }}</div>
            <div class="amount">{{ $money($outstanding) }}{!! $isCredit ? ' <span style="font-size:14px;">CR</span>' : '' !!}</div>
        </div>
        <div style="text-align:right;">
            <div class="status">
                {{ $isCredit ? 'In credit · overpaid' : ($outstanding > 0 ? 'We owe the site owner' : 'Fully settled') }}
            </div>
            @if ($isCredit)
                <div class="since">Credit carries forward and offsets upcoming location fees.</div>
            @endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Ref</th>
                <th>Date</th>
                <th>Description</th>
                <th class="num">Debit</th>
                <th class="num">Credit</th>
                <th class="num">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($ledger['rows'] as $r)
                <tr>
                    <td class="ref">{{ $r['reference_no'] ?: '—' }}</td>
                    <td style="white-space:nowrap;">{{ $r['entry_date'] ? \Carbon\Carbon::parse($r['entry_date'])->format('d M Y') : '—' }}</td>
                    <td>
                        <span class="type">{{ $typeLabel($r['entry_type']) }}</span>
                        <span class="desc">{{ $r['item'] ?: $typeLabel($r['entry_type']) }}</span>
                        @if (!empty($r['remarks']))
                            <div class="rmk">{{ $r['remarks'] }}</div>
                        @endif
                    </td>
                    <td class="num">{{ $r['debit_cents'] ? $money($r['debit_cents']) : '—' }}</td>
                    <td class="num credit-amt">{{ $r['credit_cents'] ? '−' . $money($r['credit_cents']) : '' }}<span class="muted">{{ $r['credit_cents'] ? '' : '—' }}</span></td>
                    <td class="num">{{ $money($r['balance_cents']) }}{!! $r['balance_cents'] < 0 ? ' <span class="cr">CR</span>' : '' !!}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center; color:#9ca3af; padding:24px;">No settlement records yet for this site.</td></tr>
            @endforelse
        </tbody>
        @if (count($ledger['rows']))
            <tfoot>
                <tr>
                    <td colspan="5">{{ $isCredit ? 'Credit Balance' : 'Outstanding Balance' }}</td>
                    <td class="num total">{{ $money($outstanding) }}{!! $isCredit ? ' <span style="font-size:11px;">CR</span>' : '' !!}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="legend">
        <span>Debit increases what we owe the site owner; Credit (a verified payment or waiver) reduces it.</span>
        <span>{{ $company }}</span>
    </div>

    <script>
        // Auto-open the print dialog once the page has rendered.
        window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 350); });
    </script>
</body>
</html>

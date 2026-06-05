@php
    $cur = $currency ?? ['symbol' => '', 'exponent' => 2, 'hidden' => false];

    // Mirror the page's cell formatting (text / money(cents) / percent / int).
    $fmt = function ($v, $format) use ($cur) {
        if ($v === null || $v === '') {
            return '';
        }
        if ($format === 'text') {
            return $v;
        }
        if ($format === 'money') {
            $dec = $cur['hidden'] ? 0 : $cur['exponent'];
            return $cur['symbol'] . number_format($v / pow(10, $cur['exponent']), $dec);
        }
        if ($format === 'percent') {
            return number_format($v, 2) . '%';
        }
        return number_format($v); // int
    };

    // Same green/red rule as the page: compare to the chronologically previous
    // (older) period to the right.
    $bg = function ($curv, $prevv, $format) {
        if ($format === 'text' || !is_numeric($curv) || !is_numeric($prevv)) {
            return '';
        }
        if ($curv > $prevv) return 'background-color:#bbf7d0;';
        if ($curv < $prevv) return 'background-color:#fecaca;';
        return '';
    };

    $dayKeys = array_map(fn ($c) => $c['key'], $dayColumns);
    $monthKeys = array_map(fn ($c) => $c['key'], $monthColumns);
    $totalCols = 3 + count($dayColumns) + count($monthColumns);
@endphp

<table border="1">
    {{-- ---- header / context ---- --}}
    <tr><td colspan="{{ $totalCols }}"><strong>Ops Performance — as of {{ $anchorDate }}</strong></td></tr>
    <tr><td colspan="{{ $totalCols }}">Generated {{ $generatedAt }}@if($component) · Component snapshot {{ $component['snapshotDate'] }}@endif</td></tr>
    <tr><td colspan="{{ $totalCols }}"></td></tr>

    {{-- ===================== KEY KPI ===================== --}}
    <tr>
        <th></th>
        <th colspan="{{ 2 + count($dayColumns) }}">Daily, Count of Machine (% of total machine)</th>
        <th colspan="{{ count($monthColumns) }}">Monthly, Count of Machine (% of total machine)</th>
    </tr>
    <tr>
        <th>Key KPI</th>
        <th>Avg L7d</th>
        <th>Avg L30d</th>
        @foreach($dayColumns as $c)
            <th>{{ $c['label'] }} {{ substr(str_replace('-', '', $c['date']), 2) }}</th>
        @endforeach
        @foreach($monthColumns as $c)
            <th>{{ $c['label'] }}</th>
        @endforeach
    </tr>

    @foreach($kpis as $row)
        <tr>
            <td>{{ $row['label'] }}</td>
            <td style="{{ $bg($row['daily']['avg_l7d'] ?? null, $row['daily']['avg_l30d'] ?? null, $row['format']) }}">{{ $fmt($row['daily']['avg_l7d'] ?? null, $row['format']) }}</td>
            <td>{{ $fmt($row['daily']['avg_l30d'] ?? null, $row['format']) }}</td>
            @foreach($dayColumns as $i => $c)
                @php
                    $curv = $row['daily'][$c['key']] ?? null;
                    $pk = $dayKeys[$i + 1] ?? null;
                    $prevv = $pk ? ($row['daily'][$pk] ?? null) : null;
                @endphp
                <td style="{{ $bg($curv, $prevv, $row['format']) }}">{{ $fmt($curv, $row['format']) }}</td>
            @endforeach
            @foreach($monthColumns as $i => $c)
                @php
                    $curv = $row['monthly'][$c['key']] ?? null;
                    $pk = $monthKeys[$i + 1] ?? null;
                    $prevv = $pk ? ($row['monthly'][$pk] ?? null) : null;
                @endphp
                <td style="{{ $bg($curv, $prevv, $row['format']) }}">{{ $fmt($curv, $row['format']) }}</td>
            @endforeach
        </tr>
    @endforeach

    {{-- spacer --}}
    <tr><td colspan="{{ $totalCols }}"></td></tr>

    {{-- ============ MACHINES' STATUS & COMPONENT ============ --}}
    @if($component)
        <tr>
            <th>Machines' Status &amp; Component</th>
            <th>Item</th>
            <th>Count</th>
            <th>% of set</th>
        </tr>
        @php $ctotal = $component['total'] ?? 0; @endphp
        @foreach($component['groups'] as $g)
            @if(!empty($g['lead']))
                <tr>
                    <td>{{ $g['label'] }}</td>
                    <td>{{ $g['lead'] }}</td>
                    <td>{{ number_format($g['leadValue']) }}</td>
                    <td></td>
                </tr>
            @endif
            @foreach($g['rows'] as $r)
                <tr>
                    <td>{{ $g['label'] }}</td>
                    <td>{{ $r['label'] }}</td>
                    <td>{{ number_format($r['count']) }}</td>
                    <td>{{ $ctotal > 0 ? round($r['count'] / $ctotal * 100) . '%' : '' }}</td>
                </tr>
            @endforeach
        @endforeach
    @endif
</table>

@php
    /** @var array $receipt  App\Services\SmartFreezer\FreezerReceipt::state() */
    $r = $receipt;
    $money = fn (?int $cents) => $cents === null ? '' : ($cents < 0 ? '-' : '').'$'.number_format(abs($cents) / 100, 2);
    $company = $r['company'];
    $isTaxInvoice = ! empty($company['gst_reg_no']);
    $live = in_array($r['status'], ['calculating', 'review'], true);
    // The freezer's own polar bear heads the page, one pose per state (art from the freezer APK).
    [$bear, $heroTitle, $heroLine] = match ($r['status']) {
        'calculating' => ['bear-peek', 'Just a moment…', 'Checking what you took.'],
        'review' => ['bear-happy', 'Thanks for shopping!', 'Our team is checking your purchase.'],
        'nothing' => ['bear-happy', 'See you next time!', 'Nothing was charged.'],
        'not_completed' => ['bear-happy', 'See you next time!', 'This purchase did not go through.'],
        default => ['bear-cheer', 'Thank you!', 'Enjoy your treat.'],
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $isTaxInvoice ? 'Tax Invoice' : 'Receipt' }} · {{ $company['name'] ?? 'Happy Ice' }}</title>
    <style>
        :root {
            --ink: #143a4d; --muted: #5b7180; --line: #e3ebef; --bg: #f3f8fa; --card: #ffffff;
            --brand: #0f8a8a; --brand-soft: #e3f4f2; --amber: #b46a00; --amber-soft: #fff4dc;
            --grey-soft: #eef2f4; --red: #b3261e;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--ink); font: 15px/1.45 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .wrap { max-width: 520px; margin: 0 auto; padding: 16px 16px 40px; }
        .card { background: var(--card); border-radius: 18px; box-shadow: 0 1px 3px rgba(20,58,77,.08); padding: 20px; margin-bottom: 14px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .muted { color: var(--muted); }
        .small { font-size: 13px; }
        .company { font-size: 13px; color: var(--muted); }
        .company strong { color: var(--ink); font-size: 14px; }
        .row { display: flex; justify-content: space-between; gap: 12px; }
        .meta { display: grid; grid-template-columns: auto 1fr; gap: 4px 14px; font-size: 14px; margin-top: 12px; }
        .meta dt { color: var(--muted); }
        .meta dd { margin: 0; text-align: right; word-break: break-all; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { text-align: left; color: var(--muted); font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; padding: 0 0 8px; border-bottom: 1px solid var(--line); }
        td { padding: 10px 0; border-bottom: 1px solid var(--line); vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .badge { display: inline-block; margin-top: 4px; padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .badge.extra { background: var(--amber-soft); color: var(--amber); }
        .badge.nottaken { background: var(--grey-soft); color: var(--muted); }
        .struck { color: var(--muted); }
        .totals { margin-top: 12px; font-size: 14px; }
        .totals .row { padding: 3px 0; }
        .grand { font-size: 20px; font-weight: 700; padding-top: 8px !important; border-top: 2px solid var(--ink); margin-top: 6px; }
        .status { text-align: center; padding: 28px 20px; }
        .status h2 { margin: 14px 0 6px; font-size: 19px; }
        .bar { height: 8px; border-radius: 99px; background: var(--brand-soft); overflow: hidden; margin: 18px auto 0; max-width: 280px; }
        .bar span { display: block; height: 100%; width: 40%; border-radius: 99px; background: var(--brand); animation: slide 1.4s ease-in-out infinite; }
        @keyframes slide { 0% { transform: translateX(-100%); } 100% { transform: translateX(260%); } }
        .icon { width: 64px; height: 64px; border-radius: 50%; margin: 0 auto; display: grid; place-items: center; font-size: 30px; }
        .icon.spin { border: 5px solid var(--brand-soft); border-top-color: var(--brand); animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .icon.ok { background: var(--brand-soft); color: var(--brand); }
        .icon.wait { background: var(--amber-soft); color: var(--amber); }
        .icon.none { background: var(--grey-soft); color: var(--muted); }
        .note { background: var(--brand-soft); border-radius: 12px; padding: 12px 14px; font-size: 13px; color: var(--ink); }
        .note.amber { background: var(--amber-soft); }
        .foot { text-align: center; font-size: 12px; color: var(--muted); margin-top: 18px; }
        .foot a { color: var(--brand); }
        .live { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--muted); }
        .live i { width: 8px; height: 8px; border-radius: 50%; background: var(--brand); animation: pulse 1.2s ease-in-out infinite; }
        @keyframes pulse { 50% { opacity: .25; } }
        .hero { position: relative; overflow: hidden; display: flex; align-items: flex-end; justify-content: space-between; gap: 8px; min-height: 150px; padding: 18px 12px 0 20px; margin-bottom: 14px; border-radius: 18px; background: radial-gradient(circle at 18% 22%, rgba(255,255,255,.7) 0 3px, transparent 4px), radial-gradient(circle at 62% 14%, rgba(255,255,255,.6) 0 2px, transparent 3px), radial-gradient(circle at 44% 70%, rgba(255,255,255,.5) 0 2px, transparent 3px), linear-gradient(160deg, #e6f6fc 0%, #bfe5f4 100%); }
        .hero::after { content: ""; position: absolute; left: -10%; right: -10%; bottom: -34px; height: 60px; border-radius: 50%; background: rgba(255,255,255,.75); }
        .hero-text { position: relative; z-index: 1; align-self: center; padding-bottom: 18px; }
        .hero-text .wordmark { display: block; height: 22px; width: auto; margin-bottom: 10px; }
        .hero-title { font-size: 22px; font-weight: 800; line-height: 1.2; }
        .hero-line { font-size: 14px; color: var(--muted); margin-top: 4px; }
        .hero img.bear { position: relative; z-index: 1; height: 132px; width: auto; flex: none; }
        .hero.peek img.bear { height: 100px; }
        @media (prefers-reduced-motion: no-preference) { .hero.peek img.bear { animation: bob 2.4s ease-in-out infinite; } }
        @keyframes bob { 50% { transform: translateY(6px); } }
        @media (prefers-reduced-motion: reduce) { .bar span, .icon.spin, .live i { animation: none; } }
    </style>
</head>
<body>
<div class="wrap">

    <div class="hero {{ $bear === 'bear-peek' ? 'peek' : '' }}">
        <div class="hero-text">
            <img class="wordmark" src="{{ asset('img/freezer-receipt/happyice-wordmark.webp') }}" alt="Happy Ice">
            <div class="hero-title">{{ $heroTitle }}</div>
            <div class="hero-line">{{ $heroLine }}</div>
        </div>
        <img class="bear" src="{{ asset('img/freezer-receipt/'.$bear.'.webp') }}" alt="">
    </div>

    <div class="card">
        <div class="row" style="align-items:flex-start">
            <div>
                <h1>{{ $isTaxInvoice ? 'Tax Invoice' : 'Receipt' }}</h1>
                @if ($live)
                    <span class="live"><i></i> Updating live</span>
                @endif
            </div>
        </div>
        <div class="company" style="margin-top:10px">
            @if ($company['name'])<strong>{{ $company['name'] }}</strong><br>@endif
            @if ($company['address']){{ $company['address'] }}<br>@endif
            @if ($company['uen'])UEN {{ $company['uen'] }}@endif
            @if ($company['gst_reg_no']) · GST Reg No. {{ $company['gst_reg_no'] }}@endif
        </div>
        <dl class="meta">
            <dt>Invoice no.</dt><dd>{{ $r['invoice_no'] }}</dd>
            @if ($r['issued_at'])<dt>Date</dt><dd>{{ $r['issued_at'] }}</dd>@endif
            <dt>Machine</dt><dd>{{ $r['machine'] }}</dd>
            @if ($r['payment'])<dt>Paid by</dt><dd>{{ $r['payment'] }}</dd>@endif
        </dl>
    </div>

    @if ($r['status'] === 'calculating')
        <div class="card status">
            <div class="icon spin"></div>
            <h2>Checking what you took…</h2>
            <p class="muted">Our camera system is confirming your items. Your final invoice will appear here in about 2–3 minutes. You can keep this page open or come back to it any time.</p>
            @if ($r['hold_cents'])
                <p class="small">Card held: <strong>{{ $money($r['hold_cents']) }}</strong> (not charged yet)</p>
            @endif
            <div class="bar"><span></span></div>
        </div>
    @elseif ($r['status'] === 'review')
        <div class="card status">
            <div class="icon wait">⏳</div>
            <h2>Our team is checking your purchase</h2>
            <p class="muted">The automatic check could not confirm your items, so a staff member will review the recording. Your final invoice will appear here once it is done — usually within one working day. Save this page to check back.</p>
            @if ($r['hold_cents'])
                <p class="small">Card held: <strong>{{ $money($r['hold_cents']) }}</strong> (not charged yet)</p>
            @endif
        </div>
    @elseif ($r['status'] === 'nothing')
        <div class="card status">
            <div class="icon none">✓</div>
            <h2>You didn't take anything</h2>
            <p class="muted">We are not charging you anything.@if ($r['hold_cents']) The {{ $money($r['hold_cents']) }} hold on your card has been released.@endif</p>
            @if ($r['hold_cents'])
                <div class="note small" style="text-align:left">Your bank app may still show the {{ $money($r['hold_cents']) }} hold as pending for a few days. It will drop off without being charged.</div>
            @endif
        </div>
    @elseif ($r['status'] === 'not_completed')
        <div class="card status">
            <div class="icon none">–</div>
            <h2>Payment not completed</h2>
            <p class="muted">This purchase did not go through, so nothing was charged.</p>
        </div>
    @else
        <div class="card">
            @if ($r['refunded'])
                <div class="note amber" style="margin-bottom:12px">This purchase has been refunded.</div>
            @endif
            <table>
                <thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Amount</th></tr></thead>
                <tbody>
                @foreach ($r['lines'] as $line)
                    @php $notTaken = $line['badge'] === 'Not taken – not charged'; @endphp
                    <tr>
                        <td class="{{ $notTaken ? 'struck' : '' }}">
                            {{ $line['name'] }}
                            @if ($line['unit_cents'] && ! $notTaken)<div class="small muted">{{ $money($line['unit_cents']) }} each</div>@endif
                            @if ($line['badge'])<div><span class="badge {{ $notTaken ? 'nottaken' : 'extra' }}">{{ $line['badge'] }}</span></div>@endif
                        </td>
                        <td class="num {{ $notTaken ? 'struck' : '' }}">{{ $line['qty'] }}</td>
                        <td class="num {{ $notTaken ? 'struck' : '' }}">{{ $money($line['amount_cents']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="totals">
                @if ($r['gst_rate'] > 0)
                    <div class="row"><span class="muted">Subtotal (excl. GST)</span><span>{{ $money($r['subtotal_cents']) }}</span></div>
                    <div class="row"><span class="muted">GST {{ rtrim(rtrim(number_format($r['gst_rate'], 2), '0'), '.') }}%</span><span>{{ $money($r['gst_cents']) }}</span></div>
                @endif
                <div class="row grand"><span>Total charged</span><span>{{ $money($r['total_cents']) }}</span></div>
            </div>
        </div>

        @if ($r['is_card_hold'] && $r['hold_cents'] !== null && $r['hold_cents'] !== $r['total_cents'])
            <div class="note">
                @if ($r['total_cents'] > $r['hold_cents'])
                    You took more than you paid for at the screen, so the final charge is {{ $money($r['total_cents']) }}.
                @else
                    You took less than you paid for at the screen, so you are only charged {{ $money($r['total_cents']) }}.
                @endif
                Your bank app may first show {{ $money($r['hold_cents']) }} as pending; the final {{ $money($r['total_cents']) }} replaces it within a few days.
            </div>
        @endif
    @endif

    <div class="foot">
        Questions about this purchase?
        @if ($company['phone'])Call {{ $company['phone'] }}@endif
        @if ($company['phone'] && $company['email']) or @endif
        @if ($company['email'])email <a href="mailto:{{ $company['email'] }}?subject={{ rawurlencode('Purchase '.$r['invoice_no']) }}">{{ $company['email'] }}</a>@endif
        <br>Quote invoice no. {{ $r['invoice_no'] }}
    </div>
</div>

@if (request()->boolean('auto'))
<script>
// The page's own reload marker. Gone from the address bar, so a shared link or a refresh is a real open.
(function () {
    var clean = new URL(window.location.href);
    clean.searchParams.delete('auto');
    window.history.replaceState(null, '', clean.toString());
})();
</script>
@endif

@if ($live)
<script>
(function () {
    var url = @json($stateUrl);
    var version = @json(md5(json_encode($receipt)));
    var every = @json($r['status'] === 'review' ? 30000 : 4000);
    function tick() {
        fetch(url, { cache: 'no-store' })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (s) {
                if (!s) return;
                if (s.version !== version) {
                    var next = new URL(window.location.href);
                    next.searchParams.set('auto', '1');
                    window.location.replace(next.toString());
                }
            })
            .catch(function () {})
            .finally(function () { setTimeout(tick, every); });
    }
    setTimeout(tick, every);
})();
</script>
@endif
</body>
</html>

{{-- Standalone statement page for the headless-Chromium renderer (no Vite). Tokens inlined like receipts/pdf.blade.php. Frozen snapshot only. --}}
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
<meta charset="utf-8">
<title>{{ $number }}</title>
<style>
    @font-face { font-family: "Noto Sans Arabic"; src: url("{{ $font }}") format("truetype"); font-weight: 100 900; font-style: normal; }
    @page { size: A4; margin: 0; }
    :root {
        --brand: oklch(0.484010 0.082313 193.660745);
        --brand-dark: oklch(0.32 0.048 185);
        --brand-soft: oklch(0.97 0.0093 185);
        --accent: oklch(0.61 0.065 40);
        --body: oklch(0.24 0.013 250);
        --muted: oklch(0.41 0.02 250);
        --line: oklch(0.86 0.01 250);
    }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; font-family: "Noto Sans Arabic", sans-serif; color: var(--body); background: #fff; }
    .page { padding: 16mm; }
    .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 8mm; border-block-end: 1.2mm solid var(--accent); padding-block-end: 6mm; }
    .brand { font-size: 20pt; font-weight: 800; color: var(--brand); }
    h1 { margin: 0; font-size: 22pt; font-weight: 800; color: var(--brand-dark); }
    .meta { font-size: 10pt; color: var(--muted); }
    .meta div { margin-block-end: 1mm; }
    .num { direction: ltr; unicode-bidi: isolate; display: inline-block; font-weight: 700; color: var(--body); }
    .label { font-size: 9pt; color: var(--muted); }
    .party { margin-block: 8mm; font-size: 11pt; }
    table { width: 100%; border-collapse: collapse; font-size: 10pt; }
    th { text-align: start; background: var(--brand-soft); color: var(--brand-dark); padding: 2.5mm; font-weight: 700; }
    td { padding: 2.5mm; border-block-end: 0.3mm solid var(--line); }
    .amount { text-align: end; direction: ltr; unicode-bidi: isolate; white-space: nowrap; }
    .totals { margin-block-start: 6mm; margin-inline-start: auto; width: 95mm; font-size: 11pt; }
    .totals div { display: flex; justify-content: space-between; padding-block: 1.5mm; }
    .totals .grand { border-block-start: 0.6mm solid var(--brand); font-size: 13pt; font-weight: 800; color: var(--brand-dark); }
    .note { margin-block-start: 4mm; font-size: 9pt; color: var(--muted); }
    .foot { margin-block-start: 14mm; font-size: 9pt; color: var(--muted); border-block-start: 0.3mm solid var(--line); padding-block-start: 3mm; }
</style>
</head>
<body>
<div class="page">
    <div class="head">
        <div class="brand">{{ __('app.name', [], $locale) }}</div>
        <div>
            <h1>{{ __('settlements.pdf.heading', [], $locale) }}</h1>
            <div class="meta">
                <div>{{ __('settlements.pdf.number', [], $locale) }}: <span class="num">{{ $number }}</span></div>
                <div>{{ __('settlements.pdf.period', [], $locale) }}: {{ $period }}</div>
                <div>{{ __('settlements.pdf.generated', [], $locale) }}: {{ $generatedOn }}</div>
            </div>
        </div>
    </div>

    <div class="party"><div class="label">{{ __('settlements.pdf.instructor', [], $locale) }}</div><div dir="auto">{{ $instructor }}</div></div>

    <table>
        <thead>
            <tr>
                <th>{{ __('settlements.pdf.date', [], $locale) }}</th>
                <th>{{ __('settlements.pdf.course', [], $locale) }}</th>
                <th>{{ __('settlements.pdf.type', [], $locale) }}</th>
                <th>{{ __('settlements.pdf.order', [], $locale) }}</th>
                <th class="amount">{{ __('settlements.pdf.amount', [], $locale) }}</th>
            </tr>
        </thead>
        <tbody>
        @foreach($lines as $line)
            <tr>
                <td><span class="num">{{ $line['date'] }}</span></td>
                <td dir="auto">{{ $line['course'] }}</td>
                <td>{{ $line['type'] }}</td>
                <td><span class="num">{{ $line['order'] }}</span></td>
                <td class="amount">{{ $line['amount'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div><span>{{ __('settlements.pdf.earnings', [], $locale) }}</span><span class="amount">{{ $earnings }}</span></div>
        <div><span>{{ __('settlements.pdf.reversals', [], $locale) }}</span><span class="amount">{{ $reversals }}</span></div>
        <div><span>{{ __('settlements.pdf.adjustments', [], $locale) }}</span><span class="amount">{{ $adjustments }}</span></div>
        <div><span>{{ __('settlements.pdf.net', [], $locale) }}</span><span class="amount">{{ $net }}</span></div>
        <div><span>{{ __('settlements.pdf.settled', [], $locale) }}</span><span class="amount">{{ $settled }}</span></div>
        <div><span>{{ __('settlements.pdf.forward', [], $locale) }}</span><span class="amount">{{ $forward }}</span></div>
        <div class="grand"><span>{{ __('settlements.pdf.closing', [], $locale) }}</span><span class="amount">{{ $closing }}</span></div>
    </div>
    @if($closingNegative)<div class="note">{{ __('settlements.pdf.negative_note', [], $locale) }}</div>@endif

    <div class="foot">{{ __('settlements.pdf.footer', [], $locale) }}</div>
</div>
</body>
</html>

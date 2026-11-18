{{-- Standalone receipt page for the headless-Chromium renderer (no Vite). Tokens are inlined from resources/css/tokens.css; the wordmark is text until official logo assets exist (O-027). Seller tax data is printed only when known (O-023). --}}
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
<meta charset="utf-8">
<title>{{ $number }}</title>
<style>
    @include('partials.brand-pdf-css')
    @page { size: A4; margin: 0; }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; font-family: var(--font); color: var(--body); background: var(--paper); }
    .page { padding: 16mm; }
    .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 8mm; border-block-end: 1.2mm solid var(--accent); padding-block-end: 6mm; }
    .brand { font-size: 20pt; font-weight: 800; color: var(--brand); }
    h1 { margin: 0; font-size: 22pt; font-weight: 800; color: var(--brand-dark); }
    .meta { font-size: 10pt; color: var(--muted); text-align: start; }
    .meta div { margin-block-end: 1mm; }
    .num { direction: ltr; unicode-bidi: isolate; display: inline-block; font-weight: 700; color: var(--body); }
    .parties { display: flex; justify-content: space-between; gap: 8mm; margin-block: 8mm; font-size: 11pt; }
    .label { font-size: 9pt; color: var(--muted); }
    table { width: 100%; border-collapse: collapse; font-size: 11pt; }
    th { text-align: start; background: var(--brand-soft); color: var(--brand-dark); padding: 3mm; font-weight: 700; }
    td { padding: 3mm; border-block-end: 0.3mm solid var(--line); }
    .amount { text-align: end; direction: ltr; unicode-bidi: isolate; white-space: nowrap; }
    .totals { margin-block-start: 6mm; margin-inline-start: auto; width: 80mm; font-size: 11pt; }
    .totals div { display: flex; justify-content: space-between; padding-block: 1.5mm; }
    .totals .grand { border-block-start: 0.6mm solid var(--brand); font-size: 14pt; font-weight: 800; color: var(--brand-dark); }
    .foot { margin-block-start: 14mm; font-size: 9pt; color: var(--muted); border-block-start: 0.3mm solid var(--line); padding-block-start: 3mm; }
</style>
</head>
<body>
<div class="page">
    <div class="head">
        <div>
            <div class="brand">{{ __('app.name', [], $locale) }}</div>
            @if($sellerName)<div class="meta">{{ $sellerName }}</div>@endif
            @if($sellerTaxId)<div class="meta">{{ __('receipts.pdf.tax_id', [], $locale) }}: <span class="num">{{ $sellerTaxId }}</span></div>@endif
        </div>
        <div>
            <h1>{{ $isRefund ? __('receipts.pdf.refund_heading', [], $locale) : __('receipts.pdf.heading', [], $locale) }}</h1>
            <div class="meta">
                <div>{{ __('receipts.pdf.number', [], $locale) }}: <span class="num">{{ $number }}</span></div>
                <div>{{ __('receipts.pdf.date', [], $locale) }}: {{ $date }}</div>
                <div>{{ __('receipts.pdf.order', [], $locale) }}: <span class="num">{{ $orderReference }}</span></div>
                @if($isRefund && $originalNumber)<div>{{ __('receipts.pdf.original', [], $locale) }}: <span class="num">{{ $originalNumber }}</span></div>@endif
            </div>
        </div>
    </div>

    <div class="parties">
        <div><div class="label">{{ __('receipts.pdf.billed_to', [], $locale) }}</div><div dir="auto">{{ $buyerName }}</div></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('receipts.pdf.item', [], $locale) }}</th>
                <th class="amount">{{ __('receipts.pdf.price', [], $locale) }}</th>
                <th class="amount">{{ __('receipts.pdf.line_total', [], $locale) }}</th>
            </tr>
        </thead>
        <tbody>
        @foreach($lines as $line)
            <tr>
                <td dir="auto">{{ $line['title'] }}@if($line['discount'])<div class="label">{{ __('receipts.pdf.discount', [], $locale) }}: {{ $line['discount'] }}</div>@endif</td>
                <td class="amount">{{ $line['unit'] }}</td>
                <td class="amount">{{ $line['net'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div><span>{{ __('receipts.pdf.subtotal', [], $locale) }}</span><span class="amount">{{ $subtotal }}</span></div>
        @if($discount)<div><span>{{ __('receipts.pdf.discount', [], $locale) }}</span><span class="amount">{{ $discount }}</span></div>@endif
        <div class="grand"><span>{{ $isRefund ? __('receipts.pdf.refunded', [], $locale) : __('receipts.pdf.total', [], $locale) }}</span><span class="amount">{{ $total }}</span></div>
    </div>

    <div class="foot">{{ __('receipts.pdf.footer', [], $locale) }}</div>
</div>
</body>
</html>

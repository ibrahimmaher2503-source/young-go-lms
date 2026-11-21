{{-- Standalone certificate page for the headless-Chromium renderer (no Vite). Tokens come from config/brand.php, which mirrors resources/css/tokens.css (see docs/brand/CHANGING_THE_BRAND.md); the wordmark is text until official logo assets exist (O-027). --}}
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
<meta charset="utf-8">
<title>{{ $number }}</title>
<style>
    @include('partials.brand-pdf-css')
    @page { size: A4 landscape; margin: 0; }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; width: 297mm; height: 210mm; font-family: var(--font); color: var(--body); background: var(--paper); }
    .page { position: relative; width: 297mm; height: 210mm; padding: 12mm; background: var(--brand-soft); }
    /* A fixed line height keeps tall Arabic font metrics from pushing the footer past the frame border. */
    .frame { height: 100%; border: 2mm solid var(--brand); background: var(--paper); padding: 8mm 16mm; display: flex; flex-direction: column; text-align: center; line-height: 1.3; overflow: hidden; }
    .brand { font-size: 22pt; font-weight: 800; color: var(--brand); letter-spacing: 0.02em; }
    .rule { width: 40mm; height: 1.2mm; margin: 4mm auto; background: var(--accent); }
    h1 { margin: 0; font-size: 30pt; font-weight: 800; color: var(--brand-dark); }
    .lead { margin: 5mm 0 2mm; font-size: 13pt; color: var(--muted); }
    .name { margin: 0; font-size: 30pt; font-weight: 700; color: var(--body); }
    .course { margin: 2mm 0 0; font-size: 20pt; font-weight: 700; color: var(--brand); }
    .course-other { margin: 1mm 0 0; font-size: 12pt; color: var(--muted); }
    .date { margin: 5mm 0 0; font-size: 13pt; color: var(--muted); }
    .foot { margin-block-start: auto; display: flex; align-items: flex-end; justify-content: space-between; gap: 10mm; text-align: start; }
    .issuer { flex: 1; font-size: 10pt; color: var(--muted); border-block-start: 0.4mm solid var(--line); padding-block-start: 3mm; }
    .meta { font-size: 9.5pt; color: var(--muted); }
    .meta strong { color: var(--body); }
    .num { direction: ltr; unicode-bidi: isolate; display: inline-block; font-weight: 700; color: var(--body); }
    .qr { width: 30mm; height: 30mm; display: block; }
    .verify { direction: ltr; unicode-bidi: isolate; display: block; font-size: 7.5pt; color: var(--muted); max-width: 70mm; overflow-wrap: anywhere; }
</style>
</head>
<body>
<div class="page">
    <div class="frame">
        <div class="brand">{{ __('app.name', [], $locale) }}</div>
        <div class="rule"></div>
        <h1>{{ __('certificates.pdf.heading', [], $locale) }}</h1>
        <p class="lead">{{ __('certificates.pdf.awarded_to', [], $locale) }}</p>
        <p class="name">{{ $name }}</p>
        <p class="lead">{{ __('certificates.pdf.for_completing', [], $locale) }}</p>
        <p class="course">{{ $courseTitle }}</p>
        @if($otherTitle)<p class="course-other" dir="{{ $otherDir }}" lang="{{ $otherLocale }}">{{ $otherTitle }}</p>@endif
        <p class="date">{{ __('certificates.pdf.completed_on', ['date' => $date], $locale) }}</p>
        <div class="foot">
            <div class="issuer">{{ $issuer }}</div>
            <div class="meta">
                <div>{{ __('certificates.pdf.number', [], $locale) }}: <span class="num">{{ $number }}</span></div>
                <div>{{ __('certificates.pdf.verify_at', [], $locale) }}</div>
                <span class="verify">{{ $verifyUrl }}</span>
            </div>
            <img class="qr" src="{{ $qr }}" alt="{{ __('certificates.pdf.qr_alt', [], $locale) }}">
        </div>
    </div>
</div>
</body>
</html>

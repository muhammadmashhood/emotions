<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Evaluation · {{ $e->form->title }}</title>
    <style>
        @page { margin: 18mm 16mm; }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 10.5px; color: #123A4A; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        h2 { font-size: 12.5px; margin: 0; }
        .muted { color: #4E6875; }
        .meta td { padding: 2px 14px 2px 0; vertical-align: top; }
        .score { font-size: 26px; font-weight: bold; text-align: right; }
        .green { color: #2F855A; } .amber { color: #B7791F; } .red { color: #B83A33; } .neutral { color: #A9B6BD; }
        .journey { width: 100%; border-collapse: collapse; margin: 10px 0 14px; table-layout: fixed; }
        .journey td { text-align: center; vertical-align: bottom; padding: 0 2px; font-size: 8.5px; }
        .bar-wrap { height: 54px; border: 1px solid #DCE3E7; background: #F7F9FA; position: relative; }
        .bar { position: absolute; bottom: 0; left: 0; right: 0; }
        .bg-green { background: #2F855A; } .bg-amber { background: #B7791F; } .bg-red { background: #B83A33; } .bg-neutral { background: #DCE3E7; }
        .section { border: 1px solid #DCE3E7; border-radius: 4px; margin: 0 0 8px; page-break-inside: avoid; }
        .section-head { background: #F7F9FA; padding: 5px 8px; border-bottom: 1px solid #DCE3E7; }
        .section-head .pct { float: right; font-weight: bold; }
        .answers { width: 100%; border-collapse: collapse; }
        .answers td { padding: 3px 8px; border-top: 1px solid #EEF2F4; vertical-align: top; }
        .answers td.q { width: 42%; }
        .answers td.p { width: 12%; text-align: right; white-space: nowrap; }
        .unscored { color: #4E6875; }
        .footer { margin-top: 10px; font-size: 8.5px; color: #4E6875; border-top: 1px solid #DCE3E7; padding-top: 4px; }
    </style>
</head>
<body>
@php
    $ragClass = fn ($pct) => match (\App\Enums\Rag::fromPct($pct === null ? null : (float) $pct, $e->form->settings->ragGreenMin, $e->form->settings->ragAmberMin)) {
        \App\Enums\Rag::Green => 'green', \App\Enums\Rag::Amber => 'amber', \App\Enums\Rag::Red => 'red', default => 'neutral' };
@endphp
<table style="width:100%"><tr>
    <td>
        <h1>{{ $e->infoValue('facility') ?? $e->form->title }}</h1>
        <div class="muted">{{ $e->form->hospital->name }} · {{ $e->form->title }} · submitted {{ $e->submitted_at?->format('d M Y H:i') ?? 'in progress' }}</div>
        <table class="meta" style="margin-top:6px">
            <tr>
                @foreach(\App\Enums\InfoKey::cases() as $key)
                    @if($e->infoValue($key->value) !== null && $key->isDisplayInfo())
                        <td><span class="muted">{{ $key->label() }}</span><br><strong>{{ $e->infoValue($key->value) }}</strong></td>
                    @endif
                @endforeach
            </tr>
        </table>
    </td>
    <td style="width:120px" class="score {{ $ragClass($e->overall_pct) }}">
        {{ $e->overall_pct === null ? '–' : number_format($e->overall_pct, 1).'%' }}<br>
        <span class="muted" style="font-size:9px;font-weight:normal">{{ $e->points !== null ? number_format($e->points, 0).' / '.number_format($e->max_points, 0).' points' : '' }}</span>
    </td>
</tr></table>

<table class="journey"><tr>
    @foreach($journey as $s)
        <td>
            <div><strong>{{ $s->pct === null ? '–' : number_format((float) $s->pct, 0).'%' }}</strong></div>
            <div class="bar-wrap"><div class="bar bg-{{ $ragClass($s->pct) }}" style="height: {{ $s->pct === null ? 0 : max(3, (float) $s->pct) }}%"></div></div>
            <div class="muted">{{ $s->section_name }}</div>
        </td>
    @endforeach
</tr></table>

@foreach($sections as $block)
    @php $section = $block['section']; $score = $block['score']; @endphp
    <div class="section">
        <div class="section-head">
            <span class="pct {{ $ragClass($score?->pct) }}">{{ $score && $score->pct !== null ? number_format((float) $score->pct, 1).'% · '.number_format($score->points, 0).' / '.number_format($score->max_points, 0) : 'not answered' }}</span>
            <h2>{{ $section->name }}</h2>
        </div>
        @if(! empty($block['rows']))
            <table class="answers">
                @foreach($block['rows'] as $row)
                    @php $a = $row['answer']; @endphp
                    <tr class="{{ $a->isScored() ? '' : 'unscored' }}">
                        <td class="q">{{ $row['field']->title }}</td>
                        <td>{{ $a->answer_type === 'file_url' ? 'Attachment: '.$a->value_text : ($a->value_text ?? '—') }}</td>
                        <td class="p">@if($a->isScored()){{ number_format($a->points, 0) }} / {{ number_format($a->max_points, 0) }}@endif</td>
                    </tr>
                @endforeach
            </table>
        @endif
    </div>
@endforeach

<div class="footer">Generated {{ now()->format('d M Y H:i') }} · {{ config('app.name') }} · mapping version v{{ $e->formVersion->version_number }} · {{ $e->uuid }}</div>
</body>
</html>

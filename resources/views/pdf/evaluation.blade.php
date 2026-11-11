<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Visit report · {{ $e->infoValue('facility') ?? $e->form->title }}</title>
    <style>
        @page { margin: 16mm 14mm 18mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 9.5px; color: #2B1F26; line-height: 1.35; }
        h1 { font-size: 19px; margin: 0; letter-spacing: -0.2px; }
        h2 { font-size: 11px; margin: 0; }
        h3 { font-size: 9px; margin: 0 0 4px; text-transform: uppercase; letter-spacing: 0.6px; color: #6E666B; font-weight: bold; }
        p { margin: 0; }
        .muted { color: #6E666B; }
        .small { font-size: 8.5px; }
        .eyebrow { font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.8px; color: #7470F0; font-weight: bold; }
        .green { color: #5F5A00; } .amber { color: #8A5A0E; } .red { color: #B73228; } .neutral { color: #A9A2A6; }
        .bg-green { background: #C2B700; } .bg-amber { background: #E3A048; } .bg-red { background: #F44336; } .bg-neutral { background: #ECE9E7; }
        .tint-green { background: #F3F1D6; } .tint-amber { background: #FBEEDC; } .tint-red { background: #FBE0DD; } .tint-neutral { background: #F6F5F3; }

        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; }

        .masthead { width: 100%; border-bottom: 2px solid #2B1F26; padding-bottom: 8px; margin-bottom: 10px; }
        .masthead td { vertical-align: middle; }
        .logo { height: 34px; max-width: 110px; }
        .score-box { width: 118px; text-align: right; }
        .score-box .pct { font-size: 28px; font-weight: bold; line-height: 1; }
        .score-box .band { display: inline-block; margin-top: 4px; padding: 2px 8px; border-radius: 10px; font-size: 8.5px; font-weight: bold; }

        .meta { width: 100%; border: 1px solid #ECE9E7; border-radius: 4px; margin-bottom: 10px; table-layout: fixed; }
        .meta td { width: 20%; padding: 6px 8px; border-right: 1px solid #ECE9E7; }
        .meta td:last-child { border-right: none; }
        .meta .k { display: block; font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; color: #6E666B; margin-bottom: 1px; }
        .meta .v { display: block; font-weight: bold; }

        .journey { width: 100%; table-layout: fixed; margin: 0 0 12px; }
        .journey td { text-align: center; padding: 0 3px; }
        .journey .val { font-weight: bold; font-size: 9.5px; }
        .journey .bar-wrap { height: 46px; border: 1px solid #ECE9E7; background: #F6F5F3; position: relative; margin: 2px 0 3px; }
        .journey .bar { position: absolute; bottom: 0; left: 0; right: 0; }
        .journey .lbl { font-size: 7.5px; color: #6E666B; line-height: 1.2; }

        .section { border: 1px solid #ECE9E7; border-radius: 4px; margin: 0 0 8px; page-break-inside: avoid; }
        .section-head { padding: 6px 9px; border-bottom: 1px solid #ECE9E7; }
        .section-head table td { vertical-align: middle; }
        .section-head .pts { text-align: right; white-space: nowrap; font-weight: bold; width: 130px; }
        .answers td { padding: 4px 9px; border-top: 1px solid #F3F1F0; }
        .answers tr:first-child td { border-top: none; }
        .answers td.q { width: 46%; }
        .answers td.a { width: 42%; }
        .answers td.p { width: 12%; text-align: right; white-space: nowrap; font-weight: bold; }
        .answers .unscored td { color: #6E666B; }
        .edited { font-size: 7.5px; color: #7470F0; margin-left: 4px; }
        .comment { padding: 6px 9px; border-top: 1px solid #F3F1F0; font-style: italic; }
        .comment .who { font-style: normal; color: #6E666B; font-size: 8px; display: block; margin-bottom: 2px; }
        .evidence { margin-top: 4px; }
        .evidence img { max-width: 60mm; max-height: 55mm; border: 1px solid #ECE9E7; padding: 2px; background: #fff; }
        .evidence .cap { display: block; font-size: 7.5px; color: #6E666B; margin-top: 1px; }

        .docs { page-break-inside: avoid; }
        .docs table { table-layout: fixed; }
        .docs td { width: 50%; padding: 4px; }
        .docs img { max-width: 80mm; max-height: 90mm; border: 1px solid #ECE9E7; padding: 2px; background: #fff; }
        .docs .file { border: 1px solid #ECE9E7; border-radius: 4px; padding: 6px 8px; background: #F6F5F3; }

        .footer { margin-top: 10px; font-size: 7.5px; color: #6E666B; border-top: 1px solid #ECE9E7; padding-top: 4px; }
    </style>
</head>
<body>
@php
    $band = fn ($pct) => match (\App\Enums\Rag::fromPct($pct === null ? null : (float) $pct, $e->form->settings->ragGreenMin, $e->form->settings->ragAmberMin)) {
        \App\Enums\Rag::Green => 'green', \App\Enums\Rag::Amber => 'amber', \App\Enums\Rag::Red => 'red', default => 'neutral' };
    $bandLabel = fn ($pct) => match ($band($pct)) { 'green' => 'Good', 'amber' => 'Watch', 'red' => 'Poor', default => 'No score' };
    $fmt = fn ($n, $d = 0) => $n === null ? '–' : number_format((float) $n, $d);
    $edits = is_array($e->edits ?? null) ? $e->edits : [];
    $meta = collect(\App\Enums\InfoKey::cases())->filter(fn ($k) => $k->isDisplayInfo() && $e->infoValue($k->value) !== null)->values();
@endphp

<table class="masthead"><tr>
    <td style="width: 120px">
        @if($logo)<img class="logo" src="{{ $logo }}" alt="">@else<span style="font-size:15px;font-weight:bold">{{ $e->form->hospital->name }}</span>@endif
    </td>
    <td style="padding: 0 10px">
        <div class="eyebrow">Mystery Shopping Visit Report</div>
        <h1>{{ $e->infoValue('facility') ?? $e->form->title }}</h1>
        <p class="muted">{{ $e->form->hospital->name }} · {{ $e->form->title }} · {{ $e->submitted_at ? 'submitted '.$e->submitted_at->format('d M Y H:i') : 'in progress' }}</p>
    </td>
    <td class="score-box">
        <div class="pct {{ $band($e->overall_pct) }}">{{ $e->overall_pct === null ? '–' : $fmt($e->overall_pct, 1).'%' }}</div>
        <span class="band tint-{{ $band($e->overall_pct) }} {{ $band($e->overall_pct) }}">{{ $bandLabel($e->overall_pct) }}</span>
        @if($e->points !== null)<div class="small muted">{{ $fmt($e->points) }} / {{ $fmt($e->max_points) }} points</div>@endif
    </td>
</tr></table>

@if($meta->isNotEmpty())
    <table class="meta"><tr>
        @foreach($meta->take(5) as $key)
            <td><span class="k">{{ $key->label() }}</span><span class="v">{{ $e->infoValue($key->value) }}</span></td>
        @endforeach
        @for($i = $meta->take(5)->count(); $i < 5; $i++)<td></td>@endfor
    </tr></table>
@endif

@if($journey->isNotEmpty())
    <h3>Journey touchpoints</h3>
    <table class="journey"><tr>
        @foreach($journey as $s)
            <td>
                <div class="val {{ $band($s->pct) }}">{{ $s->pct === null ? '–' : $fmt($s->pct).'%' }}</div>
                <div class="bar-wrap"><div class="bar bg-{{ $band($s->pct) }}" style="height: {{ $s->pct === null ? 0 : max(3, (float) $s->pct) }}%"></div></div>
                <div class="lbl">{{ $s->section_name }}</div>
            </td>
        @endforeach
    </tr></table>
@endif

@foreach($sections as $block)
    @php $section = $block['section']; $score = $block['score']; @endphp
    @if(! empty($block['rows']) || ! empty($block['comments']))
        <div class="section">
            <div class="section-head tint-{{ $score && $score->pct !== null ? $band($score->pct) : 'neutral' }}">
                <table><tr>
                    <td><h2>{{ $section->name }}</h2></td>
                    <td class="pts {{ $band($score?->pct) }}">{{ $score && $score->pct !== null ? $fmt($score->pct, 1).'%  ·  '.$fmt($score->points).' / '.$fmt($score->max_points) : 'not scored' }}</td>
                </tr></table>
            </div>
            @if(! empty($block['rows']))
                <table class="answers">
                    @foreach($block['rows'] as $row)
                        @php $a = $row['answer']; $f = $row['field']; $file = $row['file']; $edit = $edits[$a->typeform_field_id] ?? null; @endphp
                        <tr class="{{ $a->isScored() ? '' : 'unscored' }}">
                            <td class="q">{{ $f->title }}</td>
                            <td class="a">
                                @if($a->answer_type === 'file_url')
                                    @if($file && $file->dataUri())
                                        <div class="evidence"><img src="{{ $file->dataUri() }}" alt=""><span class="cap">{{ $file->original_name }}</span></div>
                                    @else
                                        <span class="muted">File: {{ $a->value_text }}</span>
                                    @endif
                                @elseif($f->isScale() && $a->value_text !== null)
                                    {{ $a->value_text }} / {{ $f->scale_max }}
                                @else
                                    {{ $a->value_text ?? '—' }}
                                @endif
                                @if($edit !== null)<span class="edited">edited</span>@endif
                            </td>
                            <td class="p {{ $a->isScored() ? $band($a->max_points > 0 ? 100 * $a->points / $a->max_points : null) : '' }}">@if($a->isScored()){{ $fmt($a->points) }} / {{ $fmt($a->max_points) }}@endif</td>
                        </tr>
                    @endforeach
                </table>
            @endif
            @foreach($block['comments'] as $row)
                @php $text = trim((string) $row['answer']->value_text); @endphp
                <div class="comment"><span class="who">{{ count($block['comments']) > 1 ? $row['field']->title : 'Shopper comment' }}</span>{{ $text !== '' ? '“'.$text.'”' : '—' }}</div>
            @endforeach
        </div>
    @endif
@endforeach

@if($documents->isNotEmpty())
    <div class="section docs">
        <div class="section-head"><h2>Supporting documents</h2></div>
        <div style="padding: 6px 9px">
            <table><tr>
                @foreach($documents as $doc)
                    <td>
                        @if($doc->dataUri())
                            <img src="{{ $doc->dataUri() }}" alt=""><br>
                            <span class="small muted">{{ $doc->original_name }}@if($doc->uploader) · added by {{ $doc->uploader->name }}@endif</span>
                        @else
                            <div class="file">{{ $doc->original_name }}<br><span class="small muted">@if($doc->uploader)added by {{ $doc->uploader->name }}@else supporting document @endif</span></div>
                        @endif
                    </td>
                    @if($loop->iteration % 2 === 0 && ! $loop->last)</tr><tr>@endif
                @endforeach
                @if($documents->count() % 2 === 1)<td></td>@endif
            </tr></table>
        </div>
    </div>
@endif

<div class="footer">Generated {{ now()->format('d M Y H:i') }} · {{ config('app.name') }} · mapping version v{{ $e->formVersion->version_number }} · {{ $e->uuid }}@if($e->reviewed_at) · reviewed {{ $e->reviewed_at->format('d M Y') }}@if($e->reviewer) by {{ $e->reviewer->name }}@endif @endif</div>
</body>
</html>

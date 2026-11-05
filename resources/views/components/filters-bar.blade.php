@props(['period', 'forms', 'branches' => collect(), 'form', 'branch' => '', 'from' => '', 'to' => '', 'legend' => false])
{{-- Period segmented control, survey and facility selects (design filter bar). Extra controls go in the slot. --}}
<div {{ $attributes->merge(['class' => 'panel px-3 py-3 flex flex-wrap items-center gap-2.5 no-print']) }}>
    <div class="inline-flex rounded-xl bg-[#F3F1F0] p-[3px] gap-0.5 max-w-full overflow-x-auto" role="group" aria-label="Period">
        @foreach(['30d' => '30 days', '90d' => '90 days', '12m' => '12 months', 'custom' => 'Custom'] as $key => $label)
            <button type="button" wire:click="setPeriod('{{ $key }}')" class="px-3 py-1.5 rounded-[9px] text-[12.5px] whitespace-nowrap transition-colors {{ $period === $key ? 'bg-white text-em-ink font-semibold shadow-[0_1px_2px_rgba(43,31,38,0.08)]' : 'text-em-soft hover:text-em-ink' }}" @if($period === $key) aria-pressed="true" @else aria-pressed="false" @endif>{{ $label }}</button>
        @endforeach
    </div>
    @if($period === 'custom')
        <label class="sr-only" for="from">From</label>
        <input id="from" type="date" wire:model.live="from" class="field w-auto" title="From">
        <label class="sr-only" for="to">To</label>
        <input id="to" type="date" wire:model.live="to" class="field w-auto" title="To">
    @endif
    <label class="sr-only" for="form-filter">Survey</label>
    <select id="form-filter" wire:model.live="form" class="field w-auto min-w-52">
        <option value="">All surveys</option>
        @foreach($forms as $f)
            <option value="{{ $f->uuid }}">{{ $f->title }}{{ $f->status === \App\Enums\FormStatus::Unlinked ? ' (archived)' : '' }}</option>
        @endforeach
    </select>
    @if($branches->isNotEmpty())
        <label class="sr-only" for="branch-filter">Facility</label>
        <select id="branch-filter" wire:model.live="branch" class="field w-auto min-w-44">
            <option value="">All facilities</option>
            @foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
        </select>
    @endif
    {{ $slot }}
    @if($legend)
        <div class="ml-auto flex flex-wrap items-center gap-3 text-xs text-em-soft" x-data="{ open: false }">
            <span class="eyebrow">Score bands</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-rag-green" aria-hidden="true"></span>Good ≥ {{ $legend['green'] }}%</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-rag-amber" aria-hidden="true"></span>Watch {{ $legend['amber'] }}–{{ $legend['green'] - 1 }}%</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-rag-red" aria-hidden="true"></span>Poor &lt; {{ $legend['amber'] }}%</span>
            <span class="relative">
                <button type="button" class="h-6 w-6 rounded-full border border-em-line bg-white text-[11px] text-em-soft hover:text-em-ink" x-on:click="open = !open" x-on:click.outside="open = false" aria-label="What the score bands mean" :aria-expanded="open.toString()">?</button>
                <span x-show="open" x-cloak x-transition.opacity class="absolute right-0 top-8 z-20 w-72 rounded-xl border border-em-line bg-white p-3.5 text-xs text-em-soft shadow-[0_16px_40px_-20px_rgba(43,31,38,0.35)] flex flex-col gap-2">
                    <span><b class="text-em-ok font-semibold">Good</b> · meets the eMotion service standard.</span>
                    <span><b class="text-em-warn font-semibold">Watch</b> · inconsistent delivery, worth monitoring.</span>
                    <span><b class="text-em-bad font-semibold">Poor</b> · below standard, needs action.</span>
                    <span class="text-em-mute">Thresholds come from the survey settings.</span>
                </span>
            </span>
        </div>
    @endif
</div>

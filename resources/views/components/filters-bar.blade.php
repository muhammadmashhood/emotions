@props(['period', 'forms', 'branches' => collect(), 'form', 'branch' => '', 'from' => '', 'to' => ''])
<div {{ $attributes->merge(['class' => 'panel px-4 py-3 flex flex-wrap items-end gap-3 no-print']) }}>
    <div>
        <span class="label">Period</span>
        <div class="inline-flex rounded-xl bg-paper p-1 gap-1 max-w-full overflow-x-auto" role="group" aria-label="Period">
            @foreach(['30d' => '30 days', '90d' => '90 days', '12m' => '12 months', 'custom' => 'Custom'] as $key => $label)
                <button type="button" wire:click="setPeriod('{{ $key }}')" class="px-3 py-1.5 rounded-lg text-sm whitespace-nowrap transition {{ $period === $key ? 'bg-white shadow-card text-ink font-medium' : 'text-ink-soft hover:text-ink' }}" @if($period === $key) aria-pressed="true" @endif>{{ $label }}</button>
            @endforeach
        </div>
    </div>
    @if($period === 'custom')
        <div>
            <label class="label" for="from">From</label>
            <input id="from" type="date" wire:model.live="from" class="field">
        </div>
        <div>
            <label class="label" for="to">To</label>
            <input id="to" type="date" wire:model.live="to" class="field">
        </div>
    @endif
    <div>
        <label class="label" for="form-filter">Survey</label>
        <select id="form-filter" wire:model.live="form" class="field w-auto">
            <option value="">All surveys</option>
            @foreach($forms as $f)
                <option value="{{ $f->uuid }}">{{ $f->title }}{{ $f->status === \App\Enums\FormStatus::Unlinked ? ' (archived)' : '' }}</option>
            @endforeach
        </select>
    </div>
    @if($branches->isNotEmpty())
        <div>
            <label class="label" for="branch-filter">Branch</label>
            <select id="branch-filter" wire:model.live="branch" class="field w-auto">
                <option value="">All branches</option>
                @foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
            </select>
        </div>
    @endif
    {{ $slot }}
</div>

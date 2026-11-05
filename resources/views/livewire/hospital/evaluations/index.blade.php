<div>
    <x-ui.page wide>
        <x-ui.header title="Evaluations"
            :subtitle="number_format($evaluations->total()).' '.Str::plural('visit', $evaluations->total()).' in the '.$filters->label().' · '.($form !== '' ? ($forms->firstWhere('uuid', $form)?->title ?? 'one survey') : 'all surveys')">
            <x-slot name="eyebrowSlot">{{ $hospital->name }}@if($viewingAsAdmin) <x-ui.pill tone="info" class="font-medium">Viewing as admin</x-ui.pill>@endif</x-slot>
            <x-slot name="actions">
                <a href="{{ $viewingAsAdmin ? route('hospitals.dashboard', [$hospital] + $this->filterQuery()) : route('dashboard', $this->filterQuery()) }}" wire:navigate class="btn-secondary">Dashboard</a>
                <a href="{{ $this->exportUrl() }}" class="btn-primary" data-export>Export CSV</a>
            </x-slot>
        </x-ui.header>

        <x-filters-bar :period="$period" :forms="$forms" :branches="$branches" :form="$form" :branch="$branch" :from="$from" :to="$to">
            <div class="flex flex-wrap gap-1.5" role="group" aria-label="Score band">
                @foreach(['' => ['All bands', null], 'green' => ['Good', 'bg-rag-green'], 'amber' => ['Watch', 'bg-rag-amber'], 'red' => ['Poor', 'bg-rag-red']] as $key => [$label, $dot])
                    <x-ui.chip :active="$rag === $key" wire:click="$set('rag', '{{ $key }}')" class="inline-flex items-center gap-1.5">
                        @if($dot)<span class="h-1.5 w-1.5 rounded-full {{ $dot }}" aria-hidden="true"></span>@endif{{ $label }}
                    </x-ui.chip>
                @endforeach
            </div>
            @if($isAdmin)
                <label class="sr-only" for="status-filter">Status</label>
                <select id="status-filter" wire:model.live="status" class="field w-auto">
                    <option value="">All statuses</option>
                    @foreach($statuses as $s)<option value="{{ $s->value }}">{{ $s->label() }}</option>@endforeach
                </select>
            @endif
            <div class="basis-full">
                <label class="sr-only" for="search">Search</label>
                <input id="search" type="search" wire:model.live.debounce.400ms="search" placeholder="Search facility, doctor or location…" class="field">
            </div>
        </x-filters-bar>

        @if($evaluations->isEmpty())
            <x-empty-state title="No evaluations match" message="Try a wider period or clear the filters. New evaluations appear as soon as a survey is submitted." />
        @else
            <div class="panel overflow-x-auto">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th><button type="button" wire:click="sortBy('date')" class="inline-flex items-center gap-1 uppercase tracking-[0.08em] hover:text-em-ink">Submitted @if($sort === 'date')<span aria-hidden="true">{{ $direction === 'asc' ? '↑' : '↓' }}</span>@endif</button></th>
                            <th>ID</th><th>Facility</th><th>Survey</th><th>Doctor</th><th>Booking</th>
                            @if($isAdmin)<th>Status</th>@endif
                            <th class="!text-right"><button type="button" wire:click="sortBy('score')" class="inline-flex items-center gap-1 uppercase tracking-[0.08em] hover:text-em-ink">Score @if($sort === 'score')<span aria-hidden="true">{{ $direction === 'asc' ? '↑' : '↓' }}</span>@endif</button></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($evaluations as $e)
                        @php $url = route('evaluations.show', $e); $facility = $e->infoValue('facility') ?? $e->branch?->name; @endphp
                        <tr wire:key="e-{{ $e->id }}" class="cursor-pointer" x-on:click="if (! $event.target.closest('a')) Livewire.navigate(@js($url))">
                            <td class="whitespace-nowrap">
                                <a href="{{ $url }}" wire:navigate class="hover:text-em-accent">{{ $e->submitted_at?->format('j M Y, H:i') ?? 'In progress' }}</a>
                                @if($e->is_partial)<x-ui.pill tone="warn" class="ms-1">Partial</x-ui.pill>@endif
                            </td>
                            <td class="text-em-soft tabular-nums">#{{ $e->id }}</td>
                            <td>
                                <div class="font-medium">{{ $facility ?? '—' }}</div>
                                @if($e->infoValue('location') || ($e->branch && $e->branch->name !== $facility))
                                    <div class="text-xs text-em-soft">{{ $e->infoValue('location') ?? $e->branch?->name }}</div>
                                @endif
                            </td>
                            <td class="text-em-soft">{{ $e->form->title }}</td>
                            <td>{{ $e->infoValue('doctor') ?? '—' }}</td>
                            <td class="text-em-soft">{{ $e->infoValue('booking_method') ?? '—' }}</td>
                            @if($isAdmin)<td><x-badge :variant="match ($e->status) { \App\Enums\SubmissionStatus::Approved => 'green', \App\Enums\SubmissionStatus::PendingReview => 'amber', default => 'red' }">{{ $e->status->label() }}</x-badge></td>@endif
                            <td class="text-right"><x-ui.band :pct="$e->overall_pct" :rag="$e->rag" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <div class="flex flex-wrap items-center justify-between gap-2 px-5 py-3.5 border-t border-em-line text-xs text-em-soft">
                    <span>Showing {{ $evaluations->firstItem() }}–{{ $evaluations->lastItem() }} of {{ number_format($evaluations->total()) }}</span>
                    <span>Click a row to open the full report</span>
                </div>
            </div>
            {{ $evaluations->links('pagination::simple-tailwind') }}
        @endif
    </x-ui.page>
</div>

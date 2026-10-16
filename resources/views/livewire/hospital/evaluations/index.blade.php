<div>
    <x-page-header :title="($viewingAsAdmin ? $hospital->name.' · ' : '').'Evaluations'" :subtitle="$evaluations->total().' '.Str::plural('evaluation', $evaluations->total()).' in the '.$filters->label()">
        <x-slot name="actions">
            <a href="{{ $viewingAsAdmin ? route('hospitals.dashboard', [$hospital] + $this->filterQuery()) : route('dashboard', $this->filterQuery()) }}" wire:navigate class="btn-secondary">Dashboard</a>
            <a href="{{ $this->exportUrl() }}" class="btn-primary" data-export>Export CSV</a>
        </x-slot>
    </x-page-header>

    <x-page wide>
        @if($viewingAsAdmin)<x-alert type="info">Viewing as admin.</x-alert>@endif

        <x-filters-bar :period="$period" :forms="$forms" :branches="$branches" :form="$form" :branch="$branch" :from="$from" :to="$to">
            <div>
                <label class="label" for="rag-filter">Score band</label>
                <select id="rag-filter" wire:model.live="rag" class="field w-auto">
                    <option value="">All</option>
                    @foreach($rags as $r)<option value="{{ $r->value }}">{{ $r->label() }}</option>@endforeach
                </select>
            </div>
            @if($isAdmin)
                <div>
                    <label class="label" for="status-filter">Status</label>
                    <select id="status-filter" wire:model.live="status" class="field w-auto">
                        <option value="">All</option>
                        @foreach($statuses as $s)<option value="{{ $s->value }}">{{ $s->label() }}</option>@endforeach
                    </select>
                </div>
            @endif
            <div class="flex-1 min-w-[12rem]">
                <label class="label" for="search">Search</label>
                <input id="search" type="search" wire:model.live.debounce.400ms="search" placeholder="Facility, doctor or location…" class="field">
            </div>
        </x-filters-bar>

        @if($evaluations->isEmpty())
            <x-empty-state title="No evaluations match" message="Try a wider period or clear the filters. New evaluations appear as soon as a survey is submitted." />
        @else
            <div class="panel overflow-x-auto">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th><button type="button" wire:click="sortBy('date')" class="inline-flex items-center gap-1 uppercase">Submitted @if($sort === 'date')<span aria-hidden="true">{{ $direction === 'asc' ? '↑' : '↓' }}</span>@endif</button></th>
                            <th>Survey</th><th>Facility</th><th>Location</th><th>Doctor</th><th>Booking</th>
                            @if($branches->isNotEmpty())<th>Branch</th>@endif
                            <th><button type="button" wire:click="sortBy('score')" class="inline-flex items-center gap-1 uppercase">Score @if($sort === 'score')<span aria-hidden="true">{{ $direction === 'asc' ? '↑' : '↓' }}</span>@endif</button></th>
                            @if($isAdmin)<th>Status</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($evaluations as $e)
                        <tr wire:key="e-{{ $e->id }}">
                            <td class="whitespace-nowrap"><a href="{{ route('evaluations.show', $e) }}" wire:navigate class="underline font-medium">{{ $e->submitted_at?->format('d M Y H:i') ?? 'In progress' }}</a>@if($e->is_partial)<x-badge variant="amber" class="ms-1">Partial</x-badge>@endif</td>
                            <td class="text-ink-soft">{{ $e->form->title }}</td>
                            <td>{{ $e->infoValue('facility') ?? '—' }}</td>
                            <td>{{ $e->infoValue('location') ?? '—' }}</td>
                            <td>{{ $e->infoValue('doctor') ?? '—' }}</td>
                            <td>{{ $e->infoValue('booking_method') ?? '—' }}</td>
                            @if($branches->isNotEmpty())<td>{{ $e->branch?->name ?? '—' }}</td>@endif
                            <td><x-rag-pill :pct="$e->overall_pct" :rag="$e->rag" /></td>
                            @if($isAdmin)<td><x-badge :variant="match ($e->status) { \App\Enums\SubmissionStatus::Approved => 'green', \App\Enums\SubmissionStatus::PendingReview => 'amber', default => 'red' }">{{ $e->status->label() }}</x-badge></td>@endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $evaluations->links() }}
        @endif
    </x-page>
</div>

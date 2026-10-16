<div>
    <x-page-header title="Review queue" :subtitle="$pending->total().' '.Str::plural('evaluation', $pending->total()).' waiting for approval'" />
    <x-page wide>
        <x-flash />
        <div class="flex flex-wrap gap-3 items-center">
            <select wire:model.live="hospital" class="field w-auto" aria-label="Hospital">
                <option value="">All hospitals</option>
                @foreach($hospitals as $h)<option value="{{ $h->uuid }}">{{ $h->name }}{{ $h->require_review ? '' : ' (review off)' }}</option>@endforeach
            </select>
            <p class="text-xs text-ink-soft">Only hospitals with "Require review" enabled queue new evaluations. Hospital users see approved evaluations only.</p>
        </div>

        @if($pending->isEmpty())
            <x-empty-state title="Nothing to review" message="New evaluations from hospitals with review enabled will appear here." />
        @else
            <div class="panel overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Submitted</th><th>Hospital</th><th>Survey</th><th>Facility</th><th>Doctor</th><th>Score</th><th>Note</th><th></th></tr></thead>
                    <tbody>
                    @foreach($pending as $e)
                        <tr wire:key="p-{{ $e->id }}">
                            <td class="whitespace-nowrap"><a href="{{ route('evaluations.show', $e) }}" wire:navigate class="underline font-medium">{{ $e->submitted_at?->format('d M Y H:i') ?? 'In progress' }}</a></td>
                            <td>{{ $e->hospital->name }}</td>
                            <td class="text-ink-soft">{{ $e->form->title }}</td>
                            <td>{{ $e->infoValue('facility') ?? '—' }}</td>
                            <td>{{ $e->infoValue('doctor') ?? '—' }}</td>
                            <td><x-rag-pill :pct="$e->overall_pct" :rag="$e->rag" /></td>
                            <td><input type="text" wire:model="notes.{{ $e->id }}" class="field py-1 text-xs" placeholder="Optional note" aria-label="Review note"></td>
                            <td class="text-right whitespace-nowrap space-x-2">
                                <button type="button" wire:click="approve({{ $e->id }})" class="btn-primary py-1">Approve</button>
                                <button type="button" wire:click="reject({{ $e->id }})" wire:confirm="Reject this evaluation?" class="btn-danger py-1">Reject</button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $pending->links() }}
        @endif
    </x-page>
</div>

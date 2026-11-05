<x-ui.page>
    <x-ui.header title="Review queue" :subtitle="$pending->total().' '.Str::plural('response', $pending->total()).' waiting. Every response is reviewed before it reaches a hospital dashboard.'">
        <x-slot name="actions">
            <select wire:model.live="hospital" class="field w-auto min-w-52 bg-white" aria-label="Hospital">
                <option value="">All hospitals</option>
                @foreach($hospitals as $h)<option value="{{ $h->uuid }}">{{ $h->name }}{{ $h->require_review ? '' : ' (review off)' }}</option>@endforeach
            </select>
        </x-slot>
    </x-ui.header>

    <x-ui.flash />

    @if($pending->isEmpty())
        <div class="bg-white border border-em-line rounded-2xl p-10 text-center">
            <p class="font-medium">Nothing to review</p>
            <p class="text-[13px] text-em-soft mt-1">New evaluations from hospitals with review enabled will appear here. Hospital users only see approved evaluations.</p>
        </div>
    @else
        <div class="panel overflow-x-auto">
            <table class="table-base">
                <thead><tr><th>Received</th><th>ID</th><th>Hospital · Facility</th><th>Form</th><th>Flags</th><th>Raw score</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                @foreach($pending as $e)
                    @php $facility = $e->branch?->name ?? $e->infoValue('facility'); @endphp
                    <tr wire:key="p-{{ $e->id }}">
                        <td class="whitespace-nowrap"><x-ui.when :at="$e->submitted_at" /></td>
                        <td class="text-em-soft tabular-nums">#{{ $e->id }}</td>
                        <td>
                            <div class="font-medium">{{ $e->hospital->name }}</div>
                            <div class="text-xs {{ $facility ? 'text-em-soft' : 'text-em-bad' }}">{{ $facility ?? 'No facility given' }}</div>
                        </td>
                        <td class="max-w-[240px] truncate" title="{{ $e->form->title }}">{{ $e->form->title }}</td>
                        <td>
                            <div class="flex flex-wrap gap-1">
                                @foreach($flags[$e->id] ?? [] as $flag)
                                    <x-ui.pill :tone="$flag === 'Low score' ? 'bad' : 'warn'" class="text-[10.5px]">{{ $flag }}</x-ui.pill>
                                @endforeach
                            </div>
                        </td>
                        <td><x-ui.score :pct="$e->overall_pct" /></td>
                        <td class="text-right whitespace-nowrap"><a href="{{ route('evaluations.show', $e) }}" wire:navigate class="text-em-accent hover:underline">Review →</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $pending->links() }}
    @endif
</x-ui.page>

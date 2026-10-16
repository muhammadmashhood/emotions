<div>
    @php $e = $evaluation; @endphp
    <x-page-header :title="($e->infoValue('facility') ?? $e->form->title)" :subtitle="$e->form->title.' · '.($e->submitted_at?->format('d M Y H:i') ?? 'in progress')">
        <x-slot name="actions">
            <a href="{{ $backUrl }}" wire:navigate class="btn-secondary">Back to list</a>
            <button type="button" onclick="window.print()" class="btn-secondary">Print</button>
            @if(Route::has('evaluations.pdf'))<a href="{{ route('evaluations.pdf', $e) }}" class="btn-secondary">Download PDF</a>@endif
        </x-slot>
    </x-page-header>

    <x-page>
        <x-flash />
        <div class="panel p-4 sm:p-6 grid grid-cols-1 md:grid-cols-[1fr_auto] gap-4 items-start">
            <dl class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-2 text-sm">
                @foreach(\App\Enums\InfoKey::cases() as $key)
                    @if($e->infoValue($key->value) !== null || ($key === \App\Enums\InfoKey::Branch && $e->branch))
                        <div><dt class="text-ink-soft">{{ $key->label() }}</dt><dd class="font-medium">{{ $key === \App\Enums\InfoKey::Branch && $e->branch ? $e->branch->name : $e->infoValue($key->value) }}</dd></div>
                    @endif
                @endforeach
                <div><dt class="text-ink-soft">Submitted</dt><dd class="font-medium">{{ $e->submitted_at?->format('d M Y H:i') ?? 'In progress (partial)' }}</dd></div>
                @if($isAdmin)
                    <div><dt class="text-ink-soft">Status</dt><dd><x-badge :variant="match ($e->status) { \App\Enums\SubmissionStatus::Approved => 'green', \App\Enums\SubmissionStatus::PendingReview => 'amber', default => 'red' }">{{ $e->status->label() }}</x-badge></dd></div>
                    <div><dt class="text-ink-soft">Mapping version</dt><dd class="font-medium"><a href="{{ route('admin.forms.mapping', $e->form) }}?version={{ $e->formVersion->version_number }}" class="underline">v{{ $e->formVersion->version_number }}</a> · {{ $e->source->value }}</dd></div>
                @endif
            </dl>
            <div class="text-center md:text-right">
                <x-rag-pill :pct="$e->overall_pct" :rag="$e->rag" size="lg" />
                <p class="text-xs text-ink-soft mt-1">{{ $e->points !== null ? number_format($e->points, 0).' / '.number_format($e->max_points, 0).' points' : 'No scored answers' }}</p>
            </div>
        </div>

        <x-card title="Journey">
            <x-journey-strip :scores="$e->sectionScores" />
        </x-card>

        <div class="space-y-3">
            @foreach($sections as $block)
                @php $section = $block['section']; $score = $block['score']; @endphp
                <section id="section-{{ $section->id }}" class="panel" x-data="{ open: {{ $score?->pct !== null ? 'true' : 'false' }} }" x-on:open-section="open = true">
                    <button type="button" x-on:click="open = !open" class="w-full flex items-center justify-between gap-3 px-4 py-3 text-left" :aria-expanded="open.toString()">
                        <span class="font-semibold">{{ $section->name }}</span>
                        <span class="flex items-center gap-3 text-sm">
                            @if($score && $score->pct !== null)
                                <span class="text-ink-soft tabular-nums">{{ number_format($score->points, 0) }} / {{ number_format($score->max_points, 0) }}</span>
                                <x-rag-pill :pct="$score->pct" />
                            @else
                                <span class="text-ink-soft">not answered</span>
                            @endif
                            <span aria-hidden="true" x-text="open ? '−' : '+'" class="w-4 text-ink-soft"></span>
                        </span>
                    </button>
                    <div x-show="open" x-collapse class="border-t border-line">
                        @if(empty($block['rows']))
                            <p class="px-4 py-3 text-sm text-ink-soft">No answers in this section.</p>
                        @else
                            <dl class="divide-y divide-line">
                                @foreach($block['rows'] as $row)
                                    @php $field = $row['field']; $answer = $row['answer']; $scored = $answer->isScored(); @endphp
                                    <div class="px-4 py-3 grid grid-cols-1 sm:grid-cols-[1fr_2fr_auto] gap-2 text-sm {{ $scored ? '' : 'text-ink-soft' }}">
                                        <dt class="{{ $scored ? 'font-medium text-ink' : '' }}">{{ $field->title }}</dt>
                                        <dd class="{{ $field->type === 'long_text' ? 'whitespace-pre-wrap' : '' }}">
                                            @if($answer->answer_type === 'file_url')
                                                @php $isImage = (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', (string) $answer->value_text); @endphp
                                                @if($isImage)
                                                    <a href="{{ route('evaluations.files.show', [$e, $answer->id]) }}" target="_blank" rel="noopener"><img src="{{ route('evaluations.files.show', [$e, $answer->id]) }}" alt="{{ $answer->value_text }}" class="max-h-48 rounded border border-line" loading="lazy"></a>
                                                @else
                                                    <a href="{{ route('evaluations.files.show', [$e, $answer->id]) }}" class="underline">Download {{ $answer->value_text }}</a>
                                                @endif
                                            @else
                                                {{ $answer->value_text ?? '—' }}
                                            @endif
                                        </dd>
                                        <dd class="tabular-nums text-right whitespace-nowrap">@if($scored){{ number_format($answer->points, 0) }} / {{ number_format($answer->max_points, 0) }}@endif</dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif
                    </div>
                </section>
            @endforeach

            @if($orphanAnswers->isNotEmpty())
                <section class="panel">
                    <h2 class="px-4 py-3 font-semibold text-ink-soft">Other answers <span class="text-xs font-normal">(questions not in this mapping version)</span></h2>
                    <dl class="divide-y divide-line border-t border-line">
                        @foreach($orphanAnswers as $answer)
                            <div class="px-4 py-3 text-sm text-ink-soft grid grid-cols-1 sm:grid-cols-[1fr_2fr] gap-2"><dt class="font-mono text-xs">{{ $answer->typeform_field_id }}</dt><dd>{{ $answer->value_text }}</dd></div>
                        @endforeach
                    </dl>
                </section>
            @endif
        </div>

        @if($isAdmin)
            <x-card title="Admin tools" class="no-print">
                <div class="flex flex-wrap gap-2 items-start">
                    <button type="button" wire:click="rescore" wire:confirm="Re-score this evaluation against mapping v{{ $e->formVersion->version_number }}?" class="btn-secondary">Re-score this evaluation</button>
                    @if($e->status !== \App\Enums\SubmissionStatus::Approved)
                        <button type="button" wire:click="approve" class="btn-primary">Approve</button>
                    @endif
                    @if($e->status !== \App\Enums\SubmissionStatus::Rejected)
                        <button type="button" wire:click="reject" wire:confirm="Reject this evaluation? Hospital users will not see it." class="btn-danger">Reject</button>
                    @endif
                    <div class="flex-1 min-w-[14rem]">
                        <label class="label" for="reviewNote">Review note (optional)</label>
                        <input id="reviewNote" type="text" wire:model="reviewNote" class="field" placeholder="Why approved / rejected">
                    </div>
                </div>
                @if($e->reviewed_at)
                    <p class="text-xs text-ink-soft mt-3">Reviewed {{ $e->reviewed_at->diffForHumans() }} by {{ $e->reviewer?->name ?? 'unknown' }}@if($e->review_note): “{{ $e->review_note }}”@endif</p>
                @endif
                <details class="mt-4">
                    <summary class="cursor-pointer text-sm underline">View raw payload</summary>
                    <pre class="mt-2 text-xs bg-paper border border-line rounded p-3 overflow-x-auto max-h-96">{{ $rawJson }}</pre>
                </details>
            </x-card>
        @endif
    </x-page>
</div>

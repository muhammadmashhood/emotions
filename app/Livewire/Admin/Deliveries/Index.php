<?php

namespace App\Livewire\Admin\Deliveries;

use App\Jobs\ProcessTypeformResponse;
use App\Models\Form;
use App\Models\WebhookDelivery;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    #[Url]
    public string $form = '';

    public ?int $viewing = null;

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'form'], true)) {
            $this->resetPage();
        }
    }

    public function view(int $id): void
    {
        $this->viewing = $this->viewing === $id ? null : $id;
    }

    public function retry(int $id): void
    {
        $delivery = WebhookDelivery::query()->findOrFail($id);
        if (! $delivery->signature_valid) {
            return;
        }
        $delivery->forceFill(['processed_at' => null, 'error' => null])->save();
        ProcessTypeformResponse::dispatch($delivery->id);
        session()->flash('status', 'Delivery queued for processing.');
    }

    public function render(): View
    {
        $deliveries = WebhookDelivery::query()
            ->with('form:id,title,uuid')
            ->when($this->form !== '', fn ($q) => $q->whereHas('form', fn ($f) => $f->where('uuid', $this->form)))
            ->when($this->status === 'ok', fn ($q) => $q->where('signature_valid', true)->whereNotNull('processed_at')->whereNull('error'))
            ->when($this->status === 'duplicate', fn ($q) => $q->whereNotNull('processed_at')->where('error', 'like', 'Duplicate%'))
            ->when($this->status === 'error', fn ($q) => $q->where('signature_valid', true)->whereNotNull('error')->where('error', 'not like', 'Duplicate%'))
            ->when($this->status === 'pending', fn ($q) => $q->where('signature_valid', true)->whereNull('processed_at')->whereNull('error'))
            ->when($this->status === 'invalid', fn ($q) => $q->where('signature_valid', false))
            ->orderByDesc('received_at')
            ->paginate(50);

        $viewed = $this->viewing ? WebhookDelivery::query()->find($this->viewing) : null;

        return view('livewire.admin.deliveries.index', [
            'deliveries' => $deliveries,
            'forms' => Form::withTrashed()->orderBy('title')->get(['id', 'uuid', 'title']),
            'viewedPayload' => $viewed ? json_encode($viewed->decodedPayload() ?: $viewed->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
        ]);
    }
}

<?php

namespace App\Livewire\Admin\Settings;

use App\Domain\Typeform\ConnectionManager;
use App\Domain\Typeform\Exceptions\TypeformException;
use App\Models\TypeformConnection;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Typeform extends Component
{
    public string $label = 'Main Typeform account';

    public string $base_url = 'https://api.typeform.com';

    public string $token = '';

    public ?TypeformConnection $connection = null;

    public function mount(ConnectionManager $connections): void
    {
        $this->connection = $connections->current();
        if ($this->connection) {
            $this->label = $this->connection->label;
            $this->base_url = $this->connection->base_url;
        }
    }

    public function save(ConnectionManager $connections): void
    {
        $this->validate([
            'label' => ['required', 'string', 'max:120'],
            'base_url' => ['required', Rule::in(['https://api.typeform.com', 'https://api.eu.typeform.com'])],
            'token' => ['required', 'string', 'min:20'],
        ]);

        /** @var User $user */
        $user = auth()->user();

        try {
            $this->connection = $connections->save($this->label, $this->base_url, $this->token, $user);
        } catch (TypeformException $e) {
            $this->addError('token', 'Typeform rejected the token: '.$e->getMessage());

            return;
        }

        $this->token = '';
        session()->flash('status', 'Typeform connected as '.($this->connection->account_alias ?? 'unknown account').'.');
    }

    public function test(ConnectionManager $connections): void
    {
        if ($this->connection === null) {
            return;
        }

        /** @var User $user */
        $user = auth()->user();
        $ok = $connections->test($this->connection, $user);
        $this->connection->refresh();

        session()->flash($ok ? 'status' : 'error', $ok ? 'Connection OK.' : 'Connection failed: '.$this->connection->last_error);
    }

    public function render(): View
    {
        return view('livewire.admin.settings.typeform');
    }
}

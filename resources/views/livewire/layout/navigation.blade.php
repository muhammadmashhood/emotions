<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

@if(auth()->user()->isSuperAdmin())
    @include('livewire.layout.partials.admin-sidebar')
@else
    @include('livewire.layout.partials.hospital-sidebar')
@endif

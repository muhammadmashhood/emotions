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

{{-- The component root must be the first tag on its own line, or Livewire attaches the component to the
     first element inside the partial and wire:click="logout" ends up outside it. --}}
<div>
    @if(auth()->user()->isEmotionStaff())
        @include('livewire.layout.partials.admin-sidebar')
    @else
        @include('livewire.layout.partials.hospital-sidebar')
    @endif
</div>

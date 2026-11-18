<div class="flex flex-col gap-[22px]">
    <div>
        <h1 class="text-2xl font-semibold tracking-[-0.015em]">Set your password</h1>
        <p class="text-[#D9D3D9] mt-1">Your account was created with a temporary password. Choose your own before continuing.</p>
    </div>

    <form wire:submit="save" class="flex flex-col gap-[22px]">
        <div>
            <x-input-label for="password" :value="__('New password')" class="!text-[#D9D3D9]" />
            <x-text-input wire:model="password" id="password" type="password" name="password" placeholder="At least 8 characters" required autofocus autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2 !text-[#FFB4AC]" />
        </div>
        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm password')" class="!text-[#D9D3D9]" />
            <x-text-input wire:model="password_confirmation" id="password_confirmation" type="password" name="password_confirmation" placeholder="Repeat it" required autocomplete="new-password" />
        </div>

        <div class="flex items-center justify-between gap-3">
            <button type="button" wire:click="logout" class="auth-link text-xs">Sign out</button>
            <x-primary-button wire:loading.attr="disabled" wire:target="save">Save and continue</x-primary-button>
        </div>
    </form>
</div>

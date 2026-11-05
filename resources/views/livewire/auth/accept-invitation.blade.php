<div class="flex flex-col gap-[22px]">
    @if($invitation === null)
        <div>
            <h1 class="text-2xl font-semibold tracking-[-0.015em]">This invitation link is invalid or has expired</h1>
            <p class="text-[#D9D3D9] mt-1">Ask your administrator to send a new invitation.</p>
        </div>
        <a href="{{ route('login') }}" wire:navigate class="btn-secondary">Go to sign in</a>
    @else
        <div>
            <h1 class="text-2xl font-semibold tracking-[-0.015em]">Set up your account</h1>
            <p class="text-[#D9D3D9] mt-1">
                You were invited as <strong class="text-white font-medium">{{ $invitation->role->label() }}</strong>
                @if($invitation->hospital) for <strong class="text-white font-medium">{{ $invitation->hospital->name }}</strong>@endif
                ({{ $invitation->email }}).
            </p>
        </div>
        <form wire:submit="accept" class="flex flex-col gap-[22px]">
            <div>
                <label class="label" for="name">Your name</label>
                <input id="name" type="text" wire:model="name" class="field" required autofocus autocomplete="name">
                <x-input-error :messages="$errors->get('name')" class="mt-1 !text-[#FFB4AC]" />
            </div>
            <div>
                <label class="label" for="password">Password</label>
                <input id="password" type="password" wire:model="password" class="field" required autocomplete="new-password">
                <x-input-error :messages="$errors->get('password')" class="mt-1 !text-[#FFB4AC]" />
            </div>
            <div>
                <label class="label" for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" type="password" wire:model="password_confirmation" class="field" required autocomplete="new-password">
            </div>
            <x-input-error :messages="$errors->get('token')" class="!text-[#FFB4AC]" />
            <button type="submit" class="btn-primary">Create account</button>
        </form>
    @endif
</div>

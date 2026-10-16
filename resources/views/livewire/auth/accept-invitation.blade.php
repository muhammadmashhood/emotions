<div>
    @if($invitation === null)
        <h1 class="text-lg font-semibold">This invitation link is invalid or has expired</h1>
        <p class="mt-2 text-sm text-ink-soft">Ask your administrator to send a new invitation.</p>
        <a href="{{ route('login') }}" wire:navigate class="btn-secondary mt-4">Go to login</a>
    @else
        <h1 class="text-lg font-semibold">Set up your account</h1>
        <p class="mt-1 text-sm text-ink-soft">
            You were invited as <strong>{{ $invitation->role->label() }}</strong>
            @if($invitation->hospital) for <strong>{{ $invitation->hospital->name }}</strong>@endif
            ({{ $invitation->email }}).
        </p>
        <form wire:submit="accept" class="mt-4 space-y-4">
            <div>
                <label class="label" for="name">Your name</label>
                <input id="name" type="text" wire:model="name" class="field" required autofocus autocomplete="name">
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>
            <div>
                <label class="label" for="password">Password</label>
                <input id="password" type="password" wire:model="password" class="field" required autocomplete="new-password">
                <x-input-error :messages="$errors->get('password')" class="mt-1" />
            </div>
            <div>
                <label class="label" for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" type="password" wire:model="password_confirmation" class="field" required autocomplete="new-password">
            </div>
            <x-input-error :messages="$errors->get('token')" />
            <button type="submit" class="btn-primary w-full">Create account</button>
        </form>
    @endif
</div>

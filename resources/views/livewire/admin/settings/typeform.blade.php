<div>
    <x-page-header title="Typeform connection" subtitle="The personal access token used to read forms, register webhooks and fetch responses." />
    <x-page class="max-w-3xl">
        <x-flash />

        @if($connection)
            <x-card title="Current connection">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div><dt class="text-ink-soft">Label</dt><dd class="font-medium">{{ $connection->label }}</dd></div>
                    <div><dt class="text-ink-soft">Account</dt><dd class="font-medium">{{ $connection->account_alias ?? '—' }}</dd></div>
                    <div><dt class="text-ink-soft">Base URL</dt><dd class="font-medium">{{ $connection->base_url }}</dd></div>
                    <div><dt class="text-ink-soft">Token</dt><dd class="font-mono">{{ $connection->maskedToken() }}</dd></div>
                    <div><dt class="text-ink-soft">Last verified</dt><dd>{{ $connection->last_verified_at?->diffForHumans() ?? 'never' }}</dd></div>
                    <div><dt class="text-ink-soft">Status</dt><dd>@if($connection->last_error)<x-badge variant="red">Error</x-badge> <span class="text-xs text-ink-soft">{{ $connection->last_error }}</span>@else<x-badge variant="green">Connected</x-badge>@endif</dd></div>
                </dl>
                <div class="mt-4"><button type="button" wire:click="test" class="btn-secondary" wire:loading.attr="disabled">Test connection</button></div>
            </x-card>
        @endif

        <form wire:submit="save" class="panel p-6 space-y-5">
            <h2 class="text-sm font-semibold">{{ $connection ? 'Replace token' : 'Connect Typeform' }}</h2>
            <p class="text-sm text-ink-soft">Create a personal access token in Typeform with scopes <code class="text-xs">accounts:read forms:read workspaces:read webhooks:read webhooks:write responses:read</code>. It is verified, then stored encrypted and never shown again.</p>
            <div>
                <label class="label" for="label">Label</label>
                <input id="label" type="text" wire:model="label" class="field" required>
                <x-input-error :messages="$errors->get('label')" class="mt-1" />
            </div>
            <div>
                <label class="label" for="base_url">Region</label>
                <select id="base_url" wire:model="base_url" class="field">
                    <option value="https://api.typeform.com">Global (api.typeform.com)</option>
                    <option value="https://api.eu.typeform.com">EU (api.eu.typeform.com)</option>
                </select>
                <x-input-error :messages="$errors->get('base_url')" class="mt-1" />
            </div>
            <div>
                <label class="label" for="token">Personal access token</label>
                <input id="token" type="password" wire:model="token" class="field" autocomplete="off" required placeholder="tfp_…">
                <x-input-error :messages="$errors->get('token')" class="mt-1" />
            </div>
            <div class="flex justify-end"><button type="submit" class="btn-primary" wire:loading.attr="disabled">Verify and save</button></div>
        </form>
    </x-page>
</div>

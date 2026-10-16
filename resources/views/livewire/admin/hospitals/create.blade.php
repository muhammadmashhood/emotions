<div>
    <x-page-header title="New hospital" />
    <x-page class="max-w-2xl">
        <form wire:submit="save" class="panel p-6 space-y-5">
            <div>
                <label class="label" for="name">Hospital name</label>
                <input id="name" type="text" wire:model="name" class="field" required autofocus>
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>
            <div>
                <label class="label" for="logo">Logo (optional, PNG/JPG up to 2 MB)</label>
                <input id="logo" type="file" wire:model="logo" accept="image/*" class="block text-sm text-ink-soft">
                <x-input-error :messages="$errors->get('logo')" class="mt-1" />
                <div wire:loading wire:target="logo" class="text-xs text-ink-soft mt-1">Uploading…</div>
            </div>
            <label class="flex items-start gap-3">
                <input type="checkbox" wire:model="require_review" class="mt-1 rounded border-line text-ink focus:ring-ink">
                <span>
                    <span class="block text-sm font-medium text-ink">Require review before evaluations are shown</span>
                    <span class="block text-xs text-ink-soft">New evaluations wait in the admin review queue until approved.</span>
                </span>
            </label>
            <div class="flex items-center justify-end gap-2">
                <a href="{{ route('admin.hospitals.index') }}" wire:navigate class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">Create hospital</button>
            </div>
        </form>
    </x-page>
</div>

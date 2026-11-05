<x-ui.page>
    <x-ui.header title="New hospital" :back="route('admin.hospitals.index')" back-label="Hospitals" subtitle="Create the client account. Link forms and invite its users next." />

    <form wire:submit="save" class="bg-white border border-em-line rounded-[20px] p-6 flex flex-col gap-5 max-w-[520px]">
        <div>
            <label class="label" for="name">Hospital name</label>
            <input id="name" type="text" wire:model="name" class="field py-2.5" placeholder="e.g. Marina Health Group" required autofocus>
            <x-input-error :messages="$errors->get('name')" class="mt-1" />
        </div>
        <div>
            <span class="label">Logo</span>
            <label for="logo" class="flex items-center gap-3.5 p-3 rounded-xl border border-dashed border-em-field bg-[#FCFBFA] cursor-pointer hover:border-em-hover">
                @if($logo?->isPreviewable())
                    <img src="{{ $logo->temporaryUrl() }}" alt="" class="h-10 w-10 rounded-[10px] object-contain bg-white border border-em-line p-1">
                @else
                    <span class="h-10 w-10 rounded-[10px] bg-[#7470F0] text-white flex items-center justify-center font-semibold" aria-hidden="true">?</span>
                @endif
                <span class="text-[13px] text-em-soft">Upload logo (PNG or JPG, up to 2 MB). Until then the initial is used.</span>
                <input id="logo" type="file" wire:model="logo" accept="image/*" class="sr-only">
            </label>
            <x-input-error :messages="$errors->get('logo')" class="mt-1" />
            <div wire:loading wire:target="logo" class="text-xs text-em-soft mt-1">Uploading…</div>
        </div>
        <label class="flex items-start gap-3">
            <input type="checkbox" wire:model="require_review" class="mt-0.5 rounded border-em-hover text-em-ink focus:ring-em-accent">
            <span>
                <span class="block text-[13px] font-medium">Require review before evaluations are shown</span>
                <span class="block text-xs text-em-soft">New evaluations wait in the review queue until approved.</span>
            </span>
        </label>
        <div class="flex items-center justify-end gap-2">
            <a href="{{ route('admin.hospitals.index') }}" wire:navigate class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Create hospital</button>
        </div>
    </form>
</x-ui.page>

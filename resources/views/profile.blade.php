<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-ink">Profile</h1>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div class="panel p-6">
            <livewire:profile.update-profile-information-form />
        </div>

        <div class="panel p-6">
            <livewire:profile.update-password-form />
        </div>
    </div>
</x-app-layout>

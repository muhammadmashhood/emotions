<x-app-layout>
    <x-ui.page>
        <x-ui.header title="Your profile" eyebrow="Account" subtitle="Your name, email address and password." />
        <div class="max-w-3xl flex flex-col gap-5">
            <div class="panel p-6">
                <livewire:profile.update-profile-information-form />
            </div>
            <div class="panel p-6">
                <livewire:profile.update-password-form />
            </div>
        </div>
    </x-ui.page>
</x-app-layout>

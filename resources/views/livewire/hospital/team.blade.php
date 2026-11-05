<div>
    <x-ui.page wide>
        <x-ui.header title="Team" :eyebrow="$hospital->name" subtitle="People who can see {{ $hospital->name }}'s evaluations." />
        <livewire:shared.user-management :hospital="$hospital" />
    </x-ui.page>
</div>

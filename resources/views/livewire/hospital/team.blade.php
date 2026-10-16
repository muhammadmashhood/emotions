<div>
    <x-page-header title="Team" subtitle="People who can see {{ $hospital->name }}'s evaluations." />
    <x-page>
        <livewire:shared.user-management :hospital="$hospital" />
    </x-page>
</div>

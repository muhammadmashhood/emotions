<div>
    <x-page-header title="Users" subtitle="All accounts across hospitals." />
    <x-page>
        <x-flash />
        <x-card title="Invite a user">
            <form wire:submit="invite" class="grid grid-cols-1 sm:grid-cols-4 gap-3 sm:items-end">
                <div class="sm:col-span-2">
                    <label class="label" for="inviteEmail">Email</label>
                    <input id="inviteEmail" type="email" wire:model="inviteEmail" class="field" required>
                    <x-input-error :messages="$errors->get('inviteEmail')" class="mt-1" />
                </div>
                <div>
                    <label class="label" for="inviteRole">Role</label>
                    <select id="inviteRole" wire:model.live="inviteRole" class="field">
                        @foreach($roles as $r)<option value="{{ $r->value }}">{{ $r->label() }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="inviteHospital">Hospital</label>
                    <select id="inviteHospital" wire:model="inviteHospital" class="field" @disabled($inviteRole === 'super_admin')>
                        <option value="">— none (super admin) —</option>
                        @foreach($hospitals as $h)<option value="{{ $h->uuid }}">{{ $h->name }}</option>@endforeach
                    </select>
                    <x-input-error :messages="$errors->get('inviteHospital')" class="mt-1" />
                    <x-input-error :messages="$errors->get('hospital_id')" class="mt-1" />
                    <x-input-error :messages="$errors->get('role')" class="mt-1" />
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>
                <div class="sm:col-span-4 flex justify-end"><button type="submit" class="btn-primary">Send invitation</button></div>
            </form>
        </x-card>

        <div class="flex flex-wrap gap-3">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name or email…" class="field max-w-xs" aria-label="Search users">
            <select wire:model.live="role" class="field w-auto" aria-label="Filter by role">
                <option value="">All roles</option>
                @foreach($roles as $r)<option value="{{ $r->value }}">{{ $r->label() }}</option>@endforeach
            </select>
            <select wire:model.live="hospital" class="field w-auto" aria-label="Filter by hospital">
                <option value="">All hospitals</option>
                @foreach($hospitals as $h)<option value="{{ $h->uuid }}">{{ $h->name }}</option>@endforeach
            </select>
        </div>

        <div class="panel overflow-x-auto">
            <table class="table-base">
                <thead><tr><th>Name</th><th>Email</th><th>Hospital</th><th>Role</th><th>Status</th><th>Last login</th><th></th></tr></thead>
                <tbody>
                @forelse($users as $user)
                    <tr wire:key="u-{{ $user->id }}">
                        <td class="font-medium">{{ $user->name }}</td>
                        <td class="text-ink-soft">{{ $user->email }}</td>
                        <td>{{ $user->hospital?->name ?? '—' }}</td>
                        <td>
                            @if($user->isSuperAdmin())
                                <x-badge variant="ink">Super admin</x-badge>
                            @else
                                <select class="field py-1 text-xs w-auto" aria-label="Role for {{ $user->name }}" wire:change="changeRole({{ $user->id }}, $event.target.value)">
                                    @foreach(\App\Enums\Role::hospitalRoles() as $r)
                                        <option value="{{ $r->value }}" @selected($user->role === $r)>{{ $r->label() }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </td>
                        <td>@if($user->is_active)<x-badge variant="green">Active</x-badge>@else<x-badge variant="red">Inactive</x-badge>@endif</td>
                        <td class="text-ink-soft">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                        <td class="text-right">
                            @if($user->id !== auth()->id())
                                <button type="button" wire:click="toggleActive({{ $user->id }})" @if($user->is_active) wire:confirm="Deactivate {{ $user->name }}?" @endif class="text-sm underline {{ $user->is_active ? 'text-rag-red' : 'text-ink' }}">{{ $user->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                            @else
                                <span class="text-xs text-ink-soft">You</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-ink-soft py-6">No users match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $users->links() }}

        @if($invitations->isNotEmpty())
            <x-card title="Pending invitations" :padding="false">
                <table class="table-base">
                    <thead><tr><th>Email</th><th>Hospital</th><th>Role</th><th>Expires</th><th></th></tr></thead>
                    <tbody>
                    @foreach($invitations as $invitation)
                        <tr wire:key="i-{{ $invitation->id }}">
                            <td>{{ $invitation->email }}</td>
                            <td>{{ $invitation->hospital?->name ?? '—' }}</td>
                            <td>{{ $invitation->role->label() }}</td>
                            <td class="text-ink-soft">@if($invitation->isExpired())<x-badge variant="amber">Expired</x-badge>@else{{ $invitation->expires_at->diffForHumans() }}@endif</td>
                            <td class="text-right"><button type="button" wire:click="resend({{ $invitation->id }})" class="text-sm underline">Resend</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </x-card>
        @endif
    </x-page>
</div>

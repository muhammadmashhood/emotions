<div x-data="{ inviting: {{ request()->boolean('invite') ? 'true' : 'false' }} }" x-on:invite-sent.window="inviting = false" x-on:keydown.escape.window="inviting = false">
<x-admin.page>
    <x-admin.header title="Users & invitations" subtitle="All accounts across hospitals, plus eMotion staff.">
        <x-slot name="actions">
            <button type="button" class="btn btn-primary" x-on:click="inviting = true">Invite user</button>
        </x-slot>
    </x-admin.header>

    <x-admin.flash />

    <div class="bg-white border border-adm-line rounded-2xl p-3 flex flex-wrap gap-2.5">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name or email…" class="field flex-1 min-w-56" aria-label="Search users">
        <select wire:model.live="hospital" class="field w-auto min-w-52" aria-label="Filter by hospital">
            <option value="">All hospitals</option>
            @foreach($hospitals as $h)<option value="{{ $h->uuid }}">{{ $h->name }}</option>@endforeach
        </select>
        <select wire:model.live="role" class="field w-auto min-w-40" aria-label="Filter by role">
            <option value="">All roles</option>
            @foreach($roles as $r)<option value="{{ $r->value }}">{{ $r === \App\Enums\Role::SuperAdmin ? 'eMotion admin' : $r->label() }}</option>@endforeach
        </select>
    </div>

    <div class="panel overflow-x-auto">
        <table class="table-base">
            <thead><tr><th>User</th><th>Hospital</th><th>Role</th><th>Status</th><th>Last login</th><th><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
            @foreach($invitations as $invitation)
                <tr wire:key="i-{{ $invitation->id }}">
                    <td>
                        <div class="flex items-center gap-3">
                            <span class="h-[30px] w-[30px] rounded-full bg-adm-accent-soft text-adm-accent flex items-center justify-center text-[11px] font-semibold shrink-0" aria-hidden="true">{{ mb_strtoupper(mb_substr($invitation->email, 0, 1)) }}</span>
                            <div class="min-w-0"><div class="font-medium truncate">{{ $invitation->email }}</div><div class="text-xs text-adm-soft">Invited {{ $invitation->created_at?->diffForHumans() }}</div></div>
                        </div>
                    </td>
                    <td>{{ $invitation->hospital?->name ?? 'eMotion' }}</td>
                    <td>{{ $invitation->role === \App\Enums\Role::SuperAdmin ? 'eMotion admin' : $invitation->role->label() }}</td>
                    <td>@if($invitation->isExpired())<x-admin.pill tone="warn">Expired</x-admin.pill>@else<x-admin.pill tone="info">Invited</x-admin.pill>@endif</td>
                    <td class="text-adm-soft">–</td>
                    <td class="text-right"><button type="button" wire:click="resend({{ $invitation->id }})" class="btn btn-secondary btn-sm">Resend</button></td>
                </tr>
            @endforeach
            @forelse($users as $user)
                <tr wire:key="u-{{ $user->id }}">
                    <td>
                        <div class="flex items-center gap-3">
                            <span class="h-[30px] w-[30px] rounded-full bg-adm-muted-bg text-adm-ink flex items-center justify-center text-[11px] font-semibold shrink-0" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                            <div class="min-w-0"><div class="font-medium truncate">{{ $user->name }}</div><div class="text-xs text-adm-soft truncate">{{ $user->email }}</div></div>
                        </div>
                    </td>
                    <td>{{ $user->hospital?->name ?? 'eMotion' }}</td>
                    <td>
                        @if($user->isSuperAdmin())
                            eMotion admin
                        @else
                            <select class="field py-1 pl-2 pr-8 w-auto !border-transparent hover:!border-adm-field !bg-transparent" aria-label="Role for {{ $user->name }}" wire:change="changeRole({{ $user->id }}, $event.target.value)">
                                @foreach(\App\Enums\Role::hospitalRoles() as $r)
                                    <option value="{{ $r->value }}" @selected($user->role === $r)>{{ $r->label() }}</option>
                                @endforeach
                            </select>
                        @endif
                    </td>
                    <td>@if($user->is_active)<x-admin.pill tone="ok">Active</x-admin.pill>@else<x-admin.pill>Deactivated</x-admin.pill>@endif</td>
                    <td class="text-adm-soft whitespace-nowrap">@if($user->id === auth()->id())
                            Now
                        @elseif($user->last_login_at)
                            <x-admin.when :at="$user->last_login_at" />
                        @else
                            Never
                        @endif</td>
                    <td class="text-right">
                        @if($user->id !== auth()->id())
                            <button type="button" wire:click="toggleActive({{ $user->id }})" @if($user->is_active) wire:confirm="Deactivate {{ $user->name }}?" @endif class="btn btn-secondary btn-sm">{{ $user->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                        @endif
                    </td>
                </tr>
            @empty
                @if($invitations->isEmpty())
                    <tr><td colspan="6" class="text-center text-adm-soft py-8">No users match these filters.</td></tr>
                @endif
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $users->links() }}
</x-admin.page>

    {{-- Invite dialog --}}
    <div x-show="inviting" x-cloak class="fixed inset-0 z-50 flex items-start sm:items-center justify-center p-4 bg-adm-ink/30" x-transition.opacity>
        <form wire:submit="invite" x-on:click.outside="inviting = false" role="dialog" aria-modal="true" aria-labelledby="invite-title"
              class="theme-admin w-full max-w-[460px] bg-white rounded-[20px] p-6 flex flex-col gap-4 shadow-[0_24px_64px_-24px_rgba(43,31,38,0.35)]">
            <div>
                <h2 id="invite-title" class="text-lg font-semibold">Invite user</h2>
                <p class="text-[13px] text-adm-soft">They'll get an email link to set a password.</p>
            </div>
            <div>
                <label class="label" for="inviteEmail">Email</label>
                <input id="inviteEmail" type="email" wire:model="inviteEmail" class="field py-2.5" placeholder="name@hospital.com" required>
                <x-input-error :messages="$errors->get('inviteEmail')" class="mt-1" />
                <x-input-error :messages="$errors->get('email')" class="mt-1" />
            </div>
            <div>
                <label class="label" for="inviteHospital">Hospital</label>
                <select id="inviteHospital" wire:model="inviteHospital" class="field py-2.5" @disabled($inviteRole === 'super_admin')>
                    <option value="">{{ $inviteRole === 'super_admin' ? 'eMotion (staff account)' : 'Choose a hospital…' }}</option>
                    @foreach($hospitals as $h)<option value="{{ $h->uuid }}">{{ $h->name }}</option>@endforeach
                </select>
                <x-input-error :messages="$errors->get('inviteHospital')" class="mt-1" />
                <x-input-error :messages="$errors->get('hospital_id')" class="mt-1" />
            </div>
            <fieldset>
                <legend class="label">Role</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach([\App\Enums\Role::HospitalAdmin, \App\Enums\Role::HospitalViewer, \App\Enums\Role::SuperAdmin] as $r)
                        <label class="cursor-pointer">
                            <input type="radio" wire:model.live="inviteRole" value="{{ $r->value }}" class="sr-only peer">
                            <span class="block px-3.5 py-1.5 rounded-full border text-[13px] border-adm-field peer-checked:bg-adm-ink peer-checked:border-adm-ink peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-adm-accent">{{ $r === \App\Enums\Role::SuperAdmin ? 'eMotion admin' : $r->label() }}</span>
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('role')" class="mt-1" />
            </fieldset>
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" class="btn btn-secondary" x-on:click="inviting = false">Cancel</button>
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="invite">Send invitation</button>
            </div>
        </form>
    </div>
</div>

<div class="flex flex-col gap-5">
    <x-ui.flash />
    @if($createdPassword !== null)
        <div class="rounded-2xl border border-em-ok/30 bg-em-ok-bg px-5 py-4 flex flex-col gap-2" role="status" x-data="{ copied: false }">
            <div class="text-[13px] font-semibold text-em-ink">Temporary password for {{ $createdEmail }}</div>
            <div class="flex flex-wrap items-center gap-2">
                <code class="px-3 py-1.5 rounded-lg bg-white border border-em-line text-base tracking-wider select-all">{{ $createdPassword }}</code>
                <button type="button" class="btn btn-secondary btn-sm" x-on:click="navigator.clipboard.writeText(@js($createdPassword)).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"><span x-text="copied ? 'Copied' : 'Copy'">Copy</span></button>
                <button type="button" class="btn btn-secondary btn-sm" wire:click="dismissCreated">Done</button>
            </div>
            <p class="text-xs text-em-soft">It is shown only once. Copy it now and pass it on yourself; they must choose their own password when they first sign in.</p>
        </div>
    @endif
    <section class="panel p-4 flex flex-col gap-3">
        <div class="inline-flex p-[3px] gap-0.5 bg-em-bg border border-em-line rounded-xl w-fit" role="tablist" aria-label="How to add a user">
            @foreach(['invite' => 'Email an invitation', 'create' => 'Set a temporary password'] as $key => $label)
                <button type="button" wire:click="$set('mode', '{{ $key }}')" role="tab" aria-selected="{{ $mode === $key ? 'true' : 'false' }}"
                        class="px-3.5 py-1.5 rounded-[9px] text-[13px] {{ $mode === $key ? 'bg-em-ink text-white font-semibold' : 'text-em-soft hover:text-em-ink' }}">{{ $label }}</button>
            @endforeach
        </div>
        <form wire:submit="{{ $mode === 'create' ? 'create' : 'invite' }}" class="flex flex-col sm:flex-row sm:flex-wrap gap-2.5 sm:items-end">
            @if($mode === 'create')
                <div class="flex-1 min-w-[12rem]">
                    <label class="label" for="createName">Full name</label>
                    <input id="createName" type="text" wire:model="createName" class="field" placeholder="Name as it should appear" required>
                    <x-input-error :messages="$errors->get('createName')" class="mt-1" />
                </div>
            @endif
            <div class="flex-1 min-w-[14rem]">
                <label class="label" for="inviteEmail">{{ $mode === 'create' ? 'Email' : 'Invite by email' }}</label>
                <input id="inviteEmail" type="email" wire:model="inviteEmail" class="field" placeholder="name@hospital.example" required>
                <x-input-error :messages="$errors->get('inviteEmail')" class="mt-1" />
            </div>
            <fieldset>
                <legend class="label">Role</legend>
                <div class="flex gap-1.5">
                    @foreach($roles as $role)
                        <label class="cursor-pointer">
                            <input type="radio" wire:model="inviteRole" value="{{ $role->value }}" class="sr-only peer">
                            <span class="block px-3.5 py-[9px] rounded-[10px] border text-[13px] border-em-field peer-checked:bg-em-ink peer-checked:border-em-ink peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-em-accent">{{ $role->label() }}</span>
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('inviteRole')" class="mt-1" />
            </fieldset>
            @if($mode === 'create')
                <div class="flex-1 min-w-[14rem]">
                    <label class="label" for="createPassword">Temporary password</label>
                    <div class="flex gap-2">
                        <input id="createPassword" type="text" wire:model="createPassword" class="field font-mono" placeholder="At least 8 characters" autocomplete="off" required>
                        <button type="button" class="btn-secondary shrink-0" wire:click="generatePassword">Generate</button>
                    </div>
                    <x-input-error :messages="$errors->get('createPassword')" class="mt-1" />
                </div>
                <button type="submit" class="btn-primary">Create user</button>
            @else
                <button type="submit" class="btn-primary">Send invitation</button>
            @endif
        </form>
        @if($mode === 'create')
            <p class="text-xs text-em-soft">Share the password with them yourself. They must change it when they first sign in.</p>
        @endif
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </section>

    <div class="panel overflow-x-auto">
        <table class="table-base">
            <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Last login</th><th><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
            @foreach($invitations as $invitation)
                <tr wire:key="i-{{ $invitation->id }}">
                    <td>
                        <div class="flex items-center gap-3">
                            <span class="h-[30px] w-[30px] rounded-full bg-em-accent-soft text-em-accent flex items-center justify-center text-[11px] font-semibold shrink-0" aria-hidden="true">{{ mb_strtoupper(mb_substr($invitation->email, 0, 1)) }}</span>
                            <div class="min-w-0"><div class="font-medium truncate">{{ $invitation->email }}</div><div class="text-xs text-em-soft">@if($invitation->isExpired())Invitation expired @else Invitation expires {{ $invitation->expires_at->diffForHumans() }}@endif</div></div>
                        </div>
                    </td>
                    <td>{{ $invitation->role->label() }}</td>
                    <td>@if($invitation->isExpired())<x-ui.pill tone="warn">Expired</x-ui.pill>@else<x-ui.pill tone="info">Invited</x-ui.pill>@endif</td>
                    <td class="text-em-soft">–</td>
                    <td class="text-right whitespace-nowrap space-x-1.5">
                        <button type="button" wire:click="resend({{ $invitation->id }})" class="btn-secondary btn-sm">Resend</button>
                        <button type="button" wire:click="cancelInvitation({{ $invitation->id }})" class="btn-danger btn-sm">Cancel</button>
                    </td>
                </tr>
            @endforeach
            @forelse($users as $user)
                <tr wire:key="u-{{ $user->id }}">
                    <td>
                        <div class="flex items-center gap-3">
                            <span class="h-[30px] w-[30px] rounded-full bg-em-muted-bg flex items-center justify-center text-[11px] font-semibold shrink-0" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                            <div class="min-w-0"><div class="font-medium truncate">{{ $user->name }}</div><div class="text-xs text-em-soft truncate">{{ $user->email }}</div></div>
                        </div>
                    </td>
                    <td>
                        <label class="sr-only" for="role-{{ $user->id }}">Role for {{ $user->name }}</label>
                        <select id="role-{{ $user->id }}" class="field py-1 pl-2 pr-8 w-auto !border-transparent hover:!border-em-field !bg-transparent" wire:change="changeRole({{ $user->id }}, $event.target.value)" @disabled($user->id === auth()->id())>
                            @foreach($roles as $role)
                                <option value="{{ $role->value }}" @selected($user->role === $role)>{{ $role->label() }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>@if($user->is_active)<x-ui.pill tone="ok">Active</x-ui.pill>@else<x-ui.pill>Deactivated</x-ui.pill>@endif</td>
                    <td class="text-em-soft whitespace-nowrap">@if($user->id === auth()->id()) Now @elseif($user->last_login_at)<x-ui.when :at="$user->last_login_at" />@else Never @endif</td>
                    <td class="text-right">
                        @if($user->id !== auth()->id())
                            @if($user->is_active)
                                <button type="button" wire:click="deactivate({{ $user->id }})" wire:confirm="Deactivate {{ $user->name }}? They will be logged out and cannot log in." class="btn-secondary btn-sm">Deactivate</button>
                            @else
                                <button type="button" wire:click="reactivate({{ $user->id }})" class="btn-secondary btn-sm">Reactivate</button>
                            @endif
                        @else
                            <span class="text-xs text-em-mute">You</span>
                        @endif
                    </td>
                </tr>
            @empty
                @if($invitations->isEmpty())
                    <tr><td colspan="5" class="text-center text-em-soft py-8">No users yet. Invite the first one above.</td></tr>
                @endif
            @endforelse
            </tbody>
        </table>
    </div>
</div>

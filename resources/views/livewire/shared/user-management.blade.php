<div class="flex flex-col gap-5">
    <x-ui.flash />
    <section class="panel p-4">
        <form wire:submit="invite" class="flex flex-col sm:flex-row gap-2.5 sm:items-end">
            <div class="flex-1">
                <label class="label" for="inviteEmail">Invite by email</label>
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
            <button type="submit" class="btn-primary">Send invitation</button>
        </form>
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

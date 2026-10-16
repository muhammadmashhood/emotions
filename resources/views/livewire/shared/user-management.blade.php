<div class="space-y-6">
    <x-flash />
    <x-card title="Invite a user">
        <form wire:submit="invite" class="flex flex-col sm:flex-row gap-3 sm:items-end">
            <div class="flex-1">
                <label class="label" for="inviteEmail">Email</label>
                <input id="inviteEmail" type="email" wire:model="inviteEmail" class="field" placeholder="name@hospital.example" required>
                <x-input-error :messages="$errors->get('inviteEmail')" class="mt-1" />
            </div>
            <div>
                <label class="label" for="inviteRole">Role</label>
                <select id="inviteRole" wire:model="inviteRole" class="field">
                    @foreach($roles as $role)
                        <option value="{{ $role->value }}">{{ $role->label() }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('inviteRole')" class="mt-1" />
            </div>
            <button type="submit" class="btn-primary">Send invitation</button>
        </form>
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </x-card>

    <x-card title="Users" :padding="false">
        @if($users->isEmpty())
            <p class="p-4 text-sm text-ink-soft">No users yet. Invite the first one above.</p>
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th></th></tr></thead>
                    <tbody>
                    @foreach($users as $user)
                        <tr wire:key="u-{{ $user->id }}">
                            <td class="font-medium">{{ $user->name }}</td>
                            <td class="text-ink-soft">{{ $user->email }}</td>
                            <td>
                                <label class="sr-only" for="role-{{ $user->id }}">Role for {{ $user->name }}</label>
                                <select id="role-{{ $user->id }}" class="field py-1 text-xs w-auto" wire:change="changeRole({{ $user->id }}, $event.target.value)" @disabled($user->id === auth()->id())>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->value }}" @selected($user->role === $role)>{{ $role->label() }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>@if($user->is_active)<x-badge variant="green">Active</x-badge>@else<x-badge variant="red">Inactive</x-badge>@endif</td>
                            <td class="text-ink-soft">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                            <td class="text-right">
                                @if($user->id !== auth()->id())
                                    @if($user->is_active)
                                        <button type="button" wire:click="deactivate({{ $user->id }})" wire:confirm="Deactivate {{ $user->name }}? They will be logged out and cannot log in." class="text-sm text-rag-red underline">Deactivate</button>
                                    @else
                                        <button type="button" wire:click="reactivate({{ $user->id }})" class="text-sm text-ink underline">Reactivate</button>
                                    @endif
                                @else
                                    <span class="text-xs text-ink-soft">You</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    @if($invitations->isNotEmpty())
        <x-card title="Pending invitations" :padding="false">
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Email</th><th>Role</th><th>Expires</th><th></th></tr></thead>
                    <tbody>
                    @foreach($invitations as $invitation)
                        <tr wire:key="i-{{ $invitation->id }}">
                            <td>{{ $invitation->email }}</td>
                            <td>{{ $invitation->role->label() }}</td>
                            <td class="text-ink-soft">@if($invitation->isExpired())<x-badge variant="amber">Expired</x-badge>@else{{ $invitation->expires_at->diffForHumans() }}@endif</td>
                            <td class="text-right space-x-3">
                                <button type="button" wire:click="resend({{ $invitation->id }})" class="text-sm underline">Resend</button>
                                <button type="button" wire:click="cancelInvitation({{ $invitation->id }})" class="text-sm text-rag-red underline">Cancel</button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
</div>

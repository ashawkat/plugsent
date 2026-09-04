<x-filament-panels::page>
    @php
        $members = $this->members();
        $pending = $this->pendingInvitations();
        $canManage = $this->canManageTeam();
    @endphp

    {{-- Members --}}
    <div class="plugsent-category">
        <div class="plugsent-category-head">
            <h2>Members ({{ $members->count() }})</h2>
        </div>
        <div class="plugsent-table-wrap">
            <table class="plugsent-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($members as $member)
                        @php $isOwner = $this->workspace->owner_id === $member->id; @endphp
                        <tr>
                            <td>
                                <div class="plugsent-item-name">{{ $member->name }} @if($isOwner)<span class="plugsent-state plugsent-state-active">owner</span>@endif</div>
                                <div class="plugsent-item-slug">{{ $member->email }}</div>
                            </td>
                            <td>{{ $member->email }}</td>
                            <td>
                                <span class="plugsent-state plugsent-state-active">{{ $member->pivot->role }}</span>
                            </td>
                            <td class="plugsent-cell-actions">
                                @if($canManage && ! $isOwner && $member->id !== auth()->id())
                                    <select class="plugsent-select"
                                            wire:change="changeRole({{ $member->id }}, $event.target.value)">
                                        @foreach(['member', 'admin'] as $r)
                                            <option value="{{ $r }}" @selected($member->pivot->role === $r)>{{ ucfirst($r) }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="plugsent-btn"
                                            wire:click="removeMember({{ $member->id }})"
                                            wire:confirm="Remove {{ $member->name }} from this workspace?">
                                        Remove
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pending invitations --}}
    @if($canManage)
        <div class="plugsent-category">
            <div class="plugsent-category-head">
                <h2>Pending invitations ({{ $pending->count() }})</h2>
            </div>
            <div class="plugsent-table-wrap">
                <table class="plugsent-table">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Expires</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pending as $invitation)
                            <tr>
                                <td>{{ $invitation->email }}</td>
                                <td><span class="plugsent-state plugsent-state-active">{{ $invitation->role }}</span></td>
                                <td class="plugsent-muted">expires {{ $invitation->expires_at->diffForHumans() }}</td>
                                <td class="plugsent-cell-actions">
                                    <button type="button" class="plugsent-btn plugsent-copy-btn"
                                            x-data
                                            x-on:click="navigator.clipboard.writeText(@js(route('invitations.show', ['token' => $invitation->token]))); $el.textContent = 'Copied!'; setTimeout(() => $el.textContent = 'Copy link', 1500)">
                                        Copy link
                                    </button>
                                    <button type="button" class="plugsent-btn"
                                            wire:click="revokeInvitation({{ $invitation->id }})"
                                            wire:confirm="Revoke the invitation for {{ $invitation->email }}?">
                                        Revoke
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="plugsent-empty">No pending invitations.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="plugsent-category">
            <div class="plugsent-category-head">
                <h2>Invite a teammate</h2>
            </div>
            <div class="plugsent-invite-form">
                <input type="email" class="plugsent-input" placeholder="teammate@example.com"
                       wire:model.lazy="inviteEmail" />
                <select class="plugsent-select" wire:model.lazy="inviteRole">
                    <option value="member">Member</option>
                    <option value="admin">Admin</option>
                </select>
                <button type="button" class="plugsent-btn plugsent-btn-primary" wire:click="invite">
                    Send invitation
                </button>
            </div>
            <p class="plugsent-note">They get an email with a 7-day accept link. Members see open projects; admins manage the workspace.</p>
        </div>
    @endif
</x-filament-panels::page>

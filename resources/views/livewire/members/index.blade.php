<div class="flex w-full flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Members') }}</flux:heading>
            <flux:text class="mt-1">{{ $club->name }}</flux:text>
        </div>

        <flux:modal.trigger name="add-member">
            <flux:button variant="primary" icon="user-plus">{{ __('Add member') }}</flux:button>
        </flux:modal.trigger>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by name or email...')" clearable />

    <x-table-card>
    <flux:table :paginate="$this->memberships">
        <flux:table.columns>
            <flux:table.column>#</flux:table.column>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Email') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Member since') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->memberships as $membership)
                <flux:table.row :key="$membership->id">
                    <flux:table.cell variant="strong">{{ $membership->membership_number }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:link :href="route('clubs.members.show', [$club, $membership])" wire:navigate variant="ghost"><x-user-chip :user="$membership->user" /></flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $membership->user->email }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="match($membership->status) {
                            \App\Enums\MembershipStatus::Active => 'green',
                            \App\Enums\MembershipStatus::Paused => 'yellow',
                            \App\Enums\MembershipStatus::Ended => 'zinc',
                        }">{{ $membership->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $membership->joined_at->format('d-m-Y') }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                            <flux:menu>
                                @if ($membership->status !== \App\Enums\MembershipStatus::Active)
                                    <flux:menu.item wire:click="updateStatus({{ $membership->id }}, 'active')" icon="play">
                                        {{ __('Reactivate') }}
                                    </flux:menu.item>
                                @endif
                                @if ($membership->status === \App\Enums\MembershipStatus::Active)
                                    <flux:menu.item wire:click="updateStatus({{ $membership->id }}, 'paused')" icon="pause">
                                        {{ __('Pause') }}
                                    </flux:menu.item>
                                @endif
                                @if ($membership->status !== \App\Enums\MembershipStatus::Ended)
                                    <flux:menu.item wire:click="updateStatus({{ $membership->id }}, 'ended')" icon="x-mark" variant="danger">
                                        {{ __('End membership') }}
                                    </flux:menu.item>
                                @endif
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
    </x-table-card>

    @if ($this->pendingInvitations->isNotEmpty())
        <div class="flex flex-col gap-3">
            <flux:heading size="lg">{{ __('Pending invitations') }}</flux:heading>

            <x-table-card>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Email') }}</flux:table.column>
                    <flux:table.column>{{ __('Invited') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->pendingInvitations as $invitation)
                        <flux:table.row :key="'invitation-'.$invitation->id">
                            <flux:table.cell variant="strong">{{ $invitation->email }}</flux:table.cell>
                            <flux:table.cell>{{ $invitation->created_at->timezone($club->timezone)->isoFormat('D MMM YYYY') }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$invitation->isExpired() ? 'red' : 'yellow'">
                                    {{ $invitation->isExpired() ? __('Expired') : __('Pending') }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="flex justify-end gap-2">
                                <flux:button variant="ghost" size="sm" wire:click="resendInvitation({{ $invitation->id }})">
                                    {{ __('Resend') }}
                                </flux:button>
                                <flux:button variant="ghost" size="sm" wire:click="revokeInvitation({{ $invitation->id }})">
                                    {{ __('Revoke') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
            </x-table-card>
        </div>
    @endif

    <flux:modal name="add-member" class="md:w-96">
        <form wire:submit="addMember" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add member') }}</flux:heading>
                <flux:text class="mt-2">{{ __('An existing account is linked immediately; anyone else receives an email invitation.') }}</flux:text>
            </div>

            <flux:input wire:model="email" :label="__('Email address')" type="email" required autofocus />

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Add member') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

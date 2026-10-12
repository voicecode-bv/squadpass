<div class="flex w-full flex-col gap-6">
    <x-portal.hero :title="__('Trainers')" :subtitle="$club->name">
        <x-slot:actions>
            <flux:modal.trigger name="invite-trainer">
                <flux:button variant="primary" icon="envelope">{{ __('Invite trainer') }}</flux:button>
            </flux:modal.trigger>
        </x-slot:actions>
    </x-portal.hero>

    @if ($this->trainers->isEmpty() && $this->pendingInvitations->isEmpty())
        <flux:callout icon="academic-cap">
            <flux:callout.heading>{{ __('No trainers yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Invite trainers by email. They accept the invitation with an existing or new account.') }}</flux:callout.text>
        </flux:callout>
    @endif

    @if ($this->trainers->isNotEmpty())
        <x-table-card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                <flux:table.column>{{ __('Lessons') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->trainers as $trainer)
                    <flux:table.row :key="$trainer->id">
                        <flux:table.cell class="sp-strong">{{ $trainer->user->name }}</flux:table.cell>
                        <flux:table.cell>{{ $trainer->user->email }}</flux:table.cell>
                        <flux:table.cell>{{ $trainer->lessons_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$trainer->active ? 'green' : 'zinc'">
                                {{ $trainer->active ? __('Active') : __('Inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button variant="ghost" size="sm" wire:click="toggleActive({{ $trainer->id }})">
                                {{ $trainer->active ? __('Deactivate') : __('Activate') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
        </x-table-card>
    @endif

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
                            <flux:table.cell class="sp-strong">{{ $invitation->email }}</flux:table.cell>
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

    <flux:modal name="invite-trainer" class="md:w-96">
        <form wire:submit="inviteTrainer" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Invite trainer') }}</flux:heading>
                <flux:text class="mt-2">{{ __('We will email an invitation link. The trainer accepts it with an existing account or creates a new one.') }}</flux:text>
            </div>

            <flux:input wire:model="email" :label="__('Email address')" type="email" required autofocus />

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Send invitation') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

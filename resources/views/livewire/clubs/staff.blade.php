<div class="flex w-full flex-col gap-6">
    <x-portal.hero :title="__('Staff')" :subtitle="__('Colleagues who log in to help manage :club. Choose per person what they may do.', ['club' => $club->name])">
        <x-slot:actions>
            <flux:button variant="primary" icon="envelope" wire:click="startInvite">{{ __('Invite staff member') }}</flux:button>
        </x-slot:actions>
    </x-portal.hero>

    <x-table-card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Access') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->staffMembers as $member)
                    @php
                        $isOwner = $member->pivot->role === \App\Enums\StaffRole::Owner->value;
                        $permissions = \App\Enums\StaffPermission::fromValues(json_decode((string) $member->pivot->permissions, true) ?: []);
                    @endphp

                    <flux:table.row :key="'staff-'.$member->id">
                        <flux:table.cell>
                            <p class="sp-strong">{{ $member->name }}</p>
                            <p class="sp-muted text-xs">{{ $member->email }}</p>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($isOwner)
                                {{ __('Owner · everything') }}
                            @else
                                {{ collect($permissions)->map(fn ($permission) => $permission->label())->join(', ') ?: __('No access') }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @unless ($isOwner)
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" :aria-label="__('Actions')" />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil-square" wire:click="editStaff({{ $member->id }})">{{ __('Edit permissions') }}</flux:menu.item>
                                        <flux:menu.item icon="trash" variant="danger" wire:click="removeStaff({{ $member->id }})" wire:confirm="{{ __('Remove :name as staff member? They lose access to this club right away.', ['name' => $member->name]) }}">{{ __('Remove') }}</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            @endunless
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
                        <flux:table.column>{{ __('Access') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->pendingInvitations as $invitation)
                            <flux:table.row :key="'invitation-'.$invitation->id">
                                <flux:table.cell class="sp-strong">{{ $invitation->email }}</flux:table.cell>
                                <flux:table.cell>{{ collect(\App\Enums\StaffPermission::fromValues($invitation->permissions))->map(fn ($permission) => $permission->label())->join(', ') }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" :color="$invitation->isExpired() ? 'red' : 'yellow'">
                                        {{ $invitation->isExpired() ? __('Expired') : __('Pending') }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="flex justify-end gap-2">
                                    <flux:button variant="ghost" size="sm" wire:click="resendInvitation({{ $invitation->id }})">{{ __('Resend') }}</flux:button>
                                    <flux:button variant="ghost" size="sm" wire:click="revokeInvitation({{ $invitation->id }})">{{ __('Revoke') }}</flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </x-table-card>
        </div>
    @endif

    <flux:modal name="staff-member" class="md:w-[32rem]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingUserId ? __('Edit permissions') : __('Invite staff member') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ $editingUserId
                        ? __('Changes apply right away, also when they are logged in.')
                        : __('We will email an invitation link. They accept it with an existing account or create a new one, and can then log in.') }}
                </flux:text>
            </div>

            @unless ($editingUserId)
                <flux:input wire:model="email" :label="__('Email address')" type="email" required autofocus />
            @endunless

            <div class="flex flex-col gap-2">
                <flux:label>{{ __('Start from a role') }}</flux:label>
                <div class="flex flex-wrap gap-2">
                    @foreach (\App\Enums\StaffPermission::presets() as $presetKey => $preset)
                        <flux:button type="button" size="sm" wire:click="applyPreset('{{ $presetKey }}')" wire:key="preset-{{ $presetKey }}">{{ $preset['label'] }}</flux:button>
                    @endforeach
                </div>
            </div>

            <flux:checkbox.group wire:model="selectedPermissions" :label="__('May use')">
                @foreach (\App\Enums\StaffPermission::cases() as $permission)
                    <flux:checkbox :value="$permission->value" :label="$permission->label()" :description="$permission->description()" wire:key="permission-{{ $permission->value }}" />
                @endforeach
            </flux:checkbox.group>

            <flux:text class="text-xs">{{ __('Club details, the SquadPass plan, add-ons, integrations and staff always stay with the owner.') }}</flux:text>

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ $editingUserId ? __('Save') : __('Send invitation') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

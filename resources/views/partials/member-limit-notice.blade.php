{{-- Why staff cannot add members right now (no active plan, or the plan's member limit reached), with a link to the plans for those who may change it. --}}
<x-portal.notice
    :heading="$club->hasMemberAccess() ? __('Member limit reached') : __('No active SquadPass plan')"
    :href="auth()->user()->can('update', $club) ? route('clubs.plan', $club) : null"
    :action="__('View plans')"
>
    {{ $club->hasMemberAccess()
        ? __('Your plan allows :limit active members. Upgrade the plan to add more members.', ['limit' => number_format($club->memberLimit() ?? 0, 0, ',', '.')])
        : __('Choose a plan to add members again. Existing members keep their access.') }}
</x-portal.notice>

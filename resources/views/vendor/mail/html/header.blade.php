@props(['url', 'club' => null])
@php
    $logoUrl = $club?->logoUrl();
@endphp
<tr>
<td class="header">
@if ($logoUrl)
<img src="{{ $logoUrl }}" class="club-logo" height="56" alt="{{ $club->name }}">
@elseif ($club)
<span class="club-name">{{ $club->name }}</span>
@else
<a href="{{ $url }}" class="brand">
<img src="{{ asset('images/logos/squadpass-mark.png') }}" class="brand-mark" width="36" height="36" alt="">
<span class="brand-name">{{ config('app.name') }}</span>
</a>
@endif
</td>
</tr>

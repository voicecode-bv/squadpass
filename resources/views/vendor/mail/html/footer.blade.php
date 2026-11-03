@props(['club' => null])
<tr>
<td>
<table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="center">
<a href="{{ config('app.url') }}" class="footer-brand">
<img src="{{ asset('images/logos/squadpass-mark.png') }}" class="footer-mark" width="20" height="20" alt="">
</a>
<p>
@if ($club)
{{ __('Sent by :club via :app.', ['club' => $club->name, 'app' => config('app.name')]) }}
@else
© {{ date('Y') }} {{ config('app.name') }}
@endif
</p>
</td>
</tr>
</table>
</td>
</tr>

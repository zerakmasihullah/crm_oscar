@props(['url'])
@php
    $logoUrl = \App\Models\OrgSetting::logoUrlForMail();
    $appName = (string) \App\Models\OrgSetting::getValue('app_name', config('app.name'));
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if ($logoUrl)
<img src="{{ $logoUrl }}" class="logo" alt="{{ $appName }}">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>

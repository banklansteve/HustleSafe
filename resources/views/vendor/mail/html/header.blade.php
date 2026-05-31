@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ \App\Support\BrandedMail::logoUrl() }}" class="logo lockup" alt="{{ \App\Support\BrandedMail::brandName() }}">
</a>
</td>
</tr>

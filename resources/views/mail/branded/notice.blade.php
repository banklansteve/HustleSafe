<x-mail::message>
# {{ $headline }}

{{ __('Hi :name,', ['name' => $firstName]) }}

@foreach ($lines as $line)
{{ $line }}

@endforeach

@if (! empty($panel))
<x-mail::panel>
{{ $panel }}
</x-mail::panel>
@endif

@if (! empty($ctaUrl) && ! empty($ctaLabel))
<x-mail::button :url="$ctaUrl">
{{ $ctaLabel }}
</x-mail::button>
@endif

@if (! empty($secondaryCtaUrl) && ! empty($secondaryCtaLabel))
<x-mail::button :url="$secondaryCtaUrl" color="success">
{{ $secondaryCtaLabel }}
</x-mail::button>
@endif

@if (! empty($footerLine))
{{ $footerLine }}
@endif

{{ config('app.name') }}
</x-mail::message>

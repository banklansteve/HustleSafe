<x-mail::message>
# {{ __('New proposal received') }}

{{ __('Hi :name,', ['name' => $firstName]) }}

{{ __(':freelancer submitted a structured proposal for “:title”. Review pricing, timeline, warranty, and materials in one place.', ['freelancer' => $freelancerName, 'title' => $questTitle]) }}

<x-mail::button :url="$ctaUrl">
{{ __('Open proposal') }}
</x-mail::button>

{{ __('All negotiation stays on HustleSafe — do not move payments or contact off-platform.') }}

{{ config('app.name') }}
</x-mail::message>

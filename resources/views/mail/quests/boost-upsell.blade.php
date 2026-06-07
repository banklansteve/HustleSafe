<x-mail::message>
# {{ __('Want more proposals, faster?') }}

{{ __('Your quest **:title** is live. Boost it to appear higher in Explore and freelancer search — so the right pros see it sooner.', ['title' => $questTitle]) }}

<x-mail::panel>
{{ __('Boosts are optional. You only pay if you choose a package. Boost duration cannot extend past your quest listing deadline.') }}
</x-mail::panel>

<x-mail::button :url="$ctaUrl" color="primary">
{{ __('View boost options') }}
</x-mail::button>

{{ __('You can also open your quest anytime from your dashboard and boost later.') }}

<x-mail::subcopy>
{{ __('HustleSafe — escrow-first work built for Nigeria.') }}
</x-mail::subcopy>
</x-mail::message>

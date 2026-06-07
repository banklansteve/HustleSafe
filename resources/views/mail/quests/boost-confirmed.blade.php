<x-mail::message>
# {{ __('Hi :name,', ['name' => $firstName]) }}

@if ($purchasedByClient)
{{ __('Payment confirmed — your quest is now boosted.') }}
@else
{{ __('Your quest has been boosted.') }}
@endif

<x-mail::panel>
**{{ $questTitle }}**<br>
{{ __('Boost package: :tier', ['tier' => $tierLabel]) }}<br>
@if ($expiresAt)
{{ __('Active until: :when', ['when' => $expiresAt]) }}
@endif
</x-mail::panel>

{{ __('Matching freelancers will see a Boosted badge and your quest ranks higher in Explore and recommendations while the boost is active.') }}

<x-mail::button :url="$ctaUrl" color="primary">
{{ __('Open your quest') }}
</x-mail::button>

<x-mail::subcopy>
{{ __('HustleSafe — escrow-first work built for Nigeria.') }}
</x-mail::subcopy>
</x-mail::message>

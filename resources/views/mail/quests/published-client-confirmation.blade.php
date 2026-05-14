<x-mail::message>
# {{ __('Hi :name,', ['name' => $firstName]) }}

{{ __('Your quest is now live on HustleSafe. Freelancers who match this category (and anyone you tagged) have been notified by email and in-app.') }}

<x-mail::panel>
**{{ $questTitle }}**@isset($categoryName)<br><span style="opacity:.92">{{ $categoryName }}</span>@endisset
</x-mail::panel>

<x-mail::button :url="$ctaUrl" color="primary">
{{ __('Open your quest') }}
</x-mail::button>

{{ __('Next: review proposals as they arrive, use quest messages for scope questions, and shortlist or accept when you are ready to fund escrow.') }}

<x-mail::subcopy>
{{ __('HustleSafe — escrow-first work built for Nigeria.') }}
</x-mail::subcopy>
</x-mail::message>

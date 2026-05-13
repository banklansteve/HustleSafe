<x-mail::message>
# {{ __('Hey :name,', ['name' => $firstName]) }} ✨

{{ $intro }}

<x-mail::panel>
**{{ $questTitle }}**@isset($category)<br><span style="opacity:.92">{{ $category }}</span>@endisset
@isset($location)<br><span style="opacity:.88">📍 {{ $location }}</span>@endisset
@isset($budgetLine)<br><span style="opacity:.88">{{ $budgetLine }}</span>@endisset
</x-mail::panel>

<x-mail::button :url="$ctaUrl" color="primary">
{{ __('View quest') }}
</x-mail::button>

<x-mail::subcopy>
{{ __('Reference') }}: **{{ $reference }}** · {{ __('HustleSafe — escrow-first work built for Nigeria.') }}
</x-mail::subcopy>
</x-mail::message>

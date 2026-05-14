<x-mail::message>
# {{ __('Quest brief updated') }}

{{ __('Hi :name,', ['name' => $firstName]) }}

{{ __(':client revised the brief for “:title” (ref. :ref). Review the latest details on HustleSafe in case scope, budget, or timing changed.', ['client' => $clientName, 'title' => $questTitle, 'ref' => $reference]) }}

<x-mail::button :url="$ctaUrl">
{{ __('View quest') }}
</x-mail::button>

{{ __('Thanks for building on HustleSafe.') }}

{{ config('app.name') }}
</x-mail::message>

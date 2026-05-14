<x-mail::message>
# {{ __('New message on a quest') }}

{{ __('Hi :name,', ['name' => $firstName]) }}

{{ __(':sender left a message about “:title”.', ['sender' => $senderName, 'title' => $questTitle]) }}

> {{ $preview }}

<x-mail::button :url="$ctaUrl">
{{ __('Open conversation') }}
</x-mail::button>

{{ __('Do not share phone numbers or email — replies must stay in-app.') }}

{{ config('app.name') }}
</x-mail::message>

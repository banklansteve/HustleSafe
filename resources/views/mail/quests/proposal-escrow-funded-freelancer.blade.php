<x-mail::message>
# {{ __('Escrow funded — you are cleared to start') }}

{{ __('Hi :name,', ['name' => $firstName]) }}

{{ __('The client confirmed escrow for “:title”. You can now begin work as agreed and keep updates in the quest thread.', ['title' => $questTitle]) }}

<x-mail::button :url="$threadUrl">
{{ __('Open messages') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>

<x-mail::message>
# {{ __('Congratulations — your proposal was accepted') }}

{{ __('Hi :name,', ['name' => $firstName]) }}

{{ __('The client accepted your proposal on “:title”. Funds are not released until they mark the job complete — please wait for escrow funding before starting billable work.', ['title' => $questTitle]) }}

<x-mail::panel>
{{ __('You will receive another notice when escrow is funded and you are cleared to start.') }}
</x-mail::panel>

<x-mail::button :url="$proposalUrl">
{{ __('View proposal') }}
</x-mail::button>

<x-mail::button :url="$termsUrl" color="secondary">
{{ __('Terms of Service') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>

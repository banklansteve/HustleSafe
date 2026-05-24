<x-mail::message>
# {{ $headline }}

{{ __('Hi :name,', ['name' => $firstName]) }}

{{ $body }}

@if($reason)
**{{ __('Reviewer note') }}**

{{ $reason }}
@endif

---

{{ __('Submission') }}: **{{ $verificationLabel }}**  
{{ __('Status') }}: **{{ $statusLabel }}**

<x-mail::button :url="$ctaUrl">
{{ $ctaLabel }}
</x-mail::button>

{{ __('If anything looks wrong, reply through your HustleSafe support thread — do not send identity documents by email.') }}

{{ config('app.name') }}
</x-mail::message>

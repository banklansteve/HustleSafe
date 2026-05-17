<x-mail::message>
# {{ __('Hello :name,', ['name' => $firstName]) }}

@foreach ($bodyLines as $line)
{{ $line }}

@endforeach

**{{ __('Quest') }}:** {{ $questTitle }}

<x-mail::button :url="$primaryUrl">
{{ $primaryLabel }}
</x-mail::button>

<x-mail::button :url="$secondaryUrl" color="secondary">
{{ __('Disputes & help') }}
</x-mail::button>

{{ __('Plain-language dispute overview (Markdown):') }} [{{ __('Open workflow doc') }}]({{ $workflowDocUrl }})

{{ __('Stay on-platform for scope, payments, and evidence — it keeps everyone safer.') }}

{{ config('app.name') }}
</x-mail::message>

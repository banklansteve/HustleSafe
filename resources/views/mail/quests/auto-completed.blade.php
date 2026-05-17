<x-mail::message>
# {{ __('Quest marked complete') }}

{{ __('Hi :name,', ['name' => $firstName]) }}

{{ __('“:title” has been marked complete under the automatic completion rules (no blocking dispute was open and the post-deadline notice period elapsed).', ['title' => $questTitle]) }}

<x-mail::button :url="$questUrl">
{{ __('View quest') }}
</x-mail::button>

{{ __('If this was a mistake or work is genuinely incomplete, open a dispute immediately with evidence so our team can review.') }}

<x-mail::button :url="$disputesUrl" color="secondary">
{{ __('Disputes centre') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>

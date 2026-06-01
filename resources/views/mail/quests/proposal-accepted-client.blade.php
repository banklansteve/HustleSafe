<x-mail::message>
# {{ __('Proposal accepted — escrow is next') }}

{{ __('Hi :name,', ['name' => $firstName]) }}

{{ __('You accepted a proposal for “:title”. Before the freelancer is obligated to start work, you must fund escrow for the full agreed amount plus applicable platform fees.', ['title' => $questTitle]) }}

**{{ __('Quoted total (₦)') }}:** ₦{{ $grandNgn }}

**{{ __('Indicative platform fee (%)') }}:** {{ $feePercent }}% of the job amount

@foreach($feeSummaryLines ?? [] as $line)
- {{ $line }}
@endforeach

<x-mail::panel>
{{ __('Funds stay in escrow until you confirm delivery. If something goes wrong and the engagement cannot proceed fairly, eligible amounts are refunded according to our dispute and refund policies.') }}
</x-mail::panel>

<x-mail::button :url="$termsUrl" color="secondary">
{{ __('Terms & refund posture') }}
</x-mail::button>

<x-mail::button :url="$questUrl">
{{ __('Open your quest') }}
</x-mail::button>

{{ __('Thanks for keeping payments on-platform — it protects both sides.') }}

{{ config('app.name') }}
</x-mail::message>

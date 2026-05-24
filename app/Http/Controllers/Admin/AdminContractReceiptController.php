<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quest;
use App\Support\NgnMoney;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminContractReceiptController extends Controller
{
    public function show(Request $request, Quest $quest): View
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);

        $quest->loadMissing(['client', 'freelancer', 'acceptedOffer']);
        $pricing = is_array($quest->acceptedOffer?->pricing_snapshot) ? $quest->acceptedOffer->pricing_snapshot : [];

        return view('admin.contract-receipt', [
            'quest' => $quest,
            'pricing' => $pricing,
            'grand' => NgnMoney::format((int) ($quest->acceptedOffer?->quoted_amount_minor ?? $pricing['grand_total_minor'] ?? 0)),
            'vat' => NgnMoney::format((int) ($pricing['vat_minor'] ?? 0)),
            'platform_fee' => NgnMoney::format((int) ($pricing['platform_fee_minor'] ?? 0)),
            'discount' => NgnMoney::format((int) ($pricing['discount_minor'] ?? 0)),
            'issued_at' => now()->timezone(config('app.timezone'))->format('j M Y H:i'),
        ]);
    }
}

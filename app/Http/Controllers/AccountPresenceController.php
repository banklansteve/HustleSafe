<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccountPresenceController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hide_online_presence' => ['required', 'boolean'],
        ]);

        $request->user()->forceFill([
            'hide_online_presence' => $data['hide_online_presence'],
        ])->save();

        return redirect()
            ->route('account.show', ['tab' => 'settings'])
            ->with('success', __('Online visibility preference saved.'));
    }
}

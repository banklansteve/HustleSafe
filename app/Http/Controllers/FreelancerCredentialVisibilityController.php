<?php

namespace App\Http\Controllers;

use App\Models\FreelancerCredential;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FreelancerCredentialVisibilityController extends Controller
{
    public function __invoke(Request $request, FreelancerCredential $freelancerCredential): RedirectResponse
    {
        abort_unless($freelancerCredential->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'is_public' => ['required', 'boolean'],
        ]);

        $freelancerCredential->is_public = $data['is_public'];
        $freelancerCredential->save();

        return back()->with('success', __('Credential visibility updated.'));
    }
}

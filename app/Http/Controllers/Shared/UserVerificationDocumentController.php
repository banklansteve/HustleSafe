<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\UserVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserVerificationDocumentController extends Controller
{
    public function __invoke(Request $request, UserVerification $verification): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);
        abort_unless($this->canView($user), 403);

        $path = (string) $request->query('path', '');
        abort_unless($path !== '' && $this->pathBelongsToVerification($verification, $path), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    private function canView($user): bool
    {
        $slug = $user->role?->slug;

        return in_array($slug, ['admin', 'super_admin', 'operations_staff'], true);
    }

    private function pathBelongsToVerification(UserVerification $verification, string $path): bool
    {
        $allowed = collect($verification->document_paths ?? []);

        foreach ((array) ($verification->metadata['documents'] ?? []) as $doc) {
            if (is_array($doc) && ! empty($doc['path'])) {
                $allowed->push($doc['path']);
            }
        }

        return $allowed->contains($path);
    }
}

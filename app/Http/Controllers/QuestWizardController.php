<?php

namespace App\Http\Controllers;

use App\Models\Quest;
use App\Services\QuestWizardStepValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestWizardController extends Controller
{
    public function validateStep(Request $request, QuestWizardStepValidator $wizard): JsonResponse
    {
        if (! $request->user()?->can('create', Quest::class)) {
            abort(403);
        }

        $request->validate([
            'step' => ['required', 'integer', 'min:1', 'max:6'],
            'payload' => ['required', 'array'],
        ]);

        $validator = $wizard->validate((int) $request->input('step'), $request->input('payload', []));

        if ($validator->fails()) {
            return response()->json(['message' => __('Please review the highlighted fields.'), 'errors' => $validator->errors()], 422);
        }

        return response()->json(['ok' => true]);
    }
}

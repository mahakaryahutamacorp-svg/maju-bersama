<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Assistant\ReadOnlyAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssistantController extends Controller
{
    public function __construct(
        private readonly ReadOnlyAssistant $assistant,
    ) {}

    public function index(Request $request): View
    {
        return view('backoffice.assistant.index', [
            'currentUser' => $request->user()->load('branch'),
            'isMaster' => $request->user()->isMaster(),
        ]);
    }

    public function ask(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        return response()->json(
            $this->assistant->ask($request->user(), $validated['question'])
        );
    }
}

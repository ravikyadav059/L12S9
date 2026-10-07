<?php

namespace App\Http\Controllers;

use App\Services\LinkPreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LinkPreviewController extends Controller
{
    public function __construct(
        protected LinkPreviewService $linkPreviewService
    ) {}

    /**
     * Handle link preview metadata extraction.
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'url', 'max:2048'],
        ]);

        $preview = $this->linkPreviewService->fetch($validated['url']);

        if (! ($preview['success'] ?? false)) {
            return response()->json($preview, 422);
        }

        return response()->json($preview);
    }
}

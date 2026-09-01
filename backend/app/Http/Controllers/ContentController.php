<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateContentRequest;
use App\Services\ContentGenerationService;

class ContentController extends Controller
{
    protected ContentGenerationService $contentService;

    public function __construct(ContentGenerationService $contentService)
    {
        $this->contentService = $contentService;
    }

    public function generate(GenerateContentRequest $request)
    {
        set_time_limit(300); // 5 minutes to prevent CPU Ollama from timing out
        $data = $request->all();
        $response = $this->contentService->generate($data);
        return response()->json($response);
    }
}

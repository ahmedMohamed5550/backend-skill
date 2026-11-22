<?php

namespace App\Http\Controllers\V1\Documentation;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Public API documentation endpoint.
 *
 * Serves the generated docs/API_DOCUMENTATION.md file so frontend and
 * mobile clients can access the API documentation without authentication.
 * Approved exception to the module structure: no Service, no Resources.
 */
class DocumentationController extends Controller
{
    /**
     * Serve the generated API documentation file.
     *
     * @OA\Get(
     *     path="/api/v1/documentation",
     *     operationId="documentationShow",
     *     tags={"Documentation"},
     *     summary="Get API documentation",
     *     description="Return the generated <code>docs/API_DOCUMENTATION.md</code> file as a markdown response. Frontend and mobile clients access this public link alongside the Swagger UI.<br><br>🌐 <b>Public endpoint:</b> no authentication or permission required.",
     *
     *     @OA\Response(response=200, description="API documentation markdown file"),
     *     @OA\Response(response=404, description="API documentation has not been generated yet"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function show(): BinaryFileResponse|JsonResponse
    {
        try {
            $path = base_path('docs/API_DOCUMENTATION.md');

            if (! is_file($path)) {
                return $this->apiResponse(__('messages.not_found'), 404);
            }

            return response()->file($path, ['Content-Type' => 'text/markdown; charset=utf-8']);
        } catch (\Throwable $exception) {
            Log::error('Failed to serve API documentation.', ['error' => $exception->getMessage()]);

            return $this->apiResponse(__('messages.server_error'), 500);
        }
    }
}

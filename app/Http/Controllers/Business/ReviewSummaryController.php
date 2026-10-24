<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewSummaryResource;
use App\Models\Business;
use App\Services\ReviewSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ReviewSummaryController extends Controller
{
    /**
     * Use the summary service to read fresh public results.
     */
    public function __construct(
        private readonly ReviewSummaryService $summaryService
    ) {}

    /**
     * Return the stored summary after checking business access.
     */
    public function __invoke(Business $business): JsonResponse
    {
        Gate::authorize('view', $business);

        return (new ReviewSummaryResource($this->summaryService->publicData($business->id)))
            ->response()->header('Cache-Control', 'private, no-store');
    }
}

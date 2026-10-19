<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\IndexBusinessOfferingsRequest;
use App\Http\Resources\BusinessOfferingResource;
use App\Models\Business;
use App\Repositories\Contracts\BusinessOfferingRepositoryInterface;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OfferingController extends Controller
{
    public function __construct(
        private readonly BusinessOfferingRepositoryInterface $offeringRepo
    ) {}

    /**
     * Return one public catalog page without exposing owner records.
     */
    public function __invoke(IndexBusinessOfferingsRequest $request, Business $business): AnonymousResourceCollection
    {
        Gate::authorize('view', $business);

        return BusinessOfferingResource::collection($this->offeringRepo->forBusiness($business->id, $request->integer('page', 1)));
    }
}

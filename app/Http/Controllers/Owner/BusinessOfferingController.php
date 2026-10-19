<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\IndexBusinessOfferingsRequest;
use App\Http\Requests\Business\SaveBusinessOfferingRequest;
use App\Http\Resources\BusinessOfferingResource;
use App\Models\Business;
use App\Models\BusinessOffering;
use App\Repositories\Contracts\BusinessOfferingRepositoryInterface;
use App\Services\BusinessOfferingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class BusinessOfferingController extends Controller
{
    public function __construct(
        private readonly BusinessOfferingRepositoryInterface $offeringRepo,
        private readonly BusinessOfferingService $offeringService,
    ) {}

    /**
     * Manage a bounded catalog page for one owned business.
     */
    public function index(IndexBusinessOfferingsRequest $request, Business $business): AnonymousResourceCollection
    {
        Gate::authorize('update', $business);

        return BusinessOfferingResource::collection(
            $this->offeringRepo->forBusiness($business->id, $request->integer('page', 1), 20, newestFirst: true)
        );
    }

    /**
     * Add an authorized catalog item.
     */
    public function store(SaveBusinessOfferingRequest $request, Business $business): RedirectResponse
    {
        Gate::authorize('create', [BusinessOffering::class, $business]);

        $this->offeringService->create($business, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Catalog item added.']);

        return to_route('explorer.index');
    }

    /**
     * Save an item, including POST requests with PUT method spoofing.
     */
    public function update(SaveBusinessOfferingRequest $request, Business $business, BusinessOffering $offering): RedirectResponse
    {
        Gate::authorize('update', $offering);

        $this->offeringService->update($offering, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Catalog item updated.']);

        return to_route('explorer.index');
    }

    /**
     * Remove an owned item after the UI confirmation.
     */
    public function destroy(Business $business, BusinessOffering $offering): RedirectResponse
    {
        Gate::authorize('delete', $offering);

        $this->offeringService->delete($offering);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Catalog item removed.']);

        return to_route('explorer.index');
    }
}

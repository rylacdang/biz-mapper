<?php

namespace App\Http\Controllers;

use App\Http\Requests\Business\StoreBusinessRequest;
use App\Http\Requests\Business\UpdateBusinessRequest;
use App\Models\Business;
use App\Repositories\Contracts\BusinessRepositoryInterface;
use App\Services\BusinessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class BusinessController extends Controller
{
    public function __construct(
        private readonly BusinessService $businessService,
        private readonly BusinessRepositoryInterface $businessRepo
    ) {}

    /**
     * Create new business.
     */
    public function store(StoreBusinessRequest $request): RedirectResponse
    {
        $this->businessService->registerBusiness(
            $request->validated(),
            Auth::id(),
            $request->file('cover_photo'),
            $request->file('logo'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Listing created successfully.')]);

        return redirect()->route('owner.dashboard');
    }

    /**
     * Update an existing business listing.
     */
    public function update(UpdateBusinessRequest $request, Business $business): RedirectResponse
    {
        $this->businessService->updateBusiness(
            $business,
            $request->validated(),
            $request->file('cover_photo'),
            $request->file('logo'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Listing updated successfully.')]);

        return redirect()->route('owner.dashboard');
    }

    /**
     * Soft-delete a business listing.
     */
    public function destroy(Business $business): RedirectResponse
    {
        if ($business->user_id !== Auth::id()) {
            abort(403);
        }

        $this->businessRepo->delete($business->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Listing removed successfully.')]);

        return redirect()->route('owner.dashboard');
    }

    /**
     * Restore soft-deleted business listing.
     */
    public function restore(int $id): RedirectResponse
    {
        $business = $this->businessRepo->findTrashed($id);

        if (! $business) {
            abort(404);
        }

        if ($business->user_id !== Auth::id()) {
            abort(403);
        }

        $this->businessRepo->restoreBusiness($id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Listing restored successfully.')]);

        return redirect()->route('owner.dashboard');
    }
}

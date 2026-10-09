<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\StoreBusinessRequest;
use App\Http\Requests\Business\UpdateBusinessRequest;
use App\Models\Business;
use App\Repositories\Contracts\BusinessRepositoryInterface;
use App\Services\BusinessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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
        $this->businessService->registerBusiness($request->validated(), Auth::id(), $request->file('cover_photo'), $request->file('logo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Listing created successfully.')]);

        return redirect()->route('explorer.index');
    }

    /**
     * Update an existing business listing.
     */
    public function update(UpdateBusinessRequest $request, Business $business): RedirectResponse
    {
        $this->businessService->updateBusiness($business, $request->validated(), $request->file('cover_photo'), $request->file('logo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Listing updated successfully.')]);

        return redirect()->route('explorer.index');
    }

    /**
     * Soft-delete a business listing.
     */
    public function destroy(Business $business): RedirectResponse
    {
        Gate::authorize('delete', $business);

        $this->businessRepo->delete($business->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Listing removed successfully.')]);

        return redirect()->route('explorer.index');
    }

    /**
     * Restore soft-deleted business listing.
     */
    public function restore(int $id): RedirectResponse
    {
        $business = $this->businessRepo->findTrashedOrFail($id);

        Gate::authorize('restore', $business);

        $this->businessRepo->restoreBusiness($id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Listing restored successfully.')]);

        return redirect()->route('explorer.index');
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\Business\StoreBusinessRequest;
use App\Http\Requests\Business\UpdateBusinessRequest;
use App\Models\Business;
use App\Services\BusinessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class BusinessController extends Controller
{
    public function __construct(
        private readonly BusinessService $businessService
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
}

<?php

namespace App\Http\Controllers;

use App\Repositories\Contracts\BusinessRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class OwnerDashboardController extends Controller
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepo,
        private readonly BusinessRepositoryInterface $businessRepo,
    ) {}

    /**
     * Display the owner dashboard.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'category' => ['sometimes', 'string'],
        ]);
        $selectedCategory = $validated['category'] ?? 'all';

        $categories = $this->categoryRepo->all();
        $allBusinesses = $this->businessRepo->getByCategoryWithCategory($selectedCategory);
        $myBusinesses = $this->businessRepo->getByUserIdWithCategory(Auth::id());

        return Inertia::render('Welcome', [
            'categories' => $categories,
            'allBusinesses' => $allBusinesses,
            'myBusinesses' => $myBusinesses,
            'selectedCategory' => $selectedCategory,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Repositories\Contracts\BusinessRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
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
    public function index(): Response
    {
        $categories = $this->categoryRepo->all();
        $allBusinesses = $this->businessRepo->getAllWithCategory();
        $myBusinesses = $this->businessRepo->getByUserIdWithCategory(Auth::id());

        return Inertia::render('Welcome', [
            'categories' => $categories,
            'allBusinesses' => $allBusinesses,
            'myBusinesses' => $myBusinesses,
        ]);
    }
}

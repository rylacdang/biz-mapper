<?php

namespace App\Http\Controllers;

use App\Repositories\Contracts\BusinessRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class HomeController extends Controller
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepo,
        private readonly BusinessRepositoryInterface $businessRepo,
    ) {}

    /**
     * Display the public home/map page for guests.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'category' => ['sometimes', 'string'],
        ]);
        $selectedCategory = $validated['category'] ?? 'all';

        $categories = $this->categoryRepo->all();
        $allBusinesses = $this->businessRepo->getByCategoryWithCategory($selectedCategory);

        return Inertia::render('Welcome', [
            'canRegister' => Features::enabled(Features::registration()),
            'categories' => $categories,
            'allBusinesses' => $allBusinesses,
            'selectedCategory' => $selectedCategory,
        ]);
    }
}

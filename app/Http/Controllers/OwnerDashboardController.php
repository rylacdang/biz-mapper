<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class OwnerDashboardController extends Controller
{
    /**
     * Display the owner dashboard.
     */
    public function index(): Response
    {
        $categories = Category::all();
        $allBusinesses = Business::with('category')->get();
        $myBusinesses = Business::where('user_id', Auth::id())->with('category')->get();

        return Inertia::render('Welcome', [
            'categories' => $categories,
            'allBusinesses' => $allBusinesses,
            'myBusinesses' => $myBusinesses,
        ]);
    }
}

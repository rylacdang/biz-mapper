<?php

namespace App\Http\Controllers;

use App\Http\Requests\Explorer\IndexExplorerRequest;
use App\Services\ExplorerService;
use Inertia\Inertia;
use Inertia\Response;

class ExplorerController extends Controller
{
    public function __construct(
        private readonly ExplorerService $explorerService
    ) {}

    /**
     * Display the explorer for guests and signed-in users.
     */
    public function __invoke(IndexExplorerRequest $request): Response
    {
        return Inertia::render('explorer/Index', $this->explorerService->pageData(
            $request->validated('category', 'all'),
            $request->user(),
            $request->validated('destination'),
        ));
    }
}

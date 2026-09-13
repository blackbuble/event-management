<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    /**
     * Role-aware dashboard with real stats (no static placeholders).
     */
    public function index(Request $request)
    {
        return Inertia::render('Dashboard', [
            'dashboard' => $this->dashboardService->getDashboardData($request->user()),
            'canCreateEvent' => $request->user()->can('create', Event::class),
        ]);
    }
}

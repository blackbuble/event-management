<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardService $adminDashboardService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => $this->adminDashboardService->stats(),
        ]);
    }
}

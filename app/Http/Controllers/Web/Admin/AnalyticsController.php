<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AdminAnalyticsService $adminAnalyticsService,
    ) {}

    public function index(Request $request): Response
    {
        $year = (int) $request->query('year', 0);

        return Inertia::render('Admin/Analytics', array_merge(
            $this->adminAnalyticsService->forPeriod(
                (string) $request->query('period', 'month'),
                $year > 0 ? $year : null,
            ),
            [
                'realtime' => $this->adminAnalyticsService->realtime(),
                'daily' => $this->adminAnalyticsService->daily(14),
            ],
        ));
    }
}

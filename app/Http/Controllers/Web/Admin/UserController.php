<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly AdminDashboardService $adminDashboardService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Users', $this->adminDashboardService->users([
            'search' => (string) $request->query('search', ''),
            'role' => (string) $request->query('role', ''),
            'status' => (string) $request->query('status', ''),
        ]));
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $this->guardTarget($request, $user);

        $user->forceFill(['suspended_at' => now()])->save();

        return back()->with('message', "{$user->name} ditangguhkan.");
    }

    public function activate(Request $request, User $user): RedirectResponse
    {
        $this->guardTarget($request, $user);

        $user->forceFill(['suspended_at' => null])->save();

        return back()->with('message', "{$user->name} diaktifkan kembali.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->guardTarget($request, $user);

        $user->delete();

        return back()->with('message', "{$user->name} dihapus.");
    }

    /**
     * An admin cannot suspend/delete themselves or another admin.
     */
    private function guardTarget(Request $request, User $user): void
    {
        abort_if($user->id === $request->user()->id, 403);
        abort_if($user->hasRole('admin'), 403);
    }
}

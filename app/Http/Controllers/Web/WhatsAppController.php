<?php

namespace App\Http\Controllers\Web;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreWhatsAppTopUpRequest;
use App\Services\WhatsAppQuotaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppController extends Controller
{
    public function __construct(
        private readonly WhatsAppQuotaService $quotaService,
    ) {}

    /**
     * WhatsApp quota dashboard: balance, packages, payment methods and history.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasRole(['admin', 'organizer']), 403);

        $user = $request->user();

        return Inertia::render('Dashboard/WhatsApp', [
            'balance' => $this->quotaService->balance($user),
            'packages' => $this->quotaService->packages(app()->getLocale()),
            'payment_methods' => PaymentMethod::options(app()->getLocale()),
            'history' => $this->quotaService->history($user),
        ]);
    }

    /**
     * Purchase a quota package (mock settlement) and credit the balance.
     */
    public function topUp(StoreWhatsAppTopUpRequest $request): RedirectResponse
    {
        try {
            $topUp = $this->quotaService->topUp(
                $request->user(),
                $request->validated('package'),
                PaymentMethod::from($request->validated('payment_method')),
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = app()->getLocale() === 'id'
            ? "Top up berhasil. +{$topUp->quota} kuota WhatsApp ditambahkan."
            : "Top up successful. +{$topUp->quota} WhatsApp quota added.";

        return back()->with('message', $message);
    }
}

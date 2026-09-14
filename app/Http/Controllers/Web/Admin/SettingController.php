<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function __construct(
        private readonly SettingsService $settingsService,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('Admin/Settings', [
            'settings' => $this->settingsService->adminPayload(),
        ]);
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:100'],
            'contact_center' => ['nullable', 'string', 'max:100'],
            'contact_email' => ['nullable', 'email', 'max:255'],
        ]);

        $this->settingsService->updateGeneral($data);

        return back()->with('message', 'Pengaturan umum disimpan.');
    }

    public function updatePaymentGateway(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'provider' => ['nullable', 'string', 'max:50'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'secret_key' => ['nullable', 'string', 'max:255'],
        ]);

        $this->settingsService->updatePaymentGateway($data);

        return back()->with('message', 'Pengaturan payment gateway disimpan.');
    }

    public function updateWhatsApp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'provider' => ['nullable', 'string', 'max:50'],
            'token' => ['nullable', 'string', 'max:255'],
            'sender' => ['nullable', 'string', 'max:50'],
        ]);

        $this->settingsService->updateWhatsAppProvider($data);

        return back()->with('message', 'Pengaturan WhatsApp provider disimpan.');
    }

    public function updatePlatformFee(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:fixed,percent'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $this->settingsService->updatePlatformFee($data);

        return back()->with('message', 'Besaran fee platform disimpan.');
    }
}

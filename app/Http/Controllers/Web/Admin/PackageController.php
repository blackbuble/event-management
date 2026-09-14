<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Packages', [
            'packages' => WhatsAppPackage::query()
                ->orderBy('amount')
                ->get(['id', 'slug', 'label', 'quota', 'amount', 'is_active'])
                ->map(fn (WhatsAppPackage $package) => [
                    'id' => $package->id,
                    'slug' => $package->slug,
                    'label' => $package->label,
                    'quota' => $package->quota,
                    'amount' => (float) $package->amount,
                    'is_active' => $package->is_active,
                ])
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        WhatsAppPackage::query()->create($this->validated($request));

        return back()->with('message', 'Paket ditambahkan.');
    }

    public function update(Request $request, WhatsAppPackage $package): RedirectResponse
    {
        $package->update($this->validated($request, $package->id));

        return back()->with('message', 'Paket diperbarui.');
    }

    public function destroy(WhatsAppPackage $package): RedirectResponse
    {
        $package->delete();

        return back()->with('message', 'Paket dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'slug' => ['required', 'string', 'max:50', Rule::unique('whatsapp_packages', 'slug')->ignore($ignoreId)],
            'label' => ['required', 'string', 'max:100'],
            'quota' => ['required', 'integer', 'min:1', 'max:1000000'],
            'amount' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);
    }
}

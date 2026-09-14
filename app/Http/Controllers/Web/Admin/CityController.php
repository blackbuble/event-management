<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CityController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Cities', [
            'cities' => City::query()
                ->orderBy('name')
                ->get(['id', 'name', 'is_active'])
                ->map(fn (City $city) => [
                    'id' => $city->id,
                    'name' => $city->name,
                    'is_active' => $city->is_active,
                ])
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        City::query()->create($this->validated($request));

        return back()->with('message', 'Kota ditambahkan.');
    }

    public function update(Request $request, City $city): RedirectResponse
    {
        $city->update($this->validated($request, $city->id));

        return back()->with('message', 'Kota diperbarui.');
    }

    public function destroy(City $city): RedirectResponse
    {
        $city->delete();

        return back()->with('message', 'Kota dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('cities', 'name')->ignore($ignoreId)],
            'is_active' => ['boolean'],
        ]);
    }
}

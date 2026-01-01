<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Validation\Rule;

class OnboardingController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Onboarding/Welcome');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
        ];

        // Only validate fields that are actually being updated
        if ($request->has('phone')) {
            $rules['phone'] = ['required', 'string', 'max:20', Rule::unique('users')->ignore($user->id)];
        }
        
        if ($request->has('email')) {
            $rules['email'] = ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)];
        }

        $validated = $request->validate($rules);

        $user->update($validated);

        return redirect()->route('dashboard')->with('message', 'Profile setup complete! Welcome aboard.');
    }
}

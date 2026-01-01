<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class OrganizationController extends Controller
{
    public function create()
    {
        return Inertia::render('Organization/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:organizations,slug',
        ]);

        return DB::transaction(function () use ($request) {
            $org = Organization::create([
                'name' => $request->name,
                'slug' => $request->slug,
                'user_id' => $request->user()->id,
                'branding' => [
                    'primary_color' => '#000000',
                    'logo_url' => null,
                ]
            ]);

            // Ensure 'Admin' role exists
            // We assume roles are global but assigned per organization
            $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
            
            // Set the team id for the upcoming assignment
            setPermissionsTeamId($org->id);
            
            $request->user()->assignRole($adminRole);
            
            // Reset team id? Or keep it? Middleware should handle it usually.
            // But for now we just set session.
            session(['organization_id' => $org->id]);

            return redirect()->route('dashboard')->with('success', 'Organization created successfully.');
        });
    }

    public function switch(Request $request, Organization $organization)
    {
        // Simple check if user is associated with the organization
        // We check if the user is the owner OR has any role in the organization
        $isOwner = $organization->user_id === $request->user()->id;
        
        // Check roles
        // We can't rely on $user->hasRole() easily without setting team id first, 
        // but we can check the relation we added.
        $hasRole = $request->user()->organizations()->where('organizations.id', $organization->id)->exists();

        if (!$isOwner && !$hasRole) {
             abort(403, 'You do not have access to this organization.');
        }

        session(['organization_id' => $organization->id]);

        return back();
    }

    public function show(Organization $organization)
    {
        // Set the context for retrieving roles
        setPermissionsTeamId($organization->id);
        
        $members = $organization->users()
            ->with('roles')
            ->latest()
            ->paginate(10);

        return Inertia::render('Organization/Show', [
            'organization' => $organization,
            'members' => $members,
        ]);
    }

    public function invite(Request $request, Organization $organization)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'role' => 'required|string|in:admin,member',
        ]);

        $user = \App\Models\User::where('email', $request->email)->first();
        
        // Prevent adding already existing member
        setPermissionsTeamId($organization->id);
        if ($user->hasAnyRole(['Admin', 'Member'])) {
            return back()->withErrors(['email' => 'User is already a member of this organization.']);
        }

        // Assign Role
        $roleName = ucfirst($request->role); // Admin or Member
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        
        $user->assignRole($role);

        return back()->with('success', 'Member added successfully.');
    }
}

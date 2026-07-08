<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantWelcomeNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class RegistrationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:120'],
            'contact_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $tenant = Tenant::create([
            'name' => $validated['company_name'],
            'slug' => $this->uniqueTenantSlug($validated['company_name']),
            'phone' => $validated['phone'] ?? null,
            'plan' => 'demo',
        ]);

        $user = User::create([
            'name' => $validated['contact_name'],
            'email' => $validated['email'],
            'password' => Str::random(40),
        ]);

        $tenant->users()->attach($user->id, ['role' => 'admin']);

        $token = Password::broker()->createToken($user);
        $user->notify(new TenantWelcomeNotification($tenant, $token));

        return redirect()
            ->route('welcome')
            ->with('success', 'Registrazione completata! Controlla '.$validated['email'].' per impostare la password e iniziare.');
    }

    private function uniqueTenantSlug(string $companyName): string
    {
        $base = Str::slug($companyName) ?: 'azienda';
        $slug = $base;
        $i = 2;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}

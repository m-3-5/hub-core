<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\TenantBrandManager;
use App\Support\BrandFonts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Tenant $tenant, TenantBrandManager $brand): View
    {
        return view('admin.profile.edit', [
            'tenant' => $tenant,
            'brandColor' => $brand->color($tenant) ?? $tenant->primary_color ?? '#6366f1',
            'brandLogoUrl' => $brand->logoUrl($tenant),
            'brandFont' => $brand->font($tenant),
            'fontPresets' => BrandFonts::PRESETS,
        ]);
    }

    public function update(Request $request, Tenant $tenant, TenantBrandManager $brand): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'url', 'max:255'],
            'brand_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'brand_font' => ['required', Rule::in(array_keys(BrandFonts::PRESETS))],
            'logo' => ['nullable', 'image', 'max:5120'],
        ]);

        $tenant->update([
            'name' => $validated['name'],
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'website' => $validated['website'] ?? null,
            'primary_color' => $validated['brand_color'] ?? $tenant->primary_color,
        ]);

        if (! empty($validated['brand_color'])) {
            $brand->storeColor($tenant, $validated['brand_color']);
        }

        if ($request->hasFile('logo')) {
            $brand->storeLogo($tenant, $request->file('logo'));
        }

        $brand->storeFont($tenant, $validated['brand_font']);

        return redirect()
            ->route('admin.profile.edit', $tenant)
            ->with('success', 'Profilo aggiornato.');
    }
}

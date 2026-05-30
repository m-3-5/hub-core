<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\GeminiApiException;
use App\Http\Controllers\Controller;
use App\Models\Promo;
use App\Models\Tenant;
use App\Services\GeminiPromoGenerator;
use App\Services\PromoVisualBuilder;
use App\Services\WordPressWebhookDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class PromoController extends Controller
{
    public function create(Tenant $tenant): View
    {
        return view('admin.promos.create', compact('tenant'));
    }

    public function store(
        Request $request,
        Tenant $tenant,
        GeminiPromoGenerator $generator,
        PromoVisualBuilder $visuals,
    ): RedirectResponse {
        $request->validate([
            'image' => ['required', 'image', 'max:10240'],
            'always_active' => ['boolean'],
            'skip_ai' => ['boolean'],
        ]);

        $file = $request->file('image');
        $path = $file->store("promos/{$tenant->slug}", 'public');
        $absolutePath = storage_path('app/public/'.$path);
        $flashWarning = null;

        if ($request->boolean('skip_ai')) {
            $generated = GeminiPromoGenerator::fallbackData($tenant->name);
            $flashMessage = 'Promo creata in bozza (senza IA). Controlla anteprima e pubblica quando pronta.';
        } else {
            try {
                $generated = $generator->generateFromImage($absolutePath, $file->getMimeType());
                $flashMessage = 'Promo generata con Gemini. Controlla anteprima e clicca Pubblica per inviarla su Beauty of Image.';
            } catch (GeminiApiException $e) {
                if ($e->quotaExceeded) {
                    $generated = GeminiPromoGenerator::fallbackData($tenant->name);
                    $generated['gemini_error'] = $e->getMessage();
                    $flashMessage = 'Promo creata in bozza con testi predefiniti.';
                    $flashWarning = $e->getMessage().' Puoi modificare i testi e pubblicare quando vuoi.';
                } else {
                    return back()->withInput()->withErrors(['image' => $e->getMessage()]);
                }
            } catch (Throwable $e) {
                return back()->withInput()->withErrors(['image' => 'Errore durante la generazione: '.$e->getMessage()]);
            }
        }

        $slug = Str::slug($generated['suggested_slug'] ?? $generated['title'] ?? 'promo');
        $slug = $this->uniqueSlug($tenant, $slug);

        $promo = $tenant->promos()->create([
            'title' => $generated['title'] ?? 'Nuova promozione',
            'slug' => $slug,
            'description' => $generated['description'] ?? null,
            'offers' => $generated['offers'] ?? [],
            'cta_label' => $generated['cta_label'] ?? 'Scopri l\'offerta',
            'cta_url' => $tenant->website,
            'image_path' => $path,
            'seo_title' => $generated['seo_title'] ?? null,
            'seo_description' => $generated['seo_description'] ?? null,
            'status' => 'draft',
            'always_active' => $request->boolean('always_active', true),
            'published_at' => null,
            'ai_metadata' => $generated,
        ]);

        $promo->update([
            'image_variants' => $visuals->build($tenant, $promo, $path, $absolutePath),
        ]);

        $redirect = redirect()
            ->route('admin.promos.show', [$tenant, $promo])
            ->with('success', $flashMessage);

        if ($flashWarning) {
            $redirect->with('warning', $flashWarning);
        }

        return $redirect;
    }

    public function show(Tenant $tenant, Promo $promo): View
    {
        abort_unless($promo->tenant_id === $tenant->id, 404);

        return view('admin.promos.show', compact('tenant', 'promo'));
    }

    public function edit(Tenant $tenant, Promo $promo): View
    {
        abort_unless($promo->tenant_id === $tenant->id, 404);

        return view('admin.promos.edit', compact('tenant', 'promo'));
    }

    public function update(Request $request, Tenant $tenant, Promo $promo): RedirectResponse
    {
        abort_unless($promo->tenant_id === $tenant->id, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'offers' => ['nullable', 'array', 'max:8'],
            'offers.*.name' => ['nullable', 'string', 'max:255'],
            'offers.*.price' => ['nullable', 'string', 'max:100'],
            'offers.*.detail' => ['nullable', 'string', 'max:500'],
            'always_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $alwaysActive = $request->boolean('always_active');
        $offers = collect($validated['offers'] ?? [])
            ->filter(fn ($o) => ! empty(trim($o['name'] ?? '')))
            ->values()
            ->all();

        $promo->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'offers' => $offers,
            'always_active' => $alwaysActive,
            'starts_at' => $alwaysActive ? null : ($validated['starts_at'] ?? null),
            'ends_at' => $alwaysActive ? null : ($validated['ends_at'] ?? null),
        ]);

        if ($promo->isPublished()) {
            app(WordPressWebhookDispatcher::class)->promoPublished($tenant, $promo->fresh());
        }

        return redirect()
            ->route('admin.promos.show', [$tenant, $promo])
            ->with('success', 'Promo aggiornata.');
    }

    public function publish(Tenant $tenant, Promo $promo, WordPressWebhookDispatcher $webhook): RedirectResponse
    {
        abort_unless($promo->tenant_id === $tenant->id, 404);

        if ($promo->isDraft()) {
            $promo->update([
                'status' => 'published',
                'published_at' => now(),
            ]);
        }

        $webhook->promoPublished($tenant, $promo->fresh());

        return redirect()
            ->route('admin.promos.show', [$tenant, $promo])
            ->with('success', 'Promo pubblicata! Popup, card WordPress e landing sono ora attivi.');
    }

    public function destroy(Tenant $tenant, Promo $promo, WordPressWebhookDispatcher $webhook): RedirectResponse
    {
        abort_unless($promo->tenant_id === $tenant->id, 404);

        $wasPublished = $promo->isPublished();

        if ($promo->image_path) {
            Storage::disk('public')->delete($promo->image_path);
        }

        $variants = $promo->image_variants ?? [];
        foreach ($variants as $variantPath) {
            if (is_string($variantPath)) {
                Storage::disk('public')->delete($variantPath);
            }
        }

        $promo->delete();

        if ($wasPublished) {
            $webhook->promosSync($tenant);
        }

        return redirect()
            ->route('admin.dashboard')
            ->with('success', 'Promo eliminata.');
    }

    private function uniqueSlug(Tenant $tenant, string $slug): string
    {
        $base = $slug;
        $i = 1;

        while ($tenant->promos()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}

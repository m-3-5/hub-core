<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\GeminiApiException;
use App\Http\Controllers\Controller;
use App\Models\Promo;
use App\Models\Tenant;
use App\Services\GeminiPromoGenerator;
use App\Services\PromoVisualBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class PromoController extends Controller
{
    public function create(Tenant $tenant)
    {
        return view('admin.promos.create', compact('tenant'));
    }

    public function store(Request $request, Tenant $tenant, GeminiPromoGenerator $generator, PromoVisualBuilder $visuals)
    {
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
            $flashMessage = 'Promo creata senza IA (solo immagine). Modifica i testi dalla pagina promo quando vuoi.';
        } else {
            try {
                $generated = $generator->generateFromImage($absolutePath, $file->getMimeType());
                $flashMessage = 'Promo generata con Gemini e pubblicata.';
            } catch (GeminiApiException $e) {
                if ($e->quotaExceeded) {
                    $generated = GeminiPromoGenerator::fallbackData($tenant->name);
                    $generated['gemini_error'] = $e->getMessage();
                    $flashMessage = 'Promo pubblicata con testi predefiniti.';
                    $flashWarning = $e->getMessage().' Puoi rigenerare con IA quando la quota è disponibile.';
                } else {
                    return back()
                        ->withInput()
                        ->withErrors(['image' => $e->getMessage()]);
                }
            } catch (Throwable $e) {
                return back()
                    ->withInput()
                    ->withErrors(['image' => 'Errore durante la generazione: '.$e->getMessage()]);
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
            'status' => 'published',
            'always_active' => $request->boolean('always_active', true),
            'published_at' => now(),
            'ai_metadata' => $generated,
        ]);

        $promo->update([
            'image_variants' => $visuals->build($tenant, $promo, $path, $absolutePath),
        ]);

        app(\App\Services\WordPressWebhookDispatcher::class)->promoPublished($tenant, $promo->fresh());

        $redirect = redirect()
            ->route('admin.promos.show', [$tenant, $promo])
            ->with('success', $flashMessage);

        if ($flashWarning) {
            $redirect->with('warning', $flashWarning);
        }

        return $redirect;
    }

    public function show(Tenant $tenant, Promo $promo)
    {
        abort_unless($promo->tenant_id === $tenant->id, 404);

        return view('admin.promos.show', compact('tenant', 'promo'));
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

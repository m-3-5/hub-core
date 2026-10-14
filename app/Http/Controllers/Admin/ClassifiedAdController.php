<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassifiedAd;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ClassifiedAdController extends Controller
{
    private const MAX_IMAGES = 8;

    private const MAX_VIDEOS = 3;

    public function index(Tenant $tenant): View
    {
        $ads = $tenant->classifiedAds()->latest()->get();

        return view('admin.classifieds.index', compact('tenant', 'ads'));
    }

    public function create(Tenant $tenant): View
    {
        return view('admin.classifieds.form', ['tenant' => $tenant, 'ad' => new ClassifiedAd(['category' => 'affitto'])]);
    }

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $this->validated($request);

        $ad = $tenant->classifiedAds()->create([
            ...$data,
            'slug' => $this->uniqueSlug($data['title']),
            'images' => $this->storeImages($request, $tenant, []),
            'videos' => $this->storeVideos($request, $tenant, []),
            'status' => 'draft',
        ]);

        return redirect()->route('admin.classifieds.edit', [$tenant, $ad])
            ->with('success', 'Annuncio salvato come bozza. Controllalo e poi pubblicalo.');
    }

    public function edit(Tenant $tenant, ClassifiedAd $classifiedAd): View
    {
        $this->ensureOwned($tenant, $classifiedAd);

        return view('admin.classifieds.form', ['tenant' => $tenant, 'ad' => $classifiedAd]);
    }

    public function update(Request $request, Tenant $tenant, ClassifiedAd $classifiedAd): RedirectResponse
    {
        $this->ensureOwned($tenant, $classifiedAd);
        $data = $this->validated($request);

        $images = collect($classifiedAd->images ?? []);
        $remove = collect($request->input('remove_images', []));

        foreach ($images->filter(fn ($p) => $remove->contains($p)) as $path) {
            Storage::disk('public')->delete($path);
        }

        $kept = $images->reject(fn ($p) => $remove->contains($p))->values()->all();

        $removeVideos = collect($request->input('remove_videos', []));
        $videos = collect($classifiedAd->videos ?? []);

        foreach ($videos->filter(fn ($p) => $removeVideos->contains($p)) as $path) {
            Storage::disk('public')->delete($path);
        }

        $keptVideos = $videos->reject(fn ($p) => $removeVideos->contains($p))->values()->all();

        $classifiedAd->update([
            ...$data,
            'images' => $this->storeImages($request, $tenant, $kept),
            'videos' => $this->storeVideos($request, $tenant, $keptVideos),
        ]);

        return back()->with('success', 'Annuncio aggiornato.');
    }

    public function publish(Tenant $tenant, ClassifiedAd $classifiedAd): RedirectResponse
    {
        $this->ensureOwned($tenant, $classifiedAd);

        $classifiedAd->update(['status' => 'published', 'published_at' => $classifiedAd->published_at ?? now()]);

        return back()->with('success', 'Annuncio pubblicato: '.$classifiedAd->publicUrl());
    }

    public function unpublish(Tenant $tenant, ClassifiedAd $classifiedAd): RedirectResponse
    {
        $this->ensureOwned($tenant, $classifiedAd);

        $classifiedAd->update(['status' => 'draft']);

        return back()->with('success', 'Annuncio rimosso dalla pubblicazione (tornato in bozza).');
    }

    public function destroy(Tenant $tenant, ClassifiedAd $classifiedAd): RedirectResponse
    {
        $this->ensureOwned($tenant, $classifiedAd);

        foreach (array_merge($classifiedAd->images ?? [], $classifiedAd->videos ?? []) as $path) {
            Storage::disk('public')->delete($path);
        }

        $classifiedAd->delete();

        return redirect()->route('admin.classifieds.index', $tenant)->with('success', 'Annuncio eliminato.');
    }

    private function ensureOwned(Tenant $tenant, ClassifiedAd $ad): void
    {
        abort_unless($ad->tenant_id === $tenant->id, 404);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'category' => ['required', 'in:'.implode(',', array_keys(ClassifiedAd::CATEGORIES))],
            'title' => ['required', 'string', 'max:140'],
            'zone' => ['required', 'string', 'max:120'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'price_unit' => ['nullable', 'in:,'.implode(',', array_filter(array_keys(ClassifiedAd::PRICE_UNITS)))],
            'protected_sale' => ['nullable', 'boolean'],
            'description' => ['required', 'string', 'max:5000'],
            'features' => ['nullable', 'array'],
            'features.*' => ['nullable', 'string', 'max:500'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'images' => ['nullable', 'array', 'max:'.self::MAX_IMAGES],
            'images.*' => ['image', 'max:8192'],
            'videos' => ['nullable', 'array', 'max:'.self::MAX_VIDEOS],
            'videos.*' => ['file', 'mimetypes:video/mp4,video/quicktime,video/webm', 'max:40960'],
        ]);

        // Ogni categoria ha i suoi campi: si tengono solo quelli suoi, numeri interi o scelte ammesse.
        $allowed = ClassifiedAd::CATEGORY_META[$validated['category']]['fields'] ?? [];
        $features = [];

        foreach ($validated['features'] ?? [] as $key => $value) {
            if (! in_array($key, $allowed, true) || $value === null || $value === '') {
                continue;
            }

            if (isset(ClassifiedAd::FEATURE_LABELS[$key])) {
                if (ctype_digit((string) $value) && (int) $value <= 100000) {
                    $features[$key] = (int) $value;
                }
            } elseif (isset(ClassifiedAd::TEXT_FEATURES[$key]['options'][$value])) {
                $features[$key] = $value;
            } elseif (isset(ClassifiedAd::INPUT_FEATURES[$key])) {
                $value = trim((string) $value);

                if (ClassifiedAd::INPUT_FEATURES[$key]['type'] === 'url') {
                    if (filter_var($value, FILTER_VALIDATE_URL) && preg_match('~^https?://~i', $value)) {
                        $features[$key] = mb_substr($value, 0, 500);
                    }
                } elseif ($value !== '') {
                    $features[$key] = mb_substr($value, 0, ClassifiedAd::INPUT_FEATURES[$key]['max']);
                }
            }
        }

        $validated['features'] = $features;
        $validated['price_unit'] = ($validated['price_unit'] ?? '') ?: null;
        // Il pagamento protetto vale solo per gli oggetti a prezzo fisso.
        $validated['protected_sale'] = $request->boolean('protected_sale')
            && $validated['category'] === 'oggetti'
            && ($validated['price'] ?? 0) > 0
            && $validated['price_unit'] === null;
        unset($validated['images'], $validated['videos']);

        return $validated;
    }

    /**
     * @param  array<int, string>  $existing
     * @return array<int, string>
     */
    private function storeImages(Request $request, Tenant $tenant, array $existing): array
    {
        $room = max(0, self::MAX_IMAGES - count($existing));

        foreach (array_slice($request->file('images', []), 0, $room) as $file) {
            $existing[] = $file->store('ads/'.$tenant->slug, 'public');
        }

        return array_values($existing);
    }

    /**
     * @param  array<int, string>  $existing
     * @return array<int, string>
     */
    private function storeVideos(Request $request, Tenant $tenant, array $existing): array
    {
        $room = max(0, self::MAX_VIDEOS - count($existing));

        foreach (array_slice($request->file('videos', []), 0, $room) as $file) {
            $existing[] = $file->store('ads/'.$tenant->slug, 'public');
        }

        return array_values($existing);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'annuncio';

        return $base.'-'.Str::lower(Str::random(6));
    }
}

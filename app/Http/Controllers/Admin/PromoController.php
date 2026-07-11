<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\GeminiApiException;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Promo;
use App\Models\Tenant;
use App\Models\TenantModuleCharge;
use App\Notifications\ConfirmGuestPublishNotification;
use App\Services\GeminiImageGenerator;
use App\Services\GeminiPromoGenerator;
use App\Services\GeminiSvgFlyerGenerator;
use App\Services\PromoVisualBuilder;
use App\Services\TenantBrandManager;
use App\Services\WordPressWebhookDispatcher;
use App\Support\TenantPromoQuota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class PromoController extends Controller
{
    public function index(Tenant $tenant): View
    {
        $active = $tenant->promos()->published()->active()->latest('published_at')->get();
        $expired = $tenant->promos()->expired()->latest('ends_at')->get();
        $drafts = $tenant->promos()->where('status', 'draft')->latest()->get();

        return view('admin.promos.index', compact('tenant', 'active', 'expired', 'drafts'));
    }

    public function create(Tenant $tenant, TenantBrandManager $brand): View
    {
        return view('admin.promos.create', [
            'tenant' => $tenant,
            'hasBrandLogo' => $brand->hasLogo($tenant),
            'brandLogoUrl' => $brand->logoUrl($tenant),
            'promoQuota' => [
                'included' => TenantPromoQuota::includedLimit($tenant),
                'used' => TenantPromoQuota::usedCount($tenant),
                'remaining' => TenantPromoQuota::remaining($tenant),
            ],
            'aiFlyerPrice' => config('hub.promo_ai_flyer_price', 24),
        ]);
    }

    public function store(
        Request $request,
        Tenant $tenant,
        GeminiPromoGenerator $generator,
        GeminiImageGenerator $imageGenerator,
        GeminiSvgFlyerGenerator $svgFlyerGenerator,
        PromoVisualBuilder $visuals,
        TenantBrandManager $brand,
    ): RedirectResponse {
        $request->validate([
            'promo_source' => ['required', 'in:upload,generate,svg'],
            'visual_tier' => ['required', 'in:base,ai_flyer'],
            'image' => ['required_if:promo_source,upload', 'nullable', 'image', 'max:10240'],
            'brand_mode' => ['required_if:promo_source,generate', 'nullable', 'in:tenant,once,save'],
            'logo' => ['nullable', 'image', 'max:5120'],
            'promo_hint' => ['required_if:promo_source,svg', 'nullable', 'string', 'max:500'],
            'always_active' => ['boolean'],
            'skip_ai' => ['boolean'],
            'manual_title' => ['nullable', 'string', 'max:255'],
            'manual_description' => ['nullable', 'string', 'max:2000'],
            'brand_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        if ($request->input('visual_tier') === 'ai_flyer') {
            return back()
                ->withInput()
                ->withErrors([
                    'visual_tier' => 'Il volantino generato con IA (€'.config('hub.promo_ai_flyer_price', 24).') richiede il pagamento del servizio. '
                        .'Pagamento online in arrivo — per ora usa «Promo base» con il tuo volantino o le illustrazioni incluse.',
                ]);
        }

        if ($request->input('promo_source') === 'generate') {
            return back()
                ->withInput()
                ->withErrors([
                    'promo_source' => 'La generazione volantino da logo è disponibile solo con il pacchetto IA a pagamento.',
                ]);
        }

        if (! $brand->hasColor($tenant) && $request->filled('brand_color')) {
            $brand->storeColor($tenant, $request->input('brand_color'));
        }

        if (! $brand->hasLogo($tenant) && $request->hasFile('logo')) {
            $brand->storeLogo($tenant, $request->file('logo'));
        }

        $flashWarning = null;

        if ($request->input('promo_source') === 'svg') {
            $headline = trim((string) $request->input('manual_title')) ?: (string) $request->input('promo_hint');
            $subline = trim((string) $request->input('manual_description')) ?: null;

            $flyer = $svgFlyerGenerator->generate(
                $tenant,
                $headline,
                $subline,
                $brand->absolutePath($brand->logoPath($tenant)),
                'promos/'.$tenant->slug.'/'.Str::uuid(),
            );

            if (! $flyer) {
                return back()->withInput()->withErrors([
                    'promo_source' => 'Non sono riuscito a generare il volantino automaticamente. Riprova o carica una tua immagine.',
                ]);
            }

            $path = $flyer['path'];
            $mime = $flyer['mime'];
            $absolutePath = Storage::disk('public')->path($path);
        } else {
            try {
                [$path, $absolutePath, $mime] = $this->resolvePromoImage(
                    $request,
                    $tenant,
                    $brand,
                    $imageGenerator,
                );
            } catch (Throwable $e) {
                return back()->withInput()->withErrors(['image' => $e->getMessage()]);
            }
        }

        if ($request->boolean('skip_ai') || $request->input('promo_source') === 'svg') {
            $generated = GeminiPromoGenerator::fallbackData($tenant->name);
            $flashMessage = 'Promo creata in bozza. Controlla anteprima e pubblica quando pronta.';
        } else {
            try {
                $generated = $generator->generateFromImage($absolutePath, $mime, $request->input('promo_hint'));
                $flashMessage = 'Promo generata con Gemini. Controlla anteprima e clicca Pubblica per inviarla su '.$tenant->name.'.';
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

        $manualTitle = trim((string) $request->input('manual_title', ''));
        $manualDescription = trim((string) $request->input('manual_description', ''));
        $title = $manualTitle !== '' ? $manualTitle : ($generated['title'] ?? 'Nuova promozione');
        $description = $manualDescription !== '' ? $manualDescription : ($generated['description'] ?? null);

        $slug = Str::slug($manualTitle !== '' ? $manualTitle : ($generated['suggested_slug'] ?? $generated['title'] ?? 'promo'));
        $slug = $this->uniqueSlug($tenant, $slug);

        $hashtags = $this->generateHashtags($tenant, $title, $description);

        $overQuota = ! TenantPromoQuota::hasIncludedSlot($tenant);

        $promo = $tenant->promos()->create([
            'title' => $title,
            'slug' => $slug,
            'description' => $description,
            'offers' => $generated['offers'] ?? [],
            'cta_label' => $generated['cta_label'] ?? 'Scopri l\'offerta',
            'cta_url' => $tenant->website,
            'image_path' => $path,
            'seo_title' => $generated['seo_title'] ?? null,
            'seo_description' => $generated['seo_description'] ?? null,
            'status' => 'draft',
            'always_active' => $request->boolean('always_active', true),
            'published_at' => null,
            'ai_metadata' => array_merge($generated, [
                'promo_source' => $request->input('promo_source'),
                'brand_mode' => $request->input('brand_mode'),
                'visual_tier' => $request->input('visual_tier', 'base'),
                'hashtags' => $hashtags,
            ]),
        ]);

        $promo->update([
            'image_variants' => $visuals->build($tenant, $promo, $path, $absolutePath, aiImages: false),
        ]);

        if ($overQuota) {
            $flashWarning = ($flashWarning ? $flashWarning.' ' : '')
                .'Hai superato le '.TenantPromoQuota::includedLimit($tenant).' promo incluse nel pacchetto mensile. Le promo extra saranno a pagamento.';

            TenantModuleCharge::create([
                'tenant_id' => $tenant->id,
                'module' => 'promo',
                'charge_type' => 'extra_item',
                'period' => now()->format('Y-m'),
                'description' => 'Promo "'.$promo->title.'" oltre la quota mensile gratuita (creata dal cliente)',
                'amount_cents' => config('module_pricing.promo.extra_self_cents', 3500),
                'paid' => false,
            ]);
        }

        ActivityLog::record($tenant, 'promo_created', input: [
            'promo_source' => $request->input('promo_source'),
            'promo_hint' => $request->input('promo_hint'),
            'manual_title' => $manualTitle !== '' ? $manualTitle : null,
            'manual_description' => $manualDescription !== '' ? $manualDescription : null,
            'skip_ai' => $request->boolean('skip_ai'),
        ], output: [
            'title' => $title,
            'description' => $description,
            'offers' => $generated['offers'] ?? [],
            'hashtags' => $hashtags,
            'image_path' => $path,
        ], subject: $promo);

        $redirect = redirect()
            ->route('admin.promos.show', [$tenant, $promo])
            ->with('success', $flashMessage);

        if ($flashWarning) {
            $redirect->with('warning', $flashWarning);
        }

        return $redirect;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function resolvePromoImage(
        Request $request,
        Tenant $tenant,
        TenantBrandManager $brand,
        GeminiImageGenerator $imageGenerator,
    ): array {
        if ($request->input('promo_source') === 'upload') {
            $file = $request->file('image');
            $path = $file->store("promos/{$tenant->slug}", 'public');

            return [$path, storage_path('app/public/'.$path), $file->getMimeType()];
        }

        $logoPath = $this->resolveBrandLogo($request, $tenant, $brand);
        $logoAbsolute = $brand->absolutePath($logoPath);

        if (! $logoAbsolute) {
            throw new \InvalidArgumentException('Carica il logo del brand per generare il volantino.');
        }

        $dir = 'promos/'.$tenant->slug.'/'.Str::uuid();
        $flyerPath = $dir.'/flyer-ai.jpg';

        $generated = $imageGenerator->generateFlyerFromBrand(
            $tenant,
            $logoAbsolute,
            $flyerPath,
            $request->input('promo_hint'),
        );

        if (! $generated) {
            throw new \RuntimeException(
                'Impossibile generare il volantino con IA: quota immagini Gemini esaurita o modello non disponibile. '.
                'Carica direttamente l\'immagine promo (opzione "Ho già l\'immagine") oppure riprova più tardi.'
            );
        }

        return [$flyerPath, storage_path('app/public/'.$flyerPath), 'image/jpeg'];
    }

    private function resolveBrandLogo(Request $request, Tenant $tenant, TenantBrandManager $brand): ?string
    {
        $mode = $request->input('brand_mode', 'tenant');

        if ($mode === 'tenant') {
            if (! $brand->hasLogo($tenant)) {
                throw new \InvalidArgumentException('Nessun logo salvato per questa attività. Caricalo qui sotto o scegli un\'altra opzione.');
            }

            return $brand->logoPath($tenant);
        }

        $file = $request->file('logo');

        if (! $file) {
            throw new \InvalidArgumentException('Carica il logo per generare la promo.');
        }

        return $brand->storeLogo($tenant, $file, persist: $mode === 'save');
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

    public function update(Request $request, Tenant $tenant, Promo $promo, PromoVisualBuilder $visuals): RedirectResponse
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
            'image' => ['nullable', 'image', 'max:10240'],
        ]);

        $alwaysActive = $request->boolean('always_active');
        $offers = collect($validated['offers'] ?? [])
            ->filter(fn ($o) => ! empty(trim($o['name'] ?? '')))
            ->values()
            ->all();

        $before = $promo->only(['title', 'description', 'offers', 'image_path']);

        $imagePath = $promo->image_path;
        $imageVariants = $promo->image_variants;

        if ($request->hasFile('image')) {
            $oldPath = $promo->image_path;

            $imagePath = $request->file('image')->store('promos/'.$tenant->slug, 'public');
            $imageVariants = $visuals->build(
                $tenant,
                $promo,
                $imagePath,
                Storage::disk('public')->path($imagePath),
                aiImages: false,
            );

            if ($oldPath && $oldPath !== $imagePath) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $promo->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'offers' => $offers,
            'always_active' => $alwaysActive,
            'starts_at' => $alwaysActive ? null : ($validated['starts_at'] ?? null),
            'ends_at' => $alwaysActive ? null : ($validated['ends_at'] ?? null),
            'image_path' => $imagePath,
            'image_variants' => $imageVariants,
        ]);

        ActivityLog::record($tenant, 'promo_updated', input: $before, output: [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'offers' => $offers,
            'image_path' => $imagePath,
        ], subject: $promo);

        if ($promo->isPublished()) {
            app(WordPressWebhookDispatcher::class)->promoPublished($tenant, $promo->fresh());
        }

        return redirect()
            ->route('admin.promos.show', [$tenant, $promo])
            ->with('success', 'Promo aggiornata.');
    }

    public function publish(Request $request, Tenant $tenant, Promo $promo, WordPressWebhookDispatcher $webhook): RedirectResponse
    {
        abort_unless($promo->tenant_id === $tenant->id, 404);

        if ($tenant->isGuestPending()) {
            $validated = $request->validate([
                'guest_email' => ['required', 'email', 'max:190', 'unique:users,email'],
            ]);

            $user = $tenant->users()->first();
            $token = Str::random(48);

            try {
                Notification::route('mail', $validated['guest_email'])
                    ->notify(new ConfirmGuestPublishNotification($tenant, $promo, $token));
            } catch (Throwable $e) {
                return back()->withErrors([
                    'guest_email' => 'Non sono riuscito a inviare l\'email di conferma a questo indirizzo. Controlla che sia scritto correttamente e riprova.',
                ]);
            }

            $user->update(['email' => $validated['guest_email']]);
            $tenant->update([
                'guest_email_token' => $token,
                'guest_email_token_expires_at' => now()->addHours(48),
            ]);

            return back()->with('success', 'Controlla '.$validated['guest_email'].' e clicca il link per pubblicare davvero la promo.');
        }

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

        $this->deletePromoFiles($promo);

        $promo->delete();

        if ($wasPublished) {
            $webhook->promosSync($tenant);
        }

        return redirect()
            ->route('app.home', $tenant)
            ->with('success', 'Promo eliminata.');
    }

    private function deletePromoFiles(Promo $promo): void
    {
        if ($promo->image_path) {
            Storage::disk('public')->delete($promo->image_path);
        }

        foreach ($promo->image_variants ?? [] as $key => $variant) {
            if ($key === 'decor' && is_array($variant)) {
                foreach ($variant as $meta) {
                    $path = is_array($meta) ? ($meta['path'] ?? null) : $meta;

                    if (is_string($path)) {
                        Storage::disk('public')->delete($path);
                    }
                }

                continue;
            }

            if (is_string($variant)) {
                Storage::disk('public')->delete($variant);
            }
        }
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

    /** @return string[] */
    private function generateHashtags(Tenant $tenant, string $title, ?string $description): array
    {
        $stopwords = [
            'il', 'lo', 'la', 'i', 'gli', 'le', 'di', 'a', 'da', 'in', 'con', 'su', 'per', 'tra', 'fra',
            'e', 'o', 'un', 'uno', 'una', 'che', 'del', 'della', 'dei', 'delle', 'al', 'allo', 'alla',
            'ai', 'agli', 'alle', 'nel', 'nello', 'nella', 'nei', 'negli', 'nelle', 'sul', 'sullo',
            'sulla', 'sui', 'sugli', 'sulle', 'non', 'più', 'anche', 'solo', 'tutti', 'tutte', 'nostro', 'nostra',
        ];

        $text = mb_strtolower($title.' '.($description ?? ''));
        $words = preg_split('/[^\p{L}0-9]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $keywords = [];
        foreach ($words as $word) {
            if (mb_strlen($word) < 4 || in_array($word, $stopwords, true)) {
                continue;
            }

            $tag = '#'.mb_convert_case($word, MB_CASE_TITLE, 'UTF-8');

            if (! in_array($tag, $keywords, true)) {
                $keywords[] = $tag;
            }

            if (count($keywords) >= 4) {
                break;
            }
        }

        $companyTag = '#'.Str::studly(Str::slug($tenant->name));

        return array_values(array_unique(array_merge(['#Promo', $companyTag], $keywords)));
    }
}

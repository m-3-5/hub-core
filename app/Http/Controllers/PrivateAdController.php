<?php

namespace App\Http\Controllers;

use App\Models\ClassifiedAd;
use App\Models\User;
use App\Notifications\ClassifiedToReviewNotification;
use App\Support\AdImages;
use App\Support\CreditNotices;
use App\Support\EmailLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Annunci dei privati: Vendo / Cerco / Lavoro, zona = paese. Niente password: chi non è entrato conferma con un link via email.
 * Poi lo controlla Max; approvato resta online 30 giorni. Il link precompilato arriva anche da piramide35.com:
 * /annunci/nuovo?zona=Senise&tipo=vendo&testo=...&ref=piramide35
 */
class PrivateAdController extends Controller
{
    public const MAX_IMAGES = 5;

    /** Annunci nuovi al giorno per attività (contro lo spam). */
    public const DAILY_LIMIT = 10;

    public function create(Request $request): View
    {
        $type = array_key_exists((string) $request->query('tipo'), ClassifiedAd::PRIVATE_TYPES) ? (string) $request->query('tipo') : 'vendo';
        $text = trim(Str::limit((string) $request->query('testo'), 3000, ''));
        $title = trim((string) $request->query('titolo')) ?: Str::limit(Str::before($text."\n", "\n"), 80, '');

        return view('classifieds.new', [
            'prefill' => [
                'tipo' => $type,
                'zona' => Str::limit(trim((string) $request->query('zona')), 80, ''),
                'titolo' => Str::limit($title, 100, ''),
                'testo' => $text,
                'prezzo' => is_numeric($request->query('prezzo')) ? (string) $request->query('prezzo') : '',
                'ref' => preg_match('/^[a-z0-9_-]{1,40}$/i', (string) $request->query('ref')) ? (string) $request->query('ref') : '',
            ],
            'user' => $request->user(),
            'sent' => session('sent'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->filled('website')) { // campo trappola per i robot
            return redirect()->route('classifieds.private.create')->with('sent', 'ok');
        }

        $user = $request->user();

        $data = $request->validate([
            'tipo' => ['required', 'in:'.implode(',', array_keys(ClassifiedAd::PRIVATE_TYPES))],
            'title' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:3000'],
            'zone' => ['required', 'string', 'max:80'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'phone' => ['nullable', 'string', 'max:30'],
            'name' => [$user ? 'nullable' : 'required', 'string', 'max:80'],
            'email' => [$user ? 'nullable' : 'required', 'email', 'max:190'],
            'images' => ['nullable', 'array', 'max:'.self::MAX_IMAGES],
            'images.*' => ['image', 'max:10240'],
            'ref' => ['nullable', 'regex:/^[a-z0-9_-]{1,40}$/i'],
        ], [
            'title.required' => 'Scrivi un titolo.',
            'description.required' => 'Scrivi due righe sull\'annuncio.',
            'zone.required' => 'Scrivi il paese.',
            'name.required' => 'Scrivi il tuo nome.',
            'email.required' => 'Scrivi la tua email: ti mandiamo il link per confermare.',
            'images.max' => 'Al massimo '.self::MAX_IMAGES.' foto.',
            'images.*.image' => 'Le foto devono essere immagini.',
        ]);

        if (! $user) {
            $existing = User::whereRaw('LOWER(email) = ?', [Str::lower(trim($data['email']))])->first();
            if ($existing?->isSuperAdmin()) {
                return back()->withInput()->withErrors(['email' => 'Con questa email entra prima con la password.']);
            }
            $owner = $existing ?? EmailLogin::userFor($data['email'], $data['name']);
        } else {
            $owner = $user;
        }

        $tenant = EmailLogin::tenantFor($owner);
        if (! $tenant) {
            return back()->withInput()->withErrors(['email' => 'Questo account non ha un\'attività con cui pubblicare.']);
        }

        if (ClassifiedAd::where('tenant_id', $tenant->id)->where('created_at', '>=', now()->subDay())->count() >= self::DAILY_LIMIT) {
            return back()->withInput()->withErrors(['title' => 'Hai già scritto molti annunci oggi: riprova domani.']);
        }

        $dir = 'ads/'.$tenant->slug;
        $images = [];
        foreach (array_slice($request->file('images', []), 0, self::MAX_IMAGES) as $file) {
            $images[] = AdImages::store($file, $dir);
        }

        $ad = ClassifiedAd::create([
            'tenant_id' => $tenant->id,
            'slug' => (Str::slug($data['title']) ?: 'annuncio').'-'.Str::lower(Str::random(6)),
            'category' => ClassifiedAd::PRIVATE_TYPES[$data['tipo']]['category'],
            'title' => trim($data['title']),
            'zone' => trim($data['zone']),
            'price' => $data['price'] ?? null,
            'description' => trim($data['description']),
            'features' => [],
            'images' => $images,
            'thumb' => $images ? AdImages::thumb($images[0], $dir) : null,
            'contact_name' => trim((string) ($data['name'] ?? '')) ?: $owner->name,
            'contact_phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'contact_email' => $owner->email,
            'status' => $user ? 'review' : 'email',
            'source' => $data['ref'] ?? null,
        ]);

        if ($user) {
            self::notifyMax($ad);

            return redirect()->route('classifieds.private.mine')->with('success', 'Inviato! Lo controlliamo e va online.');
        }

        EmailLogin::send($owner->email, route('classifieds.private.confirm', $ad, false), 'confermare il tuo annuncio');

        return redirect()->route('classifieds.private.create')->with('sent', $owner->email);
    }

    /** Clic sul link dell'email: l'annuncio passa al controllo di Max. */
    public function confirm(Request $request, ClassifiedAd $classifiedAd): RedirectResponse
    {
        abort_unless($request->user()->belongsToTenant($classifiedAd->tenant), 403);

        if ($classifiedAd->status === 'email') {
            $classifiedAd->update(['status' => 'review']);
            self::notifyMax($classifiedAd);
        }

        return redirect()->route('classifieds.private.mine')->with('success', 'Email confermata! Lo controlliamo e va online.');
    }

    public function mine(Request $request): View
    {
        $tenantIds = $request->user()->tenants()->pluck('tenants.id');

        return view('classifieds.mine', [
            'ads' => ClassifiedAd::with('tenant')->whereIn('tenant_id', $tenantIds)->latest()->limit(50)->get(),
        ]);
    }

    /** Altri 30 giorni (solo per annunci già approvati). */
    public function renew(Request $request, ClassifiedAd $classifiedAd): RedirectResponse
    {
        abort_unless($request->user()->belongsToTenant($classifiedAd->tenant), 403);

        if ($classifiedAd->status === 'published' && $classifiedAd->expires_at) {
            $classifiedAd->update(['expires_at' => now()->addDays(ClassifiedAd::PRIVATE_DAYS)]);
        }

        return back()->with('success', 'Rinnovato per altri '.ClassifiedAd::PRIVATE_DAYS.' giorni.');
    }

    public function remove(Request $request, ClassifiedAd $classifiedAd): RedirectResponse
    {
        abort_unless($request->user()->belongsToTenant($classifiedAd->tenant), 403);

        foreach (array_filter(array_merge($classifiedAd->images ?? [], [$classifiedAd->thumb])) as $path) {
            Storage::disk('public')->delete($path);
        }
        $classifiedAd->delete();

        return back()->with('success', 'Annuncio tolto.');
    }

    public static function notifyMax(ClassifiedAd $ad): void
    {
        try {
            if ($max = CreditNotices::maxEmail()) {
                Notification::route('mail', $max)->notify(new ClassifiedToReviewNotification($ad->loadMissing('tenant')));
            }
        } catch (Throwable $e) {
            Log::warning('Annuncio da controllare: email a Max non inviata', ['ad' => $ad->id, 'message' => $e->getMessage()]);
        }
    }
}

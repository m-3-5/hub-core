<?php

namespace App\Http\Controllers;

use App\Models\ClassifiedAd;
use App\Models\Tenant;
use App\Notifications\ClassifiedContactRequestNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;
use Throwable;

class ClassifiedPublicController extends Controller
{
    /** Bacheca globale (tutti i tenant) se $tenant è null, altrimenti solo quella dell'azienda. */
    public function board(Request $request, ?Tenant $tenant = null): View
    {
        $query = ClassifiedAd::published()->with('tenant')->latest('published_at');

        if ($tenant) {
            $query->where('tenant_id', $tenant->id);
        }

        $category = $request->query('categoria');
        if ($category && array_key_exists($category, ClassifiedAd::CATEGORIES)) {
            $query->where('category', $category);
        } else {
            $category = null;
        }

        $search = trim((string) $request->query('q'));
        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(fn ($q) => $q->where('zone', 'like', $like)->orWhere('title', 'like', $like));
        }

        return view('classifieds.board', [
            'tenant' => $tenant,
            'ads' => $query->paginate(18)->withQueryString(),
            'category' => $category,
            'search' => $search,
        ]);
    }

    public function show(Tenant $tenant, ClassifiedAd $classifiedAd): View
    {
        abort_unless($classifiedAd->tenant_id === $tenant->id && $classifiedAd->isPublished(), 404);

        return view('classifieds.show', ['tenant' => $tenant, 'ad' => $classifiedAd]);
    }

    public function contact(Request $request, Tenant $tenant, ClassifiedAd $classifiedAd): RedirectResponse
    {
        abort_unless($classifiedAd->tenant_id === $tenant->id && $classifiedAd->isPublished(), 404);

        if ($request->filled('website')) {
            return back()->with('contact_success', true);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:30', 'required_without:email'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $tenant->customerTickets()->create([
            'classified_ad_id' => $classifiedAd->id,
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'message' => $validated['message'],
            'status' => 'new',
        ]);

        $recipients = $tenant->users;

        if ($recipients->isNotEmpty()) {
            try {
                Notification::send($recipients, new ClassifiedContactRequestNotification(
                    $classifiedAd,
                    $validated['name'],
                    $validated['email'] ?? null,
                    $validated['phone'] ?? null,
                    $validated['message'],
                ));
            } catch (Throwable $e) {
                // Il messaggio è già salvato tra i "Messaggi clienti": non far fallire la richiesta.
            }
        }

        return back()->with('contact_success', true);
    }
}

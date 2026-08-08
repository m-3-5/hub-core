<?php

namespace App\Http\Controllers;

use App\Models\Promo;
use App\Models\Tenant;
use App\Notifications\PromoContactRequestNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class PromoPublicController extends Controller
{
    public function show(Tenant $tenant, Promo $promo): View
    {
        abort_unless($promo->tenant_id === $tenant->id, 404);
        abort_unless($promo->status === 'published', 404);

        return view($promo->templateView(), [
            'tenant' => $tenant,
            'promo' => $promo,
            'decorImages' => $promo->decorImages(),
            'shareLinks' => \App\Support\PromoShareLinks::for($promo),
            'isExpiredPromo' => $promo->isExpired(),
        ]);
    }

    public function contact(Request $request, Tenant $tenant, Promo $promo): RedirectResponse
    {
        abort_unless($promo->tenant_id === $tenant->id, 404);
        abort_unless($promo->status === 'published', 404);

        if ($request->filled('website')) {
            return back()->with('contact_success', true);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:30', 'required_without:email'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $recipients = $tenant->users;

        if ($recipients->isEmpty()) {
            return back()->withInput()->withErrors([
                'message' => 'Non riesco a inoltrare il messaggio in questo momento. Riprova più tardi.',
            ]);
        }

        try {
            \Illuminate\Support\Facades\Notification::send(
                $recipients,
                new PromoContactRequestNotification(
                    $promo,
                    $validated['name'],
                    $validated['email'] ?? null,
                    $validated['phone'] ?? null,
                    $validated['message'],
                ),
            );
        } catch (Throwable $e) {
            return back()->withInput()->withErrors([
                'message' => 'Non sono riuscito a inviare il messaggio. Riprova più tardi o usa un altro contatto.',
            ]);
        }

        return back()->with('contact_success', true);
    }
}

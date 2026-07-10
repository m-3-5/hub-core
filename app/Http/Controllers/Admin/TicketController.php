<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\NewTicketNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:2000'],
            'context_type' => ['nullable', 'string', 'max:50'],
            'context_id' => ['nullable', 'integer'],
            'context_label' => ['nullable', 'string', 'max:120'],
        ]);

        $message = trim((string) ($validated['message'] ?? ''));

        if ($message === '' && ! empty($validated['context_label'])) {
            $message = 'Interessato a: '.$validated['context_label'];
        }

        if ($message === '') {
            return back()->withErrors(['message' => 'Scrivi qualche parola prima di inviare.']);
        }

        $ticket = $tenant->tickets()->create([
            'user_id' => auth()->id(),
            'context_type' => $validated['context_type'] ?? null,
            'context_id' => $validated['context_id'] ?? null,
            'context_label' => $validated['context_label'] ?? null,
            'message' => $message,
            'status' => 'open',
        ]);

        Notification::send(
            User::where('is_super_admin', true)->get(),
            new NewTicketNotification($ticket),
        );

        return back()->with('success', 'Messaggio inviato a Max — ti rispondiamo entro 24 ore.');
    }

    public function index(): View
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $tickets = Ticket::with('tenant')->latest()->get();

        return view('admin.tickets.index', compact('tickets'));
    }

    public function respond(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $validated = $request->validate([
            'response' => ['required', 'string', 'max:2000'],
        ]);

        $ticket->update([
            'response' => $validated['response'],
            'status' => 'answered',
            'answered_at' => now(),
        ]);

        return back()->with('success', 'Risposta inviata.');
    }
}

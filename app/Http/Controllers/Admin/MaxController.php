<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\NewTicketNotification;
use App\Support\HubModules;
use App\Support\MaxIntentMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class MaxController extends Controller
{
    public function query(Request $request, Tenant $tenant, HubModules $modules): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $message = $validated['message'];
        $ownModules = collect($modules->forTenant($tenant))->filter(fn ($m) => $m['fits_type']);
        $match = MaxIntentMatcher::matchModule($message, $ownModules->all());

        if ($match && $match['active'] && $match['url']) {
            return redirect($match['url'])
                ->with('success', 'Max: sembra che cerchi «'.$match['label'].'» — eccoti qui!');
        }

        $contextLabel = $match ? 'Modulo: '.$match['label'] : 'Domanda a Max';

        $ticket = $tenant->tickets()->create([
            'user_id' => auth()->id(),
            'context_type' => $match ? 'module_request' : 'max_query',
            'context_label' => $contextLabel,
            'message' => $message,
            'status' => 'open',
        ]);

        if (! $match) {
            ActivityLog::record($tenant, 'max_unmatched_query', input: ['message' => $message], subject: $ticket);
        }

        Notification::send(
            User::where('is_super_admin', true)->get(),
            new NewTicketNotification($ticket),
        );

        return redirect()
            ->route('app.home', $tenant)
            ->with('success', $match
                ? 'Max: ho segnato il tuo interesse per «'.$match['label'].'» — ti ricontattiamo presto.'
                : 'Max: ho segnato la tua richiesta — ti rispondiamo entro 24 ore.');
    }
}

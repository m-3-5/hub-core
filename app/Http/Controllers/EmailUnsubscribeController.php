<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Support\MarketingEmails;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Link «Non mandatemele più» delle email promozionali: firmato, nessun accesso richiesto. */
class EmailUnsubscribeController extends Controller
{
    public function show(Tenant $tenant): View
    {
        return view('email-unsubscribe', ['tenant' => $tenant, 'out' => MarketingEmails::optedOut($tenant)]);
    }

    public function update(Request $request, Tenant $tenant): View
    {
        MarketingEmails::setOptOut($tenant, ! $request->boolean('resubscribe'));

        return view('email-unsubscribe', ['tenant' => $tenant->fresh(), 'out' => MarketingEmails::optedOut($tenant->fresh()), 'done' => true]);
    }
}

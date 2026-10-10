<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteLead;
use App\Models\SiteOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Solo super admin: richieste di preventivo arrivate dalla landing siti web. */
class SiteLeadController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        return view('admin.leads.index', [
            'leads' => SiteLead::query()->latest()->limit(200)->get(),
            'orders' => SiteOrder::query()->where('status', '!=', 'pending')->latest()->limit(100)->get(),
            'newCount' => SiteLead::where('status', 'new')->count(),
        ]);
    }

    public function toggle(SiteLead $lead): RedirectResponse
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $lead->update(['status' => $lead->status === 'new' ? 'contacted' : 'new']);

        return back();
    }
}

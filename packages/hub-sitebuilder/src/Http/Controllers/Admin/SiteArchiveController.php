<?php

namespace M35\HubSitebuilder\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use M35\HubSitebuilder\Models\ArchivedSite;

/** Archivio dei siti rimossi: solo per l'amministratore. */
class SiteArchiveController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        return view('hub-sitebuilder::admin.archive', [
            'sites' => ArchivedSite::query()->latest('archived_at')->paginate(30),
        ]);
    }

    public function destroy(ArchivedSite $archivedSite): RedirectResponse
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $archivedSite->delete();

        return back()->with('success', 'Eliminato definitivamente dall\'archivio.');
    }
}

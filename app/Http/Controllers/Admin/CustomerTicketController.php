<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerTicket;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomerTicketController extends Controller
{
    public function index(Tenant $tenant): View
    {
        $customerTickets = $tenant->customerTickets()->with('promo')->latest()->get();

        return view('admin.customer-tickets.index', compact('tenant', 'customerTickets'));
    }

    public function toggleStatus(Tenant $tenant, CustomerTicket $customerTicket): RedirectResponse
    {
        abort_unless($customerTicket->tenant_id === $tenant->id, 404);

        $customerTicket->update([
            'status' => $customerTicket->isNew() ? 'handled' : 'new',
        ]);

        return back();
    }
}

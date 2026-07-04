<?php

namespace M35\HubPayments\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Response;
use Illuminate\View\View;
use M35\HubPayments\Models\PayableService;

class ServicePublicController extends Controller
{
    public function archive(Tenant $tenant): View
    {
        $services = PayableService::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', 'service')
            ->where('status', 'active')
            ->where('published_to_site', true)
            ->latest()
            ->get();

        return view('hub-payments::public.archive', compact('tenant', 'services'));
    }

    public function show(Tenant $tenant, PayableService $service): View
    {
        $this->abortUnlessVisible($tenant, $service);

        return view('hub-payments::public.show', compact('tenant', 'service'));
    }

    public function embed(Tenant $tenant, PayableService $service): View
    {
        $this->abortUnlessVisible($tenant, $service);

        return view('hub-payments::public.show', [
            'tenant' => $tenant,
            'service' => $service,
            'embedMode' => true,
        ]);
    }

    public function iframeSnippet(Tenant $tenant, PayableService $service): Response
    {
        abort_unless($service->tenant_id === $tenant->id, 404);

        $src = route('client.services.embed', [$tenant, $service]);

        $html = <<<HTML
<!-- Hub Core: pagina servizio su {$tenant->website} -->
<div id="hub-core-service-{$service->slug}" style="max-width:100%;margin:0 auto">
  <iframe src="{$src}" title="{$service->title}" style="width:100%;min-height:760px;border:0;border-radius:12px" loading="lazy"></iframe>
</div>
HTML;

        return response($html, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    private function abortUnlessVisible(Tenant $tenant, PayableService $service): void
    {
        abort_unless($service->tenant_id === $tenant->id, 404);
        abort_unless($service->type === 'service', 404);
        abort_unless($service->status === 'active' && $service->published_to_site, 404);
    }
}

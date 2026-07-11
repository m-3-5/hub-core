<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantModuleAccess
{
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        /** @var Tenant|null $tenant */
        $tenant = $request->route('tenant');

        if (! $tenant) {
            return $next($request);
        }

        $module = collect(config('hub.modules'))->firstWhere('key', $moduleKey);
        $tenantType = $tenant->type ?: 'azienda';
        $allowedTypes = $module['for_types'] ?? ['azienda', 'privato', 'ente'];

        if (in_array($tenantType, $allowedTypes, true)) {
            return $next($request);
        }

        abort(403, 'Questo modulo non è disponibile per il tuo tipo di attività.');
    }
}

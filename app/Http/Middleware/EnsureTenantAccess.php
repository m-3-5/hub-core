<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('admin.login');
        }

        /** @var Tenant|null $tenant */
        $tenant = $request->route('tenant');

        if (! $tenant) {
            return $next($request);
        }

        if ($user->isSuperAdmin() || $user->belongsToTenant($tenant)) {
            return $next($request);
        }

        abort(403, 'Non hai accesso a questa attività.');
    }
}

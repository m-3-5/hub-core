<?php

namespace M35\HubSitebuilder\Http\Controllers\Admin;

use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use M35\HubSitebuilder\Models\GeneratedSite;
use M35\HubSitebuilder\Services\SiteBuilderHeroGenerator;

class SiteBuilderController extends Controller
{
    public function show(Tenant $tenant): View
    {
        $site = $tenant->generatedSite;

        return view('hub-sitebuilder::admin.wizard', [
            'tenant' => $tenant,
            'site' => $site,
        ]);
    }

    public function generate(Request $request, Tenant $tenant, SiteBuilderHeroGenerator $heroGenerator): RedirectResponse
    {
        $validated = $request->validate([
            'tagline' => ['required', 'string', 'max:200'],
            'services' => ['required', 'string', 'max:1000'],
            'cta_label' => ['nullable', 'string', 'max:60'],
            'extra' => ['nullable', 'string', 'max:1000'],
        ]);

        $site = $tenant->generatedSite ?: new GeneratedSite(['tenant_id' => $tenant->id]);

        $hero = $heroGenerator->generate($tenant, 'sites/'.$tenant->slug.'/'.\Illuminate\Support\Str::uuid());

        $site->fill([
            'answers' => [
                'tagline' => trim($validated['tagline']),
                'services' => collect(explode(',', $validated['services']))
                    ->map(fn ($s) => trim($s))
                    ->filter()
                    ->values()
                    ->all(),
                'cta_label' => trim((string) ($validated['cta_label'] ?? '')) ?: 'Contattaci',
                'extra' => trim((string) ($validated['extra'] ?? '')) ?: null,
            ],
            'status' => 'published',
            'published_at' => now(),
        ]);

        if ($hero) {
            $site->hero_svg_path = $hero['svg_path'];
            $site->hero_video_path = $hero['video_path'];
        }

        $site->save();

        return redirect()
            ->route('admin.sitebuilder.show', $tenant)
            ->with('success', 'Sito creato! Eccolo qui: '.route('site.public.show', $tenant));
    }
}

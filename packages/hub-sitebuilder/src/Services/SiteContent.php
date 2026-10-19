<?php

namespace M35\HubSitebuilder\Services;

use App\Models\Tenant;
use Illuminate\Support\Str;
use M35\HubSitebuilder\Models\GeneratedSite;

/** Testi del sito: regole di validazione, trasformazione e salvataggio condivisi da area riservata e link segreto. */
class SiteContent
{
    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'tagline' => ['required', 'string', 'max:200'],
            'services' => ['required', 'string', 'max:1000'],
            'cta_label' => ['nullable', 'string', 'max:60'],
            'extra' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function answers(array $validated): array
    {
        return [
            'tagline' => trim(strip_tags($validated['tagline'])),
            'services' => collect(explode(',', strip_tags($validated['services'])))
                ->map(fn ($s) => trim($s))
                ->filter()
                ->take(20)
                ->values()
                ->all(),
            'cta_label' => trim(strip_tags((string) ($validated['cta_label'] ?? ''))) ?: 'Contattaci',
            'extra' => trim(strip_tags((string) ($validated['extra'] ?? ''))) ?: null,
        ];
    }

    /** Salva i testi dopo il controllo: il sito è modificato adesso, quindi ogni avviso di rimozione decade. */
    public static function save(Tenant $tenant, array $answers, ?array $hero = null): GeneratedSite
    {
        $site = $tenant->generatedSite ?: new GeneratedSite(['tenant_id' => $tenant->id]);

        $site->fill([
            'answers' => $answers,
            'status' => 'published',
            'published_at' => $site->published_at ?? now(),
            'last_edited_at' => now(),
            'warned_at' => null,
            'delete_after' => null,
        ]);

        if ($hero) {
            $site->hero_svg_path = $hero['svg_path'];
            $site->hero_video_path = $hero['video_path'];
        }

        $site->save();

        return $site;
    }

    public static function heroDir(Tenant $tenant): string
    {
        return 'sites/'.$tenant->slug.'/'.Str::uuid();
    }
}

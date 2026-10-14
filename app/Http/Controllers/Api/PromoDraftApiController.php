<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use App\Models\Tenant;
use App\Services\PromoDraftImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Bozze di promo create da un post esterno (Facebook…): chiamata firmata dal sito del cliente. */
class PromoDraftApiController extends Controller
{
    public function store(Request $request, string $tenantSlug, PromoDraftImporter $importer): JsonResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $data = $request->validate([
            'source' => ['required', 'string', 'in:facebook,instagram'],
            'source_id' => ['required', 'string', 'max:190'],
            'source_url' => ['nullable', 'url:https', 'max:2000'],
            'title' => ['required', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:10000'],
            'image_url' => ['required', 'string', 'max:2000'],
            'ends_at' => ['nullable', 'date'],
            'posted_at' => ['nullable', 'date'],
            'publish' => ['nullable', 'boolean'],
        ]);

        if (! PromoDraftImporter::imageUrlAllowed($data['image_url'])) {
            return response()->json([
                'message' => 'image_url non consentito: solo https da fbcdn.net, cdninstagram.com o facebook.com.',
            ], 422);
        }

        try {
            $result = $importer->import($tenant, $data);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($result['duplicate']) {
            return response()->json([
                'duplicate' => true,
                'promo' => $result['promo'] ? $this->present($tenant, $result['promo']) : null,
                'deleted' => $result['promo'] === null,
            ], 200);
        }

        return response()->json(['duplicate' => false, 'promo' => $this->present($tenant, $result['promo'])], 201);
    }

    /** @return array<string, mixed> */
    private function present(Tenant $tenant, Promo $promo): array
    {
        return [
            'id' => $promo->id,
            'slug' => $promo->slug,
            'status' => $promo->status,
            'title' => $promo->title,
            'admin_url' => route('admin.promos.show', [$tenant, $promo]),
            'public_url' => $promo->isPublished() ? $promo->publicUrl() : null,
        ];
    }
}

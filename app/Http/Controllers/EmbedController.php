<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Support\PromoLinks;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class EmbedController extends Controller
{
    public function script(string $tenantSlug): Response
    {
        $tenantModel = Tenant::where('slug', $tenantSlug)->firstOrFail();
        $promo = $tenantModel->activePromo();

        if (! $promo) {
            return response('// hub-core: nessuna promo attiva', 200)
                ->header('Content-Type', 'application/javascript');
        }

        $popupTitle = e($promo->title);
        $popupDescription = e(Str::limit(strip_tags($promo->description ?? ''), 120));
        $imageUrl = e($promo->variantUrl('flyer') ?? $promo->imageUrl() ?? '');
        $primaryColor = e($tenantModel->primary_color);
        $buttonsHtml = $this->popupButtonsHtml($tenantModel, $promo, $primaryColor);

        $imageBlock = $promo->imageUrl()
            ? '<img src="'.$imageUrl.'" alt="" style="width:100%;display:block;max-height:280px;object-fit:cover">'
            : '';

        $js = <<<JS
(function () {
  if (document.getElementById('hub-core-promo-popup')) return;
  var overlay = document.createElement('div');
  overlay.id = 'hub-core-promo-popup';
  overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:99999;display:flex;align-items:center;justify-content:center;padding:16px;font-family:system-ui,sans-serif';
  overlay.innerHTML = '<div style="background:#fff;border-radius:16px;max-width:520px;width:100%;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.3)">' +
    '<div style="background:{$primaryColor};color:#fff;padding:14px 18px;font-weight:700;font-size:18px">{$popupTitle}</div>' +
    '{$imageBlock}' +
    '<div style="padding:18px"><p style="margin:0 0 16px;color:#444;line-height:1.5">{$popupDescription}</p>' +
    '<div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:12px">{$buttonsHtml}</div>' +
    '<button type="button" id="hub-core-promo-close" style="background:#eee;border:0;padding:10px 16px;border-radius:8px;cursor:pointer;font-size:14px">Chiudi</button></div></div>';
  document.body.appendChild(overlay);
  overlay.addEventListener('click', function (e) { if (e.target === overlay) overlay.remove(); });
  document.getElementById('hub-core-promo-close').addEventListener('click', function () { overlay.remove(); });
})();
JS;

        return response($js, 200)->header('Content-Type', 'application/javascript');
    }

    private function popupButtonsHtml(Tenant $tenant, \App\Models\Promo $promo, string $primaryColor): string
    {
        $html = '';

        foreach (PromoLinks::forPromo($tenant, $promo) as $link) {
            $url = e($link['url']);
            $label = e($link['label']);
            $style = match ($link['key']) {
                'whatsapp' => 'background:#25D366;color:#fff',
                'all_promos' => 'background:#fff;color:'.$primaryColor.';border:2px solid '.$primaryColor,
                default => 'background:'.$primaryColor.';color:#fff',
            };

            $html .= '<a href="'.$url.'" target="_blank" rel="noopener" '
                .'style="display:inline-block;'.$style.';text-decoration:none;padding:11px 16px;border-radius:8px;font-weight:600;font-size:14px">'
                .$label.'</a>';
        }

        return $html;
    }
}

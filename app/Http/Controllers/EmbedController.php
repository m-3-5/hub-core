<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
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
        $promoUrl = e($promo->publicUrl());
        $imageUrl = e($promo->variantUrl('flyer') ?? $promo->imageUrl() ?? '');
        $ctaLabel = e($promo->cta_label);
        $primaryColor = e($tenantModel->primary_color);

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
    '<a href="{$promoUrl}" style="display:inline-block;background:{$primaryColor};color:#fff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:600">{$ctaLabel}</a> ' +
    '<button type="button" id="hub-core-promo-close" style="margin-left:10px;background:#eee;border:0;padding:12px 16px;border-radius:8px;cursor:pointer">Chiudi</button></div></div>';
  document.body.appendChild(overlay);
  overlay.addEventListener('click', function (e) { if (e.target === overlay) overlay.remove(); });
  document.getElementById('hub-core-promo-close').addEventListener('click', function () { overlay.remove(); });
})();
JS;

        return response($js, 200)->header('Content-Type', 'application/javascript');
    }
}

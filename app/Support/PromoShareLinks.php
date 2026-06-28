<?php

namespace App\Support;

use App\Models\Promo;

class PromoShareLinks
{
    /** @return array<string, string> */
    public static function for(Promo $promo): array
    {
        $url = urlencode($promo->publicUrl());
        $title = urlencode($promo->seo_title ?? $promo->title);
        $text = urlencode($promo->seo_description ?? $promo->title);

        return [
            'whatsapp' => 'https://wa.me/?text='.$title.'%20'.$url,
            'facebook' => 'https://www.facebook.com/sharer/sharer.php?u='.$url,
            'twitter' => 'https://twitter.com/intent/tweet?url='.$url.'&text='.$title,
            'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url='.$url,
            'copy' => $promo->publicUrl(),
        ];
    }
}

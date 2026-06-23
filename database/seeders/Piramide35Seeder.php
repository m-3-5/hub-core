<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class Piramide35Seeder extends Seeder
{
    public function run(): void
    {
        Tenant::updateOrCreate(
            ['slug' => 'piramide35'],
            [
                'name' => 'M 3.5 — Piramide 35',
                'domain' => 'piramide35.com',
                'website' => 'https://piramide35.com',
                'phone' => '+39 348 756 4418',
                'address' => 'Via Soldato Belfi 11, 85038 Senise (PZ)',
                'primary_color' => '#22c55e',
                'settings' => [
                    'whatsapp' => '393487564418',
                    'email' => 'info@inm35.net',
                    'promos_page_url' => 'https://piramide35.com',
                    'booking_url' => null,
                    'tagline' => 'Tech & Engineering Hub — servizi digitali, web agency e piattaforma innovativa',
                    'brand' => [
                        'logo_path' => 'brand/piramide35/logo.png',
                    ],
                    'modules' => [
                        'promo' => true,
                    ],
                ],
            ]
        );
    }
}

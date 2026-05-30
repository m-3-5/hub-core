<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class BeautyOfImageSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::updateOrCreate(
            ['slug' => 'beauty-of-image'],
            [
                'name' => 'Beauty of Image',
                'domain' => 'beautyofimage.com',
                'website' => 'https://beautyofimage.com',
                'phone' => '+39 0973 686734 / +39 335 7282998',
                'address' => 'Senise — Corso Garibaldi 7',
                'primary_color' => '#e91e8c',
                'settings' => [
                    'whatsapp' => '393357282998',
                    'promos_page_url' => 'https://beautyofimage.com/promozioni',
                ],
            ]
        );
    }
}

<?php

namespace App\Console\Commands;

use App\Models\ClassifiedAd;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Crea (o elimina) un venditore privato di prova con un annuncio da 1 €, per provare gli acquisti protetti dal vivo
 * in modalità sandbox. Non crea utenti né password: collega i super admin esistenti, che vi accedono con il loro login.
 */
class MakeTestSeller extends Command
{
    protected $signature = 'hub:make-test-seller {slug=prova-privato : Identificativo del venditore di prova} {--cleanup : Elimina il venditore di prova e i suoi annunci}';

    protected $description = 'Crea un tenant privato di prova con un annuncio «oggetti» a 1 € e pagamento protetto (o lo elimina con --cleanup)';

    public function handle(): int
    {
        $slug = (string) $this->argument('slug');

        if (! str_starts_with($slug, 'prova-')) {
            $this->error('Per sicurezza lo identificativo deve iniziare con «prova-».');

            return self::FAILURE;
        }

        if ($this->option('cleanup')) {
            $tenant = Tenant::where('slug', $slug)->first();

            if (! $tenant) {
                $this->info('Nessun venditore di prova da eliminare.');

                return self::SUCCESS;
            }

            foreach ($tenant->classifiedAds as $ad) {
                foreach (array_merge($ad->images ?? [], $ad->videos ?? []) as $path) {
                    Storage::disk('public')->delete($path);
                }
            }

            $tenant->delete(); // annunci, ordini e collegamenti vanno via a cascata
            $this->info("Eliminato il venditore di prova «{$slug}».");

            return self::SUCCESS;
        }

        $tenant = Tenant::firstOrCreate(['slug' => $slug], [
            'name' => 'Prova Privato (da eliminare)',
            'type' => 'privato',
            'plan' => 'demo',
        ]);

        $admins = User::where('is_super_admin', true)->pluck('id');

        foreach ($admins as $id) {
            $tenant->users()->syncWithoutDetaching([$id => ['role' => 'admin']]);
        }

        $ad = ClassifiedAd::firstOrCreate(['tenant_id' => $tenant->id, 'slug' => 'prova-oggetto-da-eliminare'], [
            'category' => 'oggetti',
            'title' => 'Oggetto di prova (da eliminare)',
            'zone' => 'Corigliano-Rossano',
            'price' => 1,
            'protected_sale' => true,
            'description' => 'Annuncio di prova tecnica del pagamento protetto: non acquistare.',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->info('Venditore di prova pronto: '.route('admin.classifieds.index', $tenant));
        $this->info('Annuncio: '.$ad->publicUrl());

        return self::SUCCESS;
    }
}

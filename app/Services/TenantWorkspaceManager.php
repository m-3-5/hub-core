<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class TenantWorkspaceManager
{
    public function connectionName(Tenant $tenant): string
    {
        return 'tenant_workspace_'.$tenant->slug;
    }

    public function registerConnection(Tenant $tenant): string
    {
        if (! $tenant->workspace_database) {
            throw new RuntimeException("Tenant {$tenant->slug} non ha workspace_database configurato.");
        }

        $base = config('database.connections.mysql');
        $name = $this->connectionName($tenant);
        $workspace = config("hub.workspace.{$tenant->slug}", []);

        $overrides = ['database' => $tenant->workspace_database];

        if (! empty($workspace['username'])) {
            $overrides['username'] = $workspace['username'];
        }

        if (array_key_exists('password', $workspace) && $workspace['password'] !== null && $workspace['password'] !== '') {
            $overrides['password'] = $workspace['password'];
        }

        config([
            "database.connections.{$name}" => array_merge($base, $overrides),
        ]);

        return $name;
    }

    public function connectionUsername(Tenant $tenant): string
    {
        $workspace = config("hub.workspace.{$tenant->slug}", []);

        return $workspace['username'] ?? (string) config('database.connections.mysql.username');
    }

    public function ensureDatabase(Tenant $tenant, bool $attemptCreate = true): void
    {
        if (! $tenant->workspace_database) {
            throw new RuntimeException("Tenant {$tenant->slug} non ha workspace_database configurato.");
        }

        $database = $tenant->workspace_database;

        if (! preg_match('/^[a-zA-Z0-9_]+$/', $database)) {
            throw new RuntimeException('Nome database workspace non valido.');
        }

        if ($attemptCreate) {
            try {
                DB::connection('mysql')->statement(
                    "CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
                );

                return;
            } catch (QueryException $e) {
                if (! $this->isAccessDenied($e)) {
                    throw $e;
                }
            }
        }

        $this->assertDatabaseReachable($tenant);
    }

    public function assertDatabaseReachable(Tenant $tenant): void
    {
        $connection = $this->registerConnection($tenant);

        try {
            DB::connection($connection)->getPdo();
        } catch (QueryException $e) {
            $user = $this->connectionUsername($tenant);

            throw new RuntimeException(
                "Impossibile accedere al database [{$tenant->workspace_database}] con l'utente [{$user}]. "
                .'Verifica TENANT_*_DB_USERNAME e TENANT_*_DB_PASSWORD nel .env, oppure su Plesk aggiungi '
                .config('database.connections.mysql.username').' al database.',
                previous: $e,
            );
        }
    }

    private function isAccessDenied(QueryException $e): bool
    {
        return (string) $e->getCode() === '42000' || str_contains($e->getMessage(), 'Access denied');
    }

    public function migrate(Tenant $tenant): void
    {
        $connection = $this->registerConnection($tenant);

        Artisan::call('migrate', [
            '--database' => $connection,
            '--path' => 'database/migrations/workspace',
            '--force' => true,
        ]);
    }

    public function defaultWorkspaceUrl(Tenant $tenant): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'inm35.it';

        if (Str::endsWith($host, '.test')) {
            return 'http://'.$tenant->slug.'.test';
        }

        $subdomain = match ($tenant->slug) {
            'beauty-of-image' => 'beautyofimage',
            default => $tenant->slug,
        };

        return 'https://'.$subdomain.'.inm35.it';
    }

    public function provision(Tenant $tenant, bool $migrate = true, bool $attemptCreate = true): string
    {
        $this->ensureDatabase($tenant, $attemptCreate);
        $connection = $this->registerConnection($tenant);

        if ($migrate) {
            $this->migrate($tenant);
        }

        return $connection;
    }
}

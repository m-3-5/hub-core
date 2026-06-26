<?php

namespace App\Services;

use App\Models\Tenant;
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

        config([
            "database.connections.{$name}" => array_merge($base, [
                'database' => $tenant->workspace_database,
            ]),
        ]);

        return $name;
    }

    public function createDatabase(Tenant $tenant): void
    {
        if (! $tenant->workspace_database) {
            throw new RuntimeException("Tenant {$tenant->slug} non ha workspace_database configurato.");
        }

        $database = $tenant->workspace_database;

        if (! preg_match('/^[a-zA-Z0-9_]+$/', $database)) {
            throw new RuntimeException('Nome database workspace non valido.');
        }

        DB::connection('mysql')->statement(
            "CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
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

    public function provision(Tenant $tenant, bool $migrate = true): string
    {
        $this->createDatabase($tenant);
        $connection = $this->registerConnection($tenant);

        if ($migrate) {
            $this->migrate($tenant);
        }

        return $connection;
    }
}

@isset($tenant)
@auth
<style>
    .bottom-nav { display: none; }
    @media (max-width: 860px) {
        .bottom-nav {
            display: flex;
            position: fixed;
            left: 0; right: 0; bottom: 0;
            background: var(--card, #fff);
            border-top: 1px solid rgba(15,23,42,.08);
            box-shadow: 0 -4px 20px rgba(15,23,42,.06);
            padding: 6px 4px calc(6px + env(safe-area-inset-bottom));
            z-index: 40;
        }
        .bottom-nav-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            padding: 6px 2px;
            text-decoration: none;
            color: var(--muted, #64748b);
            border-radius: 12px;
            transition: color .15s ease, background .15s ease;
        }
        .bottom-nav-item.is-active {
            color: var(--accent, #e91e8c);
            background: color-mix(in srgb, var(--accent, #e91e8c) 10%, transparent);
        }
        .bottom-nav-icon { font-size: 1.3rem; line-height: 1; }
        .bottom-nav-label { font-size: .65rem; font-weight: 600; }
        body.has-bottom-nav { padding-bottom: calc(64px + env(safe-area-inset-bottom)); }
    }
</style>
<nav class="bottom-nav">
    <a href="{{ route('app.home', $tenant) }}" class="bottom-nav-item {{ request()->routeIs('app.home') ? 'is-active' : '' }}">
        <span class="bottom-nav-icon">🏠</span>
        <span class="bottom-nav-label">Home</span>
    </a>
    <a href="{{ route('admin.promos.index', $tenant) }}" class="bottom-nav-item {{ request()->routeIs('admin.promos.*') ? 'is-active' : '' }}">
        <span class="bottom-nav-icon">✨</span>
        <span class="bottom-nav-label">Promo</span>
    </a>
    @if ($tenant->type !== 'privato')
    <a href="{{ route('admin.services.index', $tenant) }}" class="bottom-nav-item {{ request()->routeIs('admin.services.*') ? 'is-active' : '' }}">
        <span class="bottom-nav-icon">💆</span>
        <span class="bottom-nav-label">Servizi</span>
    </a>
    @endif
    <a href="{{ route('admin.billing.show', $tenant) }}" class="bottom-nav-item {{ request()->routeIs('admin.billing.*') || request()->routeIs('admin.module-billing.*') ? 'is-active' : '' }}">
        <span class="bottom-nav-icon">🧾</span>
        <span class="bottom-nav-label">Abbonamento</span>
    </a>
</nav>
@endauth
@endisset

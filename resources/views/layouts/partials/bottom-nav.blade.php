{{--
    Navigazione sempre visibile: barra laterale su computer (tutte le sezioni), barra in basso su telefono (le 5 principali).
    Stili in public/css/hub-ui.css. Per l'attività corrente usa $tenant; per l'amministratore senza attività mostra le sezioni di gestione.
--}}
@auth
@php
    $uiUser = auth()->user();
    $uiTenant = $tenant ?? null;
    $uiItems = [];   // [label, emoji, url, attivo, badge]
    $uiTabs = [];

    if ($uiTenant) {
        $uiActive = collect(app(\App\Support\HubModules::class)->forTenant($uiTenant))->where('active', true)->pluck('key')->all();
        $uiNew = (int) \App\Models\CustomerTicket::query()->where('tenant_id', $uiTenant->id)->where('status', 'new')->count();

        $uiItems[] = ['Home', 'home', route('app.home', $uiTenant), request()->routeIs('app.home')];
        if (in_array('promo', $uiActive, true)) $uiItems[] = ['Promo', 'promo', route('admin.promos.index', $uiTenant), request()->routeIs('admin.promos.*')];
        if (in_array('services', $uiActive, true)) $uiItems[] = ['Servizi', 'services', route('admin.services.index', $uiTenant), request()->routeIs('admin.services.*')];
        if (in_array('products', $uiActive, true)) $uiItems[] = ['Prodotti', 'products', route('admin.products.index', $uiTenant), request()->routeIs('admin.products.*')];
        if (in_array('quotes', $uiActive, true)) $uiItems[] = ['Preventivi', 'quotes', route('admin.quotes.index', $uiTenant), request()->routeIs('admin.quotes.*')];
        if (in_array('classifieds', $uiActive, true)) $uiItems[] = ['Annunci', 'classifieds', route('admin.classifieds.index', $uiTenant), request()->routeIs('admin.classifieds.*')];
        if (in_array('website', $uiActive, true)) $uiItems[] = ['Sito web', 'globe', route('admin.sitebuilder.show', $uiTenant), request()->routeIs('admin.sitebuilder.*')];
        if (array_intersect(['services', 'products', 'quotes'], $uiActive)) $uiItems[] = ['Ordini', 'orders', route('admin.orders.index', $uiTenant), request()->routeIs('admin.orders.*')];
        $uiItems[] = ['Messaggi', 'messages', route('admin.customer-tickets.index', $uiTenant), request()->routeIs('admin.customer-tickets.*'), $uiNew];

        $uiMore = [];
        $uiMore[] = ['Profilo', 'user', route('admin.profile.edit', $uiTenant), request()->routeIs('admin.profile.*')];
        if (in_array('billing', $uiActive, true)) $uiMore[] = ['Abbonamento', 'card', route('admin.billing.show', $uiTenant), request()->routeIs('admin.billing.*') || request()->routeIs('admin.module-billing.*')];

        // Barra in basso: Home + le prime sezioni di lavoro + Messaggi (max 5)
        $work = collect($uiItems)->reject(fn ($i) => in_array($i[0], ['Home', 'Messaggi', 'Ordini'], true))->take(3)->all();
        $uiTabs = array_merge([$uiItems[0]], $work, [collect($uiItems)->firstWhere(0, 'Messaggi')]);
        $brandName = $uiTenant->name;
        $brandSub = ['azienda' => 'Azienda', 'privato' => 'Privato', 'ente' => 'Ente'][$uiTenant->type ?: 'azienda'] ?? 'Azienda';
        $brandLogo = app(\App\Services\TenantBrandManager::class)->logoUrl($uiTenant);
        $brandHome = route('app.home', $uiTenant);
        $uiPublic = [['Pagina pubblica', 'external', route('promo.archive', $uiTenant), false]];
    } elseif ($uiUser->isSuperAdmin()) {
        $uiItems = [
            ['Clienti', 'users', route('admin.dashboard'), request()->routeIs('admin.dashboard') || request()->routeIs('admin.tenants.*')],
            ['Ticket', 'messages', route('admin.tickets.index'), request()->routeIs('admin.tickets.*')],
            ['Richieste sito', 'mail', route('admin.leads.index'), request()->routeIs('admin.leads.*')],
            ['Pagamenti protetti', 'shield', route('admin.protected-orders.index'), request()->routeIs('admin.protected-orders.*')],
            ['Commissioni', 'coins', route('admin.commissions.index'), request()->routeIs('admin.commissions.*')],
            ['Attività', 'chart', route('admin.activity.index'), request()->routeIs('admin.activity.*')],
            ['Feedback', 'messages', route('admin.feedback.index'), request()->routeIs('admin.feedback.*')],
            ['Archivio siti', 'archive', route('admin.site-archive.index'), request()->routeIs('admin.site-archive.*')],
        ];
        $uiMore = [['Le mie attività', 'home', route('app.index'), request()->routeIs('app.index')]];
        $uiPublic = [['Listino prezzi', 'tag', route('pricing.show'), false]];
        $uiTabs = array_slice($uiItems, 0, 4);
        $uiTabs[] = ['Attività', 'chart', route('admin.activity.index'), request()->routeIs('admin.activity.*')];
        $brandName = 'Hub Core';
        $brandSub = 'Amministrazione';
        $brandLogo = null;
        $brandHome = route('admin.dashboard');
    }
@endphp
@if (! empty($uiItems))
<aside class="ui-side" aria-label="Menu principale">
    <a class="ui-side__brand" href="{{ $brandHome }}">
        <span class="ui-side__logo">@if (! empty($brandLogo))<img src="{{ $brandLogo }}" alt="">@else{{ mb_strtoupper(mb_substr($brandName, 0, 1)) }}@endif</span>
        <span><b>{{ $brandName }}</b><small>{{ $brandSub }}</small></span>
    </a>

    @foreach ($uiItems as $it)
        <a class="ui-side__item {{ $it[3] ? 'is-active' : '' }}" href="{{ $it[2] }}" @if($it[3]) aria-current="page" @endif>
            <span class="ui-side__ico">@include('layouts.partials.icon', ['name' => $it[1], 'size' => 19])</span>{{ $it[0] }}
            @if (! empty($it[4]))<span class="ui-side__badge">{{ $it[4] }}</span>@endif
        </a>
    @endforeach

    <div class="ui-side__sep"></div>
    @foreach (array_merge($uiMore ?? [], $uiPublic ?? []) as $it)
        <a class="ui-side__item {{ $it[3] ? 'is-active' : '' }}" href="{{ $it[2] }}" @if($it[1] === 'external') target="_blank" rel="noopener" @endif>
            <span class="ui-side__ico">@include('layouts.partials.icon', ['name' => $it[1], 'size' => 19])</span>{{ $it[0] }}
        </a>
    @endforeach
    @if ($uiTenant && $uiUser->isSuperAdmin())
        <a class="ui-side__item" href="{{ route('admin.dashboard') }}"><span class="ui-side__ico">@include('layouts.partials.icon', ['name' => 'back', 'size' => 19])</span>Tutti i clienti</a>
    @endif

    <div class="ui-side__foot">
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="ui-side__item"><span class="ui-side__ico">@include('layouts.partials.icon', ['name' => 'logout', 'size' => 19])</span>Esci</button>
        </form>
    </div>
</aside>

<nav class="ui-tabbar" aria-label="Menu principale">
    @foreach ($uiTabs as $it)
        <a class="ui-tab {{ $it[3] ? 'is-active' : '' }}" href="{{ $it[2] }}" @if($it[3]) aria-current="page" @endif>
            <span class="ui-tab__ico">@include('layouts.partials.icon', ['name' => $it[1], 'size' => 23])</span>
            <span class="ui-tab__label">{{ $it[0] }}</span>
            @if (! empty($it[4]))<span class="ui-tab__badge">{{ $it[4] }}</span>@endif
        </a>
    @endforeach
</nav>
@endif
@endauth

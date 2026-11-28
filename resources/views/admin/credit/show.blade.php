@extends('layouts.admin')

@section('title', 'Pagamenti — '.$tenant->name)

@php $eur = fn (int $c) => \App\Support\PayPerUse::euro($c); @endphp

@section('content')
<style>
    .cr { max-width: 560px; margin: 0 auto; }
    .cr-hero { text-align: center; padding: 22px 16px; border-radius: 22px; color: #fff; background: linear-gradient(135deg, #4f46e5, #a855f7); margin-bottom: 18px; }
    .cr-hero b { display: block; font-size: 1.6rem; line-height: 1.2; }
    .cr-hero small { display: block; opacity: .9; margin-top: 6px; font-weight: 600; }
    .cr-wait { background: #fff7e6; border: 1px solid #fde4b0; border-radius: 16px; padding: 14px 16px; margin-bottom: 18px; }
    .cr-wait p { margin: 0; }
    .cr-cta { width: 100%; min-height: 56px; font-size: 1.05rem; }
    .cr-card { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px; border-radius: 16px; background: #f0fdf4; color: #166534; font-weight: 700; margin-bottom: 18px; }
    .cr-prices { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 18px; text-align: center; }
    .cr-prices div { background: #f6f7fb; border-radius: 14px; padding: 10px 4px; }
    .cr-prices small { display: block; color: #64748b; font-size: .78rem; }
    .cr-prices b { font-size: 1.05rem; }
    .cr-log { list-style: none; margin: 0; padding: 0; }
    .cr-log li { display: flex; justify-content: space-between; gap: 10px; padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: .92rem; }
    .cr-log b { white-space: nowrap; }
    .cr-top { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 8px; }
    .cr-top .btn { width: 100%; min-height: 52px; font-size: 1.05rem; }
    .cr-custom { display: flex; gap: 8px; margin-bottom: 6px; }
    .cr-custom input, .cr-custom select { flex: 1; min-height: 48px; border: 2px solid #e2e3ea; border-radius: 12px; padding: 8px 12px; font-size: 1rem; min-width: 0; }
    .cr-note { color: #64748b; font-size: .85rem; margin: 0 0 14px; }
    .cr-more { background: #f6f7fb; border-radius: 16px; padding: 12px 14px; margin-bottom: 18px; }
    .cr-more summary { cursor: pointer; font-weight: 700; }
    .cr-more p { margin: 10px 0; }
    @media (max-width: 420px) { .cr-prices { grid-template-columns: repeat(2, 1fr); } }
</style>

<div class="card cr">
    <h1 style="margin:0 0 14px">Pagamenti</h1>

    @if (session('status'))<p class="alert">{{ session('status') }}</p>@endif
    @if (session('success'))<p class="alert">{{ session('success') }}</p>@endif
    @error('credit')<p class="error">{{ $message }}</p>@enderror

    <div class="cr-hero" style="{{ $credit < 0 ? 'background:linear-gradient(135deg,#dc2626,#f97316)' : '' }}">
        <b>{{ $eur($credit) }}</b>
        <small>
            @if ($credit < 0)
                Credito finito: stai usando il fido ({{ $eur($overdraft + $credit) }} rimasti). Ricarica per saldare.
            @else
                Il tuo credito · ogni cosa a 1 € si scala da qui
            @endif
        </small>
    </div>

    @if ($gameLocked > 0)
        <div class="cr-wait">
            <p>🎮 Hai altri <strong>{{ $eur($gameLocked) }}</strong> vinti ai giochi di piramide35.com. Dei buoni dei giochi ne puoi usare {{ $eur($gameFree) }} subito: <strong>il resto si sblocca con una ricarica da {{ $eur($topUpMin) }}</strong>.</p>
        </div>
    @endif

    @if ($pending)
        <div class="cr-wait">
            <p>Per pubblicare <strong>«{{ $pending['title'] }}»</strong>: <strong>{{ $eur($pendingPrice) }}</strong>.</p>
            @if ($canPayPending)
                <form method="POST" action="{{ route('admin.credit.publish', $tenant) }}" style="margin-top:10px">@csrf
                    <button class="btn cr-cta" type="submit" data-busy="Pubblico…">🚀 Pubblica ora</button>
                </form>
            @else
                <p style="margin-top:6px">Ricarica il credito qui sotto (da {{ $eur($topUpMin) }}): poi si pubblica da solo.</p>
            @endif
        </div>
    @endif

    @if ($premiumOn)
        @php
            $premiumUntil = ! empty($premium['until']) ? \Illuminate\Support\Carbon::parse($premium['until'])->timezone(config('app.timezone'))->format('d/m/Y') : null;
            $perks = function (array $p) use ($eur) {
                $l = ['<b>'.$p['items'].'</b> promo, prodotti e servizi al mese', 'Sito web incluso'];
                if ($p['addons']) $l[] = 'SEO, «Fatti trovare dall\'IA» e segnalazione a Google e Bing';
                if ($p['langs'] >= 99) $l[] = 'Tutte le lingue del sito';
                elseif ($p['langs'] > 0) $l[] = $p['langs'].' lingue del sito';
                if ($p['done_for_you']) $l[] = '«Lo facciamo noi»: sito e prime promo';
                if ($p['priority']) $l[] = 'Assistenza prioritaria';
                if ($p['flyers'] > 0) $l[] = $p['flyers'].' volantino'.($p['flyers'] > 1 ? 'i' : '').' con l\'IA al mese <small>(in arrivo)</small>';
                if ($p['featured_weeks'] > 0) $l[] = $p['featured_weeks'].' settiman'.($p['featured_weeks'] > 1 ? 'e' : 'a').' «In evidenza» al mese <small>(in arrivo)</small>';
                return $l;
            };
        @endphp
        <style>
            .pk-grid { display: grid; gap: 10px; margin-bottom: 18px; }
            .pk { border: 2px solid #fde4b0; background: #fffaf0; border-radius: 18px; padding: 14px; }
            .pk.best { border-color: #f59e0b; }
            .pk h3 { margin: 0; font-size: 1.1rem; }
            .pk .pr { font-size: 1.3rem; font-weight: 800; margin: 2px 0 6px; }
            .pk ul { margin: 0 0 10px 18px; padding: 0; font-size: .92rem; display: grid; gap: 3px; }
            .pk form { display: flex; gap: 8px; }
            .pk select { flex: 1; min-width: 0; min-height: 46px; border: 2px solid #e2e3ea; border-radius: 12px; padding: 6px 10px; font-size: .95rem; background: #fff; }
            .pk-on { border-radius: 18px; padding: 16px; margin-bottom: 18px; background: linear-gradient(135deg,#f59e0b,#f97316); color: #fff; }
        </style>

        @if ($package)
            <div class="pk-on">
                <b style="font-size:1.15rem">{{ $package['stars'] }} {{ $package['label'] }} attivo fino al {{ $premiumUntil }}</b>
                <p style="margin:6px 0">Questo mese ti restano <b>{{ $packageItemsLeft }}</b> pubblicazioni incluse su {{ $package['items'] }}; dopo, {{ $eur($overCents) }} l'una.</p>
                <form method="POST" action="{{ route('admin.credit.premium.renewal', $tenant) }}">@csrf
                    @if (! empty($premium['renew']))
                        <p style="margin:0 0 8px;font-size:.9rem">Si rinnova da solo dal credito.</p>
                        <button type="submit" class="btn btn-secondary" style="width:100%;min-height:46px">Non rinnovare</button>
                    @else
                        <input type="hidden" name="renew" value="1">
                        <p style="margin:0 0 8px;font-size:.9rem">Non si rinnova: dopo il {{ $premiumUntil }} paghi {{ $eur($exitCents) }} a pubblicazione per un mese, poi 1 €.</p>
                        <button type="submit" class="btn btn-secondary" style="width:100%;min-height:46px">Rinnova da solo</button>
                    @endif
                </form>
            </div>
        @endif

        <details class="cr-more" @if (! $package) open @endif>
            <summary>⭐ {{ $package ? 'Cambia o allunga il pacchetto' : 'Pubblichi tanto? Scegli un pacchetto' }}</summary>
            <p class="cr-note" style="margin-top:8px">Facoltativo: senza pacchetto paghi solo quello che pubblichi. Si scala dal credito, si rinnova da solo e lo fermi quando vuoi. Più mesi insieme costano meno.@if ($package) Se cambi pacchetto, la parte non usata di quello di adesso torna nel credito.@endif</p>
            @if ($premiumExit)<p style="font-weight:700">Adesso paghi {{ $eur($exitCents) }} a pubblicazione (sei uscito da un pacchetto da poco).</p>@endif
            <div class="pk-grid">
                @foreach ($plans as $key => $p)
                    <div class="pk {{ $key === 'premium' ? 'best' : '' }}">
                        <h3>{{ $p['stars'] }} {{ $p['label'] }}</h3>
                        <div class="pr">{{ $eur($p['cents']) }} <small style="font-size:.8rem;font-weight:600">al mese</small></div>
                        <ul>@foreach ($perks($p) as $perk)<li>{!! $perk !!}</li>@endforeach</ul>
                        <form method="POST" action="{{ route('admin.credit.premium', $tenant) }}">@csrf
                            <input type="hidden" name="plan" value="{{ $key }}">
                            <select name="months" aria-label="Per quanti mesi">
                                @foreach ($monthOptions as $m => $disc)
                                    <option value="{{ $m }}">{{ $m }} {{ $m === 1 ? 'mese' : 'mesi' }} · {{ $eur(\App\Support\Packages::priceCents($key, $m)) }}{{ $disc ? ' (−'.$disc.'%)' : '' }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn" data-busy="Attivo…">Attiva</button>
                        </form>
                    </div>
                @endforeach
            </div>
            <p class="cr-note">Oltre le pubblicazioni del mese: {{ $eur($overCents) }} l'una. Gli annunci sono sempre gratis.</p>
        </details>
    @endif

    <h2 style="font-size:1rem;margin:0 0 8px">Ricarica il credito</h2>
    <div class="cr-top">
        @foreach ([5, 10, 20] as $amount)
            @if ($amount * 100 >= $topUpMin)
                <form method="POST" action="{{ route('admin.credit.topup', $tenant) }}">@csrf
                    <input type="hidden" name="amount" value="{{ $amount }}">
                    <button type="submit" class="btn" data-busy="Apro…">+ {{ $amount }} €</button>
                </form>
            @endif
        @endforeach
    </div>
    <form method="POST" action="{{ route('admin.credit.topup', $tenant) }}" class="cr-custom">@csrf
        <input type="number" name="amount" min="{{ $topUpMin / 100 }}" max="500" step="1" placeholder="Altro importo (da {{ $topUpMin / 100 }} €)" inputmode="numeric">
        <button type="submit" class="btn btn-secondary" data-busy="Apro…">Ricarica</button>
    </form>
    <p class="cr-note">Paghi subito con la carta sulla pagina sicura di Stripe. Ricevuta per email a te e a noi.</p>

    <form method="POST" action="{{ route('voucher.redeem') }}" class="cr-custom" style="margin-top:4px">@csrf
        <input type="hidden" name="attivita" value="{{ $tenant->slug }}">
        <input type="text" name="codice" maxlength="80" placeholder="🎁 Hai un codice buono?" autocapitalize="characters" autocomplete="off" spellcheck="false" required value="{{ old('codice') }}">
        <button type="submit" class="btn btn-secondary">Usa</button>
    </form>
    @error('codice')<p class="error">{{ $message }}</p>@enderror
    <p class="cr-note">I buoni vinti ai giochi di piramide35.com diventano credito: {{ $eur($gameFree) }} si usano subito, il resto dopo la prima ricarica da {{ $eur($topUpMin) }}.</p>

    <details class="cr-more" @if ($credit < 0) open @endif>
        <summary>Altri modi per pagare</summary>
        @if ($cardEnabled || $toBill > 0)
        <p><strong>💳 Carta salvata, paghi dopo:</strong> quando il credito finisce, quello che pubblichi lo paghi dopo {{ $billDays }} giorni, tutto insieme.</p>
        @if ($hasCard)
            <div class="cr-card"><span>💳 {{ ucfirst($card['brand'] ?? 'Carta') }} •••• {{ $card['last4'] ?? '' }}</span><span>✓</span></div>
            @if ($toBill > 0)
                <p>Da pagare sulla carta: <strong>{{ $eur($toBill) }}</strong> il {{ \Illuminate\Support\Carbon::parse($nextBill)->timezone(config('app.timezone'))->format('d/m/Y') }}.</p>
            @endif
        @endif
        @if (! empty($card['failed']))
            <p class="error">L'ultimo addebito non è andato a buon fine: aggiungi un'altra carta.</p>
        @endif
        @if ($cardEnabled)
        <form method="POST" action="{{ route('admin.credit.checkout', $tenant) }}" style="margin-bottom:12px">@csrf
            <button type="submit" class="btn btn-secondary" style="width:100%;min-height:48px" data-busy="Apro…">{{ $hasCard ? 'Cambia carta' : 'Salva la carta' }}</button>
        </form>
        @endif
        @endif
        <p><strong>🏦 Bonifico o contanti:</strong> scrivi a <a href="mailto:{{ config('company.email') }}?subject={{ rawurlencode('Ricarica credito '.$tenant->name) }}">{{ config('company.email') }}</a> con il nome dell'attività: segniamo noi la ricarica e ti arriva la ricevuta.</p>
        <p><strong>⏳ Credito finito?</strong> Puoi andare avanti lo stesso fino a {{ $eur($overdraft) }} (fido): ti avvisiamo per email e saldi con la prossima ricarica.</p>
    </details>

    @if (auth()->user()?->isSuperAdmin())
        <form method="POST" action="{{ route('admin.credit.manual', $tenant) }}" class="cr-custom" style="margin-top:12px">@csrf
            <input type="number" name="amount" min="1" max="5000" step="0.5" placeholder="€ ricevuti" required>
            <select name="method"><option value="bonifico">Bonifico</option><option value="contanti">Contanti</option><option value="regalo">Regalo</option></select>
            <button type="submit" class="btn btn-secondary">Segna ricarica</button>
        </form>
    @endif

    <div class="cr-prices" aria-label="Quanto costa pubblicare adesso">
        <div><small>Annuncio</small><b>Gratis</b></div>
        @foreach (\App\Support\PayPerUse::KINDS as $kind => $label)
            <div><small>{{ ucfirst($label) }}</small><b>{{ $prices[$kind] === 0 ? 'Incluso' : $eur($prices[$kind]) }}</b></div>
        @endforeach
    </div>

    <p style="margin:0 0 18px">@include('layouts.partials.costs-link')</p>

    @if ($entries->isNotEmpty())
        <h2 style="font-size:1rem;margin:0 0 6px">Movimenti</h2>
        <ul class="cr-log">
            @foreach ($entries as $e)
                <li>
                    <span>{{ $e->description }}<br><small style="color:#94a3b8">{{ $e->created_at->timezone(config('app.timezone'))->format('d/m/Y') }}</small></span>
                    <b>
                        @if ($e->reason === 'included') Incluso
                        @elseif ($e->reason === 'premium') ⭐ Premium
                        @elseif (in_array($e->reason, ['topup', 'gift', 'voucher', 'premium_refund'], true)) <span style="color:#16a34a">+ {{ $eur($e->amount_cents) }}</span>
                        @elseif ($e->status === 'credit') − {{ $eur($e->amount_cents) }}
                        @elseif ($e->status === 'owed') − {{ $eur($e->amount_cents) }} <small>(fido)</small>
                        @elseif ($e->status === 'billed') ✓ {{ $eur($e->amount_cents) }}
                        @else {{ $eur($e->amount_cents) }} <small>(carta)</small>
                        @endif
                    </b>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection

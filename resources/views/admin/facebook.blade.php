@extends('layouts.admin')

@section('title', 'Pubblica da Facebook — '.$tenant->name)

@section('content')
<style>
    .fb { max-width: 560px; margin: 0 auto; }
    .fb-hero { text-align: center; padding: 22px 16px; border-radius: 22px; color: #fff; background: linear-gradient(135deg, #1877f2, #4f46e5); margin-bottom: 18px; }
    .fb-hero b { display: block; font-size: 1.45rem; line-height: 1.25; }
    .fb-hero small { display: block; opacity: .92; margin-top: 6px; font-weight: 600; }
    .fb-card { background: #fff; border: 1px solid #e7e7ef; border-radius: 18px; padding: 16px; margin-bottom: 14px; }
    .fb-card h2 { font-size: 1.05rem; margin: 0 0 8px; }
    .fb-card p { margin: 0 0 10px; color: #4b4b5a; line-height: 1.5; }
    .fb-steps { margin: 0; padding-left: 20px; color: #4b4b5a; line-height: 1.6; }
    .fb-btn { display: flex; align-items: center; justify-content: center; width: 100%; min-height: 52px; border: 0; border-radius: 14px; background: #1877f2; color: #fff; font-weight: 700; font-size: 1.02rem; cursor: pointer; text-decoration: none; }
    .fb-btn.ghost { background: #f1f2f7; color: #333; }
    .fb-field { width: 100%; min-height: 48px; border: 2px solid #e2e3ea; border-radius: 12px; padding: 10px 12px; font-size: 1rem; margin-bottom: 10px; }
    .fb-ok { background: #e8f5e9; color: #2e7d32; padding: 12px 14px; border-radius: 14px; margin-bottom: 14px; }
    .fb-bad { background: #fdecea; color: #b42318; padding: 12px 14px; border-radius: 14px; margin-bottom: 14px; }
    .fb-page { display: flex; align-items: center; gap: 10px; font-weight: 700; }
</style>

<div class="fb">
    <div class="fb-hero"><b>📘 Pubblica da Facebook</b><small>Scrivi il post una volta sola: con #promo diventa una promo e va anche nel tuo sito.</small></div>

    @if (session('success'))<div class="fb-ok">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="fb-bad">{{ $errors->first() }}</div>@endif

    @if (! $enabled)
        <div class="fb-card">
            <h2>In arrivo</h2>
            <p>Stiamo completando l'approvazione con Facebook. Appena è pronta, qui trovi il tasto «Collega Facebook».</p>
        </div>
    @elseif ($pages)
        <div class="fb-card">
            <h2>Quale pagina colleghiamo?</h2>
            @foreach ($pages as $page)
                <form method="POST" action="{{ route('admin.facebook.choose', $tenant) }}" style="margin-bottom:8px">
                    @csrf
                    <input type="hidden" name="page_id" value="{{ $page['id'] }}">
                    <button type="submit" class="fb-btn ghost">{{ $page['name'] }}</button>
                </form>
            @endforeach
        </div>
    @elseif ($fb)
        <div class="fb-card">
            <div class="fb-page">✅ Collegata: {{ $fb['page_name'] }}</div>
            <p style="margin-top:10px">Pubblica su questa pagina un post <b>con una foto</b> e con <b>{{ implode(' o ', $hashtags) }}</b> nel testo. Diventa una promo da sola:</p>
            <ul class="fb-steps">
                <li>con il credito (o la carta salvata) va subito online, anche nel tuo sito (1 €, come ogni promo);</li>
                <li>se il credito non basta, o se il testo va controllato, resta una bozza e ti scriviamo per approvarla.</li>
            </ul>
        </div>
        <div class="fb-card">
            <h2>Gli hashtag e dove va il post</h2>
            <ul class="fb-steps">
                <li><b>{{ implode(' ', $hashtags) }}</b> → promo (1 €, con foto)</li>
                <li><b>#prodotto</b> → prodotto in vetrina (1 €). Scrivi il prezzo, es. «25 €».</li>
                <li><b>#servizio</b> → servizio in vetrina (1 €). Scrivi il prezzo, es. «40 €».</li>
                <li><b>#novita</b> → «Novità ed eventi» del tuo sito (gratis)</li>
                <li><b>#evento</b> → come evento, con la data se la scrivi: «24 ottobre alle 18:30» (gratis)</li>
                <li><b>#blog</b> → un articolo: prima riga il titolo, poi il testo (gratis)</li>
                <li><b>#lavori</b> → la foto va nella galleria del tuo sito (gratis)</li>
            </ul>
            <p style="margin-top:10px">Senza prezzo, senza credito o se il testo va controllato, prodotti e servizi restano da completare nel pannello. Novità, eventi, blog e lavori vanno nel sito se ce l'hai. <a href="{{ route('guides.facebook') }}" target="_blank">Leggi la guida completa</a></p>
        </div>
        <div class="fb-card">
            <h2>Hashtag delle promo</h2>
            <form method="POST" action="{{ route('admin.facebook.hashtags', $tenant) }}">
                @csrf
                <input class="fb-field" name="hashtags" value="{{ old('hashtags', implode(' ', $hashtags)) }}" placeholder="#promo #offerta" maxlength="120">
                <button type="submit" class="fb-btn ghost">Salva hashtag</button>
            </form>
        </div>
        <form method="POST" action="{{ route('admin.facebook.disconnect', $tenant) }}" onsubmit="return confirm('Scollegare la pagina?')">
            @csrf
            <button type="submit" class="fb-btn ghost">Scollega la pagina</button>
        </form>
    @else
        <div class="fb-card">
            <h2>Come funziona</h2>
            <ol class="fb-steps">
                <li>Premi «Collega Facebook» ed entra con il tuo Facebook.</li>
                <li>Scegli la pagina della tua attività.</li>
                <li>Fatto. Da ora un post con <b>#promo</b> e una foto diventa una promo; con <b>#prodotto</b>, <b>#servizio</b>, <b>#novita</b>, <b>#evento</b>, <b>#blog</b> o <b>#lavori</b> va nella sezione giusta.</li>
            </ol>
        </div>
        <form method="POST" action="{{ route('admin.facebook.connect', $tenant) }}">
            @csrf
            <button type="submit" class="fb-btn">Collega Facebook</button>
        </form>
        <p style="font-size:.85rem;color:#6b6b7b;margin-top:10px">Leggiamo solo i post della pagina che scegli, per trasformarli in promo, prodotti, servizi, novità e foto del sito. Non pubblichiamo niente su Facebook al posto tuo. Puoi scollegare quando vuoi.</p>
    @endif
</div>
@endsection

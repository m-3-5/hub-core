<article class="svc-card">
    <a href="{{ route('admin.services.show', [$tenant, $service]) }}" class="svc-card__media">
        @if ($service->coverImageUrl())
            <img src="{{ $service->coverImageUrl() }}" alt="{{ $service->title }}">
        @else
            <div class="svc-card__placeholder" aria-hidden="true">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M3 7a2 2 0 0 1 2-2h2l1.5-2h7L17 5h2a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/>
                    <circle cx="12" cy="13" r="3.5"/>
                </svg>
                <span>Nessuna foto</span>
            </div>
        @endif
    </a>
    <div class="svc-card__body">
        <span class="svc-badge {{ $service->published_to_site ? 'svc-badge--live' : 'svc-badge--off' }}">
            {{ $service->published_to_site ? '● Pubblicato (inm35.it + '.($tenant->website ? preg_replace('#^https?://#', '', $tenant->website) : 'sito').')' : 'Non pubblicato' }}
        </span>
        <h3><a href="{{ route('admin.services.show', [$tenant, $service]) }}">{{ $service->title }}</a></h3>
        <p class="svc-card__price">{{ $service->amountEuros() }} €</p>

        <div class="svc-card__actions">
            <a href="{{ route('admin.services.edit', [$tenant, $service]) }}" class="svc-btn" title="Modifica">✎ Modifica</a>

            <form method="POST" action="{{ route('admin.services.publish', [$tenant, $service]) }}" class="svc-inline">
                @csrf
                <button type="submit" class="svc-btn" title="{{ $service->published_to_site ? 'Nascondi dal sito' : 'Pubblica sul sito' }}">
                    {{ $service->published_to_site ? '◎ Nascondi' : '◉ Pubblica' }}
                </button>
            </form>

            <button type="button" class="svc-btn" title="Copia link cliente"
                    onclick="navigator.clipboard.writeText('{{ $service->payment_url }}');this.textContent='✓ Copiato!';setTimeout(()=>this.textContent='🔗 Link cliente',1500)">
                🔗 Link cliente
            </button>

            <form method="POST" action="{{ route('admin.services.destroy', [$tenant, $service]) }}"
                  onsubmit="return confirm('Archiviare questo servizio e disattivare il link Stripe?')" class="svc-inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="svc-btn svc-btn--danger" title="Elimina">🗑 Elimina</button>
            </form>
        </div>
    </div>
</article>

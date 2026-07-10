<style>
    .mreq-overlay {
        position: fixed; inset: 0; background: rgba(15,23,42,.45);
        display: flex; align-items: flex-end; justify-content: center;
        z-index: 70;
    }
    .mreq-overlay[hidden] { display: none; }
    .mreq-card {
        background: var(--card, #fff);
        border-radius: 22px 22px 0 0;
        padding: 22px 20px calc(20px + env(safe-area-inset-bottom));
        width: 100%; max-width: 420px;
        box-shadow: 0 -10px 40px rgba(15,23,42,.2);
    }
    @media (min-width: 640px) {
        .mreq-overlay { align-items: center; }
        .mreq-card { border-radius: 22px; }
    }
    .mreq-card h3 { margin: 0 0 6px; font-size: 1.1rem; }
    .mreq-card p { margin: 0 0 14px; color: var(--muted, #64748b); font-size: .9rem; }
    .mreq-card textarea {
        width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 12px;
        font-family: inherit; font-size: .9rem; margin-bottom: 14px;
    }
    .mreq-actions { display: flex; gap: 10px; }
    .mreq-actions button { flex: 1; padding: 12px; border-radius: 12px; border: 0; font-weight: 700; cursor: pointer; }
    .mreq-cancel { background: #e2e8f0; color: var(--text, #0f172a); }
    .mreq-submit { background: var(--accent); color: #fff; }
</style>

<div class="mreq-overlay" id="mreq-overlay" hidden>
    <div class="mreq-card">
        <h3 id="mreq-title">Richiedi questo modulo</h3>
        <p>Segnaci il tuo interesse — ti ricontattiamo per capire se e quando attivarlo per la tua attività.</p>
        <form method="POST" action="{{ route('admin.tickets.store', $tenant) }}">
            @csrf
            <input type="hidden" name="context_type" value="module_request">
            <input type="hidden" name="context_label" id="mreq-label" value="">
            <textarea name="message" rows="3" maxlength="2000" placeholder="Facoltativo: raccontaci a cosa ti servirebbe"></textarea>
            <div class="mreq-actions">
                <button type="button" class="mreq-cancel" id="mreq-cancel">Annulla</button>
                <button type="submit" class="mreq-submit">Invia richiesta</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const overlay = document.getElementById('mreq-overlay');
    const title = document.getElementById('mreq-title');
    const label = document.getElementById('mreq-label');
    const cancel = document.getElementById('mreq-cancel');

    document.querySelectorAll('[data-request-module]').forEach(btn => {
        btn.addEventListener('click', () => {
            const moduleLabel = btn.dataset.requestModule;
            title.textContent = 'Richiedi ' + moduleLabel;
            label.value = 'Modulo: ' + moduleLabel;
            overlay.hidden = false;
        });
    });

    cancel.addEventListener('click', () => { overlay.hidden = true; });
    overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.hidden = true; });
})();
</script>

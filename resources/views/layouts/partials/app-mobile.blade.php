{{-- Aspetto da app su cellulare, condiviso dai layout admin e app (regola §7 di docs/STATO-PROGETTO.md).
     Classi: .chips (azioni secondarie, riga scorrevole), .fab-bar (azione principale, barra fissa in basso),
     .tiles/.tile (voci grandi con icona), .rtable (tabella che su cellulare diventa elenco di schede, con data-label). --}}
<style>
    .ph-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
    .ph-act { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .chips { display: flex; gap: 8px; flex-wrap: wrap; }
    .fab-bar { display: flex; gap: 8px; flex-wrap: wrap; }
    .tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 12px; margin: 18px 0 8px; }
    .tile { display: flex; align-items: center; gap: 12px; min-height: 64px; padding: 12px 14px; background: #fff; border: 1px solid rgba(15,23,42,.08); border-radius: 16px; box-shadow: 0 2px 10px rgba(15,23,42,.05); color: #1a1a2e; text-decoration: none; font-weight: 700; font-size: .95rem; line-height: 1.2; }
    .tile .ti { width: 44px; height: 44px; border-radius: 14px; background: #f4f4f8; display: grid; place-items: center; font-size: 1.5rem; flex: none; }
    .tile .tb { margin-left: auto; background: #c62828; color: #fff; border-radius: 999px; padding: 1px 9px; font-size: .75rem; }

    @media (max-width: 860px) {
        /* Azione principale: barra fissa in basso, sopra la barra di navigazione se c'è */
        .fab-bar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 35; flex-wrap: nowrap; gap: 10px; background: #fff; border-top: 1px solid rgba(15,23,42,.08); box-shadow: 0 -8px 24px rgba(15,23,42,.07); padding: 10px 16px calc(10px + env(safe-area-inset-bottom)); }
        body.has-bottom-nav .fab-bar { bottom: calc(64px + env(safe-area-inset-bottom)); padding-bottom: 10px; }
        .fab-bar .btn { flex: 1; min-height: 52px; font-size: 1.02rem; text-align: center; display: flex; align-items: center; justify-content: center; }
        body:has(.fab-bar) { padding-bottom: calc(96px + env(safe-area-inset-bottom)); }
        body.has-bottom-nav:has(.fab-bar) { padding-bottom: calc(160px + env(safe-area-inset-bottom)); }
    }

    @media (max-width: 640px) {
        .ph-row { margin-bottom: 14px; }
        .ph-act { width: 100%; }
        .chips { flex-wrap: nowrap; overflow-x: auto; margin: 0 -16px; padding: 2px 16px 6px; scrollbar-width: none; -webkit-overflow-scrolling: touch; }
        .chips::-webkit-scrollbar { display: none; }
        .chips .btn { flex: none; white-space: nowrap; }
        .tiles { grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .tile { flex-direction: column; align-items: flex-start; gap: 8px; min-height: 96px; position: relative; }
        .tile .tb { position: absolute; top: 10px; right: 10px; margin: 0; }

        /* Misure da touch */
        .btn { min-height: 48px; display: inline-flex; align-items: center; justify-content: center; border-radius: 14px; }
        input:not([type=checkbox]):not([type=radio]):not([type=file]):not([type=hidden]), select, textarea { font-size: 16px; min-height: 48px; }

        /* Schede e intestazioni più compatte */
        main { margin-top: 12px; margin-bottom: 20px; }
        main > .card { padding: 16px; border-radius: 18px; }
        main h1 { font-size: 1.35rem; line-height: 1.25; }
        header.hdr { position: sticky; top: 0; z-index: 30; padding: calc(10px + env(safe-area-inset-top)) 16px 10px; }
        header.hdr .hdr-name { display: none; }
        header.hdr .btn { padding: 8px 12px; min-height: 40px; }
        .app-shell { padding-top: calc(14px + env(safe-area-inset-top)); }
        .app-top { margin-bottom: 18px; }
        .app-actions { flex-wrap: nowrap; overflow-x: auto; margin: 0 -16px; padding: 2px 16px 6px; width: calc(100% + 32px); scrollbar-width: none; }
        .app-actions::-webkit-scrollbar { display: none; }
        .app-actions .btn, .app-actions form { flex: none; white-space: nowrap; }

        /* Tabelle → elenco di schede */
        .rtable, .rtable tbody { display: block; width: 100%; }
        .rtable thead { display: none; }
        .rtable tr { display: block; background: #fff; border: 1px solid rgba(15,23,42,.1) !important; border-radius: 16px; padding: 12px 14px; margin-bottom: 12px; }
        .rtable td { display: block; padding: 5px 0 !important; text-align: left !important; border: 0 !important; white-space: normal !important; }
        .rtable td[data-label]::before { content: attr(data-label); display: block; font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #8a8a99; }
    }
</style>

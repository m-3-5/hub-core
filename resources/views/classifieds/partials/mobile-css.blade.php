{{-- Pagine da telefono per i privati (nuovo annuncio, i miei annunci, entra con email, buono): tasti grandi, barra fissa in basso. --}}
<style>
    :root { --p: #4f46e5; --p2: #7c3aed; --ink: #1b1b24; --muted: #6b6b7b; --line: #e5e5ee; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: -apple-system, "Segoe UI", system-ui, sans-serif; color: var(--ink); background: #f6f6fa; line-height: 1.4; }
    a { color: var(--p); }
    .m-wrap { max-width: 560px; margin: 0 auto; padding: 18px 16px 110px; }
    .m-center { text-align: center; padding-top: 48px; }
    .m-big { font-size: 4rem; line-height: 1; margin-bottom: 10px; }
    h1 { font-size: 1.45rem; margin: 0 0 14px; }
    .m-muted { color: var(--muted); margin: 6px 0; }
    .m-small { font-size: .88rem; }
    .m-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; border-radius: 14px; padding: 12px 14px; margin-bottom: 14px; font-weight: 600; }
    .m-ok { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; border-radius: 14px; padding: 12px 14px; margin-bottom: 14px; font-weight: 600; }
    .m-types { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 14px; }
    .m-type input { position: absolute; opacity: 0; pointer-events: none; }
    .m-type span { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 14px 6px; border: 2px solid var(--line); border-radius: 18px; background: #fff; font-weight: 800; cursor: pointer; }
    .m-type i { font-style: normal; font-size: 1.8rem; }
    .m-type input:checked + span { border-color: var(--p); background: #eef2ff; color: var(--p); }
    .m-type input:focus-visible + span { outline: 3px solid #c7d2fe; }
    .m-photos { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 4px; margin-bottom: 14px; }
    .m-add, .m-thumb { flex: none; width: 92px; height: 92px; border-radius: 18px; }
    .m-add { display: flex; flex-direction: column; align-items: center; justify-content: center; border: 2px dashed #c7c7d6; background: #fff; font-weight: 700; color: var(--muted); cursor: pointer; }
    .m-add i { font-style: normal; font-size: 1.8rem; }
    .m-thumb { border: 0; background: #ddd center/cover no-repeat; position: relative; cursor: pointer; padding: 0; }
    .m-thumb span { position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; border-radius: 50%; background: rgba(0,0,0,.6); color: #fff; font-size: .8rem; display: flex; align-items: center; justify-content: center; }
    .m-field { display: block; margin-bottom: 12px; }
    .m-field > span { display: block; font-weight: 700; font-size: .92rem; margin-bottom: 5px; }
    .m-field small { font-weight: 500; color: var(--muted); }
    .m-field input, .m-field textarea { width: 100%; min-height: 50px; border: 2px solid var(--line); border-radius: 14px; padding: 12px 14px; font: inherit; font-size: 1rem; background: #fff; }
    .m-field input:focus, .m-field textarea:focus { outline: none; border-color: var(--p); }
    .m-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    @media (max-width: 380px) { .m-row { grid-template-columns: 1fr; } }
    .m-bar { position: fixed; left: 0; right: 0; bottom: 0; padding: 12px 16px calc(12px + env(safe-area-inset-bottom)); background: rgba(255,255,255,.96); border-top: 1px solid var(--line); z-index: 50; }
    .m-bar .m-btn { max-width: 528px; margin: 0 auto; display: block; }
    .m-btn { display: inline-block; width: 100%; min-height: 56px; border: 0; border-radius: 16px; background: linear-gradient(135deg, var(--p), var(--p2)); color: #fff; font: inherit; font-size: 1.1rem; font-weight: 800; cursor: pointer; text-align: center; text-decoration: none; line-height: 56px; padding: 0 16px; }
    .m-btn:disabled { opacity: .6; }
    .m-btn--ghost { background: #fff; color: var(--p); border: 2px solid var(--line); line-height: 52px; }
    .m-btn--sm { min-height: 42px; line-height: 42px; font-size: .95rem; width: auto; border-radius: 12px; }
    .m-trap { position: absolute; left: -9999px; width: 1px; height: 1px; }
    .m-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 12px; }
    .m-ad { display: flex; gap: 12px; background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 10px; }
    .m-ad__img { flex: none; width: 84px; height: 84px; border-radius: 14px; background: #eef2ff center/cover no-repeat; display: flex; align-items: center; justify-content: center; font-size: 2rem; }
    .m-ad__body { flex: 1; min-width: 0; }
    .m-ad__title { font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .m-chip { display: inline-block; font-size: .78rem; font-weight: 800; border-radius: 999px; padding: 2px 10px; background: #f1f1f6; color: var(--muted); }
    .m-chip--online { background: #dcfce7; color: #166534; }
    .m-chip--review, .m-chip--email { background: #fef3c7; color: #92400e; }
    .m-chip--rejected, .m-chip--expired { background: #fee2e2; color: #991b1b; }
    .m-actions { display: flex; gap: 8px; margin-top: 8px; flex-wrap: wrap; }
    .m-actions form { margin: 0; }
    .m-link { background: none; border: 0; color: var(--muted); font: inherit; font-size: .88rem; text-decoration: underline; cursor: pointer; padding: 8px 0; }
</style>

{{-- Stile condiviso di tutte le pagine (foto uguali nelle card, navigazione sempre visibile). Va incluso per ultimo nell'<head>. --}}
<link rel="stylesheet" href="{{ asset('css/hub-ui.css') }}?v={{ @filemtime(public_path('css/hub-ui.css')) }}">
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-ui-scroll]').forEach(function (row) {
        var wrap = document.createElement('div');
        wrap.className = 'ui-scroll-wrap';
        if (row.classList.contains('ui-pub__links')) { wrap.style.flex = '1'; }
        row.parentNode.insertBefore(wrap, row);
        wrap.appendChild(row);
        [['prev', '‹', 'Indietro'], ['next', '›', 'Avanti']].forEach(function (b) {
            var btn = document.createElement('button');
            btn.type = 'button'; btn.className = 'ui-scroll__btn ui-scroll__btn--' + b[0];
            btn.textContent = b[1]; btn.setAttribute('aria-label', b[2]);
            btn.addEventListener('click', function () { row.scrollBy({ left: (b[0] === 'next' ? 1 : -1) * row.clientWidth * 0.7, behavior: 'smooth' }); });
            wrap.appendChild(btn);
        });
        function update() {
            wrap.classList.toggle('can-left', row.scrollLeft > 4);
            wrap.classList.toggle('can-right', row.scrollLeft + row.clientWidth < row.scrollWidth - 4);
        }
        row.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        var active = row.querySelector('.on, .is-active');
        if (active) { row.scrollLeft = Math.max(0, active.offsetLeft - 16); }
        update();
        // trascinamento col mouse (sul telefono basta il dito)
        var down = false, moved = false, startX = 0, startLeft = 0;
        row.addEventListener('pointerdown', function (e) { if (e.pointerType !== 'mouse') return; down = true; moved = false; startX = e.clientX; startLeft = row.scrollLeft; });
        window.addEventListener('pointermove', function (e) {
            if (!down) return;
            var dx = e.clientX - startX;
            if (Math.abs(dx) > 5) { moved = true; row.classList.add('is-dragging'); }
            if (moved) { row.scrollLeft = startLeft - dx; }
        });
        window.addEventListener('pointerup', function () { if (down) { down = false; setTimeout(function () { row.classList.remove('is-dragging'); }, 0); } });
    });
});
</script>

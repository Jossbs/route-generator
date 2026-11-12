{{-- Reparte el itinerario con las fuentes y medidas reales antes de imprimir. --}}
<script>
(() => {
    const root = document.querySelector('.ruta-inicial-doc');
    const originals = [...root.querySelectorAll('.inicial-ruta')];
    if (!originals.length) return;

    const templates = originals.map(page => page.cloneNode(true));
    const blocks = templates.flatMap(page => [...page.querySelectorAll('.ruta-lista > .bloque')]);
    const headings = new Map();
    templates.forEach(page => page.querySelectorAll('.ruta-grupo').forEach(heading => {
        const type = heading.nextElementSibling?.dataset.rutaTipo;
        if (type && !headings.has(type)) headings.set(type, heading);
    }));
    const pace = templates.at(-1).querySelector('.ritmo');
    const anchor = document.createComment('fin del itinerario');
    originals.at(-1).after(anchor);
    const closing = root.querySelector('.inicial-cierre .cuerpo');
    const support = [...closing.querySelectorAll(':scope > .entradilla, :scope > .tarjeta')];
    const supportTemplates = support.map(node => node.cloneNode(true));
    const supportAnchor = document.createComment('modalidad y constancias');
    if (support.length) support[0].before(supportAnchor);
    let mediaRules = [];

    function printStyles() {
        // beforeprint también se dispara desde una ventana con tamaño de celular.
        // Mide con las reglas de impresión aunque Chrome aún reporte medio screen.
        mediaRules = [];
        for (const sheet of document.styleSheets) {
            for (const rule of sheet.cssRules) {
                if (!(rule instanceof CSSMediaRule)) continue;
                const media = rule.media.mediaText;
                if (!media.includes('print') && !media.includes('screen')) continue;
                mediaRules.push([rule, media]);
                rule.media.mediaText = media.includes('print') ? 'all' : 'not all';
            }
        }
    }

    function paginate() {
        printStyles();
        root.querySelectorAll('.inicial-ruta').forEach(page => page.remove());
        closing.querySelectorAll(':scope > .entradilla, :scope > .tarjeta').forEach(node => node.remove());
        supportAnchor.after(...supportTemplates.map(node => node.cloneNode(true)));
        const pages = [];

        function newPage() {
            const page = templates[0].cloneNode(true);
            page.querySelector('.ruta-lista').replaceChildren();
            page.querySelector('.ritmo')?.remove();
            if (pages.length) {
                page.querySelectorAll('.cuerpo > .h-sec, .cuerpo > .objetivo, .cuerpo > .entradilla').forEach(node => node.remove());
            }
            if (pace) page.querySelector('.cuerpo').append(pace.cloneNode(true));
            page.querySelector('.enc .der').textContent = pages.length ? `Tu ruta (${pages.length + 1})` : 'Tu ruta';
            anchor.before(page);
            pages.push(page);
            return page;
        }

        function append(page, block) {
            const list = page.querySelector('.ruta-lista');
            if (list.lastElementChild?.dataset.rutaTipo !== block.dataset.rutaTipo) {
                const heading = headings.get(block.dataset.rutaTipo);
                if (heading) list.append(heading.cloneNode(true));
            }
            list.append(block);
        }

        let page = newPage();
        const height = 279.3 * 96 / 25.4;
        const packets = [];
        let extraCourses = 0;
        for (const block of blocks) {
            const count = block.querySelectorAll('.curso').length;
            if (block.dataset.rutaTipo === 'refuerzo' && packets.at(-1)?.[0].dataset.rutaTipo === 'refuerzo' && extraCourses + count <= 3) {
                packets.at(-1).push(block);
            } else {
                packets.push([block]);
                extraCourses = 0;
            }
            if (block.dataset.rutaTipo === 'refuerzo') extraCourses += count;
        }
        for (const packet of packets) {
            const previousCount = page.querySelectorAll('.bloque').length;
            const list = page.querySelector('.ruta-lista');
            const previousChildren = [...list.children];
            packet.forEach(block => append(page, block.cloneNode(true)));
            const extraCount = page.querySelectorAll('.bloque[data-ruta-tipo="refuerzo"] .curso').length;
            if (previousCount && (page.getBoundingClientRect().height > height + 0.25 || extraCount > 3)) {
                list.replaceChildren(...previousChildren);
                page = newPage();
                packet.forEach(block => append(page, block.cloneNode(true)));
            }
        }

        pages.slice(0, -1).forEach(page => page.querySelector('.ritmo')?.remove());
        pages.forEach(page => {
            if ([...page.querySelectorAll('.bloque')].every(block => block.dataset.rutaTipo === 'refuerzo')) {
                page.classList.add('solo-refuerzos');
                if (page.getBoundingClientRect().height > height + 0.25) page.classList.remove('solo-refuerzos');
            }
        });
        const last = pages.at(-1);
        if (last.classList.contains('solo-refuerzos') && supportTemplates.length) {
            const extra = document.createElement('div');
            extra.className = 'refuerzo-apoyo';
            extra.append(...supportTemplates.map(node => node.cloneNode(true)));
            last.querySelector('.cuerpo').append(extra);
            if (last.getBoundingClientRect().height <= height + 0.25) {
                closing.querySelectorAll(':scope > .entradilla, :scope > .tarjeta').forEach(node => node.remove());
            } else {
                extra.remove();
            }
        }
        const allPages = [...root.querySelectorAll('section.hoja')];
        const total = String(allPages.length).padStart(2, '0');
        allPages.forEach((page, index) => {
            const folio = page.querySelector('.pie .num, .pt-pie .folio');
            if (folio) folio.innerHTML = `${String(index + 1).padStart(2, '0')}<i>/${total}</i>`;
        });
    }

    window.addEventListener('beforeprint', paginate);
    window.addEventListener('afterprint', () => {
        mediaRules.forEach(([rule, media]) => { rule.media.mediaText = media; });
        mediaRules = [];
    });
})();
</script>

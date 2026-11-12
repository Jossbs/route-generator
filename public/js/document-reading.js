/* Seguimiento propio: solo se carga en el enlace público, nunca en el PDF. */
(() => {
    const endpoint = document.currentScript?.dataset.endpoint;
    if (!endpoint) return;

    const sections = {};
    const clicks = new Set();
    let active = 0;
    let progress = 0;
    let lastSection = null;
    let furthestSection = null;
    let candidate = null;
    let candidateTime = 0;
    let previous = performance.now();
    let interaction = previous;
    let lastSent = '';
    let sending = false;
    let printing = false;

    function tick() {
        const now = performance.now();
        const elapsed = Math.min(1500, Math.max(0, Math.floor(now - previous)));
        previous = now;
        if (document.hidden || printing || now - interaction > 30000) {
            candidate = null;
            candidateTime = 0;
            return;
        }
        active += elapsed;

        // Elegir la hoja con más área visible funciona también con hojas más
        // altas que el teléfono, sin depender de un porcentaje de intersección.
        let visible = null;
        let largest = 0;
        document.querySelectorAll('[data-reading-section]').forEach((page) => {
            const rect = page.getBoundingClientRect();
            const height = Math.max(
                0,
                Math.min(rect.bottom, innerHeight) - Math.max(rect.top, 0),
            );
            if (height > largest) {
                largest = height;
                visible = page.dataset.readingSection;
            }
        });
        if (!visible) {
            candidate = null;
            candidateTime = 0;
            return;
        }
        if (candidate !== visible) {
            candidate = visible;
            candidateTime = 0;
            return;
        }
        candidateTime += elapsed;
        // Una sección necesita al menos un segundo en pantalla. Pasar rápido
        // por ella no se presenta al asesor como una sección vista.
        if (candidateTime >= 1000) {
            sections[visible] = (sections[visible] || 0) + candidateTime;
            candidateTime = 0;
            lastSection = visible;
            const root = document.documentElement;
            const height = Math.max(
                root.scrollHeight,
                document.body.scrollHeight,
            );
            const reached =
                scrollY + innerHeight >= height - 2
                    ? 100
                    : Math.floor((100 * (scrollY + innerHeight)) / height);
            if (reached > progress) {
                progress = Math.min(100, reached);
                furthestSection = visible;
            }
        }
    }

    function flush(beacon = false) {
        if (active < 1000 && clicks.size === 0) return;
        const body = JSON.stringify({
            progress,
            active_ms: active,
            sections,
            last_section: lastSection,
            furthest_section: furthestSection,
            clicks: [...clicks],
        });
        if (body === lastSent) return;
        if (
            beacon &&
            navigator.sendBeacon?.(
                endpoint,
                new Blob([body], { type: 'application/json' }),
            )
        )
            return;
        if (sending && !beacon) return;
        sending = true;
        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body,
            keepalive: true,
        })
            .then((response) => {
                if (response.ok) lastSent = body;
            })
            .catch(() => {
                // El próximo envío reintenta los acumulados si se interrumpe la red.
            })
            .finally(() => {
                sending = false;
            });
    }

    ['pointerdown', 'pointermove', 'keydown', 'scroll', 'touchstart'].forEach(
        (event) => {
            addEventListener(
                event,
                () => {
                    interaction = performance.now();
                },
                { passive: true },
            );
        },
    );
    tick();
    setInterval(() => {
        tick();
        if (active >= 1000 && active < 2500) flush();
    }, 1000);
    setInterval(() => flush(), 10000);
    document.addEventListener('click', (event) => {
        const link = event.target.closest?.('[data-reading-click]');
        if (!link) return;
        tick();
        clicks.add(link.dataset.readingClick);
        flush(true);
    });
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) flush(true);
        else {
            previous = performance.now();
            interaction = previous;
        }
    });
    addEventListener('pagehide', () => {
        tick();
        flush(true);
    });
    addEventListener('beforeprint', () => {
        printing = true;
        flush(true);
    });
    addEventListener('afterprint', () => {
        printing = false;
        previous = performance.now();
    });
})();

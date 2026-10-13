/* Centro de confianza · DP SyS
 *
 * Dos trabajos y nada más:
 *   1. Poner el WhatsApp del asesor que mandó el enlace (?a=clave, desde asesores.json).
 *   2. Avisarle a route-generator qué pasó en la visita: apertura, secciones vistas,
 *      preguntas abiertas y clics. Si el enlace trae ?l=<token de la ruta inicial>,
 *      el receptor liga la visita al lead.
 *
 * Todo va con sendBeacon y cuerpo text/plain: cruza de dominio sin preflight y no
 * se pierde cuando el lead cierra la pestaña justo después de tocar WhatsApp.
 * Si algo falla, la página sigue funcionando igual: medir nunca debe romperla.
 */
(function () {
    'use strict';

    var meta = function (name) {
        var el = document.querySelector('meta[name="' + name + '"]');
        return el ? el.content : '';
    };

    var ENDPOINT = meta('hub-endpoint');
    var WHATSAPP_GENERAL = meta('hub-whatsapp');
    var MENSAJE =
        'Hola, vi el centro de confianza de DP SyS y tengo una duda: ';

    var params = new URLSearchParams(location.search);

    function store(key, value) {
        try {
            if (value) sessionStorage.setItem('hub:' + key, value);
            return value || sessionStorage.getItem('hub:' + key) || '';
        } catch {
            return value || '';
        }
    }

    var clean = function (v, re, max) {
        return v && re.test(v) ? v.slice(0, max) : '';
    };

    var advisorKey = store('a', clean(params.get('a'), /^[a-z0-9-]+$/, 40));
    var leadToken = store('l', clean(params.get('l'), /^[A-Za-z0-9_-]+$/, 64));

    var visitId =
        store('v', '') ||
        store(
            'v',
            window.crypto && crypto.randomUUID
                ? crypto.randomUUID()
                : Date.now().toString(36) +
                      '-' +
                      Math.random().toString(36).slice(2, 12),
        );

    var utm = {};
    ['source', 'medium', 'campaign', 'content'].forEach(function (k) {
        var v = params.get('utm_' + k);
        if (v) utm[k] = v.slice(0, 80);
    });

    function track(event, target) {
        if (!ENDPOINT) return;

        var payload = { e: event, v: visitId };
        if (target) payload.t = target;
        if (advisorKey) payload.a = advisorKey;
        if (leadToken) payload.l = leadToken;
        if (event === 'abierta') {
            if (document.referrer) payload.r = document.referrer.slice(0, 255);
            if (Object.keys(utm).length) payload.utm = utm;
        }

        var body = JSON.stringify(payload);

        try {
            if (
                navigator.sendBeacon &&
                navigator.sendBeacon(
                    ENDPOINT,
                    new Blob([body], { type: 'text/plain' }),
                )
            )
                return;
            void fetch(ENDPOINT, {
                method: 'POST',
                mode: 'no-cors',
                keepalive: true,
                headers: { 'Content-Type': 'text/plain' },
                body: body,
            }).catch(() => {});
        } catch {
            /* medir nunca rompe la página */
        }
    }

    /* 1 · WhatsApp del asesor */

    function setWhatsApp(number, name) {
        var href =
            'https://wa.me/' + number + '?text=' + encodeURIComponent(MENSAJE);
        document.querySelectorAll('[data-wa]').forEach(function (a) {
            a.href = href;
            a.target = '_blank';
            a.rel = 'noopener';
        });
        if (name) {
            document
                .querySelectorAll('[data-advisor-name]')
                .forEach(function (el) {
                    el.textContent = name;
                });
            document.querySelectorAll('.btn[data-wa]').forEach(function (a) {
                a.textContent = 'Escribir a ' + name.split(' ')[0];
            });
        }
    }

    setWhatsApp(WHATSAPP_GENERAL, '');

    if (advisorKey) {
        fetch('asesores.json', { cache: 'no-cache' })
            .then(function (r) {
                return r.ok ? r.json() : {};
            })
            .then(function (asesores) {
                var a = asesores[advisorKey];
                if (a && /^\d{10,15}$/.test(a.whatsapp))
                    setWhatsApp(a.whatsapp, a.nombre || '');
            })
            .catch(function () {});
    }

    /* 2 · Medición */

    track('abierta');

    if ('IntersectionObserver' in window) {
        var seen = {};
        var io = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    var id = entry.target.id;
                    if (entry.isIntersecting && !seen[id]) {
                        seen[id] = true;
                        io.unobserve(entry.target);
                        track('seccion_vista', id);
                    }
                });
            },
            { threshold: 0.35 },
        );
        document.querySelectorAll('[data-section]').forEach(function (s) {
            io.observe(s);
        });
    }

    document.querySelectorAll('details[data-faq]').forEach(function (d) {
        var sent = false;
        d.addEventListener('toggle', function () {
            if (d.open && !sent) {
                sent = true;
                track('pregunta_abierta', d.dataset.faq);
            }
        });
    });

    document.addEventListener('click', function (ev) {
        var el = ev.target.closest && ev.target.closest('[data-track]');
        if (el) track('clic', el.dataset.track);
    });
})();

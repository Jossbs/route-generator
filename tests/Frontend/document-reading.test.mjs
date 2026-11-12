import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(
    new URL('../../public/js/document-reading.js', import.meta.url),
    'utf8',
);

function browser({ offline = false } = {}) {
    let now = 0;
    const events = {};
    const documentEvents = {};
    const timers = [];
    const requests = [];
    const pages = ['portada', 'ruta', 'confianza', 'siguiente-paso'];
    const context = {
        Blob,
        Set,
        Math,
        JSON,
        innerHeight: 800,
        scrollY: 0,
        performance: { now: () => now },
        navigator: {
            sendBeacon: (_url, body) => {
                requests.push({ body, beacon: true });
                return true;
            },
        },
        addEventListener: (name, callback) => {
            events[name] = callback;
        },
        setInterval: (callback, interval) => {
            timers.push({ callback, interval });
        },
        fetch: (_url, options) => {
            requests.push({ body: options.body });
            return offline
                ? Promise.reject(new Error('offline'))
                : Promise.resolve({ ok: true });
        },
        document: {
            currentScript: { dataset: { endpoint: '/signed-reading' } },
            hidden: false,
            documentElement: { scrollHeight: 4000 },
            body: { scrollHeight: 4000 },
            addEventListener: (name, callback) => {
                documentEvents[name] = callback;
            },
            querySelectorAll: () =>
                pages.map((section, index) => ({
                    dataset: { readingSection: section },
                    getBoundingClientRect: () => ({
                        top: index * 1000 - context.scrollY,
                        bottom: (index + 1) * 1000 - context.scrollY,
                    }),
                })),
        },
    };
    vm.runInNewContext(source, context);
    return {
        context,
        requests,
        async advance(ms) {
            const until = now + ms;
            while (now < until) {
                now += Math.min(1000, until - now);
                for (const timer of timers)
                    if (now % timer.interval === 0) timer.callback();
                await new Promise(setImmediate);
            }
        },
        event(name) {
            events[name]?.();
        },
        hide(hidden) {
            context.document.hidden = hidden;
            documentEvents.visibilitychange();
        },
        click(id) {
            documentEvents.click({
                target: { closest: () => ({ dataset: { readingClick: id } }) },
            });
        },
        offline(value) {
            offline = value;
        },
        async latest() {
            const body = requests.at(-1).body;
            return JSON.parse(
                typeof body === 'string' ? body : await body.text(),
            );
        },
    };
}

await test('una vista previa o una apertura inferior a un segundo no crea una visita', async () => {
    const page = browser();
    assert.equal(page.requests.length, 0);
    await page.advance(500);
    page.event('pagehide');
    assert.equal(page.requests.length, 0);
});

await test('pausa al ocultarse o quedar inactivo y retoma con interacción', async () => {
    const page = browser();
    await page.advance(2000);
    page.hide(true);
    const active = (await page.latest()).active_ms;
    await page.advance(10000);
    assert.equal((await page.latest()).active_ms, active);
    page.hide(false);
    await page.advance(35000);
    page.hide(true);
    assert.equal((await page.latest()).active_ms, active + 30000);
    page.hide(false);
    page.event('pointerdown');
    await page.advance(1000);
    page.hide(true);
    assert.equal((await page.latest()).active_ms, active + 31000);
});

await test('conserva el avance máximo al volver arriba y envía el clic al salir', async () => {
    const page = browser();
    await page.advance(1000);
    page.context.scrollY = 3200;
    page.event('scroll');
    await page.advance(2000);
    page.context.scrollY = 0;
    page.event('scroll');
    await page.advance(2000);
    page.click('membresia');
    const payload = await page.latest();
    assert.equal(payload.progress, 100);
    assert.equal(payload.furthest_section, 'siguiente-paso');
    assert.equal(payload.last_section, 'portada');
    assert.equal(payload.sections['siguiente-paso'], 1000);
    assert.deepEqual(payload.clicks, ['membresia']);
    assert.equal(page.requests.at(-1).beacon, true);
    assert.ok(
        Object.values(payload.sections).reduce((a, b) => a + b, 0) <=
            payload.active_ms,
    );
});

await test('un envío fallido se recupera con los acumulados y se pausa durante impresión', async () => {
    const page = browser({ offline: true });
    await page.advance(2000);
    assert.equal(page.requests.length, 2);
    page.offline(false);
    await page.advance(8000);
    assert.equal((await page.latest()).active_ms, 10000);
    page.event('beforeprint');
    await page.advance(10000);
    page.event('afterprint');
    await page.advance(1000);
    page.hide(true);
    assert.equal((await page.latest()).active_ms, 11000);
});

await test('registra un clic inmediato aunque todavía no haya un segundo de lectura', async () => {
    const page = browser();
    await page.advance(300);
    page.click('conversacion');
    const payload = await page.latest();
    assert.deepEqual(payload.clicks, ['conversacion']);
    assert.equal(payload.active_ms, 300);
    assert.deepEqual(payload.sections, {});
});

await test('si falla el beacon de salida conserva el clic aunque haya otro envío pendiente', async () => {
    const page = browser();
    page.context.navigator.sendBeacon = () => false;
    page.context.fetch = (_url, options) => {
        page.requests.push({ body: options.body });
        return new Promise(() => {});
    };
    await page.advance(1000);
    assert.equal(page.requests.length, 1);
    page.click('membresia');
    assert.equal(page.requests.length, 2);
    assert.deepEqual((await page.latest()).clicks, ['membresia']);
});

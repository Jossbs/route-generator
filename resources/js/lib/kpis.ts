import type { Estado, Unidad } from '@/types/kpis';

const enteros = new Intl.NumberFormat('es-MX', { maximumFractionDigits: 0 });
const decimales = new Intl.NumberFormat('es-MX', { maximumFractionDigits: 2 });

/**
 * El mismo formato que `ValueFormatter` del servidor: «12», «$40,000», «25 %».
 * El servidor ya manda las etiquetas de lo principal; esto es para la gráfica y
 * los tooltips, que calculan en el navegador.
 */
export function formatear(
    valor: number | null | undefined,
    unidad: Unidad,
): string {
    if (valor === null || valor === undefined) {
        return '—';
    }

    const entero = Math.abs(valor - Math.round(valor)) < 0.005;
    const numero = (entero ? enteros : decimales).format(Math.abs(valor));
    const signo = valor < 0 ? '-' : '';

    if (unidad === 'dinero') {
        return `${signo}$${numero}`;
    }

    if (unidad === 'porcentaje') {
        return `${signo}${numero} %`;
    }

    return `${signo}${numero}`;
}

/** Versión corta para los ejes: 1.2 mil, $40 mil. */
export function formatearCorto(valor: number, unidad: Unidad): string {
    if (Math.abs(valor) < 10000) {
        return formatear(valor, unidad);
    }

    const corto = new Intl.NumberFormat('es-MX', {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(valor);

    return unidad === 'dinero' ? `$${corto}` : corto;
}

/**
 * Cómo se dice cada estado. Lo que va atrás no se llama «atrasado» ni se pinta
 * de rojo (decidido el 2026-09-26): se dice lo que falta.
 */
export const ESTADOS: Record<
    Estado,
    { etiqueta: string; clase: string; barra: string }
> = {
    logrado: {
        etiqueta: 'Meta alcanzada',
        clase: 'text-kpi-bien-texto',
        barra: 'bg-kpi-bien',
    },
    en_ritmo: {
        etiqueta: 'En ritmo',
        clase: 'text-kpi-bien-texto',
        barra: 'bg-kpi-real',
    },
    cerca: {
        etiqueta: 'Cerca del ritmo',
        clase: 'text-foreground',
        barra: 'bg-kpi-real',
    },
    por_alcanzar: {
        etiqueta: 'Por alcanzar',
        clase: 'text-muted-foreground',
        barra: 'bg-kpi-real',
    },
    sin_meta: {
        etiqueta: 'Sin meta',
        clase: 'text-muted-foreground',
        barra: 'bg-kpi-real',
    },
};

/**
 * Los días llegan como fecha sin hora. `new Date('2026-10-01')` los lee en UTC y
 * en México saldrían un día antes; al mediodía no hay zona que los mueva.
 */
export function dia(valor: string): Date {
    return new Date(`${valor}T12:00:00`);
}

export function fechaCorta(valor: string): string {
    return dia(valor).toLocaleDateString('es-MX', {
        day: 'numeric',
        month: 'short',
    });
}

export function fechaLarga(valor: string): string {
    return dia(valor).toLocaleDateString('es-MX', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });
}

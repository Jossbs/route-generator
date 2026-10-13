/** «16 h» y no «16 horas»: el sistema habla de horas, y en tabla el espacio pesa. */
export function horas(valor: number | null): string {
    return valor === null ? '— h' : `${valor} h`;
}

/** Suma las horas publicadas de una lista de cursos, ignorando las que faltan. */
export function sumaHoras(valores: (number | null)[]): number {
    return valores.reduce<number>((total, valor) => total + (valor ?? 0), 0);
}

export function fechaCorta(valor: string | null): string {
    if (!valor) {
        return '—';
    }

    return new Date(valor).toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

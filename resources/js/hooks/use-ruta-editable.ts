import { useMemo, useState } from 'react';
import {
    agregarCurso,
    alternarCurso,
    reemplazarCurso,
    moverCurso,
    moverBloque,
} from '@/lib/ruta-editable';
import type { BloquePropuesto, CursoDelCatalogo } from '@/types/rutas';

export type Direccion = -1 | 1;
export type OrdenDeLaRuta = {
    moverCurso: (
        especialidadId: number,
        cursoId: number,
        hacia: Direccion,
    ) => void;
    moverBloque: (indice: number, hacia: Direccion) => void;
    cruzaBloques?: boolean;
    ocupado?: boolean;
};

/** La campaña abre la ruta; los adicionales y su secuencia pertenecen al asesor. */
export function useRutaEditable(bloquesIniciales: BloquePropuesto[]) {
    const [previos, setPrevios] = useState(bloquesIniciales);
    const [borrador, setBorrador] = useState(() => ({
        bloques: [...bloquesIniciales].sort(
            (a, b) => Number(b.principal) - Number(a.principal),
        ),
        fuera: [] as number[],
    }));
    if (previos !== bloquesIniciales) {
        setPrevios(bloquesIniciales);
        setBorrador({
            bloques: [...bloquesIniciales].sort(
                (a, b) => Number(b.principal) - Number(a.principal),
            ),
            fuera: [],
        });
    }
    const fijos = useMemo(
        () =>
            new Set(
                borrador.bloques
                    .filter((bloque) => bloque.principal)
                    .flatMap((bloque) =>
                        bloque.cursos.map((curso) => curso.id),
                    ),
            ),
        [borrador.bloques],
    );
    const finales = useMemo(
        () => [
            ...new Set(
                borrador.bloques
                    .flatMap((bloque) => bloque.cursos.map((curso) => curso.id))
                    .filter((id) => !borrador.fuera.includes(id)),
            ),
        ],
        [borrador],
    );
    return {
        ...borrador,
        fijos,
        finales,
        alternar: (id: number) =>
            setBorrador((actual) => alternarCurso(actual, id)),
        agregar: (curso: CursoDelCatalogo) =>
            setBorrador((actual) => agregarCurso(actual, curso)),
        reemplazar: (id: number, curso: CursoDelCatalogo) =>
            setBorrador((actual) => reemplazarCurso(actual, id, curso)),
        orden: {
            cruzaBloques: true,
            moverCurso: (_especialidad: number, id: number, hacia: Direccion) =>
                setBorrador((actual) => moverCurso(actual, id, hacia)),
            moverBloque: (indice: number, hacia: Direccion) =>
                setBorrador((actual) => moverBloque(actual, indice, hacia)),
        } satisfies OrdenDeLaRuta,
    };
}

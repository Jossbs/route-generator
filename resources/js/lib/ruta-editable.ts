import type {
    BloquePropuesto,
    CursoDelCatalogo,
    CursoPropuesto,
} from '@/types/rutas';

type Fila = { bloque: BloquePropuesto; curso: CursoPropuesto };
export type BorradorInicial = { bloques: BloquePropuesto[]; fuera: number[] };

function filas(borrador: BorradorInicial): Fila[] {
    return borrador.bloques.flatMap((bloque) =>
        bloque.cursos.map((curso) => ({ bloque, curso })),
    );
}

/** Agrupa solo vecinos: volver a una especialidad no cambia el orden elegido. */
function agrupar(lista: Fila[]): BloquePropuesto[] {
    const bloques: BloquePropuesto[] = [];
    for (const fila of lista) {
        const anterior = bloques.at(-1);
        if (
            anterior &&
            anterior.especialidad_id === fila.bloque.especialidad_id &&
            anterior.principal === fila.bloque.principal &&
            anterior.mes === fila.bloque.mes
        ) {
            anterior.cursos.push(fila.curso);
        } else {
            bloques.push({ ...fila.bloque, cursos: [fila.curso] });
        }
    }
    return bloques;
}

function filaDelCatalogo(curso: CursoDelCatalogo): Fila {
    return {
        bloque: {
            especialidad_id: curso.especialidad_id,
            especialidad: curso.especialidad,
            mes: null,
            principal: false,
            justificacion: 'Seleccionado por el asesor.',
            cursos_en_la_especialidad: 0,
            cursos: [],
        },
        curso: {
            id: curso.id,
            nombre: curso.nombre,
            horas: curso.horas,
            orden: 0,
            fijo: false,
        },
    };
}

export function agregarCurso(
    borrador: BorradorInicial,
    curso: CursoDelCatalogo,
): BorradorInicial {
    const lista = filas(borrador);
    if (lista.some((fila) => fila.curso.id === curso.id)) {
        return {
            ...borrador,
            fuera: borrador.fuera.filter((id) => id !== curso.id),
        };
    }
    return {
        ...borrador,
        bloques: agrupar([...lista, filaDelCatalogo(curso)]),
    };
}

export function alternarCurso(
    borrador: BorradorInicial,
    id: number,
): BorradorInicial {
    const fila = filas(borrador).find((fila) => fila.curso.id === id);
    if (!fila || fila.curso.fijo || fila.bloque.principal) return borrador;
    return {
        ...borrador,
        fuera: borrador.fuera.includes(id)
            ? borrador.fuera.filter((curso) => curso !== id)
            : [...borrador.fuera, id],
    };
}

export function reemplazarCurso(
    borrador: BorradorInicial,
    id: number,
    curso: CursoDelCatalogo,
): BorradorInicial {
    const lista = filas(borrador);
    const anterior = lista.find((fila) => fila.curso.id === id);
    if (
        !anterior ||
        anterior.curso.fijo ||
        anterior.bloque.principal ||
        borrador.fuera.includes(id) ||
        id === curso.id
    )
        return borrador;
    if (
        lista.some(
            (fila) =>
                fila.curso.id === curso.id &&
                !borrador.fuera.includes(curso.id),
        )
    )
        return borrador;
    // El sustituto ocupa exactamente el lugar del anterior, incluso al cambiar de especialidad.
    const nuevas = lista
        .filter((fila) => fila.curso.id !== curso.id)
        .map((fila) => (fila.curso.id === id ? filaDelCatalogo(curso) : fila));
    return {
        bloques: agrupar(nuevas),
        fuera: borrador.fuera.filter(
            (fuera) => fuera !== id && fuera !== curso.id,
        ),
    };
}

export function moverCurso(
    borrador: BorradorInicial,
    id: number,
    hacia: -1 | 1,
): BorradorInicial {
    const lista = filas(borrador);
    const movibles = lista
        .map((fila, indice) => ({ ...fila, indice }))
        .filter(
            (fila) =>
                !fila.curso.fijo &&
                !fila.bloque.principal &&
                !borrador.fuera.includes(fila.curso.id),
        );
    const desde = movibles.findIndex((fila) => fila.curso.id === id);
    const origen = movibles[desde];
    const destino = movibles[desde + hacia];
    if (desde < 0 || !origen || !destino) return borrador;
    [lista[origen.indice], lista[destino.indice]] = [
        lista[destino.indice],
        lista[origen.indice],
    ];
    return { ...borrador, bloques: agrupar(lista) };
}

export function moverBloque(
    borrador: BorradorInicial,
    desde: number,
    hacia: -1 | 1,
): BorradorInicial {
    const bloques = [...borrador.bloques];
    const hasta = desde + hacia;
    if (
        !bloques[desde] ||
        !bloques[hasta] ||
        bloques[desde].principal ||
        bloques[hasta].principal
    )
        return borrador;
    [bloques[desde], bloques[hasta]] = [bloques[hasta], bloques[desde]];
    return { ...borrador, bloques };
}

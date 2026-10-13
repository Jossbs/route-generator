import { useMemo, useState } from 'react';
import type { BloquePropuesto, CursoDelCatalogo } from '@/types/rutas';

/** Hacia arriba en la lista (-1) o hacia abajo (1). */
export type Direccion = -1 | 1;

export type OrdenDeLaRuta = {
    moverCurso: (
        especialidadId: number,
        cursoId: number,
        hacia: Direccion,
    ) => void;
    /** Por posición: en los doce meses, una especialidad puede tener varios bloques. */
    moverBloque: (indice: number, hacia: Direccion) => void;
    /**
     * ¿Puede un curso salir de su bloque? En los doce meses sí: el orden es una
     * sola secuencia y el bloque es solo dónde cayó. En la ruta inicial no: cada
     * bloque es una especialidad y sus cursos se ordenan dentro de ella.
     */
    cruzaBloques?: boolean;
    /** Mientras el servidor recorta los meses, nada se mueve. */
    ocupado?: boolean;
};

type Opciones = {
    /**
     * ¿Manda el asesor en el orden?
     *
     * En la ruta inicial el bloque principal abre el documento y lo demás se
     * acomoda a mano. La ruta final no usa este hook: su orden es una secuencia
     * que el servidor corta en meses (`useSecuenciaAnual`).
     */
    reordenable?: boolean;
};

/**
 * La propuesta convertida en borrador del asesor.
 *
 * Lo que el motor entrega es un punto de partida, no un veredicto: quien tiene al
 * lead enfrente sabe cosas que la ocupación capturada no dice. Por eso la lista
 * se poda aquí y viaja al servidor como lo que es —los cursos que quedaron, en su
 * orden—, que es exactamente lo que el motor espera de vuelta.
 *
 * La ruta principal es la excepción: son los cursos que la campaña curó, van
 * completos —esa es la promesa del documento— y no se quitan ni se mueven, sean
 * de la especialidad que sean. Un embudo que mezcla tres ramas las mantiene bajo
 * sus tres títulos, y las tres quedan igual de fijas.
 */
export function useRutaEditable(
    bloquesIniciales: BloquePropuesto[],
    { reordenable = true }: Opciones = {},
) {
    const acomodar = (bloques: BloquePropuesto[]): BloquePropuesto[] =>
        reordenable ? principalPrimero(bloques) : bloques;

    const [previos, setPrevios] = useState(bloquesIniciales);
    const [bloques, setBloques] = useState(() => acomodar(bloquesIniciales));
    const [fuera, setFuera] = useState<number[]>([]);

    // Una propuesta nueva del servidor reemplaza el borrador: lo que el asesor
    // había movido era sobre otra composición.
    if (previos !== bloquesIniciales) {
        setPrevios(bloquesIniciales);
        setBloques(acomodar(bloquesIniciales));
        setFuera([]);
    }

    /**
     * Los cursos que la campaña curó. No se quitan: la pantalla no ofrece el
     * botón, y esto impide además que un atajo de teclado o un clic en un
     * renglón que acaba de cambiar de sitio los saque sin querer.
     */
    const fijos = useMemo(
        () =>
            new Set(
                bloques
                    .flatMap((bloque) => bloque.cursos)
                    .filter((curso) => curso.fijo)
                    .map((curso) => curso.id),
            ),
        [bloques],
    );

    const alternar = (cursoId: number) => {
        if (fijos.has(cursoId)) {
            return;
        }

        setFuera((previos) =>
            previos.includes(cursoId)
                ? previos.filter((id) => id !== cursoId)
                : [...previos, cursoId],
        );
    };

    /**
     * Lo que el asesor suma entra sin mes: en la ruta final el reparto se
     * recalcula al generar, así que colgarlo de un mes concreto aquí sería
     * prometer una casilla que el motor todavía puede mover.
     */
    const agregar = (curso: CursoDelCatalogo) =>
        setBloques((previos) => {
            const fila = {
                id: curso.id,
                nombre: curso.nombre,
                horas: curso.horas,
                orden: 0,
            };

            // Nunca dentro de un bloque curado: lo que el asesor agrega es una
            // recomendación suya, y meterla ahí la haría pasar por parte de la
            // campaña. En los doce meses no hay bloques curados, así que el curso
            // cae junto a los de su especialidad como siempre.
            const destino = previos.findIndex(
                (bloque) =>
                    !bloque.cursos.some((enElBloque) => enElBloque.fijo) &&
                    bloque.especialidad_id === curso.especialidad_id &&
                    bloque.mes === null,
            );

            if (destino === -1) {
                return [
                    ...previos,
                    {
                        especialidad_id: curso.especialidad_id,
                        especialidad: curso.especialidad,
                        mes: null,
                        principal: false,
                        justificacion: 'Lo agregaste tú.',
                        cursos_en_la_especialidad: 0,
                        cursos: [fila],
                    },
                ];
            }

            return previos.map((bloque, indice) =>
                indice === destino
                    ? { ...bloque, cursos: [...bloque.cursos, fila] }
                    : bloque,
            );
        });

    const moverCurso = (
        especialidadId: number,
        cursoId: number,
        hacia: Direccion,
    ) =>
        setBloques((previos) =>
            previos.map((bloque) => {
                if (bloque.especialidad_id !== especialidadId) {
                    return bloque;
                }

                const desde = bloque.cursos.findIndex(
                    (curso) => curso.id === cursoId,
                );

                return {
                    ...bloque,
                    cursos: intercambiar(bloque.cursos, desde, desde + hacia),
                };
            }),
        );

    /**
     * La ruta principal nunca se mueve: abre el documento porque es lo que el lead
     * pidió, y leerla en segundo lugar rompería esa continuidad.
     */
    const moverBloque = (desde: number, hacia: Direccion) =>
        setBloques((previos) => {
            const hasta = desde + hacia;

            if (previos[desde]?.principal || previos[hasta]?.principal) {
                return previos;
            }

            return intercambiar(previos, desde, hasta);
        });

    /**
     * La lista final, en el orden en que se va a leer: bloque por bloque, curso
     * por curso, sin los que el asesor quitó y sin repetidos.
     */
    const finales = useMemo(
        () => [
            ...new Set(
                bloques
                    .flatMap((bloque) => bloque.cursos.map((curso) => curso.id))
                    .filter((id) => !fuera.includes(id)),
            ),
        ],
        [bloques, fuera],
    );

    return {
        bloques,
        fuera,
        fijos,
        finales,
        alternar,
        agregar,
        orden: reordenable
            ? ({ moverCurso, moverBloque } satisfies OrdenDeLaRuta)
            : undefined,
    };
}

/** Los bloques de la ruta principal abren la lista, en el orden en que se curó. */
function principalPrimero(bloques: BloquePropuesto[]): BloquePropuesto[] {
    return [...bloques].sort(
        (a, b) => Number(b.principal) - Number(a.principal),
    );
}

function intercambiar<T>(lista: T[], desde: number, hasta: number): T[] {
    if (desde < 0 || hasta < 0 || hasta >= lista.length) {
        return lista;
    }

    const copia = [...lista];
    [copia[desde], copia[hasta]] = [copia[hasta], copia[desde]];

    return copia;
}

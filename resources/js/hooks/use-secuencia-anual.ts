import { useMemo, useState } from 'react';
import type { Direccion, OrdenDeLaRuta } from '@/hooks/use-ruta-editable';
import type {
    ArregloDeSecuencia,
    BloquePropuesto,
    CursoDelCatalogo,
} from '@/types/rutas';

type Quitado = {
    id: number;
    nombre: string;
    especialidad: string | null;
    horas: number | null;
    /** El curso que iba justo antes, para devolverlo a su lugar. */
    despuesDe: number | null;
};

/**
 * El itinerario de los doce meses como lo edita el asesor: una sola secuencia.
 *
 * El asesor decide qué cursos van y en qué orden; el motor decide dónde termina
 * cada mes, por horas y por prerrequisitos duros. Por eso cada ajuste vuelve al
 * servidor en lugar de acomodarse aquí: los meses que se ven son siempre los
 * que el motor cortó sobre lo que el asesor acaba de dejar, y el validador ya
 * los revisó. Duplicar el corte en la pantalla sería tener dos motores que un
 * día dejan de coincidir.
 *
 * Lo quitado sí vive aquí, porque el servidor ya no lo devuelve: la pantalla lo
 * recuerda para poder devolverlo a su lugar.
 */
export function useSecuenciaAnual(
    bloques: BloquePropuesto[],
    enviar: (cursoIds: number[]) => void,
    ocupado: boolean,
) {
    const [quitados, setQuitados] = useState<Quitado[]>([]);

    /** Los cursos de cada bloque, en el orden en que se leen. */
    const tramos = useMemo(
        () => bloques.map((bloque) => bloque.cursos.map((curso) => curso.id)),
        [bloques],
    );

    const secuencia = useMemo(() => [...new Set(tramos.flat())], [tramos]);

    const aplanar = (partes: number[][]) => [
        ...new Set(partes.filter((parte) => parte.length > 0).flat()),
    ];

    /**
     * Dentro del bloque, el curso cambia de lugar con su vecino. En la orilla sale
     * del bloque y brinca el de al lado: si solo cambiara con el curso vecino, el
     * motor lo volvería a juntar con los de su especialidad en el mismo mes y el
     * clic no haría nada visible.
     */
    const moverCurso = (_: number, cursoId: number, hacia: Direccion) => {
        const indice = tramos.findIndex((tramo) => tramo.includes(cursoId));
        const partes = tramos.map((tramo) => [...tramo]);
        const tramo = partes[indice];
        const fila = tramo.indexOf(cursoId);
        const destino = fila + hacia;

        if (destino >= 0 && destino < tramo.length) {
            [tramo[fila], tramo[destino]] = [tramo[destino], tramo[fila]];
            enviar(aplanar(partes));

            return;
        }

        const vecino = indice + hacia;

        if (vecino < 0 || vecino >= partes.length) {
            return;
        }

        tramo.splice(fila, 1);
        partes.splice(hacia === -1 ? vecino : vecino + 1, 0, [cursoId]);
        enviar(aplanar(partes));
    };

    /** El bloque entero cambia de lugar con el de al lado, aunque sea de otro mes. */
    const moverBloque = (indice: number, hacia: Direccion) => {
        const destino = indice + hacia;

        if (destino < 0 || destino >= tramos.length) {
            return;
        }

        const partes = [...tramos];
        [partes[indice], partes[destino]] = [partes[destino], partes[indice]];
        enviar(aplanar(partes));
    };

    /**
     * Quitar lo saca de esta hoja, no de su membresía. El último no se quita: un
     * itinerario vacío no es un documento.
     */
    const quitar = (cursoId: number) => {
        if (secuencia.length <= 1) {
            return;
        }

        const bloque = bloques.find((b) =>
            b.cursos.some((curso) => curso.id === cursoId),
        );
        const curso = bloque?.cursos.find((c) => c.id === cursoId);

        if (!bloque || !curso) {
            return;
        }

        const posicion = secuencia.indexOf(cursoId);

        setQuitados((previos) => [
            ...previos,
            {
                id: curso.id,
                nombre: curso.nombre,
                especialidad: bloque.especialidad,
                horas: curso.horas,
                despuesDe: posicion > 0 ? secuencia[posicion - 1] : null,
            },
        ]);
        enviar(secuencia.filter((id) => id !== cursoId));
    };

    /** Vuelve justo después del curso que tenía antes, si ese sigue en la hoja. */
    const devolver = (cursoId: number) => {
        const quitado = quitados.find((q) => q.id === cursoId);

        if (!quitado) {
            return;
        }

        setQuitados((previos) => previos.filter((q) => q.id !== cursoId));
        enviar(
            insertarDespuesDe(
                secuencia,
                cursoId,
                quitado.despuesDe === null
                    ? null
                    : secuencia.includes(quitado.despuesDe)
                      ? quitado.despuesDe
                      : (secuencia.at(-1) ?? null),
            ),
        );
    };

    /**
     * Lo que el asesor suma entra al final de su especialidad, si ya hay cursos
     * de ella; si no, al final del año. Desde ahí lo sube con las flechas.
     */
    const agregar = (curso: CursoDelCatalogo) => {
        const deSuEspecialidad = bloques
            .filter(
                (bloque) => bloque.especialidad_id === curso.especialidad_id,
            )
            .flatMap((bloque) => bloque.cursos.map((c) => c.id));

        setQuitados((previos) => previos.filter((q) => q.id !== curso.id));
        enviar(
            insertarDespuesDe(
                secuencia,
                curso.id,
                deSuEspecialidad.at(-1) ?? secuencia.at(-1) ?? null,
            ),
        );
    };

    /**
     * El arreglo que propone el validador: cada requisito, justo antes del curso
     * que lo pide. Solo mueve esos cursos; el resto queda como el asesor lo dejó.
     */
    const arreglar = (movimientos: ArregloDeSecuencia[]) => {
        let lista = [...secuencia];

        for (const { mover, antes_de } of movimientos) {
            lista = lista.filter((id) => id !== mover);
            const destino = lista.indexOf(antes_de);
            lista.splice(destino === -1 ? lista.length : destino, 0, mover);
        }

        enviar(lista);
    };

    return {
        secuencia,
        quitados,
        quitar,
        devolver,
        agregar,
        arreglar,
        orden: {
            moverCurso,
            moverBloque,
            cruzaBloques: true,
            ocupado,
        } satisfies OrdenDeLaRuta,
    };
}

function insertarDespuesDe(
    lista: number[],
    cursoId: number,
    despuesDe: number | null,
): number[] {
    const sin = lista.filter((id) => id !== cursoId);
    const indice = despuesDe === null ? -1 : sin.indexOf(despuesDe);

    return [...sin.slice(0, indice + 1), cursoId, ...sin.slice(indice + 1)];
}

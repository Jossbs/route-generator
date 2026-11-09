import { useEffect, useState } from 'react';
import { fieldErrors, jsonRequest } from '@/lib/json-request';
import type { Bloqueo, TotalesRuta } from '@/types/rutas';

type Resultado = {
    totales: TotalesRuta | null;
    bloqueos: Bloqueo[];
    advertencias: Bloqueo[];
};

/** Valida el borrador sin reemplazar sus cursos, orden ni textos editados. */
export function useValidacionInicial(
    url: string,
    body: string,
    inicial: Resultado,
) {
    const [revision, setRevision] = useState(0);
    const key = `${url}:${revision}:${body}`;
    const [resultado, setResultado] = useState(() => ({
        ...inicial,
        key,
        errores: {} as Record<string, string>,
    }));
    const [fallo, setFallo] = useState<{ key: string; mensaje: string } | null>(
        null,
    );

    useEffect(() => {
        if (resultado.key === key) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            void (async () => {
                try {
                    const response = await jsonRequest(
                        url,
                        body,
                        'POST',
                        controller.signal,
                    );
                    const data = (await response.json()) as Resultado & {
                        errors?: Record<string, string | string[]>;
                    };

                    if (controller.signal.aborted) {
                        return;
                    }

                    if (response.status === 422) {
                        setResultado({
                            key,
                            totales: null,
                            bloqueos: [],
                            advertencias: [],
                            errores: fieldErrors(data),
                        });

                        return;
                    }

                    if (!response.ok) {
                        throw new Error('No se pudo validar la selección.');
                    }

                    setResultado({ ...data, key, errores: {} });
                } catch {
                    if (!controller.signal.aborted) {
                        setFallo({
                            key,
                            mensaje:
                                'No se pudo revisar la selección. Reintenta antes de generar.',
                        });
                    }
                }
            })();
        }, 300);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [key, resultado.key, url, body]);

    const vigente = resultado.key === key;
    const error = fallo?.key === key ? fallo.mensaje : null;

    return {
        totales: vigente ? resultado.totales : null,
        bloqueos: vigente ? resultado.bloqueos : [],
        advertencias: vigente ? resultado.advertencias : [],
        errores: vigente ? resultado.errores : {},
        pendiente: !vigente && error === null,
        listo:
            vigente &&
            error === null &&
            Object.keys(resultado.errores).length === 0,
        error,
        revalidar: () => setRevision((previous) => previous + 1),
    };
}

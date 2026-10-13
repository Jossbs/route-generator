import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * Si hay una visita de Inertia en vuelo.
 *
 * Los filtros de las dos bandejas recargan la lista sin cambiar de pantalla: sin
 * esto, entre el clic y la respuesta no pasa nada visible y se vuelve a hacer
 * clic. Con esto la lista se atenúa mientras llega lo nuevo.
 *
 * La barra de progreso de Inertia no basta aquí: aparece hasta los 150 ms y vive
 * hasta arriba de la ventana, lejos de donde está mirando quien filtró.
 */
export function useCargando(): boolean {
    const [cargando, setCargando] = useState(false);

    useEffect(() => {
        const empezo = router.on('start', () => setCargando(true));
        const acabo = router.on('finish', () => setCargando(false));

        return () => {
            empezo();
            acabo();
        };
    }, []);

    return cargando;
}

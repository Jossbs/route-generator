import { toast } from 'sonner';

/**
 * Lo que pasa cuando el servidor devuelve un formulario con errores.
 *
 * Cada mensaje ya aparece debajo de su campo, pero en el celular el campo puede
 * estar dos pantallas arriba: el toast dice cuántos son y la vista salta al
 * primero, para que el botón no parezca no haber hecho nada.
 */
export function avisarErrores(
    errores: Record<string, string | undefined>,
): void {
    const claves = Object.keys(errores).filter((clave) => errores[clave]);

    if (claves.length === 0) {
        return;
    }

    toast.error(
        claves.length === 1
            ? 'Falta un dato por corregir'
            : `Faltan ${claves.length} datos por corregir`,
        { description: 'Los marqué en rojo en el formulario.' },
    );

    // Después de que React pinte los mensajes: antes no hay a dónde saltar.
    requestAnimationFrame(() => {
        const primero = primerCampo(claves);

        if (!primero) {
            return;
        }

        primero.scrollIntoView({ behavior: 'smooth', block: 'center' });
        primero.focus({ preventScroll: true });
    });
}

/** El campo con error que va más arriba en la página. */
function primerCampo(claves: string[]): HTMLElement | null {
    const candidatos = claves
        .flatMap((clave) => {
            // `course_ids.3` no tiene campo propio: el error es de la lista.
            const raiz = clave.split('.')[0];

            return [
                document.getElementById(clave),
                document.getElementById(raiz),
                document.querySelector<HTMLElement>(
                    `[name="${CSS.escape(raiz)}"]:not([type="hidden"])`,
                ),
            ];
        })
        .filter((el): el is HTMLElement => el !== null);

    const alerta = document.querySelector<HTMLElement>(
        '#contenido [role="alert"]',
    );

    if (alerta) {
        candidatos.push(alerta);
    }

    return (
        candidatos.sort((a, b) =>
            a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING
                ? -1
                : 1,
        )[0] ?? null
    );
}

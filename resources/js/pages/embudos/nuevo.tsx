import { Head } from '@inertiajs/react';
import { ArmadorEmbudo } from '@/components/embudos/armador-embudo';
import Heading from '@/components/heading';
import AppLayout from '@/layouts/app-layout';
import type {
    Curso,
    Especialidad,
    PermisosEmbudo,
    Traslapes,
    Validacion,
} from '@/types/embudos';

type PageProps = {
    catalogo: Curso[];
    especialidades: Especialidad[];
    traslapes: Traslapes;
    permisos: PermisosEmbudo;
    validacionEnVivo?: Validacion;
};

export default function EmbudoNuevo({
    catalogo,
    especialidades,
    traslapes,
    permisos,
    validacionEnVivo,
}: PageProps) {
    return (
        <AppLayout>
            <Head title="Nuevo embudo" />

            <div className="flex flex-col gap-6">
                <Heading
                    overline="Embudos"
                    title="Nuevo embudo"
                    description="Un nombre y los cursos de la rama con la que vas a enganchar al lead. El asesor la elige después, tal cual, al generar el documento."
                    className="mb-0"
                />

                <ArmadorEmbudo
                    embudo={null}
                    catalogo={catalogo}
                    especialidades={especialidades}
                    traslapes={traslapes}
                    validacion={null}
                    validacionEnVivo={validacionEnVivo}
                    permisos={permisos}
                />
            </div>
        </AppLayout>
    );
}

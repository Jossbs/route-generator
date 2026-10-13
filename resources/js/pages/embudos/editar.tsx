import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { ArmadorEmbudo } from '@/components/embudos/armador-embudo';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { show } from '@/routes/funnels';
import type {
    Curso,
    Embudo,
    Especialidad,
    PermisosEmbudo,
    Traslapes,
    Validacion,
} from '@/types/embudos';

type PageProps = {
    embudo: Embudo;
    catalogo: Curso[];
    especialidades: Especialidad[];
    traslapes: Traslapes;
    validacion: Validacion;
    validacionEnVivo?: Validacion;
    permisos: PermisosEmbudo;
};

export default function EmbudoEditar({
    embudo,
    catalogo,
    especialidades,
    traslapes,
    validacion,
    validacionEnVivo,
    permisos,
}: PageProps) {
    return (
        <AppLayout>
            <Head title={embudo.nombre} />

            <div className="flex flex-col gap-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        overline="Embudos"
                        title={embudo.nombre}
                        description="Cambia el nombre o los cursos. El validador revisa la lista mientras la mueves."
                        className="mb-0"
                    />

                    <Button variant="ghost" asChild className="max-sm:w-full">
                        <Link href={show(embudo.id)}>
                            <ArrowLeft className="size-4" />
                            Ver la ruta
                        </Link>
                    </Button>
                </div>

                <ArmadorEmbudo
                    embudo={embudo}
                    catalogo={catalogo}
                    especialidades={especialidades}
                    traslapes={traslapes}
                    validacion={validacion}
                    validacionEnVivo={validacionEnVivo}
                    permisos={permisos}
                />
            </div>
        </AppLayout>
    );
}

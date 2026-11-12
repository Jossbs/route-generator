import {
    ArrowLeftRight,
    ChevronDown,
    ChevronUp,
    Lock,
    Plus,
    Undo2,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { OrdenDeLaRuta } from '@/hooks/use-ruta-editable';
import { cn } from '@/lib/utils';
import type { BloquePropuesto, CursoDelCatalogo } from '@/types/rutas';

type Props = {
    bloques: BloquePropuesto[];
    catalogo: CursoDelCatalogo[];
    fuera: number[];
    onToggle: (cursoId: number) => void;
    onAgregar: (curso: CursoDelCatalogo) => void;
    /**
     * Si viene, la pantalla deja ordenar los cursos recomendados: cada uno de
     * ellos y cada bloque traen sus flechas. La ruta principal nunca las lleva
     * —va completa y en el orden en que se curó— así que su presencia es también
     * lo que distingue a la ruta inicial de la final: en la final no hay ruta
     * principal y todo se ordena, cruzando meses si hace falta.
     */
    orden?: OrdenDeLaRuta;
    onReemplazar?: (cursoId: number, curso: CursoDelCatalogo) => void;
};

function horasDe(valor: number | null): string {
    return valor === null ? '— h' : `${valor} h`;
}

/**
 * El renglón que separa las dos partes del gancho.
 *
 * Arriba la ruta principal: todos los cursos del embudo, repartidos en los
 * títulos de las especialidades a las que pertenecen. Abajo lo recomendado, que
 * es la muestra de lo que el lead no sabía que venía dentro y es lo único que el
 * asesor acomoda. Sin estos dos títulos, las dos partes se leen como una sola
 * lista y el asesor no sabe cuál puede tocar.
 */
function seccionDe(
    bloques: BloquePropuesto[],
    indice: number,
    primeroRecomendado: number,
): Seccion | null {
    if (indice === 0 && bloques[0]?.principal) {
        return {
            titulo: 'Ruta principal',
            nota: 'La armó Eje Cursos para esta campaña. Va completa y en este orden.',
        };
    }

    return indice === primeroRecomendado && indice > 0
        ? {
              titulo: 'Lo que le recomiendo',
              nota: 'Por su área y por lo que te dijo. Aquí decides tú: agrega, reemplaza, quita y ordena.',
          }
        : null;
}

type Seccion = { titulo: string; nota: string };

/**
 * El renglón que separa un mes del siguiente, o nada.
 *
 * Un bloque sin mes al principio de la lista es la ruta inicial, que no los tiene;
 * uno sin mes después de los que sí lo tienen es lo que el asesor acaba de sumar,
 * y su mes lo decide el motor al generar.
 */
function separadorDe(
    bloque: BloquePropuesto,
    anterior: BloquePropuesto | undefined,
    indice: number,
): string | null {
    if (bloque.mes === anterior?.mes) {
        return null;
    }

    if (bloque.mes !== null) {
        return `Mes ${bloque.mes}`;
    }

    return indice === 0 ? null : 'Lo que agregaste';
}

/**
 * Los cursos propuestos, agrupados por especialidad.
 *
 * Cada bloque lleva el porqué que el asesor va a defender en la conversación, y
 * dice cuántos cursos trae la especialidad completa: al lead no se le agrega un
 * curso suelto, se le agregan especialidades. Quitar uno no lo borra de su
 * membresía —sigue incluido— solo lo saca de esta hoja.
 *
 * El orden que se ve aquí es el orden en que se va a leer el documento: los
 * bloques bajan en el mismo orden en que están, y dentro de cada uno los cursos
 * también. En la ruta inicial se mueve dentro de lo recomendado, porque la ruta
 * principal va tal cual. En la ruta final se mueve todo: el asesor ordena y el
 * motor decide dónde termina cada mes, que encabeza los bloques que caen en él,
 * cada especialidad bajo su propio título.
 */
export function BloquesPropuesta({
    bloques,
    catalogo,
    fuera,
    onToggle,
    onAgregar,
    orden,
    onReemplazar,
}: Props) {
    const [buscando, setBuscando] = useState(false);
    const [busqueda, setBusqueda] = useState('');
    const [reemplazando, setReemplazando] = useState<{
        id: number;
        nombre: string;
    } | null>(null);
    const selector = useRef<HTMLDivElement>(null);
    useEffect(() => {
        if (buscando)
            selector.current?.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });
    }, [buscando, reemplazando]);

    const enLaRuta = useMemo(
        () =>
            new Set(
                bloques
                    .flatMap((b) => b.cursos.map((c) => c.id))
                    .filter((id) => !fuera.includes(id)),
            ),
        [bloques, fuera],
    );

    const normalizar = (texto: string) =>
        texto
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase();
    const candidatos = catalogo
        .filter((curso) => !enLaRuta.has(curso.id))
        .filter((curso) =>
            normalizar(`${curso.nombre} ${curso.especialidad ?? ''}`).includes(
                normalizar(busqueda.trim()),
            ),
        );
    const movibles = bloques
        .flatMap((bloque) => bloque.cursos)
        .filter((curso) => !curso.fijo && !fuera.includes(curso.id))
        .map((curso) => curso.id);

    const quitados = bloques
        .flatMap((b) => b.cursos)
        .filter((curso) => fuera.includes(curso.id));

    // La ruta principal no se mueve, así que los recomendados se ordenan solo
    // entre ellos. Su primer bloque es también donde empieza la segunda parte
    // del documento.
    const primeroMovible = bloques.findIndex((bloque) => !bloque.principal);

    return (
        <div className="space-y-6">
            {bloques.map((bloque, indice) => {
                // Un bloque es de la campaña si trae cursos de la campaña. No se
                // mira el rol: en los doce meses todos los bloques del itinerario
                // llevan roles de recomendación y ninguno es intocable.
                const curado = bloque.cursos.some((curso) => curso.fijo);

                return (
                    <section
                        key={`${bloque.mes}:${bloque.principal}:${bloque.especialidad_id}:${bloque.cursos[0]?.id}`}
                        className="space-y-2"
                    >
                        {/* En la ruta inicial, las dos partes del documento. En los
                        doce meses, el mes cuando cambia: los bloques ya vienen en
                        el orden en que se van a leer. */}
                        {orden && bloque.mes === null
                            ? (() => {
                                  const seccion = seccionDe(
                                      bloques,
                                      indice,
                                      primeroMovible,
                                  );

                                  return (
                                      seccion && (
                                          <div className="border-b pb-2">
                                              <p className="sys-overline">
                                                  {seccion.titulo}
                                              </p>
                                              <p className="text-muted-foreground mt-1 text-sm">
                                                  {seccion.nota}
                                              </p>
                                          </div>
                                      )
                                  );
                              })()
                            : separadorDe(
                                  bloque,
                                  bloques[indice - 1],
                                  indice,
                              ) && (
                                  <p className="text-muted-foreground border-b pb-1 text-xs font-semibold tracking-wide uppercase">
                                      {separadorDe(
                                          bloque,
                                          bloques[indice - 1],
                                          indice,
                                      )}
                                  </p>
                              )}

                        <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <h3 className="min-w-0 font-semibold break-words">
                                {bloque.especialidad}
                            </h3>
                            {curado && (
                                <Badge
                                    variant="secondary"
                                    className="gap-1 whitespace-nowrap"
                                >
                                    <Lock className="size-3" />
                                    No se toca
                                </Badge>
                            )}
                            <span className="text-muted-foreground text-xs">
                                {
                                    catalogo.filter(
                                        (curso) =>
                                            curso.especialidad_id ===
                                            bloque.especialidad_id,
                                    ).length
                                }{' '}
                                cursos incluidos en su membresía
                            </span>

                            {orden && !bloque.principal && (
                                <div className="ml-auto flex items-center gap-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-8"
                                        disabled={
                                            orden.ocupado ||
                                            indice <= primeroMovible
                                        }
                                        aria-label={`Subir ${bloque.especialidad}`}
                                        onClick={() =>
                                            orden.moverBloque(indice, -1)
                                        }
                                    >
                                        <ChevronUp className="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-8"
                                        disabled={
                                            orden.ocupado ||
                                            indice === bloques.length - 1
                                        }
                                        aria-label={`Bajar ${bloque.especialidad}`}
                                        onClick={() =>
                                            orden.moverBloque(indice, 1)
                                        }
                                    >
                                        <ChevronDown className="size-4" />
                                    </Button>
                                </div>
                            )}
                        </div>

                        {bloque.justificacion && (
                            <p className="text-muted-foreground text-sm">
                                {bloque.justificacion}
                            </p>
                        )}

                        {orden && curado && (
                            <p className="text-muted-foreground text-sm">
                                {bloque.abre
                                    ? 'Es la rama por la que preguntó: abre el documento y va completa.'
                                    : 'También viene de la campaña, aunque sea de otra especialidad. Va tal cual.'}
                            </p>
                        )}

                        <ul className="divide-y rounded-lg border">
                            {bloque.cursos.map((curso, fila) => {
                                const quitado = fuera.includes(curso.id);
                                // Lo que vino de la campaña no se quita ni se mueve,
                                // sin importar en qué bloque haya caído.
                                const fijo = curso.fijo === true;
                                const posicion = movibles.indexOf(curso.id);

                                return (
                                    <li
                                        key={curso.id}
                                        className={cn(
                                            'flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2 text-sm',
                                            quitado && 'text-muted-foreground',
                                        )}
                                    >
                                        <span className="flex min-w-0 flex-1 items-center gap-3 max-sm:basis-full">
                                            {orden && (
                                                <span className="text-muted-foreground w-5 shrink-0 text-right text-xs tabular-nums">
                                                    {onReemplazar && !fijo
                                                        ? posicion < 0
                                                            ? '—'
                                                            : posicion + 1
                                                        : fila + 1}
                                                </span>
                                            )}
                                            <span
                                                className={cn(
                                                    'min-w-0 flex-1 break-words',
                                                    quitado && 'line-through',
                                                )}
                                            >
                                                {curso.nombre}
                                            </span>
                                        </span>
                                        <span className="text-muted-foreground shrink-0 tabular-nums max-sm:ml-auto">
                                            {horasDe(curso.horas)}
                                            {bloque.mes !== null &&
                                                curso.mes_fin != null &&
                                                curso.mes_fin > bloque.mes &&
                                                ` · meses ${bloque.mes}–${curso.mes_fin}`}
                                        </span>

                                        {onReemplazar && !fijo && !quitado && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                aria-label={`Reemplazar ${curso.nombre}`}
                                                onClick={() => {
                                                    setReemplazando({
                                                        id: curso.id,
                                                        nombre: curso.nombre,
                                                    });
                                                    setBusqueda('');
                                                    setBuscando(true);
                                                }}
                                            >
                                                <ArrowLeftRight className="size-4" />{' '}
                                                Reemplazar
                                            </Button>
                                        )}

                                        {orden && !fijo && (
                                            <>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8"
                                                    disabled={
                                                        orden.ocupado ||
                                                        quitado ||
                                                        (orden.cruzaBloques
                                                            ? posicion <= 0
                                                            : fila === 0)
                                                    }
                                                    aria-label={`Subir ${curso.nombre}`}
                                                    onClick={() =>
                                                        orden.moverCurso(
                                                            bloque.especialidad_id,
                                                            curso.id,
                                                            -1,
                                                        )
                                                    }
                                                >
                                                    <ChevronUp className="size-4" />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8"
                                                    disabled={
                                                        orden.ocupado ||
                                                        quitado ||
                                                        (orden.cruzaBloques
                                                            ? posicion ===
                                                              movibles.length -
                                                                  1
                                                            : fila ===
                                                              bloque.cursos
                                                                  .length -
                                                                  1)
                                                    }
                                                    aria-label={`Bajar ${curso.nombre}`}
                                                    onClick={() =>
                                                        orden.moverCurso(
                                                            bloque.especialidad_id,
                                                            curso.id,
                                                            1,
                                                        )
                                                    }
                                                >
                                                    <ChevronDown className="size-4" />
                                                </Button>
                                            </>
                                        )}

                                        {/* La ruta principal va completa: ahí no hay
                                        qué quitar, solo qué ordenar. */}
                                        {!(orden && fijo) && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-8"
                                                disabled={orden?.ocupado}
                                                aria-label={
                                                    quitado
                                                        ? `Devolver ${curso.nombre}`
                                                        : `Quitar ${curso.nombre}`
                                                }
                                                onClick={() =>
                                                    onToggle(curso.id)
                                                }
                                            >
                                                {quitado ? (
                                                    <Undo2 className="size-4" />
                                                ) : (
                                                    <X className="size-4" />
                                                )}
                                            </Button>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    </section>
                );
            })}

            {quitados.length > 0 && (
                <p className="text-muted-foreground text-sm">
                    Quitados ({quitados.length}). Siguen incluidos en su
                    membresía; solo no van en esta hoja.
                </p>
            )}

            {buscando ? (
                <div ref={selector} className="space-y-2 rounded-lg border p-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="text-sm font-medium">
                            {reemplazando
                                ? `Reemplazar «${reemplazando.nombre}»`
                                : 'Agregar un curso adicional'}
                        </p>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setBuscando(false);
                                setReemplazando(null);
                                setBusqueda('');
                            }}
                        >
                            Cancelar
                        </Button>
                    </div>
                    {reemplazando && (
                        <p className="text-muted-foreground text-xs">
                            El curso que elijas ocupará el mismo lugar. El
                            actual se conserva hasta que elijas su reemplazo.
                        </p>
                    )}
                    <Input
                        autoFocus
                        aria-label="Buscar un curso del catálogo"
                        placeholder="Buscar por curso o especialidad"
                        value={busqueda}
                        onChange={(event) => setBusqueda(event.target.value)}
                    />
                    <ul className="max-h-64 space-y-1 overflow-y-auto">
                        {candidatos.map((curso) => (
                            <li key={curso.id}>
                                <button
                                    type="button"
                                    className="hover:bg-accent focus-visible:ring-ring/50 flex w-full items-start gap-2 rounded-md px-2 py-2 text-left text-sm transition-colors outline-none focus-visible:ring-[3px]"
                                    onClick={() => {
                                        if (reemplazando && onReemplazar)
                                            onReemplazar(
                                                reemplazando.id,
                                                curso,
                                            );
                                        else onAgregar(curso);
                                        setReemplazando(null);
                                        setBuscando(false);
                                        setBusqueda('');
                                    }}
                                >
                                    <Plus className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                                    <span className="min-w-0 flex-1">
                                        <span className="block break-words">
                                            {curso.nombre}
                                        </span>
                                        <span className="text-muted-foreground block text-xs break-words">
                                            {curso.especialidad} ·{' '}
                                            {horasDe(curso.horas)}
                                        </span>
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                    {candidatos.length === 0 && (
                        <p
                            role="status"
                            className="text-muted-foreground p-2 text-sm"
                        >
                            No hay cursos disponibles con esa búsqueda. Los
                            cursos que ya están seleccionados no se repiten.
                        </p>
                    )}
                </div>
            ) : (
                <Button
                    type="button"
                    variant="outline"
                    className="max-sm:w-full"
                    onClick={() => {
                        setReemplazando(null);
                        setBusqueda('');
                        setBuscando(true);
                    }}
                >
                    <Plus className="size-4" />
                    Agregar otro curso
                </Button>
            )}
        </div>
    );
}

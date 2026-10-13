<?php

declare(strict_types=1);

namespace App\Services\RouteComposition\Support;

use App\Models\Course;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reparte el itinerario anual en meses, balanceado por horas y respetando la
 * secuencia pedagógica: fundamentos, después herramientas, certificación al final.
 *
 * Tres cosas gobiernan el reparto, en este orden:
 *
 *   1. Los prerrequisitos duros. Excel Intermedio no puede caer en un mes anterior
 *      al de Excel Básico. Es la restricción que LEVEL_SEQUENCE verifica después.
 *   2. La complejidad. Dentro de lo que los prerrequisitos permiten, primero lo
 *      que funda y al final lo que exige base previa.
 *   3. Las horas. Un mes de 90 h junto a uno de 12 h no es una recomendación
 *      creíble para alguien que estudia después del trabajo.
 *
 * El reparto es de horas, no de fechas: los cursos son autogestivos y el documento
 * dice «mes 1 sugerido».
 */
final class MonthPlanner
{
    /**
     * @param  Collection<int, Course>  $courses  Indexados por id.
     * @param  list<int>  $specializationOrder
     * @return array<int, array<int, list<Course>>> Mes => especialización => cursos.
     */
    public function distribute(
        Collection $courses,
        array $specializationOrder,
        CourseRanker $ranker,
        int $months,
        ?StudyPace $pace = null,
    ): array {
        $this->endMonths = [];

        if ($courses->isEmpty() || $months < 1) {
            return [];
        }

        return $this->sortBlocks(
            $this->cut($this->orderPedagogically($courses, $specializationOrder, $ranker), $months, $pace),
            $specializationOrder,
        );
    }

    /** @var array<int, int> Curso => mes en que termina, solo los que abarcan más de uno. */
    private array $endMonths = [];

    /**
     * Los cursos que no caben en un solo mes al ritmo del miembro, y el mes en que
     * terminan. Es del último reparto: el documento imprime «meses 3–5» y los meses
     * de en medio dicen «continúa», para que ninguno parezca vacío sin serlo.
     *
     * @return array<int, int>
     */
    public function endMonths(): array
    {
        return $this->endMonths;
    }

    /**
     * Reparte una secuencia que ya viene ordenada —la que dejó el asesor— sin
     * reordenarla: solo decide dónde termina cada mes.
     *
     * El orden es del asesor; las horas y los prerrequisitos duros siguen siendo
     * del motor. Un curso cuyo requisito va antes en la secuencia sigue sin poder
     * caer en su mismo mes, y uno que el asesor puso antes de su requisito no se
     * mueve en silencio: se queda donde lo dejó y LEVEL_SEQUENCE lo señala, con
     * el arreglo a un clic.
     *
     * Dentro de un mes, cada especialización abre su bloque donde aparece por
     * primera vez. Dos cursos de la misma especialización en un mes van bajo un
     * solo título aunque el asesor los haya separado: comparten mes, y el título
     * es uno.
     *
     * @param  list<Course>  $sequence
     * @return array<int, array<int, list<Course>>> Mes => especialización => cursos.
     */
    public function sequence(array $sequence, int $months, ?StudyPace $pace = null): array
    {
        $this->endMonths = [];

        if ($sequence === [] || $months < 1) {
            return [];
        }

        $plan = $this->cut($sequence, $months, $pace);
        ksort($plan);

        return $plan;
    }

    /**
     * El corte en meses de una secuencia dada, en el orden en que llega.
     *
     * @param  list<Course>  $ordered
     * @return array<int, array<int, list<Course>>>
     */
    private function cut(array $ordered, int $months, ?StudyPace $pace = null): array
    {
        if ($pace !== null) {
            return $this->cutAtPace($ordered, $months, $pace);
        }

        $plan = [];
        $month = 1;
        $spent = 0;
        $monthOf = [];
        $remainingHours = array_sum(array_map(fn (Course $c): int => $c->duration_hours ?? 0, $ordered));

        // Cuota del mes en curso: lo que queda por repartir entre los meses que
        // faltan, incluido este. Se fija al abrir el mes y no se toca hasta el
        // siguiente. Recalcularla a media marcha la haría encogerse conforme el mes
        // se llena, cerrando cada mes antes de tiempo y arrastrando un déficit que
        // caería entero en diciembre.
        $budget = $this->budgetFor($remainingHours, $months, $month);

        foreach ($ordered as $course) {
            $hours = $course->duration_hours ?? 0;
            $earliest = $this->earliestMonthFor($course, $monthOf);

            $closeMonth = $month < $months && $spent > 0
                && ($earliest > $month || $this->overshoots($spent, $hours, $budget));

            if ($closeMonth) {
                $month = min($months, max($month + 1, $earliest));
                $spent = 0;
                $budget = $this->budgetFor($remainingHours, $months, $month);
            }

            $plan[$month][$course->specialization_id][] = $course;
            $monthOf[$course->id] = $month;
            $spent += $hours;
            $remainingHours -= $hours;
        }

        return $plan;
    }

    /**
     * El corte al ritmo del miembro: cada mes se llena hasta las horas que dijo
     * que tiene —o menos, si lo elegido no las necesita todas—, en el orden de la
     * secuencia, y un curso empieza donde terminó el anterior. Balanceado por
     * construcción —todos los meses pesan lo mismo— y sostenible, porque ninguno
     * pide más de lo que cabe.
     *
     * Un curso más largo que un mes empieza en uno y termina en otro; su mes es el
     * de inicio, que es el que ordena la lectura. Un requisito duro obliga a que
     * el curso empiece en un mes posterior al que su requisito termina: el tiempo
     * que queda libre se pierde a propósito, antes que encimar los dos.
     *
     * Lo que no cabe en el año se queda en el último mes. No se esconde: la
     * pantalla y la emisión lo detienen antes de que llegue al documento.
     *
     * @param  list<Course>  $ordered
     * @return array<int, array<int, list<Course>>>
     */
    private function cutAtPace(array $ordered, int $months, StudyPace $pace): array
    {
        // El ritmo es un tope, no una cuota: si lo elegido cabe con holgura, se
        // reparte parejo en todo el año en vez de llenar los primeros meses y
        // dejar los últimos vacíos.
        $total = (float) array_sum(array_map(fn (Course $c): int => $c->duration_hours ?? 0, $ordered));
        $monthly = max(1.0, min($pace->monthlyHours(), $total / $months));
        $plan = [];
        $monthOf = [];
        $elapsed = 0.0;

        foreach ($ordered as $course) {
            $hours = (float) ($course->duration_hours ?? 0);
            $start = (int) floor($elapsed / $monthly) + 1;
            $earliest = $this->earliestMonthFor($course, $monthOf);

            if ($earliest > $start) {
                $start = $earliest;
                $elapsed = ($earliest - 1) * $monthly;
            }

            // Un curso que termina justo en la orilla del mes no la cruza.
            $end = $hours > 0 ? (int) floor(($elapsed + $hours - 0.001) / $monthly) + 1 : $start;
            $start = min($start, $months);
            $end = min(max($end, $start), $months);

            $plan[$start][$course->specialization_id][] = $course;
            $monthOf[$course->id] = $end;
            $elapsed += $hours;

            if ($end > $start) {
                $this->endMonths[$course->id] = $end;
            }
        }

        return $plan;
    }

    /**
     * El orden en que se recorren los cursos antes de repartirlos: especialización
     * por especialización, y dentro de cada una de lo que funda a lo que exige base.
     *
     * @param  Collection<int, Course>  $courses
     * @param  list<int>  $specializationOrder
     * @return list<Course>
     */
    private function orderPedagogically(Collection $courses, array $specializationOrder, CourseRanker $ranker): array
    {
        $rank = array_flip($specializationOrder);
        $ordered = [];

        foreach ($courses->groupBy('specialization_id') as $specializationId => $group) {
            $ordered[$rank[$specializationId] ?? PHP_INT_MAX] = $ranker->sequence($group);
        }

        ksort($ordered);

        return array_merge(...array_values($ordered));
    }

    private function budgetFor(int $remainingHours, int $months, int $month): int
    {
        return (int) max(1, ceil($remainingHours / max(1, $months - $month + 1)));
    }

    /**
     * ¿Agregar este curso aleja más del presupuesto que cerrar el mes aquí?
     *
     * Se compara la desviación en ambos sentidos en vez de cortar en cuanto se
     * excede: un mes de 52 h contra un presupuesto de 50 está mejor repartido que
     * uno de 30 seguido de otro que arrastra las 22 restantes.
     */
    private function overshoots(int $spent, int $hours, int $budget): bool
    {
        return abs($spent + $hours - $budget) > abs($spent - $budget);
    }

    /**
     * El primer mes en que un curso puede caer sin adelantarse a lo que necesita.
     * Un prerrequisito que no está en el itinerario no estorba: si el curso previo
     * quedó fuera por un traslape, exigirlo empujaría la ruta sin motivo.
     *
     * @param  array<int, int>  $monthOf
     */
    private function earliestMonthFor(Course $course, array $monthOf): int
    {
        $earliest = 1;

        foreach ($this->hardPrerequisites()[$course->id] ?? [] as $requiredId) {
            if (isset($monthOf[$requiredId])) {
                $earliest = max($earliest, $monthOf[$requiredId] + 1);
            }
        }

        return $earliest;
    }

    /** @var array<int, list<int>>|null */
    private ?array $hardPrerequisites = null;

    /**
     * Solo los prerrequisitos duros. Los sugeridos orientan al generador a través
     * del orden por complejidad, pero no bloquean un mes.
     *
     * @return array<int, list<int>>
     */
    private function hardPrerequisites(): array
    {
        if ($this->hardPrerequisites !== null) {
            return $this->hardPrerequisites;
        }

        $map = [];

        foreach (DB::table('course_prerequisites')->where('strength', 'duro')->get() as $row) {
            $map[(int) $row->course_id][] = (int) $row->requires_course_id;
        }

        return $this->hardPrerequisites = $map;
    }

    /**
     * Deja los meses en orden y, dentro de cada mes, las especializaciones en el
     * orden de recorrido. Cada una conserva su propio bloque: dos especializaciones
     * pueden compartir un mes, jamás un encabezado.
     *
     * @param  array<int, array<int, list<Course>>>  $plan
     * @param  list<int>  $specializationOrder
     * @return array<int, array<int, list<Course>>>
     */
    private function sortBlocks(array $plan, array $specializationOrder): array
    {
        $rank = array_flip($specializationOrder);

        ksort($plan);

        foreach ($plan as $month => $blocks) {
            uksort($blocks, fn (int $a, int $b): int => ($rank[$a] ?? PHP_INT_MAX) <=> ($rank[$b] ?? PHP_INT_MAX));
            $plan[$month] = $blocks;
        }

        return $plan;
    }
}

{{--
    Una fila de curso, igual en los dos documentos.

    Tres cosas que antes no decía y ahora sí:

    El **nivel** va como distintivo junto al nombre. Es lo que deja leer la ruta
    como una progresión —Básico, luego Intermedio, luego Avanzado— en vez de como
    una lista de cursos sueltos, y es el mismo dato sobre el que se evalúa
    `LEVEL_SEQUENCE`.

    Un curso **absorbido** por un paquete presente en la ruta ya no imprime sus
    horas: dice «incluido» y nada más. Imprimir «16 h · incluido» invitaba
    justamente a la suma que `MODULE_DOUBLE_COUNT` prohíbe.

    Las **horas** van en píldora y la fila tiene alto mínimo. Un curso sin frase de
    resultado ocupaba la mitad que sus vecinos y dos seguidos se apelotonaban
    contra el margen derecho, con los números amontonados a la izquierda: se leían
    como una nota al pie y no como dos pasos más de la ruta.
--}}
@php
    $nivelEtiqueta = [
        'basico' => 'Básico',
        'intermedio' => 'Intermedio',
        'avanzado' => 'Avanzado',
        'completo' => 'Completo',
    ][$curso['nivel'] ?? ''] ?? null;

    $absorbido = (bool) ($curso['horas_absorbidas'] ?? false);
@endphp
<div class="curso{{ ($primero ?? false) ? ' primero' : '' }}">
    <span class="cu-n">{{ $marca }}</span>

    <div class="cu-cuerpo">
        @if ($primero ?? false)
            <span class="cu-aqui">Empieza aquí</span>
        @endif
        <div class="cu-nom">{{ $curso['nombre'] }}@if ($nivelEtiqueta)<span class="cu-nivel{{ $curso['nivel'] === 'completo' ? ' paquete' : '' }}">{{ $nivelEtiqueta }}</span>@endif</div>
        @if ($curso['frase_resultado'])
            <div class="cu-desc">{{ $curso['frase_resultado'] }}</div>
        @endif
    </div>

    <div class="cu-meta">
        @if ($absorbido)
            <span class="inc">Incluido</span>
        @else
            <span class="dur">{{ $curso['horas'] === null ? '—' : $curso['horas'] }}&nbsp;h</span>
        @endif
    </div>
</div>

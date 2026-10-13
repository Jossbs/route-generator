{{--
    Por qué esta ruta y no otra, dicho con las palabras del propio lead.

    Es la pieza que sustituye al audio del asesor: lo que demostraba interés no era
    la lista de cursos, era que alguien se acordara de lo que el lead dijo. Nombra
    además el primer curso de la ruta, el mismo que nombra el guión del audio, para
    que lo que oye y lo que lee digan lo mismo.

    La frase sale de `copy_blocks` (`porque.con-contexto`) o del ajuste que el
    asesor hizo en la revisión, ya escaneado. Si el asesor no capturó contexto, el
    bloque no sale y la portada se lee igual (docs/ARQUITECTURA.md §6).
--}}
@if ($porque)
    <div class="pt-porque">
        <span class="k">Por qué esta ruta</span>
        <p>{{ $porque }}</p>
    </div>
@endif

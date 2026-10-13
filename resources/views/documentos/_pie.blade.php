{{--
    El pie de hoja.

    Solo la marca, el sitio y el folio con su total. Hasta el 2026-09-24 repetía en
    cada hoja las cifras de la ruta —programas, horas, constancias—, que ya dice la
    portada: cada cifra se dice una vez.

    El folio va con su total. La portada lleva el «01» en su propio pie, así que
    «02 / 04» se lee sin salto.
--}}
<div class="pie">
    <b>Desarrollo Profesional SyS</b>
    <span>academy.dpsys.com.mx</span>
    <span class="num">{{ $folio }}<i>/{{ str_pad((string) $paginas, 2, '0', STR_PAD_LEFT) }}</i></span>
</div>

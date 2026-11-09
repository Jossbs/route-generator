<div class="enc">
    @include('documentos._marca-anual')
    <div class="rf-progreso"><span>Tu año</span><div>
        @foreach (range(1, 12) as $numero)
            <span class="{{ in_array($numero, $activos ?? [], true) ? 'activo' : '' }}{{ $numero === 1 ? ' primero' : '' }}"><i></i>{{ $numero }}</span>
        @endforeach
    </div></div>
    <span>{{ $seccion }}</span>
</div>

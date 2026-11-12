<section class="hoja confianza-sys" data-reading-section="confianza">
    @include('documentos._encabezado', ['seccion' => 'Quiénes somos'])
    <div class="cuerpo">
        <div class="h-sec"><span class="kicker">Conócenos</span><h2>Quiénes somos</h2></div>
        @if ($confianza['entradilla'])
            <p class="conf-intro">{{ $confianza['entradilla'] }}</p>
        @endif

        <div class="conf-cifras">
            @foreach ($confianza['cifras'] as $cifra)
                <div><b>{{ $cifra['valor'] }}</b><span>{{ $cifra['etiqueta'] }}</span></div>
            @endforeach
        </div>

        <div class="conf-empresas">
            <h4>Empresas que confían en SyS</h4>
            <div class="logos">
                @foreach (array_filter($confianza['empresas'], fn ($empresa) => in_array($empresa['nombre'], ['Pfizer', 'Caterpillar', 'PEMEX', 'Baxter', 'APTIV', 'COPARMEX'], true)) as $empresa)
                    <span>@if ($empresa['logo'])<img src="{{ $empresa['logo'] }}" alt="{{ $empresa['nombre'] }}">@else{{ $empresa['nombre'] }}@endif</span>
                @endforeach
            </div>
        </div>

        <div class="conf-respaldos">
            <h4>Certificaciones y acreditaciones</h4>
            <div class="conf-avales">
                @foreach ($confianza['avales'] as $aval)
                    <a data-reading-click="respaldo" href="{{ $aval['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="Consultar respaldo: {{ $aval['nombre'] }}">
                        @if ($aval['src'])<img src="{{ $aval['src'] }}" alt="{{ $aval['nombre'] }}">@endif
                        <span>{{ $aval['etiqueta'] }}</span><small>Ver respaldo ↗</small>
                    </a>
                @endforeach
            </div>
            <p class="conf-alcance">Consulta el documento de cada aval y su alcance según el programa.</p>
        </div>

        <div class="conf-fundadores">
            <h4>Conoce a nuestros cofundadores</h4>
            <div class="conf-fundadores-grid">
                @foreach ($confianza['cofundadores'] as $fundador)
                    <article class="conf-fundador">
                        <img src="{{ $fundador['src'] }}" alt="{{ $fundador['nombre'] }}">
                        <div class="conf-fundador-info">
                            <h5>{{ $fundador['nombre'] }}</h5>
                            <p>{{ $fundador['semblanza'] }}</p>
                            <div class="conf-fundador-links">
                                <a href="{{ $fundador['perfil'] }}" target="_blank" rel="noopener noreferrer">Semblanza ↗</a>
                                <a href="{{ $confianza['fuentes']['stps'] }}" target="_blank" rel="noopener noreferrer" aria-label="Buscar a {{ $fundador['agente'] }} en el registro oficial de agentes capacitadores de la STPS">Buscar en STPS: {{ $fundador['agente'] }} ↗</a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>

        <div class="conf-verifica">
            <a href="{{ $confianza['fuentes']['egresados'] }}" target="_blank" rel="noopener noreferrer">Egresados ↗</a>
            <a href="{{ $confianza['fuentes']['sitio'] }}" target="_blank" rel="noopener noreferrer">Sitio Oficial SyS ↗</a>
            <a href="{{ $confianza['fuentes']['cssc'] }}" target="_blank" rel="noopener noreferrer">Acreditación Six Sigma Council ↗</a>
        </div>
        <nav class="conf-redes" aria-label="Redes sociales oficiales de Desarrollo Profesional SyS">
            @foreach ($confianza['redes'] as $red)
                <a href="{{ $red['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $red['nombre'] }} oficial de SyS">
                    @switch($red['icono'])
                        @case('facebook')<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.5 21v-8.2h2.8l.4-3.2h-3.2V7.5c0-.9.3-1.5 1.6-1.5h1.7V3.1c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3v2.3H7.3v3.2h2.8V21z"/></svg>@break
                        @case('instagram')<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="18" cy="6" r="1" class="punto"/></svg>@break
                        @case('linkedin')<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5.2 8.5H2V21h3.2zM3.6 2a1.9 1.9 0 1 0 0 3.8 1.9 1.9 0 0 0 0-3.8M22 13.8c0-3.8-2-5.6-4.7-5.6a4 4 0 0 0-3.6 2V8.5h-3.2V21h3.2v-6.2c0-1.6.3-3.2 2.3-3.2s2 1.9 2 3.3V21H21z"/></svg>@break
                        @case('youtube')<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M23 7a3 3 0 0 0-2.1-2.1C19 4.4 12 4.4 12 4.4s-7 0-8.9.5A3 3 0 0 0 1 7a31 31 0 0 0-.5 5 31 31 0 0 0 .5 5 3 3 0 0 0 2.1 2.1c1.9.5 8.9.5 8.9.5s7 0 8.9-.5A3 3 0 0 0 23 17a31 31 0 0 0 .5-5 31 31 0 0 0-.5-5M9.7 15.4V8.6l6 3.4z"/></svg>@break
                        @case('tiktok')<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19.6 7.1a6.5 6.5 0 0 1-4.1-1.5v8.2a5.8 5.8 0 1 1-5-5.7v3.2a2.7 2.7 0 1 0 1.8 2.5V2.5h3.2a6.4 6.4 0 0 0 4.1 3.5z"/></svg>@break
                    @endswitch
                    <span>{{ $red['nombre'] }}</span>
                </a>
            @endforeach
        </nav>
        <p class="conf-fuentes">Desarrollo Profesional SyS · <a href="{{ $confianza['fuentes']['sitio'] }}" target="_blank" rel="noopener noreferrer">academy.dpsys.com.mx</a> · <a href="{{ $confianza['fuentes']['trayectoria'] }}" target="_blank" rel="noopener noreferrer">Trayectoria desde 2018</a></p>
    </div>
    @include('documentos._pie', ['folio' => $folio])
</section>

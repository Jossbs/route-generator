<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * El área en la que trabaja el lead.
 *
 * No es lo mismo que la ocupación. La ocupación es texto libre —«ingeniero de
 * procesos en una automotriz»— y sirve para leer matices; el área es un valor de
 * una lista corta, y por eso se puede contar, segmentar y usar como señal
 * determinista de composición. Las dos conviven: el área dice de qué lado de la
 * planta viene, la ocupación dice qué hace ahí.
 *
 * La lista sale de los leads reales de DP SyS —manufactura y sus áreas de
 * soporte— más el caso de quien todavía no trabaja. Lo que no cabe aquí entra
 * como texto por `leads.area_other`, ya estandarizado por AreaNormalizer.
 *
 * ### Por qué es enum y no tabla
 *
 * Las reglas del catálogo viven en PostgreSQL porque cambian sin deploy y el
 * validador las interpreta. Esto es otra cosa: un vocabulario cerrado de captura
 * del que cuelgan preferencias de composición escritas en código. Meterlo en una
 * tabla obligaría a una pantalla de administración que la v1 no tiene, y dejaría
 * la afinidad partida entre dato y código. Cuando lleguen los 8 arquetipos del
 * Excel, esta preferencia se reemplaza por `archetype_specialization_affinity`.
 */
enum LeadArea: string
{
    case Calidad = 'calidad';
    case Produccion = 'produccion';
    case Mantenimiento = 'mantenimiento';
    case Ingenieria = 'ingenieria';
    case Logistica = 'logistica';
    case Compras = 'compras';
    case Seguridad = 'seguridad';
    case RecursosHumanos = 'rh';
    case Administracion = 'administracion';
    case Ventas = 'ventas';
    case Direccion = 'direccion';
    case Proyectos = 'proyectos';
    case Sistemas = 'ti';
    case Estudiante = 'estudiante';

    /** Como se lee en la pantalla y como se guarda de vuelta al asesor. */
    public function label(): string
    {
        return match ($this) {
            self::Calidad => 'Calidad',
            self::Produccion => 'Producción / Manufactura',
            self::Mantenimiento => 'Mantenimiento',
            self::Ingenieria => 'Ingeniería / Procesos',
            self::Logistica => 'Logística y Almacén',
            self::Compras => 'Compras / Abastecimiento',
            self::Seguridad => 'Seguridad e Higiene (EHS)',
            self::RecursosHumanos => 'Recursos Humanos',
            self::Administracion => 'Administración y Finanzas',
            self::Ventas => 'Ventas / Comercial',
            self::Direccion => 'Dirección / Gerencia',
            self::Proyectos => 'Proyectos',
            self::Sistemas => 'TI / Sistemas',
            self::Estudiante => 'Estudiante o buscando empleo',
        };
    }

    /**
     * Cómo lo escribe la gente.
     *
     * Es lo que AreaNormalizer compara para que «QA», «aseguramiento de calidad»
     * y «Calidad» terminen en la misma fila. No pretende ser exhaustiva: lo que no
     * cae aquí se guarda como texto estandarizado, y si empieza a repetirse se
     * agrega un sinónimo y deja de repartirse.
     *
     * Van sin acentos y en minúscula porque se comparan ya normalizados.
     *
     * @return list<string>
     */
    public function synonyms(): array
    {
        return match ($this) {
            self::Calidad => ['calidad', 'control de calidad', 'aseguramiento de calidad', 'gestion de calidad', 'sgc', 'qa', 'qc', 'inspeccion', 'metrologia', 'laboratorio'],
            self::Produccion => ['produccion', 'manufactura', 'operaciones', 'planta', 'piso de produccion', 'ensamble', 'maquinados', 'produccion manufactura'],
            self::Mantenimiento => ['mantenimiento', 'mantto', 'confiabilidad', 'tpm', 'servicios generales'],
            self::Ingenieria => ['ingenieria', 'procesos', 'ingenieria de procesos', 'ingenieria industrial', 'mejora continua', 'metodos', 'manufactura esbelta', 'diseno'],
            self::Logistica => ['logistica', 'almacen', 'inventarios', 'cadena de suministro', 'supply chain', 'embarques', 'trafico', 'distribucion', 'materiales'],
            self::Compras => ['compras', 'abastecimiento', 'adquisiciones', 'procurement', 'proveedores', 'desarrollo de proveedores'],
            self::Seguridad => ['seguridad e higiene', 'seguridad', 'seguridad industrial', 'higiene', 'ehs', 'hse', 'sso', 'medio ambiente', 'proteccion civil'],
            self::RecursosHumanos => ['recursos humanos', 'rh', 'rrhh', 'capital humano', 'talento', 'capacitacion', 'reclutamiento'],
            self::Administracion => ['administracion', 'finanzas', 'administracion y finanzas', 'contabilidad', 'contraloria', 'costos', 'facturacion'],
            self::Ventas => ['ventas', 'comercial', 'mercadotecnia', 'marketing', 'servicio al cliente', 'atencion a clientes', 'postventa'],
            self::Direccion => ['direccion', 'gerencia', 'direccion general', 'alta direccion', 'gerente general', 'dueno', 'propietario', 'socio'],
            self::Proyectos => ['proyectos', 'gestion de proyectos', 'administracion de proyectos', 'pmo', 'project management'],
            self::Sistemas => ['ti', 'it', 'sistemas', 'tecnologias de la informacion', 'informatica', 'soporte tecnico', 'desarrollo de software'],
            self::Estudiante => ['estudiante', 'buscando empleo', 'busco empleo', 'desempleado', 'sin empleo', 'recien egresado', 'egresado', 'practicante', 'becario'],
        };
    }

    /**
     * Las especializaciones que mejor acompañan a esta área, en orden de preferencia.
     *
     * Es la señal que el asesor ya no tiene que escribir: antes la única forma de
     * que el motor supiera que el lead viene de mantenimiento era que la palabra
     * apareciera en la ocupación. Solo pesa sobre los cursos **recomendados**; la
     * ruta principal la decide el embudo por el que preguntó, y eso no se toca.
     *
     * Cadena de Suministros no aparece aunque un lead de logística la pediría:
     * tiene un solo curso y su ficha no la admite como complementaria. Quien lo
     * impide es `allowed_roles`, no esta lista.
     *
     * @return list<string> Slugs de `specializations`.
     */
    public function specializationSlugs(): array
    {
        return match ($this) {
            self::Calidad => ['calidad', 'iso', 'problemas'],
            self::Produccion => ['lean', 'kaizen', 'problemas'],
            self::Mantenimiento => ['lean', 'kaizen', 'problemas'],
            self::Ingenieria => ['lean', 'sixsigma', 'problemas'],
            self::Logistica => ['lean', 'kaizen', 'datos'],
            self::Compras => ['kaizen', 'datos', 'calidad'],
            self::Seguridad => ['kaizen', 'problemas', 'iso'],
            self::RecursosHumanos => ['soft', 'kaizen', 'excel'],
            self::Administracion => ['excel', 'datos', 'kaizen'],
            self::Ventas => ['soft', 'excel', 'kaizen'],
            self::Direccion => ['soft', 'sixsigma', 'kaizen'],
            self::Proyectos => ['problemas', 'soft', 'lean'],
            self::Sistemas => ['datos', 'excel', 'problemas'],
            self::Estudiante => ['soft', 'excel', 'lean'],
        };
    }

    /**
     * ¿El área ya implica mando?
     *
     * Dirección es la única que lo dice por sí sola. En las demás lo decide el
     * puesto —un supervisor de calidad manda gente y un inspector no— y eso sigue
     * saliendo de la ocupación.
     */
    public function leadsPeople(): bool
    {
        return $this === self::Direccion;
    }

    /**
     * La lista tal como la ve el asesor, en el orden en que se despliega.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $area): array => ['value' => $area->value, 'label' => $area->label()],
            self::cases(),
        );
    }
}

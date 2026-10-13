# Módulo Embudos — Especificación de implementación

> Especificación funcional y técnica del módulo de embudos.
> Complementa a `docs/ARQUITECTURA.md` y al `Diccionario_Datos_DPSyS_v1.0.md`. No los reemplaza.
>
> **Versión:** 1.0 · **Fecha:** 2026-09-10 · **Estado:** aprobado para implementación

---

## 0. Cómo leer este documento

Cada elemento del modelo lleva una etiqueta:

| Etiqueta | Significado |
|---|---|
| `[v1]` | Se construye ahora, con interfaz de usuario. |
| `[dormido]` | La columna se crea en la migración, pero **no** se expone en la interfaz ni se usa en lógica. Existe para no migrar después. |
| `[bloqueado]` | Depende de una decisión pendiente. **No implementar. No inventar el valor.** Ver sección 11. |

El principio rector de todo el módulo es: **diseñar el modelo completo, construir solo lo mínimo.**
Si algo está marcado `[dormido]`, crear la columna y detenerse ahí. No construir formularios,
validaciones ni lógica de negocio alrededor de ella.

### Convención de idioma

El repo ya usa modelos de dominio en español (`Curso`, `CursoSeeder`). Este módulo sigue esa
convención: `Embudo`, `EmbudoRuta`, `PerfilLead`, `RutaEmitida`.

**Si al abrir el repo los modelos existentes resultan estar en inglés** (`Course`, `Specialization`),
voltear todos los nombres de este documento a inglés (`Funnel`, `FunnelRoute`, `LeadProfile`,
`IssuedRoute`) y mantener la consistencia con lo que ya existe. La consistencia con el repo gana
sobre lo escrito aquí.

Textos de cara al usuario y al lead: siempre español.

---

## 1. Contexto y problema que resuelve

DP SyS no vende cursos sueltos ni rutas genéricas. Vende **rutas curadas**: paquetes que mezclan
cursos de varias especialidades, armados alrededor de un tema comercial, dirigidos a un perfil de
lead concreto y vigentes por un periodo corto.

Esas rutas **cambian semana con semana o mes con mes** según la estrategia publicitaria vigente.
Hoy eso implicaría editar código en cada rotación, lo cual es inviable.

Este módulo permite que Mercadotecnia arme y publique esas rutas desde el sistema.

### Hallazgo que condiciona el diseño

La auditoría de la publicidad activa mostró que rotan el mismo producto bajo **ángulos comerciales
distintos** (descuento, urgencia, informativo, retención de miembros), en lotes de 6 a 12 creativos
cada una o dos semanas. El gancho principal es la credencial DC-3, no el curso.

**Consecuencia de diseño:** el ángulo comercial es un campo independiente de la lista de cursos.
Un mismo paquete se vende con distintos ganchos sin duplicar el embudo.

### Advertencia de vocabulario

"Embudo" es lenguaje **interno**. La palabra nunca aparece en el PDF ni en nada que vea el lead.
Por eso cada embudo tiene `nombre_interno` y `nombre_publico` separados.

En v1 un embudo tiene una sola etapa. El nombre apunta al destino del módulo, no a su alcance
actual. El modelo no debe cerrarle la puerta a etapas futuras, pero **no se construyen ahora**.

---

## 2. Arquitectura: cómo encaja con lo que ya existe

Hay dos formas de armar una ruta y **un solo motor** que las procesa:

```
                 ┌─────────────────────────┐
  Ruta generada  │  Asesor llena formulario│
  (ya existe)    │  → motor compone desde  │──┐
                 │    catálogo             │  │
                 └─────────────────────────┘  │
                                              ├──► Motor de rutas ──► Snapshot ──► PDF
                 ┌─────────────────────────┐  │    (aplica reglas)   (inmutable)   /r/{token}
  Ruta curada    │  Asesor elige un embudo │  │
  (este módulo)  │  → precarga sus cursos  │──┘
                 │  → motor personaliza    │
                 └─────────────────────────┘
```

**El embudo es un preset que siembra al motor, no un motor paralelo.**

No se debe crear una segunda plantilla de PDF, un segundo generador ni una segunda ruta de
descarga. Si la implementación empieza a duplicar el pipeline, está mal encaminada: parar y
replantear.

### Orden dentro del plan del proyecto

Este módulo va **entre la capa de base de datos/catálogo y el motor de rutas**, no después.
El motor debe saber recibir un preset desde su primera versión.

---

## 3. Modelo de datos

Cinco tablas nuevas. PostgreSQL. Todas las migraciones con `timestamps()` y `softDeletes()` salvo
donde se indique.

### 3.1 `embudos`

El contenedor comercial. Una campaña de venta.

```php
Schema::create('embudos', function (Blueprint $table) {
    $table->id();
    $table->string('slug')->unique();                    // [v1]
    $table->string('nombre_interno');                    // [v1] lo ve el equipo
    $table->string('nombre_publico');                    // [v1] lo ve el lead
    $table->string('angulo');                            // [v1] enum, ver 4.1
    $table->string('headline')->nullable();              // [v1] portada del PDF
    $table->string('subheadline')->nullable();           // [v1]
    $table->date('vigencia_inicio');                     // [v1]
    $table->date('vigencia_fin')->nullable();            // [v1] null = permanente
    $table->string('estado')->default('borrador');       // [v1] enum, ver 4.2

    // --- dormidos ---
    $table->integer('precio_lista_centavos')->nullable();      // [bloqueado] ver 11.2
    $table->integer('precio_promo_centavos')->nullable();      // [bloqueado] ver 11.2
    $table->string('etiqueta_descuento')->nullable();          // [bloqueado] ver 11.2
    $table->foreignId('curso_ancla_precio_id')->nullable()->constrained('cursos');
    $table->string('utm_campaign')->nullable();                // [dormido]
    $table->string('meta_campaign_ref')->nullable();           // [dormido]

    $table->foreignId('creado_por')->constrained('users');           // [v1]
    $table->foreignId('publicado_por')->nullable()->constrained('users'); // [v1]
    $table->timestamp('publicado_en')->nullable();                   // [v1]
    $table->text('notas_internas')->nullable();                      // [v1]

    $table->timestamps();
    $table->softDeletes();

    $table->index(['estado', 'vigencia_inicio', 'vigencia_fin']);
});
```

**Convención obligatoria del slug:** `{año}-{mes}-{tema}-{angulo}`

```
2026-10-calidad-descuento
2026-10-lean-urgencia
2026-11-sixsigma-informativo
```

A la velocidad de rotación actual habrá decenas de embudos en meses. Sin esta convención el
listado se vuelve inservible. Validar el formato con regex en el FormRequest:

```
/^\d{4}-(0[1-9]|1[0-2])-[a-z0-9]+(-[a-z0-9]+)*$/
```

### 3.2 `embudo_rutas`

La ruta concreta dentro del embudo. Un embudo puede tener varias, cada una apuntando a un perfil
distinto (ej. campaña de octubre con una ruta de Calidad, otra de Lean y otra de Six Sigma).

```php
Schema::create('embudo_rutas', function (Blueprint $table) {
    $table->id();
    $table->foreignId('embudo_id')->constrained('embudos')->cascadeOnDelete();
    $table->string('nombre');                                     // [v1]
    $table->unsignedSmallInteger('posicion')->default(0);         // [v1] orden en la UI
    $table->foreignId('perfil_lead_id')->nullable()->constrained('perfil_leads'); // [v1]
    $table->foreignId('especialidad_principal_id')->nullable()->constrained('especialidades'); // [v1]
    $table->unsignedTinyInteger('duracion_meses')->default(12);   // [v1]
    $table->text('texto_intro')->nullable();                      // [v1] override de copy
    $table->text('texto_cierre')->nullable();                     // [v1] override de copy

    // --- dormido ---
    $table->unsignedTinyInteger('elige_n')->nullable();  // [dormido] mecánica "elige N de M"

    $table->timestamps();
    $table->softDeletes();

    $table->index(['embudo_id', 'posicion']);
});
```

**Por qué existe `elige_n`:** el sitio comercial histórico ya opera con planes de "3 cursos a
elegir" y "más de 50 cursos a elegir". Esa mecánica va a regresar. La columna cuesta cero hoy y
evita una migración incómoda después. **No construir la lógica de selección en v1.**

### 3.3 `embudo_ruta_cursos`

La composición. Pivote enriquecido.

```php
Schema::create('embudo_ruta_cursos', function (Blueprint $table) {
    $table->id();
    $table->foreignId('embudo_ruta_id')->constrained('embudo_rutas')->cascadeOnDelete();
    $table->foreignId('curso_id')->constrained('cursos');
    $table->unsignedSmallInteger('posicion');                      // [v1]
    $table->unsignedTinyInteger('mes_sugerido')->nullable();       // [v1]

    // --- dormidos ---
    $table->string('tipo')->default('fijo');            // [dormido] fijo | elegible
    $table->text('frase_resultado_override')->nullable(); // [dormido] ver nota

    $table->timestamps();

    $table->unique(['embudo_ruta_id', 'curso_id']);
    $table->index(['embudo_ruta_id', 'posicion']);
});
```

**Sobre `frase_resultado_override`:** las 64 frases de resultado del catálogo maestro siguen sin
capturarse (Capa 4, 0 de 64). Esta columna es la válvula de escape para que una promoción concreta
lleve su propia frase sin que nadie fabrique contenido en el catálogo maestro.
**Sigue prohibido inventar frases de resultado.** La columna existe, se queda vacía.

**Sobre `mes_sugerido`:** los cursos son autoritmo con acceso de por vida. El PDF dice
"mes 1 sugerido", nunca una fecha. La ruta es una recomendación de secuencia, no un compromiso
de calendario.

### 3.4 `perfil_leads`

El arquetipo de lead. En v1 se **selecciona a mano**, no se detecta.

```php
Schema::create('perfil_leads', function (Blueprint $table) {
    $table->id();
    $table->string('slug')->unique();          // [v1]
    $table->string('nombre');                  // [v1]
    $table->text('descripcion')->nullable();   // [v1]
    $table->boolean('activo')->default(true);  // [v1]

    // --- dormido ---
    $table->json('reglas_match')->nullable();  // [dormido] detección automática, post-v1

    $table->timestamps();
});
```

La detección automática de arquetipo está **fuera de alcance de v1** por decisión previa del
proyecto. Esta tabla la introduce como entidad sin reactivar ese alcance: el asesor elige el perfil
de una lista. La columna `reglas_match` queda vacía.

### 3.5 `rutas_emitidas`

**La tabla más importante del módulo.** Guarda cada ruta generada, congelada.

```php
Schema::create('rutas_emitidas', function (Blueprint $table) {
    $table->id();
    $table->string('token', 32)->unique();     // → /r/{token}

    // datos del lead (capturados en el formulario)
    $table->string('lead_nombre');
    $table->string('lead_telefono')->nullable();
    $table->string('lead_email')->nullable();
    $table->string('kommo_id')->nullable();

    $table->foreignId('asesor_id')->constrained('users');

    // origen: si vino de un embudo o fue generación libre
    $table->foreignId('embudo_id')->nullable()->constrained('embudos');
    $table->foreignId('embudo_ruta_id')->nullable()->constrained('embudo_rutas');
    $table->foreignId('perfil_lead_id')->nullable()->constrained('perfil_leads');

    // EL SNAPSHOT
    $table->jsonb('payload');
    $table->string('catalogo_version');
    $table->string('snapshot_hash', 64);

    $table->string('pdf_path')->nullable();
    $table->timestamp('generado_en');

    $table->timestamps();

    $table->index('embudo_id');
    $table->index('asesor_id');
    $table->index('generado_en');
});
```

`embudo_id` nulo significa ruta generada sin embudo (el flujo que ya existe). Las dos conviven en
la misma tabla.

Esta tabla **no lleva `softDeletes`**. Una ruta emitida es un hecho histórico: el lead ya la tiene.

---

## 4. Enums

Implementar como PHP enums respaldados por string (`enum ... : string`), no como constantes sueltas.

### 4.1 `AnguloEmbudo`

```php
enum AnguloEmbudo: string {
    case Descuento    = 'descuento';     // "50% Descuento DC-3"
    case Urgencia     = 'urgencia';      // "¡INSCRÍBETE AHORA!"
    case Informativo  = 'informativo';   // "Conoce más", "Infórmate aquí"
    case Retencion    = 'retencion';     // "Accede a tu membresía"
}
```

Los cuatro valores salen de la rotación real de creativos observada en la cuenta publicitaria.
No agregar más sin evidencia.

### 4.2 `EstadoEmbudo`

```php
enum EstadoEmbudo: string {
    case Borrador  = 'borrador';   // editable, no seleccionable por asesores
    case Activo    = 'activo';     // publicado y dentro de vigencia
    case Pausado   = 'pausado';    // publicado pero temporalmente oculto
    case Archivado = 'archivado';  // histórico, no editable, no seleccionable
}
```

**Transiciones permitidas:**

```
borrador  → activo      (requiere validador sin bloqueantes + permiso de publicar)
activo    ↔ pausado
activo    → archivado
pausado   → archivado
archivado → (terminal)
```

Un embudo `activo` cuya `vigencia_fin` ya pasó **no se archiva solo**. Deja de aparecer en el
selector del asesor, pero se conserva `activo` para que el listado muestre por qué desapareció.
Un job diario puede marcarlos como `archivado`, pero eso es post-v1.

---

## 5. El congelamiento (snapshot)

### Regla dura

> **El PDF se renderiza desde el `payload`, nunca desde el catálogo.**

Al generar, el motor resuelve todo (cursos, nombres, horas, especialidades, vigencia, textos,
precios) y lo escribe completo dentro de `payload`. A partir de ahí el documento es inmutable.

### Por qué

1. Los PDFs viven en una URL pública permanente `/r/{token}`. Un lead que recibió su ruta el 15 de
   septiembre con "vigencia al 30 de septiembre" tiene que seguir viendo eso en enero.
2. Si Mercadotecnia edita el embudo mañana, los PDFs de ayer no se mueven. Solo cambian las
   generaciones nuevas.
3. La generación de PDF se vuelve una función pura: recibe JSON, devuelve documento. Testeable sin
   base de datos.
4. Si mañana se corrigen las horas de un curso en el catálogo, queda trazabilidad de qué se le
   prometió a quién y bajo qué versión.

El costo es unos pocos KB de JSON por emisión. Es el mejor intercambio del proyecto.

### Estructura del payload

```json
{
  "schema_version": "1.0",
  "generado_en": "2026-10-03T14:22:11-06:00",
  "catalogo_version": "1.0",

  "lead": {
    "nombre": "...",
    "telefono": "...",
    "email": null,
    "kommo_id": null
  },

  "asesor": { "id": 3, "nombre": "..." },

  "embudo": {
    "slug": "2026-10-calidad-descuento",
    "nombre_publico": "...",
    "angulo": "descuento",
    "headline": "...",
    "subheadline": "...",
    "vigencia_inicio": "2026-10-01",
    "vigencia_fin": "2026-10-31"
  },

  "ruta": {
    "nombre": "...",
    "perfil_lead": "...",
    "especialidad_principal": "...",
    "duracion_meses": 12,
    "texto_intro": "...",
    "texto_cierre": "...",
    "cursos": [
      {
        "orden": 1,
        "mes_sugerido": 1,
        "nombre": "...",
        "slug": "...",
        "especialidad": "...",
        "horas": 16,
        "frase_resultado": null,
        "tipo": "fijo",
        "es_paquete": false
      }
    ],
    "totales": {
      "cursos": 9,
      "horas_netas": 128
    }
  },

  "precios": null,

  "avisos_validador": []
}
```

Notas:

- `embudo` es `null` cuando la ruta se generó sin embudo.
- `horas_netas` se calcula **descontando los componentes de paquete**. Nunca sumar horas brutas.
  Para AutoCAD y SolidWorks se usa siempre el valor declarado del paquete (36 h), no la suma de sus
  componentes (48 h).
- `precios` queda `null` hasta que se resuelva la decisión 11.2.
- `avisos_validador` guarda las advertencias que se confirmaron al publicar. Sirve para auditoría.
- `snapshot_hash` es `hash('sha256', json_encode($payload))`. Detecta manipulación posterior.

---

## 6. El validador

Corre al guardar una `EmbudoRuta` y pinta el resultado en pantalla en tiempo real. **No es solo una
verificación al generar el PDF.**

Motivo: con la rotación actual de campañas, el volumen de embudos creados será alto. El criterio de
quien arma la promoción no puede ser la única defensa contra un error de credibilidad frente a un
lead.

Implementar como una clase de servicio dedicada (`ValidadorEmbudoRuta`) que **reutiliza el motor de
reglas del catálogo ya existente**. No duplicar las reglas.

### 6.1 Bloqueantes — impiden publicar el embudo

| Código | Regla |
|---|---|
| `EXC-LIDERAZGO` | Dos o más de los tres cursos de liderazgo que se traslapan en la misma ruta. |
| `EXC-AUTOCAD` | Paquete AutoCAD junto con cualquiera de sus componentes. |
| `EXC-SOLIDWORKS` | Paquete SolidWorks junto con cualquiera de sus componentes. |
| `EXC-EXCEL` | Paquete Excel Completo junto con cualquiera de sus componentes. |
| `EXC-DMAIC` | DMAIC junto con Green Belt o Black Belt. |
| `SCM-STANDALONE` | Cadena de Suministros como única especialidad de la ruta. Tiene un solo curso y nunca se presenta como especialidad autónoma. |
| `CORE-TOOLS-INCOMPLETO` | Cualquier campo de copy que afirme "Core Tools completo" (o variantes) cuando el perfil objetivo es automotriz. Core Tools cubre 4 de las 5 AIAG: faltan APQP y MSA. |
| `LEXICO-LECCIONES` | La palabra "lecciones" en cualquier campo de copy de cara al lead. El sistema habla de horas. |

### 6.2 Advertencias — se publica confirmando explícitamente

| Código | Regla |
|---|---|
| `TRASLAPE-LEAN` | Lean Manufacturing junto con cualquiera de los ocho cursos Lean que históricamente listaba como su contenido (TPS, TPM, Kanban, VSM, 8 Desperdicios, SMED, Kaizen, Trabajo Estándar). Sospecha de doble conteo abierta, no confirmada. |
| `TRASLAPE-KAIZEN` | Kaizen junto con 5'S u OEE. Misma naturaleza. |
| `PRERREQUISITO` | Salto en cualquiera de las 7 cadenas de prerrequisito (las tres progresiones Básico→Intermedio→Avanzado, la escalera White→Yellow→Green→Black, y las tres recomendadas). |
| `NO-APTO-RUTA` | Cursos marcados `apto_para_ruta = false`. **Ver decisión pendiente 11.1.** |
| `DURACION-EN-DISPUTA` | Uso de cursos con `requiere_validacion_dc3` (Kaizen, Gestión de Inventarios). Sus horas están en disputa entre fuentes. |

Cada advertencia confirmada se persiste en `payload.avisos_validador` para auditoría.

### 6.3 Contrato de salida

```php
// ValidadorEmbudoRuta::validar(EmbudoRuta $ruta): ResultadoValidacion
[
  'bloqueantes' => [
    ['codigo' => 'EXC-LIDERAZGO', 'mensaje' => '...', 'curso_ids' => [12, 34]],
  ],
  'advertencias' => [
    ['codigo' => 'TRASLAPE-LEAN', 'mensaje' => '...', 'curso_ids' => [5, 8, 9]],
  ],
]
```

La UI resalta los cursos involucrados usando `curso_ids`. El mensaje se muestra tal cual, en
español, orientado a quien arma la promoción y no a un desarrollador.

---

## 7. Interfaz de usuario (v1)

Inertia + React + shadcn/ui + Tailwind, como el resto del proyecto.

### 7.1 Listado de embudos — `/embudos`

Tabla con: nombre interno, slug, ángulo, estado, vigencia, número de rutas, autor.
Filtros por estado y por vigencia. Búsqueda por slug o nombre.
Botón "Nuevo embudo".

Ordenar por `vigencia_inicio` descendente. Los vencidos se muestran atenuados.

### 7.2 Editor de embudo — `/embudos/{id}/editar`

Dos zonas:

**Cabecera.** Los datos del embudo: nombres, ángulo, headline, subheadline, vigencia, notas
internas. El slug se sugiere automáticamente desde año, mes, tema y ángulo, pero es editable hasta
que el embudo se publica. Después de publicado, el slug se congela.

**Rutas.** Lista de `EmbudoRuta` con acordeón. Cada ruta abre a: nombre, perfil de lead,
especialidad principal, duración en meses, textos de intro y cierre, y el selector de cursos.

**Selector de cursos.** Buscador con filtro por especialidad, arrastrar para ordenar, campo de mes
sugerido por curso. Al costado, un panel fijo que muestra en vivo:

- Total de cursos y horas netas (con descuento de paquetes ya aplicado)
- Distribución por especialidad
- **El resultado del validador**, con bloqueantes en rojo y advertencias en ámbar

El panel del validador es el elemento central de esta pantalla. Debe ser imposible de ignorar.

**Vista previa del PDF.** Botón que genera un PDF de prueba con un lead ficticio, sin escribir en
`rutas_emitidas`. Marcar el documento con una marca de agua "VISTA PREVIA" para que nunca se
confunda con uno emitido.

### 7.3 Publicación

Acción separada del guardado. Requiere:

1. Cero bloqueantes en el validador
2. Confirmación explícita de cada advertencia
3. Permiso `embudos.publicar` — **ver decisión pendiente 11.3**

Al publicar se sellan `publicado_por` y `publicado_en`.

### 7.4 Selector en el formulario del asesor

En el formulario de generación que ya existe, agregar un select de embudo **arriba** del resto de
campos.

Comportamiento:

- Muestra solo embudos en estado `activo` y dentro de vigencia
- Agrupados por ángulo
- Al elegir uno, si tiene varias rutas, aparece un segundo select para la ruta
- Al elegir ruta, se **precargan** sus cursos en el formulario
- El asesor puede modificar la selección precargada; el motor sigue personalizando encima
- Opción explícita "Sin embudo (ruta libre)" que preserva el flujo actual intacto

**El asesor decide siempre.** El embudo precarga, no impone.

---

## 8. Integración con el motor de rutas

El motor recibe un objeto de entrada que puede venir sembrado o vacío:

```php
final class EntradaRuta
{
    public function __construct(
        public readonly DatosLead $lead,
        public readonly ?Embudo $embudo,          // null = ruta libre
        public readonly ?EmbudoRuta $embudoRuta,  // null = ruta libre
        public readonly ?PerfilLead $perfil,
        public readonly array $cursoIds,          // precargados o elegidos por el asesor
        public readonly int $duracionMeses,
    ) {}
}
```

El motor:

1. Aplica las reglas duras del catálogo sobre `cursoIds` (mismo motor que usa el validador)
2. Resuelve nombres, horas, especialidades y frases desde la base
3. Calcula horas netas descontando paquetes
4. Construye el `payload`
5. Persiste en `rutas_emitidas`
6. Encola la generación del PDF

**No hay bifurcación por tipo de origen después del paso 1.** Si aparece un `if ($embudo)` en los
pasos 2 a 6, el diseño se desvió.

---

## 9. Parámetro de URL — pendiente de confirmación

El formulario ya lee `?nombre=&telefono=&especializacion=&kommo_id=`.

Está propuesto agregar `?embudo={slug}` para que el anuncio del que vino el lead **preseleccione**
el embudo en el formulario. El asesor confirma o lo cambia. Cero lógica de ruteo automático.

**Estado: esperando confirmación de Ros.** Mientras tanto:

- La columna `slug` ya existe y es única, así que el soporte está listo
- **No implementar la lectura del parámetro todavía**
- Cuando se confirme, son unas veinte líneas en el controlador del formulario

---

## 10. Seeders

### `PerfilLeadSeeder`

Los perfiles de lead **no existen todavía como dato**. Son contenido de negocio de DP SyS.

**Prohibido inventarlos.** Crear el seeder con la estructura vacía y un comentario:

```php
// Los perfiles de lead los define DP SyS. No fabricar.
// Cuando lleguen, poblar aquí con slug, nombre y descripción.
```

Sin perfiles, el campo `perfil_lead_id` queda nulable y el selector muestra "Sin perfil". El módulo
funciona igual.

### `EmbudoSeeder`

Solo para entorno local. Un embudo de ejemplo marcado claramente como tal, que **no debe llegar a
producción**. Usar cursos reales del catálogo para que el validador tenga algo que evaluar en
desarrollo.

---

## 11. Decisiones pendientes que bloquean partes del módulo

**No inventar respuestas a nada de esto. Preguntar.**

### 11.1 Micro-cursos de empleabilidad en embudos

Los siete micro-cursos de empleabilidad (PQT Contraten, CV de Alto Impacto, entrevista, cómo
venderte, Mente Millonaria, Liderazgo Efectivo, Negociación) están marcados
`apto_para_ruta = false` de forma provisional.

**Pregunta:** ¿un embudo puede incluirlos?

- Si sí → `NO-APTO-RUTA` es advertencia
- Si no → `NO-APTO-RUTA` es bloqueante

Mientras no se resuelva: implementar como **advertencia**, que es reversible sin migración.

### 11.2 Administración del descuento

**Pregunta:** ¿el descuento se administra en este módulo o solo se refleja desde la pasarela de
pago?

Bloquea: `precio_lista_centavos`, `precio_promo_centavos`, `etiqueta_descuento`,
`curso_ancla_precio_id`, y el bloque `precios` del payload.

Mientras no se resuelva: crear las columnas, dejarlas nulas, `payload.precios = null`. No construir
interfaz de precios.

Dato de contexto para cuando se decida: el ancla de precio disponible es Green Belt individual
contra la membresía anual completa.

### 11.3 Permiso de publicación

**Pregunta:** ¿quién puede publicar un embudo?

Publicar significa que el contenido sale en PDFs con el nombre de la empresa frente a leads. Es
plausible que quien crea no deba ser quien publica.

Mientras no se resuelva: implementar el permiso `embudos.publicar` como Gate de Laravel y
otorgarlo por ahora al mismo rol que puede crear. Cambiar la política después es de una línea; no
tenerla desde el inicio obliga a refactorizar los controladores.

---

## 12. Fuera de alcance de v1

No construir, aunque el modelo lo permita:

- Etapas del embudo (captación, nutrición, cierre)
- Detección automática de arquetipo de lead
- Mecánica "elige N de M"
- Interfaz de precios y descuentos
- Ruteo automático por parámetro de URL
- Integración con Kommo CRM
- Landing pages o formularios públicos
- Archivado automático de embudos vencidos
- Métricas de conversión por embudo

Todo lo anterior tiene su columna o su hueco reservado en el modelo. Ninguno necesita migración
para activarse.

---

## 13. Orden de implementación

1. Migraciones de las cinco tablas
2. Modelos Eloquent con relaciones y casts de enum
3. `PerfilLeadSeeder` vacío + `EmbudoSeeder` local
4. `ValidadorEmbudoRuta`, reutilizando el motor de reglas del catálogo, con pruebas unitarias por
   cada regla de la sección 6
5. Construcción del `payload` y `snapshot_hash`, con prueba de que el PDF se renderiza desde el
   payload y no desde la base
6. `EntradaRuta` y adaptación del motor para recibir preset
7. CRUD de embudos (listado, editor, publicación)
8. Panel del validador en vivo dentro del editor
9. Vista previa de PDF con marca de agua
10. Selector de embudo en el formulario del asesor
11. Gates y policies

**Los pasos 4 y 5 son el núcleo.** Si algo se recorta por tiempo, no se recortan esos.

---

## 14. Criterios de aceptación

- [ ] Editar un embudo publicado **no** altera ningún PDF ya emitido
- [ ] Un embudo con bloqueantes no se puede publicar por ninguna vía, incluida la API
- [ ] El PDF se renderiza correctamente desde un `payload` con la base de datos apagada
- [ ] Una ruta sin embudo (flujo actual) sigue funcionando sin cambios de comportamiento
- [ ] Las horas netas descuentan paquetes: AutoCAD y SolidWorks reportan 36 h, no 48 h
- [ ] La palabra "lecciones" no puede llegar a ningún campo visible por el lead
- [ ] Un embudo vencido desaparece del selector del asesor pero sigue visible en el listado interno
- [ ] `snapshot_hash` cambia si el payload cambia
- [ ] Ninguna columna marcada `[dormido]` o `[bloqueado]` tiene interfaz ni lógica asociada

---

## 15. Recordatorios heredados del proyecto

- Las 64 frases de resultado no existen. **Prohibido fabricarlas.**
- El sitio oficial nunca se consulta en tiempo de ejecución. Todo se lee de base propia.
- El sistema nunca almacena ni imprime conteos duros de cursos. Los deriva.
- La ruta anual es recomendación de secuencia, no compromiso con fechas.
- Migraciones y seeders son obligatorios para todo dato de catálogo. Carga manual no se acepta.
- Toda afirmación de equivalencia de precio contra competidores solo es válida si la acreditación
  es equivalente. Cuidar la redacción.

# Diccionario de Datos Consolidado
## Generador de Rutas de Aprendizaje · DP SyS · V1

---

## 0. Control del documento

| Campo | Valor |
|---|---|
| Versión | **1.0 — consolidada** |
| Sustituye a | v0.1 (borrador de inventario) |
| Fecha | 2026-09-10 |
| Fuente primaria | `academy.dpsys.com.mx` — sitio oficial |
| Páginas auditadas | `/cursos`, `/home`, `/planes`, `/empresas`, `/suscripcion`, `/mini-cursos`, `/green-belt-six-sigma` |
| Anexo obligatorio | `Inconsistencias_Catalogo_DPSyS.md` (17 hallazgos + resoluciones adoptadas) |
| Cálculo reproducible | `calc.py` — recalcula los 64 niveles desde cero |
| Universo | **12 especialidades · 64 cursos · 881 h brutas · 773 h netas · 469 lecciones** |

**Razón de ser:** el generador de rutas nunca consulta el sitio web en tiempo de ejecución. Lee de esta base. Si `academy.dpsys.com.mx` cae, el sistema sigue emitiendo documentos y la trazabilidad de lo prometido a cada lead permanece intacta.

---

## 1. Convenciones de trazabilidad

Todo campo lleva una **procedencia** declarada. Esto no es decoración: es lo que permite defender cada línea de un PDF frente a un lead.

| Código | Significado | Se puede imprimir en el PDF |
|---|---|---|
| `[SITIO]` | Literal del sitio oficial, verificado | ✅ Sí |
| `[CALC]` | Derivado por operación aritmética sobre `[SITIO]` | ✅ Sí |
| `[ANÁLISIS]` | Juicio metodológico documentado y reproducible | ⚠️ Sí, como criterio interno de armado — nunca citado como dato oficial |
| `[INTERNO]` | Contenido que produce DP SyS, no existe en el sitio | ⛔ No hasta que exista |
| `[PENDIENTE]` | Campo modelado, sin dato | ⛔ Nunca |

**Regla dura:** un campo `[PENDIENTE]` jamás se rellena con un valor plausible. Se queda `NULL` y el motor de rutas lo omite del documento.

### 1.1 Estado de las 4 capas de captura

| Capa | Contenido | Cobertura |
|---|---|---|
| **1 · Inventario** | Especialidad, nombre, slug, lecciones, horas, descripción corta | **64 / 64** ✅ |
| **2 · Ficha detallada** | Módulos, temas, temario, descripción larga, precio individual, dirigido a, requisitos | **1 / 64** ⏳ |
| **3 · Clasificación** | `nivel_complejidad`, prerrequisitos, exclusiones, paquetes | **64 / 64** ✅ `[ANÁLISIS]` |
| **4 · Contenido comercial** | `frase_resultado` (columnas amarillas) | **0 / 64** ❌ `[INTERNO]` |

> **Sobre la Capa 2.** El sitio bloquea la lectura automatizada de las fichas individuales: solo pude abrir `/green-belt-six-sigma`, que quedó documentada como espécimen de referencia. Los campos `modulos` y `temas` existen en el modelo, están poblados en 1 de 64 y marcados `[PENDIENTE]` en los otros 63. **No se inventaron.** La vía práctica para completarlos es un export de la plataforma LMS, no scraping.

---

## 2. Modelo de datos

Trece tablas en cuatro dominios. Todas en `snake_case`, convención Laravel, PostgreSQL.

### DOMINIO A · Catálogo

#### `especialidades`

| Campo | Tipo | Null | Proc. | Regla |
|---|---|---|---|---|
| `id` | `bigserial` PK | No | — | |
| `slug` | `varchar(60)` UQ | No | `[CALC]` | kebab-case del nombre |
| `nombre` | `varchar(80)` | No | `[SITIO]` | Nombre canónico = el de `/cursos` |
| `nombre_alterno` | `varchar(80)` | Sí | `[SITIO]` | Variante usada en `/home` (INC-13) |
| `descripcion` | `text` | Sí | `[INTERNO]` | Párrafo introductorio del PDF |
| `apta_standalone` | `boolean` | No | `[ANÁLISIS]` | `false` si `COUNT(cursos) < 2` |
| `cobertura_completa` | `boolean` | No | `[ANÁLISIS]` | `false` si el marco de referencia está incompleto (Core Tools) |
| `restriccion_texto` | `varchar(200)` | Sí | `[ANÁLISIS]` | Qué no se puede afirmar de esta especialidad |
| `orden_presentacion` | `smallint` | No | `[INTERNO]` | Secuencia en el PDF |
| `activa` | `boolean` | No | — | default `true` |

**No existe `total_cursos`.** Se deriva siempre. Un contador materializado es exactamente el error que el sitio comete cuatro veces (INC-01).

#### `cursos`

| Campo | Tipo | Null | Proc. | Regla |
|---|---|---|---|---|
| `id` | `bigserial` PK | No | — | |
| `slug` | `varchar(80)` UQ | No | `[SITIO]` | = ruta pública. 7 en `[PENDIENTE]` (INC-02) |
| `nombre` | `varchar(120)` | No | `[SITIO]` | Canónico = `/cursos` |
| `nombre_alterno` | `varchar(120)` | Sí | `[SITIO]` | INC-08, INC-13 |
| `especialidad_id` | `bigint` FK | No | `[SITIO]` | `ON DELETE RESTRICT` |
| `descripcion_corta` | `varchar(200)` | No | `[SITIO]` | Tagline de tarjeta — 64/64 |
| `descripcion_larga` | `text` | Sí | `[PENDIENTE]` | 1/64 |
| `lecciones` | `smallint` | Sí | `[SITIO]` | 64/64 · **no** equivale a `modulos` (INC-05) |
| `modulos` | `smallint` | Sí | `[PENDIENTE]` | 1/64 |
| `temas` | `smallint` | Sí | `[PENDIENTE]` | 1/64 |
| `horas` | `smallint` | No | `[SITIO]` | Valor DC-3. **Único campo consistente entre fuentes** |
| `densidad` | `numeric(4,2)` | No | `[CALC]` | `horas / lecciones` |
| `nivel_complejidad` | `smallint` | No | `[ANÁLISIS]` | 1–4, rúbrica §3 |
| `indice_complejidad` | `numeric(3,2)` | No | `[CALC]` | Promedio de los 4 criterios, antes de redondear |
| `es_paquete` | `boolean` | No | `[ANÁLISIS]` | 3 confirmados |
| `revisar_traslape` | `boolean` | No | `[ANÁLISIS]` | 3 casos abiertos (INC-10, INC-11) |
| `apto_para_ruta` | `boolean` | No | `[ANÁLISIS]` | `false` en los 7 micro-cursos (INC-02) |
| `requiere_validacion_dc3` | `boolean` | No | `[ANÁLISIS]` | `true` en Kaizen y Gestión de Inventarios |
| `valor_discrepante` | `jsonb` | Sí | `[SITIO]` | Conserva el dato alterno sin perderlo |
| `precio_individual` | `numeric(10,2)` | Sí | `[PENDIENTE]` | 1/64 — Green Belt $3,799 MXN |
| `emite_dc3` | `boolean` | No | `[SITIO]` | `true` en los 64 |
| `avales` | `varchar[]` | Sí | `[PENDIENTE]` | Avales internacionales por curso |
| `url_canonica` | `varchar(200)` | Sí | `[SITIO]` | Ruta nueva |
| `url_legacy` | `varchar(200)` | Sí | `[SITIO]` | Ruta `/producto/` (INC-12) |
| `frase_resultado` | `varchar(160)` | Sí | `[INTERNO]` | **Columnas amarillas — 0/64** |
| `activo` | `boolean` | No | — | default `true` |

Índices: `especialidad_id`, `nivel_complejidad`, `apto_para_ruta`, `slug` UQ.

#### `modulos` · `temas` — modeladas, pobladas al 1.5 %

`modulos`: `id` · `curso_id` FK · `orden` · `nombre` · `total_temas`
`temas`: `id` · `modulo_id` FK · `orden` · `nombre`

Espécimen de referencia (Green Belt – Six Sigma, 9 módulos / 45 temas): Minitab (5) · Six Sigma (5) · DMAIC (3) · Define (6) · Measure (5) · Analyze (7) · Improve (6) · Control (6) · Conclusiones (2).

### DOMINIO B · Reglas de negocio

#### `curso_paquete` — evita el doble conteo

| Campo | Tipo | Regla |
|---|---|---|
| `paquete_id` | FK `cursos` | El curso agregador |
| `componente_id` | FK `cursos` | El curso contenido |
| `confirmado` | `boolean` | `true` = verificado · `false` = sospecha |

#### `reglas_exclusion` — cursos que nunca coexisten

| Campo | Tipo | Regla |
|---|---|---|
| `grupo` | `varchar(40)` | Etiqueta del conjunto mutuamente excluyente |
| `curso_id` | FK `cursos` | Miembro del grupo |
| `motivo` | `varchar(200)` | Por qué se excluyen |

#### `prerequisitos` — cadenas de progresión

`curso_id` · `requiere_curso_id` · `tipo` (`duro` \| `recomendado`)

### DOMINIO C · Rutas

#### `rutas`

| Campo | Tipo | Regla |
|---|---|---|
| `id` · `token` | `bigserial` · `varchar(32)` UQ | `token` alimenta la URL pública `/r/{token}` |
| `tipo` | `enum` | `enfocada` \| `anual` |
| `lead_id` · `asesor_id` | FK | |
| `especialidad_principal_id` | FK | La que el lead pidió |
| `especialidad_comp_1_id` · `_2_id` | FK, null | Complementarias elegidas por el asesor |
| `horas_totales` | `smallint` `[CALC]` | Suma neta, descontando paquetes |
| `generada_en` · `pdf_path` · `vistas` | `timestamptz` · `varchar` · `int` | |

**Invariantes que el motor debe garantizar:**
1. Ninguna especialidad con `apta_standalone = false` puede ocupar un slot complementario.
2. Ningún par de cursos del mismo `grupo` de `reglas_exclusion` coexiste.
3. Si entra un paquete, sus componentes quedan fuera (y viceversa).
4. Ningún curso con `apto_para_ruta = false` entra.
5. La ruta anual tiene exactamente 12 posiciones y respeta `prerequisitos` de tipo `duro`.

#### `ruta_cursos`

`ruta_id` · `curso_id` · `orden` · `mes` (1–12, null en rutas enfocadas) · `justificacion` `[INTERNO]`

### DOMINIO D · Operación

| Tabla | Campos |
|---|---|
| `asesores` | `id` · `nombre` · `email` UQ · `activo` |
| `leads` | `id` · `nombre` · `telefono` · `email` · `kommo_id` (null, post-V1) · `origen` |
| `bitacora` | `id` · `asesor_id` · `ruta_id` · `accion` · `payload` `jsonb` · `creado_en` |
| `planes` | `id` · `nombre` · `periodicidad` · `precio` · `moneda` · `beneficios` `jsonb` |
| `avales` | `id` · `siglas` · `nombre` · `tipo` · `url_certificado` |

Parámetros de URL soportados desde el día uno: `?nombre=&telefono=&especializacion=&kommo_id=`.

---

## 3. Rúbrica de `nivel_complejidad` `[ANÁLISIS]`

Cuatro criterios, cada uno 1–4. Índice = promedio simple. Nivel = índice redondeado al entero más cercano (empate hacia arriba). Reproducible con `calc.py`.

| Criterio | 1 | 2 | 3 | 4 |
|---|---|---|---|---|
| **C1 · Carga formativa** — horas oficiales | ≤ 8 h | 9–16 h | 17–24 h | ≥ 25 h |
| **C2 · Profundidad por lección** — h/lección | < 1.2 | 1.2–1.7 | 1.8–2.4 | > 2.4 |
| **C3 · Prerrequisito técnico** | Ninguno | Conceptual: conocer el proceso | Estadística descriptiva o software especializado | Estadística inferencial, capacidad de proceso o simulación |
| **C4 · Alcance de aplicación** | Individual | Estación o célula | Área o planta | Organización o cadena de valor |

**C1 y C2 son `[CALC]`** sobre datos verificados. **C3 y C4 son juicio metodológico** sobre la naturaleza reconocida de cada herramienta.

### Escala resultante

| Nivel | Etiqueta | Lectura para el asesor | Cursos |
|---|---|---|---|
| **N1** | Fundamentos | Punto de entrada, sin prerrequisitos | 10 |
| **N2** | Aplicación | Implementa la herramienta en su área | 29 |
| **N3** | Especialización | Lidera la implementación, requiere base previa | 22 |
| **N4** | Dominio / Dirección | Dirige proyectos y forma a otros | 3 |

**Por qué C2 existe.** Sin ella, Black Belt (10 lecciones) parecería más ligero que AutoCAD Completo (18 lecciones). Con ella, Black Belt marca 4.0 h/lección — la densidad más alta del catálogo — y AutoCAD Completo marca 2.0.

**Advertencia de recalibración.** C2 usa `horas / lecciones` porque `temas` solo existe en 1 de 64 cursos. Cuando llegue el export del LMS con módulos y temas reales, C2 debe recalcularse como `temas / horas`; los otros tres criterios no se mueven. En Green Belt, el único curso con ambos datos, las dos fórmulas coinciden en el mismo nivel (N4).

---

## 4. Catálogo maestro — Especialidades (12)
| # | Especialidad | Cursos | Horas | Lec | N1 | N2 | N3 | N4 | Standalone |
|---|---|---|---|---|---|---|---|---|---|
| 1 | Lean Manufacturing | 11 | 150 | 70 | 1 | 5 | 4 | 1 | ✅ |
| 2 | Six Sigma | 5 | 128 | 47 | 1 | 0 | 2 | 2 | ✅ |
| 3 | Ingeniería de Calidad | 4 | 42 | 24 | 0 | 3 | 1 | 0 | ✅ |
| 4 | Kaizen y Mejora Continua | 4 | 38 | 22 | 0 | 3 | 1 | 0 | ✅ |
| 5 | Solución de Problemas | 3 | 40 | 19 | 0 | 0 | 3 | 0 | ✅ |
| 6 | Core Tools | 4 | 46 | 26 | 0 | 1 | 3 | 0 | ✅ |
| 7 | Normas ISO | 2 | 36 | 17 | 0 | 0 | 2 | 0 | ✅ |
| 8 | Cadena de Suministros | 1 | 12 | 7 | 0 | 0 | 1 | 0 | ❌ NO |
| 9 | Diseño Industrial | 8 | 168 | 88 | 0 | 5 | 3 | 0 | ✅ |
| 10 | Microsoft Excel | 4 | 72 | 45 | 0 | 3 | 1 | 0 | ✅ |
| 11 | Análisis de Datos | 2 | 28 | 17 | 0 | 1 | 1 | 0 | ✅ |
| 12 | Soft Skills | 16 | 121 | 87 | 8 | 8 | 0 | 0 | ✅ |
| | **TOTAL** | **64** | **881** | **469** | **10** | **29** | **22** | **3** | |
**Horas brutas 881 h · horas netas 773 h** (descontando los 3 paquetes agregadores: AutoCAD Completo 36 h, SolidWorks Completo 36 h, Excel Completo 36 h).

Orden de presentación: técnico-industrial primero, habilidades blandas al final. Soft Skills concentra 16 de los 64 cursos pero solo 121 de las 881 horas y ningún curso N3 o N4 — es amplitud, no profundidad, y así debe leerse en la ruta.

---

## 5. Catálogo maestro — Cursos (64)

Ordenados por nivel descendente dentro de cada especialidad: el primer curso de cada bloque es el ancla de la especialidad.

**Leyenda de banderas**

| Bandera | Significado |
|---|---|
| `PAQUETE` | Agrega otros cursos. Nunca coexiste con sus componentes |
| `EXC-LIDERAZGO` | Grupo de exclusión mutua. Solo uno por ruta |
| `revisar_traslape` | Traslape sospechado, sin confirmar. No se aplica exclusión automática |
| `dc3_validar` | Duración contradictoria entre fuentes. Verificar antes de emitir constancia |
| `no_ruta` | `apto_para_ruta = false`. Visible en membresía, excluido de rutas |
| `nombre_alterno` | El sitio lo nombra de dos formas |
| `esp_no_standalone` | Su especialidad no se presenta sola |
| `ficha_completa` | Capa 2 poblada — único caso |

### Lean Manufacturing — 11 cursos · 150 h · 70 lecciones

| ID | Slug | Curso | Lec | Horas | h/lec | C1·C2·C3·C4 | Índice | Nivel | Banderas |
|---|---|---|---|---|---|---|---|---|---|
| 01 | `lean-manufacturing` | **Lean Manufacturing** | 12 | 40 | 3.33 | 4·4·2·4 | 3.5 | **N4** | revisar_traslape |
| 02 | `toyota-production-system` | **Toyota Production System** | 8 | 16 | 2.0 | 2·3·2·4 | 2.75 | **N3** | — |
| 03 | `tpm-mantenimiento-total-productivo` | **TPM – Mantenimiento Total Productivo** | 8 | 16 | 2.0 | 2·3·2·3 | 2.5 | **N3** | — |
| 04 | `value-stream-mapping` | **Value Stream Mapping** | 6 | 12 | 2.0 | 2·3·2·4 | 2.75 | **N3** | — |
| 05 | `metodologia-kanban` | **Metodología Kanban** | 5 | 10 | 2.0 | 2·3·2·3 | 2.5 | **N3** | — |
| 06 | `lean-thinking` | **Lean Thinking** | 8 | 16 | 2.0 | 2·3·1·3 | 2.25 | **N2** | — |
| 07 | `smed` | **SMED** | 6 | 12 | 2.0 | 2·3·2·2 | 2.25 | **N2** | — |
| 08 | `los-8-desperdicios` | **Los 8 Desperdicios** | 5 | 8 | 1.6 | 1·2·1·2 | 1.5 | **N2** | — |
| 09 | `trabajo-estandar` | **Trabajo Estándar** | 4 | 8 | 2.0 | 1·3·2·2 | 2.0 | **N2** | — |
| 10 | `tiempo-de-takt-vs-tiempo-de-ciclo` | **Balanceo de Líneas** | 4 | 6 | 1.5 | 1·2·2·3 | 2.0 | **N2** | nombre_alterno |
| 11 | `historia-de-la-economia-y-manufactura` | **Historia de la Economía y Manufactura** | 4 | 6 | 1.5 | 1·2·1·1 | 1.25 | **N1** | — |

### Six Sigma — 5 cursos · 128 h · 47 lecciones

| ID | Slug | Curso | Lec | Horas | h/lec | C1·C2·C3·C4 | Índice | Nivel | Banderas |
|---|---|---|---|---|---|---|---|---|---|
| 12 | `green-belt-six-sigma` | **Green Belt – Six Sigma** | 14 | 40 | 2.86 | 4·4·4·3 | 3.75 | **N4** | ficha_completa |
| 13 | `black-belt-six-sigma` | **Black Belt – Six Sigma** | 10 | 40 | 4.0 | 4·4·4·4 | 4.0 | **N4** | — |
| 14 | `yellow-belt-six-sigma` | **Yellow Belt – Six Sigma** | 10 | 24 | 2.4 | 3·3·3·2 | 2.75 | **N3** | — |
| 15 | `dmaic` | **DMAIC** | 8 | 16 | 2.0 | 2·3·2·3 | 2.5 | **N3** | revisar_traslape |
| 16 | `white-belt-six-sigma` | **White Belt – Six Sigma** | 5 | 8 | 1.6 | 1·2·1·1 | 1.25 | **N1** | — |

### Ingeniería de Calidad — 4 cursos · 42 h · 24 lecciones

| ID | Slug | Curso | Lec | Horas | h/lec | C1·C2·C3·C4 | Índice | Nivel | Banderas |
|---|---|---|---|---|---|---|---|---|---|
| 17 | `7-herramientas-de-la-calidad` | **7 Herramientas de la Calidad** | 8 | 16 | 2.0 | 2·3·3·3 | 2.75 | **N3** | — |
| 18 | `metodologia-scrum` | **Metodología SCRUM** | 7 | 12 | 1.71 | 2·2·2·3 | 2.25 | **N2** | — |
| 19 | `poka-yoke` | **Poka Yoke** | 5 | 8 | 1.6 | 1·2·2·2 | 1.75 | **N2** | — |
| 20 | `sistema-andon` | **Sistema Andon** | 4 | 6 | 1.5 | 1·2·2·2 | 1.75 | **N2** | — |

### Kaizen y Mejora Continua — 4 cursos · 38 h · 22 lecciones

| ID | Slug | Curso | Lec | Horas | h/lec | C1·C2·C3·C4 | Índice | Nivel | Banderas |
|---|---|---|---|---|---|---|---|---|---|
| 21 | `kaizen-mejora-continua` | **Kaizen – Mejora Continua** | 8 | 16 | 2.0 | 2·3·2·3 | 2.5 | **N3** | dc3_validar · revisar_traslape |
| 22 | `metodologia-5-s` | **Metodología 5'S** | 5 | 8 | 1.6 | 1·2·1·2 | 1.5 | **N2** | — |
| 23 | `oee` | **OEE** | 5 | 8 | 1.6 | 1·2·2·3 | 2.0 | **N2** | — |
| 24 | `foda` | **FODA** | 4 | 6 | 1.5 | 1·2·1·4 | 2.0 | **N2** | — |

### Solución de Problemas — 3 cursos · 40 h · 19 lecciones

| ID | Slug | Curso | Lec | Horas | h/lec | C1·C2·C3·C4 | Índice | Nivel | Banderas |
|---|---|---|---|---|---|---|---|---|---|
| 25 | `hoshin-kanri-planeacion-estrategica` | **Hoshin Kanri – Planeación Estratégica** | 7 | 16 | 2.29 | 2·3·2·4 | 2.75 | **N3** | — |
| 26 | `solucion-de-problemas-8-d-s` | **Solución de Problemas 8 D's** | 6 | 12 | 2.0 | 2·3·2·3 | 2.5 | **N3** | — |
| 27 | `solucion-de-problemas-para-ingenieros` | **Solución de Problemas para Ingenieros** | 6 | 12 | 2.0 | 2·3·3·3 | 2.75 | **N3** | — |

### Core Tools — 4 cursos · 46 h · 26 lecciones

| ID | Slug | Curso | Lec | Horas | h/lec | C1·C2·C3·C4 | Índice | Nivel | Banderas |
|---|---|---|---|---|---|---|---|---|---|
| 28 | `control-estadistico-de-procesos-spc` | **Control Estadístico de Procesos (SPC)** | 8 | 16 | 2.0 | 2·3·4·3 | 3.0 | **N3** | — |
| 29 | `amef` | **AMEF** | 7 | 12 | 1.71 | 2·2·3·3 | 2.5 | **N3** | nombre_alterno |
| 30 | `ppap` | **PPAP** | 6 | 10 | 1.67 | 2·2·3·4 | 2.75 | **N3** | — |
| 31 | `plan-de-control` | **Plan de Control** | 5 | 8 | 1.6 | 1·2·3·3 | 2.25 | **N2** | — |

### Normas ISO — 2 cursos · 36 h · 17 lecciones

| ID | Slug | Curso | Lec | Horas | h/lec | C1·C2·C3·C4 | Índice | Nivel | Banderas |
|---|---|---|---|---|---|---|---|---|---|
| 32 | `iso-9001-2015-auditor-interno` | **ISO 9001:2015 Auditor Interno** | 9 | 20 | 2.22 | 3·3·3·4 | 3.25 | **N3** | — |
| 33 | `iso-19011-2018-auditorias-de-sistemas-de-gestion` | **ISO 19011:2018 Auditorías de Sistemas de Gestión** | 8 | 16 | 2.0 | 2·3·3·4 | 3.0 | **N3** | — |

### Cadena de Suministros — 1 cursos · 12 h · 7 lecciones

| ID | Slug | Curso | Lec | Horas | h/lec | C1·C2·C3·C4 | Índice | Nivel | Banderas |
|---|---|---|---|---|---|---|---|---|---|
| 34 | `gestion-de-inventarios` | **Gestión de Inventarios** | 7 | 12 | 1.71 | 2·2·3·4 | 2.75 | **N3** | dc3_validar · esp_no_standalone |

### Diseño Industrial — 8 cursos · 168 h · 88 lecciones

| ID | Slug | Curso | Lec | Horas | h/lec | C1·C2·C3·C4 | Índice | Nivel | Banderas |
|---|---|---|---|---|---|---|---|---|---|
| 35 | `autocad-completo` | **AutoCAD Completo** | 18 | 36 | 2.0 | 4·3·3·2 | 3.0 | **N3** | PAQUETE |
| 36 | `solidworks-completo` | **SolidWorks Completo** | 16 | 36 | 2.25 | 4·3·3·2 | 3.0 | **N3** | PAQUETE |
| 37 | `solidworks-avanzado` | **SolidWorks Avanzado** | 9 | 16 | 1.78 | 2·2·4·2 | 2.5 | **N3** | — |
| 38 | `autocad-basico` | **AutoCAD Básico** | 9 | 16 | 1.78 | 2·2·3·1 | 2.0 | **N2** | — |
| 39 | `autocad-intermedio` | **AutoCAD Intermedio** | 9 | 16 | 1.78 | 2·2·3·1 | 2.0 | **N2** | — |
| 40 | `autocad-avanzado` | **AutoCAD Avanzado** | 9 | 16 | 1.78 | 2·2·3·2 | 2.25 | **N2** | — |
| 41 | `solidworks-basico` | **SolidWorks Básico** | 9 | 16 | 1.78 | 2·2·3·1 | 2.0 | **N2** | — |
| 42 | `solidworks-intermedio` | **SolidWorks Intermedio** | 9 | 16 | 1.78 | 2·2·3·1 | 2.0 | **N2** | — |

### Microsoft Excel — 4 cursos · 72 h · 45 lecciones

| ID | Slug | Curso | Lec | Horas | h/lec | C1·C2·C3·C4 | Índice | Nivel | Banderas |
|---|---|---|---|---|---|---|---|---|---|
| 43 | `microsoft-excel-completo` | **Microsoft Excel Completo** | 18 | 36 | 2.0 | 4·3·3·2 | 3.0 | **N3** | PAQUETE |
| 44 | `microsoft-excel-basico` | **Microsoft Excel Básico** | 8 | 12 | 1.5 | 2·2·1·1 | 1.5 | **N2** | — |
| 45 | `microsoft-excel-intermedio` | **Microsoft Excel Intermedio** | 9 | 12 | 1.33 | 2·2·3·1 | 2.0 | **N2** | — |
| 46 | `microsoft-excel-avanzado` | **Microsoft Excel Avanzado** | 10 | 12 | 1.2 | 2·2·3·2 | 2.25 | **N2** | — |

### Análisis de Datos — 2 cursos · 28 h · 17 lecciones

| ID | Slug | Curso | Lec | Horas | h/lec | C1·C2·C3·C4 | Índice | Nivel | Banderas |
|---|---|---|---|---|---|---|---|---|---|
| 47 | `power-bi` | **Power BI** | 8 | 12 | 1.5 | 2·2·3·3 | 2.5 | **N3** | — |
| 48 | `sql-server` | **SQL Server** | 9 | 16 | 1.78 | 2·2·3·2 | 2.25 | **N2** | — |

### Soft Skills — 16 cursos · 121 h · 87 lecciones

| ID | Slug | Curso | Lec | Horas | h/lec | C1·C2·C3·C4 | Índice | Nivel | Banderas |
|---|---|---|---|---|---|---|---|---|---|
| 49 | `liderazgo-para-ingenieros` | **Liderazgo para Ingenieros** | 9 | 16 | 1.78 | 2·2·1·3 | 2.0 | **N2** | EXC-LIDERAZGO |
| 50 | `experto-en-ventas` | **Experto en Ventas** | 8 | 16 | 2.0 | 2·3·1·2 | 2.0 | **N2** | — |
| 51 | `liderazgo-para-lideres-y-supervisores` | **Liderazgo para Líderes y Supervisores** | 7 | 12 | 1.71 | 2·2·1·3 | 2.0 | **N2** | EXC-LIDERAZGO |
| 52 | `comunicacion-efectiva` | **Comunicación Efectiva** | 7 | 12 | 1.71 | 2·2·1·2 | 1.75 | **N2** | — |
| 53 | `manejo-del-personal` | **Manejo del Personal** | 6 | 10 | 1.67 | 2·2·1·3 | 2.0 | **N2** | — |
| 54 | `inteligencia-emocional` | **Inteligencia Emocional** | 6 | 10 | 1.67 | 2·2·1·1 | 1.5 | **N2** | — |
| 55 | `integracion-y-trabajo-en-equipo` | **Integración y Trabajo en Equipo** | 5 | 8 | 1.6 | 1·2·1·2 | 1.5 | **N2** | — |
| 56 | `nom-035` | **NOM-035** | 5 | 8 | 1.6 | 1·2·2·4 | 2.25 | **N2** | — |
| 57 | `?`&nbsp;pqt-contraten | **PQT Contraten** | 12 | 9 | 0.75 | 2·1·1·1 | 1.25 | **N1** | no_ruta |
| 58 | `el-arte-de-la-persuasion` | **El Arte de la Persuasión** | 5 | 8 | 1.6 | 1·2·1·1 | 1.25 | **N1** | — |
| 59 | `?`&nbsp;como-venderte-a-las-empresas | **Cómo venderte a las empresas** | 5 | 3 | 0.6 | 1·1·1·1 | 1.0 | **N1** | no_ruta |
| 60 | `?`&nbsp;curriculum-vitae-de-alto-impacto | **Curriculum Vitae de Alto Impacto** | 5 | 3 | 0.6 | 1·1·1·1 | 1.0 | **N1** | no_ruta |
| 61 | `?`&nbsp;como-hablar-en-una-entrevista-de-trabajo | **Cómo hablar en una entrevista de trabajo** | 4 | 3 | 0.75 | 1·1·1·1 | 1.0 | **N1** | no_ruta |
| 62 | `?`&nbsp;mente-millonaria | **Mente Millonaria** | 1 | 1 | 1.0 | 1·1·1·1 | 1.0 | **N1** | no_ruta |
| 63 | `?`&nbsp;liderazgo-efectivo | **Liderazgo Efectivo** | 1 | 1 | 1.0 | 1·1·1·1 | 1.0 | **N1** | no_ruta · EXC-LIDERAZGO |
| 64 | `?`&nbsp;estrategias-de-negociacion | **Estrategias de Negociación** | 1 | 1 | 1.0 | 1·1·1·1 | 1.0 | **N1** | no_ruta |
---

## 6. Descripciones cortas — literal del sitio (64/64)

Este es el texto que el PDF puede imprimir junto a cada curso sin riesgo: viene textual del sitio oficial.
| ID | Curso | Descripción corta (literal del sitio) |
|---|---|---|
| 01 | Lean Manufacturing | Elimina desperdicios con el sistema de producción Toyota |
| 02 | Toyota Production System | Los pilares del sistema de producción más eficiente del mundo |
| 03 | TPM – Mantenimiento Total Productivo | Maximiza la disponibilidad y vida útil de tus equipos |
| 04 | Value Stream Mapping | Mapea el flujo de valor y detecta cuellos de botella |
| 05 | Metodología Kanban | Controla el flujo de trabajo con señales visuales |
| 06 | Lean Thinking | Adopta la mentalidad de mejora y flujo de valor |
| 07 | SMED | Cambios rápidos de herramental para flexibilizar la producción |
| 08 | Los 8 Desperdicios | Identifica y elimina las mudas de tu proceso |
| 09 | Trabajo Estándar | Estandariza operaciones para asegurar calidad y ritmo |
| 10 | Balanceo de Líneas | Balancea la producción al ritmo de la demanda con Takt Time y tiempo de ciclo |
| 11 | Historia de la Economía y Manufactura | Contexto y evolución de los sistemas productivos |
| 12 | Green Belt – Six Sigma | Lidera proyectos DMAIC y reduce la variación |
| 13 | Black Belt – Six Sigma | Dirige proyectos DMAIC complejos y domina la estadística avanzada |
| 14 | Yellow Belt – Six Sigma | Apoya proyectos de mejora con herramientas estadísticas |
| 15 | DMAIC | El ciclo Definir-Medir-Analizar-Mejorar-Controlar |
| 16 | White Belt – Six Sigma | Fundamentos de la metodología Six Sigma |
| 17 | 7 Herramientas de la Calidad | Analiza y resuelve problemas de calidad con datos |
| 18 | Metodología SCRUM | Gestiona proyectos ágiles por sprints |
| 19 | Poka Yoke | Diseña sistemas a prueba de errores |
| 20 | Sistema Andon | Gestión visual de anomalías en tiempo real |
| 21 | Kaizen – Mejora Continua | Implementa mejoras incrementales día a día |
| 22 | Metodología 5'S | Organiza y estandariza tu lugar de trabajo |
| 23 | OEE | Mide la eficiencia global de tus equipos |
| 24 | FODA | Diagnostica fortalezas, debilidades y oportunidades |
| 25 | Hoshin Kanri – Planeación Estratégica | Despliega objetivos estratégicos a toda la organización |
| 26 | Solución de Problemas 8 D's | Resuelve problemas de raíz con el método 8D |
| 27 | Solución de Problemas para Ingenieros | Metodologías analíticas para causa raíz |
| 28 | Control Estadístico de Procesos (SPC) | Controla la variación con gráficos de control |
| 29 | AMEF | Analiza modos y efectos de falla potenciales |
| 30 | PPAP | Proceso de aprobación de partes para producción |
| 31 | Plan de Control | Documenta y asegura el control del proceso |
| 32 | ISO 9001:2015 Auditor Interno | Planea y ejecuta auditorías internas de calidad |
| 33 | ISO 19011:2018 Auditorías de Sistemas de Gestión | Directrices para auditar sistemas de gestión |
| 34 | Gestión de Inventarios | Optimiza niveles de stock y rotación |
| 35 | AutoCAD Completo | Diseño 2D y 3D de principio a fin |
| 36 | SolidWorks Completo | De básico a avanzado: modelado, ensambles y planos |
| 37 | SolidWorks Avanzado | Superficies, chapa metálica y simulación |
| 38 | AutoCAD Básico | Dibujo 2D para ingeniería |
| 39 | AutoCAD Intermedio | Bloques, capas y acotación profesional |
| 40 | AutoCAD Avanzado | Modelado 3D y presentación de planos |
| 41 | SolidWorks Básico | Modelado 3D de piezas desde cero |
| 42 | SolidWorks Intermedio | Ensambles y relaciones de posición |
| 43 | Microsoft Excel Completo | De básico a macros y dashboards |
| 44 | Microsoft Excel Básico | Hojas, fórmulas y formato esencial |
| 45 | Microsoft Excel Intermedio | Funciones lógicas, búsqueda y gráficos |
| 46 | Microsoft Excel Avanzado | Tablas dinámicas, macros y automatización |
| 47 | Power BI | Dashboards ejecutivos y análisis para planta |
| 48 | SQL Server | Consulta y administra bases de datos relacionales |
| 49 | Liderazgo para Ingenieros | Lleva tu perfil técnico a roles de liderazgo |
| 50 | Experto en Ventas | Técnicas de venta consultiva y cierre |
| 51 | Liderazgo para Líderes y Supervisores | Dirige equipos de planta con autoridad y empatía |
| 52 | Comunicación Efectiva | Transmite ideas con claridad e impacto |
| 53 | Manejo del Personal | Coordina, motiva y desarrolla a tu gente |
| 54 | Inteligencia Emocional | Gestiona emociones para decidir y colaborar mejor |
| 55 | Integración y Trabajo en Equipo | Construye equipos cohesionados y productivos |
| 56 | NOM-035 | Identifica y previene factores de riesgo psicosocial |
| 57 | PQT Contraten | Consigue el trabajo que quieres, de principio a fin |
| 58 | El Arte de la Persuasión | Influye y convence con argumentos sólidos |
| 59 | Cómo venderte a las empresas | Muestra lo que las empresas de verdad contratan |
| 60 | Curriculum Vitae de Alto Impacto | Un CV que llega a la entrevista, no al archivo |
| 61 | Cómo hablar en una entrevista de trabajo | Llega preparado a la pregunta que descarta candidatos |
| 62 | Mente Millonaria | Cambia tu forma de pensar el dinero |
| 63 | Liderazgo Efectivo | Dirige con claridad y gana el respeto de tu equipo |
| 64 | Estrategias de Negociación | Cierra acuerdos sin ceder de más |
---

## 7. Reglas de negocio codificadas

### 7.1 `curso_paquete` — 3 paquetes confirmados

| Paquete | Horas | Componentes | Confirmado |
|---|---|---|---|
| AutoCAD Completo | 36 | AutoCAD Básico (16) + Intermedio (16) + Avanzado (16) | ✅ |
| SolidWorks Completo | 36 | SolidWorks Básico (16) + Intermedio (16) + Avanzado (16) | ✅ |
| Microsoft Excel Completo | 36 | Excel Básico (12) + Intermedio (12) + Avanzado (12) | ✅ |

Nota aritmética: en Excel, 12+12+12 = 36 y el paquete declara 36 h — la suma cierra exacta. En AutoCAD y SolidWorks los componentes suman 48 h y el paquete declara 36 h; hay una compresión de 12 h que el sitio no explica. **Al construir la ruta se usa el valor del paquete (36 h), nunca la suma de componentes.**

### 7.2 `curso_paquete` — 2 sospechas abiertas, sin aplicar

| Paquete sospechado | Componentes probables | Estado |
|---|---|---|
| Lean Manufacturing (40 h) | TPS, TPM, Kanban, VSM, 8 Desperdicios, SMED, Trabajo Estándar, Kaizen | ⚠️ INC-10 — requiere tu confirmación |
| Kaizen – Mejora Continua (16 h) | Metodología 5'S, OEE | ⚠️ INC-11 — requiere tu confirmación |

Mientras no se confirmen, los cursos entran a rutas normalmente con `revisar_traslape = true`. **No se inventó una relación de paquete que el sitio no declara.**

### 7.3 `reglas_exclusion`

| Grupo | Cursos | Motivo |
|---|---|---|
| `EXC-LIDERAZGO` | Liderazgo para Ingenieros · Liderazgo para Líderes y Supervisores · Liderazgo Efectivo | Traslape de contenido. Dos en una ruta se leen como relleno |
| `EXC-AUTOCAD` | AutoCAD Completo ↔ Básico/Intermedio/Avanzado | Doble conteo de horas |
| `EXC-SOLIDWORKS` | SolidWorks Completo ↔ Básico/Intermedio/Avanzado | Doble conteo de horas |
| `EXC-EXCEL` | Excel Completo ↔ Básico/Intermedio/Avanzado | Doble conteo de horas |
| `EXC-DMAIC` | DMAIC ↔ Green Belt · Black Belt | DMAIC es el ciclo que ambos belts desarrollan íntegro |

### 7.4 `prerequisitos`

| Cadena | Tipo |
|---|---|
| Excel Básico → Intermedio → Avanzado | `duro` |
| AutoCAD Básico → Intermedio → Avanzado | `duro` |
| SolidWorks Básico → Intermedio → Avanzado | `duro` |
| White Belt → Yellow Belt → Green Belt → Black Belt | `duro` |
| 7 Herramientas de la Calidad → SPC | `recomendado` |
| Los 8 Desperdicios → Lean Manufacturing | `recomendado` |
| Metodología 5'S → Kaizen – Mejora Continua | `recomendado` |

Solo se declararon cadenas evidentes por nomenclatura o por dependencia metodológica reconocida. No se fabricaron prerrequisitos "razonables".

### 7.5 Restricciones de lenguaje del PDF

| Regla | Aplica a | Prohibición |
|---|---|---|
| `R-01` | Core Tools | No afirmar "Core Tools completo". Faltan APQP y MSA |
| `R-02` | Cadena de Suministros | No presentarla como especialidad complementaria autónoma. Tiene 1 curso |
| `R-03` | Todo el catálogo | No imprimir conteos duros de cursos. El sitio se contradice (INC-01) |
| `R-04` | Todo el catálogo | No usar "lecciones" en texto de cara al lead. Usar horas (INC-05) |
| `R-05` | Equivalencias de precio | Solo válidas si la acreditación es equivalente. Verificar antes de comparar contra competencia |

---

## 8. Datos institucionales verificados

Estos datos cierran dos bloqueos que estaban abiertos en la ruta crítica del proyecto.

### 8.1 Credencial emitida — **BLOQUEO RESUELTO**

Al concluir un curso, DP SyS emite:

| Documento | Emisor | Detalle |
|---|---|---|
| Constancia **DC-3** | STPS | Conforme a lineamientos de la Secretaría del Trabajo y Previsión Social |
| **Diploma** con código verificable | DP SyS | Verificable en línea por RRHH |
| **QR** de validez | Red CONOCER · SEP | Sello de estándar SEP |
| Certificación internacional | CSSC · IAS | Opcional, solo en cursos aplicables |

Emisión: **3 a 5 días hábiles** tras terminar el curso y entregar el proyecto final. Los 64 cursos emiten DC-3.

### 8.2 Modalidad — **BLOQUEO RESUELTO**

**Autoritmo, no calendarizado.** Clases grabadas, acceso 24/7 multidispositivo, acceso de por vida en compra individual, grupo exclusivo con instructores para dudas y proyectos. Solo la modalidad empresarial admite sesiones en vivo o presenciales.

**Consecuencia para el sistema:** la ruta anual es una **recomendación de secuencia**, no un compromiso operativo con fechas. La plantilla del PDF debe decir "mes 1 sugerido", no "inicia el 3 de marzo".

### 8.3 Avales y acreditaciones (6)

| Siglas | Organismo | Tipo |
|---|---|---|
| STPS | Secretaría del Trabajo y Previsión Social | Constancia DC-3 |
| CSSC | Council for Six Sigma Certification | Certificación Six Sigma |
| CertiProf | CertiProf | Authorized Partner |
| CertiCorp | CertiCorp | Aval académico |
| IAS | International Accreditation Service | Acreditación internacional |
| Red CONOCER · SEP | Secretaría de Educación Pública | Estándar SEP |

### 8.4 Planes vigentes

| Plan | Periodicidad | Precio | Alcance |
|---|---|---|---|
| Mensual | Mensual | $999 MXN | Todo el catálogo, cancela cuando quieras |
| **Expert** | Anual | **$4,199 MXN** | Todo el catálogo · equivale a $350/mes · ahorro de $7,789 frente al mensual |
| Expert Duo | Anual | $5,999 MXN | Todo el catálogo, 2 estudiantes |
| Business | Cotización | — | Programas presenciales en sitio, reportes, facturación centralizada |

Precios en MXN, IVA incluido. Garantía de satisfacción de 30 días con reembolso del 100 %.

**Ancla de precio para el PDF:** un curso individual como Green Belt cuesta $3,799 MXN. La membresía anual completa cuesta $4,199. La ruta de 12 meses vale más que la diferencia — ése es el argumento, y es aritméticamente cierto.

### 8.5 Instructores

| Nombre | Rol | Perfil |
|---|---|---|
| Ing. Rosbel Salinas | CEO y Cofundador · Doctorante en Ingeniería | Ingeniero Industrial con MBA, especializado en Manufactura Esbelta (U. de Ibagué) e Investigación de Operaciones (UNAM). +10 años en proyectos de ingeniería, conferencista internacional en más de 8 países |
| Ing. Yussef Salomón | CEO y Cofundador · Doctorante en Empresas Sostenibles | Ingeniero Industrial con MBA, +10 años de experiencia. Conferencista internacional, delegado de México ante la ONU y representante en cumbres del G20 |

### 8.6 Prueba social utilizable

- **5.0** de calificación con **105 opiniones** en Google
- **+80,000** ingenieros capacitados · **15+** países *(cifra en disputa, ver INC-14 e INC-15)*
- Empresas cliente: Pfizer, Hospital Santander, COPARMEX, APTIV, Nidec, LG, index, C&D Technologies, Trostel, IEnova, PEMEX, AMETEK, Panasonic, Caterpillar, Baxter
- Sede: Reynosa, Tamaulipas, México

---

## 9. Estado de completitud, campo por campo

| Campo | Cobertura | Procedencia | Listo para el PDF |
|---|---|---|---|
| `nombre` | 64/64 | `[SITIO]` | ✅ |
| `especialidad_id` | 64/64 | `[SITIO]` | ✅ |
| `descripcion_corta` | 64/64 | `[SITIO]` | ✅ |
| `horas` | 64/64 | `[SITIO]` | ✅ (2 con `dc3_validar`) |
| `lecciones` | 64/64 | `[SITIO]` | ⚠️ uso interno, no imprimir |
| `slug` | 57/64 | `[SITIO]` | ✅ |
| `nivel_complejidad` | 64/64 | `[ANÁLISIS]` | ✅ criterio de armado |
| `es_paquete` | 3 confirmados | `[ANÁLISIS]` | ✅ |
| `reglas_exclusion` | 5 grupos | `[ANÁLISIS]` | ✅ |
| `prerequisitos` | 7 cadenas | `[ANÁLISIS]` | ✅ |
| `descripcion_larga` | 1/64 | `[PENDIENTE]` | ⛔ |
| `modulos` / `temas` | 1/64 | `[PENDIENTE]` | ⛔ |
| `precio_individual` | 1/64 | `[PENDIENTE]` | ⛔ |
| `frase_resultado` | 0/64 | `[INTERNO]` | ⛔ |

**El sistema es construible hoy.** Las migraciones, los seeders del catálogo, el motor de rutas con sus cinco invariantes y la plantilla del PDF no dependen de ningún campo `[PENDIENTE]`. Los tres campos faltantes enriquecen el documento; no lo bloquean.

---

## 10. Semilla

`cursos.json` acompaña este documento con los 64 registros y sus cuatro criterios desglosados, listos para `CursoSeeder`. `calc.py` recalcula los niveles desde cero: si mañana cambias un umbral de la rúbrica, los 64 se recalculan sin tocar el diccionario a mano.

---

## 11. Lo que necesito de ti para cerrar v1.1

| # | Decisión | Bloquea |
|---|---|---|
| 1 | ¿Qué son los **55 programas**? No existen como entidad en el sitio | Modelar una tercera tabla, si aplica |
| 2 | Lean Manufacturing 40 h: ¿contiene a los otros 8 cursos Lean? | Motor de rutas de la especialidad más vendida |
| 3 | Kaizen 16 h: ¿contiene 5'S y OEE? | Motor de rutas de Kaizen |
| 4 | Kaizen: ¿16 h o 24 h? · Gestión de Inventarios: ¿12 h o 16 h? | Constancia DC-3 |
| 5 | Los 7 micro-cursos: ¿entran a rutas o son bonus de membresía? | Universo del motor: 57 o 64 |
| 6 | Rúbrica: ¿validas los 4 niveles o prefieres 3? | Reclasificación de los 64 |
| 7 | Export del LMS con módulos y temas reales | Capa 2 y recalibración de C2 |

---

*Diccionario de Datos v1.0 · 64 cursos · 12 especialidades · 13 tablas · 5 grupos de exclusión · 7 cadenas de prerrequisito · 17 inconsistencias resueltas provisionalmente. Cerrado y consistente. Ninguna línea fabricada.*

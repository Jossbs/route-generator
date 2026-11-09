# DP SyS — Generador de rutas formativas

Decisiones de arquitectura, requisitos de dominio y convenciones del proyecto.

> **Ubicación:** el proyecto vive en `/home/sebasm/DevProjects/route-generator/`.
> Si alguna configuración apunta a `report-generator`, está desactualizada.

---

## 1. Qué es este proyecto

Una herramienta interna que genera documentos PDF personalizados con una **ruta formativa recomendada** para cada lead de DP SyS (Desarrollo Profesional SyS), empresa que vende cursos.

Hoy los asesores de venta graban audios personalizados recomendando cursos. Eso consume mucho tiempo y no escala. Este sistema sustituye ese audio por un PDF generado automáticamente que cumple dos funciones a la vez: demostrar interés genuino en el desarrollo del lead, y construir valor percibido para la decisión de compra.

**El PDF es el producto.** La interfaz es un medio. Si hay que elegir dónde poner esfuerzo, va en el documento.

### Contexto de negocio

- DP SyS vende **una sola cosa**: suscripción anual de acceso completo a 64 cursos organizados en 12 especializaciones.
- Los leads llegan por WhatsApp desde promociones en redes sociales.
- Se gestionan en el CRM del sistema anfitrión.
- El lead suele expresar interés en **una** especialización. El asesor le presenta el paquete completo.
- Planes publicados: $999 MXN/mes · **$4,199 MXN/año (Expert Anual)** · $5,999 Duo · Business a cotización.
  Estos precios **no se imprimen en el PDF**. Ver sección 4.

### Dos tipos de documento

Difieren en momento, canal y contenido. No son variantes del mismo entregable.

| | **Ruta enfocada** (gancho) | **Ruta anual completa** (cortesía) |
|---|---|---|
| Momento | antes de la venta | al cerrar la membresía |
| Canal | **archivo PDF** que se comparte con el lead; el enlace con token queda como respaldo | **archivo PDF** que se descarga y se envía |
| Trazabilidad | solo si se entrega por el enlace: ahí sí se registra cada apertura | no, ahí termina nuestra visibilidad |
| Composición | especialización principal completa + 3 cursos adicionales de refuerzo | recorrido de los 12 meses sobre el catálogo completo |
| Capas | solo itinerario recomendado | itinerario recomendado **+** inventario de lo que incluye la membresía |
| Datos del lead | los del formulario | nombre completo, ocupación, inicio y fin de vigencia |

El primero demuestra trato personalizado y engancha. El segundo es una cortesía posterior a la compra: el miembro tiene libertad de seguirlo o no.

---

## 2. Stack

Decidido y cerrado. No propongas alternativas salvo que encuentres un bloqueo técnico real; si lo encuentras, exponlo antes de cambiar nada.

| Capa | Tecnología |
|---|---|
| Backend | Laravel |
| Puente front-back | Inertia.js |
| Frontend | React |
| Componentes | shadcn/ui |
| Estilos | Tailwind CSS |
| Base de datos | PostgreSQL |
| Render de PDF | Chromium headless — **Gotenberg** (contenedor Docker), decidido el 2026-09-17 |
| Colas | Driver `database` |

### Descartado explícitamente

No reintroducir: capa API separada, Livewire, Next.js, DomPDF, mPDF, servicios SaaS de documentos (PDFMonkey, DocRaptor, Carbone), Typst, Redis, separación en microservicios.

### Versiones

Fija las versiones mayores vigentes consultando la documentación oficial **al momento de instalar**. No asumas versiones de memoria.

### Infraestructura

Requiere VPS o contenedor por la dependencia de Chromium. El hosting compartido está descartado.

---

## 3. Reglas de arquitectura

Estas no son preferencias de estilo. Son las decisiones que sostienen el mantenimiento del sistema.

**Las reglas del catálogo viven como datos en PostgreSQL, nunca como código.**
Ninguna restricción de negocio puede quedar escrita dentro de una plantilla Blade, un componente React o un condicional suelto en un controlador.

**Los tipos de documento también son datos.**
Tabla `document_types`, no un `enum` en código. Un tercer documento (renovación, reactivación) debe ser un `INSERT`, no un deploy.

**El validador es un intérprete genérico de tipos de regla.**
Ocho tipos interpretados sobre `catalog_rules`. Un traslape nuevo o una especialización incompleta se resuelve insertando una fila.

**El copy del PDF es catálogo, no plantilla.**
Las restricciones 4 y 5 son sobre texto. Si el copy vive dentro de Blade, son inverificables. Vive en `copy_blocks` y el validador escanea el texto ensamblado antes de renderizar.

**Existe una capa de validación con pruebas.**
Flujo obligatorio: el generador propone una ruta → el validador la aprueba o la rechaza → solo entonces se renderiza. Una ruta inválida nunca debe llegar al PDF.
Única excepción, decidida el 2026-09-22: quien cura un embudo puede **ignorar** un hallazgo desde el armador y seguir con la ruta. Se guarda en `funnels.waived_rules` (quién y cuándo), solo alcanza a reglas con `catalog_rules.waivable = true` —las ocho de la sección 4 nacen en falso y nunca se ignoran— y solo cubre los cursos que el embudo curó: un curso que se sume al emitir y caiga en el mismo conflicto vuelve a bloquear. El fallo ignorado se asienta igual en `route_validations`, con `waived` en el detalle. Antes de ignorar, el panel ofrece sugerencias que corrigen la lista con un clic.

**La generación de ruta es un servicio, no lógica de controlador.**
Debe poder invocarse desde un formulario hoy y desde un webhook mañana sin reescribir nada.

**Cada ruta generada se persiste con snapshot en JSONB.**
Si el catálogo cambia en tres meses, el PDF enviado a un lead debe seguir siendo reproducible tal cual.

**El render corre en cola.**
Chromium tarda segundos. El asesor no debe ver una petición colgada.

**Costura preparada para CRM, sin construirla todavía.**
- El lead es entidad propia, con campo `external_id` nullable.
- El formulario acepta parámetros por URL desde el primer día.
- Interfaz `LeadSource` con dos implementaciones posibles: formulario (se escribe ahora) y el CRM (no se escribe todavía).

---

## 4. Restricciones de dominio — críticas

Estas restricciones vienen de auditar el catálogo real. Violarlas produce documentos que **destruyen la credibilidad del asesor frente al lead**. Son bloqueantes, no advertencias.

Cada una corresponde a una fila de `catalog_rules` y a un caso de prueba obligatorio.

1. **Cadena de Suministros contiene un solo curso** (Gestión de Inventarios).
   Conserva su identidad y su encabezado propio: es una especialidad y así se presenta.
   Lo prohibido es que ocupe rol de **ancla** o de **complementaria** en la ruta enfocada, donde quedaría un bloque de un curso junto a otros de 8 o 16. En el itinerario anual sí tiene bloque propio y puede compartir mes con Lean o Kaizen — **cada una bajo su propio título, nunca revueltas**.
   → `SUPPLY_CHAIN_ROLE`

2. **Los tres paquetes "Completo" (Excel, AutoCAD, SolidWorks) contienen a sus propios niveles.**
   El catálogo publica 64 cursos, que equivalen a **55 programas de contenido distintos** (64 − 9 niveles absorbidos). El PDF declara ambas cifras con la contención explícita: "64 cursos · 55 programas". El conteo debe ser correcto y auditable si el lead suma.
   → `MODULE_DOUBLE_COUNT`

3. **Tres cursos de liderazgo se traslapan en contenido**: Liderazgo para Ingenieros, Liderazgo para Líderes y Supervisores, Liderazgo Efectivo.
   Nunca pueden aparecer juntos **como recomendación**. La regla se evalúa sobre la capa de itinerario, no sobre el inventario de la membresía: el documento anual puede listar los tres como incluidos, pero solo recomienda uno, el que corresponda al puesto del lead.
   → `LEADERSHIP_OVERLAP`

4. **Core Tools está incompleto**: tiene AMEF, PPAP, SPC y Plan de Control. **Faltan APQP y MSA**, dos de las cinco herramientas AIAG. Está prohibido generar cualquier texto que prometa "Core Tools completo" a leads de cadena de suministro automotriz.
   → `CORE_TOOLS_PARTIAL`

5. **Sin afirmaciones de equivalencia de certificación.** Las referencias de mercado para certificaciones comparables (por ejemplo Six Sigma Green Belt) van de 4 a 8 veces el precio de DP SyS, pero una comparación de precio solo es válida si la acreditación es equivalente. Usa fraseo de cumplimiento, nunca equivalencia directa.
   El PDF **no imprime precios**: cuantifica horas, programas y constancias DC-3. El precio lo dice el asesor en la conversación, donde puede manejar objeciones. La regla aplica igual a cualquier frase de comparación, lleve cifra o no.
   → `NO_CERT_EQUIVALENCE`

6. **El documento anual exige datos de membresía**: nombre completo, ocupación, fecha de inicio y fecha de fin de vigencia. Sin ellos no se genera.
   → `ANNUAL_REQUIRES_MEMBERSHIP`

7. **Secuencia de niveles.** En el itinerario, Básico nunca cae después de Intermedio o Avanzado.
   → `LEVEL_SEQUENCE`

8. **Sin mezcla de encabezados.** Cada especialización se presenta bajo su propio título. Dos especializaciones pueden compartir un mes, jamás un encabezado.
   → `NO_HEADING_MERGE`

### Nota de persuasión

El clúster de empleabilidad dentro de Soft Skills es el puente de persuasión más fuerte del catálogo para leads motivados por avance profesional: **PQT Contraten, Cómo venderte a las empresas, Curriculum Vitae de Alto Impacto, Cómo hablar en una entrevista de trabajo**. Va etiquetado como `cluster:empleabilidad` y pesa alto en la afinidad por arquetipo.

### Nota de auditoría pendiente

*AutoCAD Completo* declara 18 lecciones / 36 h, pero sus tres niveles suman 27 lecciones / 48 h. La contención es real pero **parcial**, no equivalente. Puede ser condensación o un dato viejo del sitio. Se modela como `containment_type = parcial` con nota. **No lo resuelvas inventando.**

---

## 5. Alcance de la v1

### Dentro

- Formulario estructurado con selects y valores por defecto inteligentes.
- Generación de los dos tipos de documento.
- Los dos documentos entregados como **archivo PDF**, que se descarga solo al generarse. El enlace con token sigue emitiéndose para la ruta enfocada como respaldo —pesa menos que el archivo en el chat y es lo único que avisa si el lead lo abrió— pero ya no es el canal principal.
- Bandeja de rutas generadas: para quién, qué documento, quién lo armó y cuándo. Existe para no volver a generar lo que ya se mandó, y administra lo que ya salió: cada renglón se abre, se corrige y se borra. Decidido el 2026-09-17.
  - **Corregir** vuelve a emitir sobre el mismo renglón —datos del lead o de la membresía, cursos recomendados y embudo consultado; conserva el tipo de documento— y pisa el snapshot anterior. `learning_routes.revisions` cuenta las veces y la bandeja lo muestra donde antes iba el estado, porque el PDF que el lead tiene en su chat puede no ser el último.
  - **Borrar** es suave. Se va lo que está de cara al lead —el renglón, el enlace y el archivo— y se quedan `route_validations` y `usage_events`, que son la prueba de que las restricciones de la sección 4 se evaluaron antes de mandar algo.
  - **Un renglón por lead**, decidido el 2026-09-23: la ruta inicial y la final van lado a lado, y la final que falta se ve como «pendiente» con el botón que la genera prellenada. Ese botón manda el `lead`, y la final se emite sobre ese mismo lead aunque el gancho no tenga teléfono. Filtros: periodo (hoy, ayer, semana, mes, rango), etapa (esperando final / con membresía), asesor y especialidad. Pagina de 15 en 15.
  - El **estado** no va en la bandeja ni como columna ni como filtro: una ruta se emite y se renderiza en el mismo minuto, así que decía lo mismo en todos los renglones. Sigue en la base —lo usan la cola y el enlace público— y la ficha sí lo dice.
- **Guión del audio** que acompaña a la ruta inicial, decidido el 2026-09-22. No tiene captura propia: `AudioScript` lo arma con la misma ruta, se congela en `snapshot.guion` y se muestra en la ficha. Su texto vive en `copy_blocks` con alcance `guion_audio` y pasa por el mismo escaneo de fraseo. No se copia ni se pega en ningún lado: se lee en la ficha de cada ruta inicial (2026-09-23).
  - Actualizado el 2026-11-08 con el texto autorizado: saludo del asesor, conexión con el perfil, duración y constancias de la ruta, acceso anual al catálogo y respuesta «¡Me gustó!» para conocer las facilidades de pago. El audio mantiene una referencia de **45 a 60 segundos**; las cifras se expresan en palabras.
  - **Mensaje escrito:** versión breve autorizada del mismo contenido, con cifras y emojis. `WrittenMessage` usa el nombre y los totales del documento; evita afirmar que el lead contó algo cuando no hay contexto y adapta el audio para estudiantes. Las constancias corresponden al conteo de cursos, mientras que la duración sale de las horas netas y del mismo ritmo que el PDF (18 h/semana).
  - Los textos viven en `copy_blocks` y se congelan al emitir. `RouteCompanions` presenta la versión vigente en las fichas y la bandeja de documentos anteriores usando sus datos congelados, sin modificar el snapshot, su hash ni el archivo PDF histórico.

- **Revisión de la ruta inicial durante la edición (2026-11-08).** Los cambios de cursos, orden y textos se validan por JSON sin guardar ni reemplazar el borrador del asesor. Las horas netas y los programas se recalculan en el servidor con la misma contención de paquetes que el PDF. Las advertencias se muestran por separado y permiten continuar; los bloqueos identifican los cursos involucrados.
  - La emisión interactiva exige una validación final antes de persistir. Un conflicto devuelve errores al editor y conserva textos, selección y orden. Si se estaba corrigiendo una ruta, también conserva el PDF anterior, el enlace, el snapshot y la revisión. Los servicios que registran intentos rechazados siguen disponibles para otros flujos.
  - Cada validación nueva conserva mensaje y gravedad en sus detalles. Las fichas anteriores resuelven las advertencias desde el snapshot y las reglas del catálogo, evitando presentar un aviso como razón de rechazo.
- **Áreas del lead (2026-11-08).** La captura usa categorías funcionales curadas y ordenadas alfabéticamente. Cada categoría declara las especialidades que prioriza; los valores históricos escritos a mano se vuelven a resolver contra ese catálogo al componer, para que las variantes comunes sigan dando recomendaciones específicas sin llenar el selector de cargos y duplicados. Una categoría que se agrega al enum entra automáticamente en el orden.
- Persistencia de cada ruta generada como snapshot JSONB.
- Bitácora de uso: generación, rechazo del validador y apertura del enlace por el lead.

### Fuera

- **Acceso propio.** No hay login ni registro: el módulo se inserta en un sistema que ya tiene el suyo, y una segunda pantalla de acceso le pediría a la misma persona que entre dos veces. La identidad entra por la costura `CurrentAdvisor`, igual que CRM entra por `LeadSource`: interfaz con dos implementaciones, de las que hoy solo se escribe la de desarrollo. Decidido el 2026-09-12.
- Pantallas de cuenta (perfil, contraseña, 2FA, passkeys). Son del sistema anfitrión. El único dato del asesor que este módulo necesita es su WhatsApp —`users.whatsapp`, al que enlaza el cierre del gancho—; mientras el anfitrión no lo administre, lo siembra `DEVELOPMENT_ADVISOR_WHATSAPP`. Sin número el documento no pinta el botón y el detalle de la ruta lo avisa.
- Integración con el CRM (la costura sí, la implementación no).
- Detección automática de arquetipo.
- Interfaz de administración del catálogo.
- Anclas de precio en el documento. Desde el 2026-11-08 la captura de rutas no pide el importe; los valores históricos se conservan en `memberships.price` y nunca entran al snapshot ni al PDF.

El disparo es **manual, iniciado por el asesor**. La decisión es deliberada: el riesgo de adopción pesa más que la complejidad técnica, y construir con los asesores desde el inicio permite iterar antes de automatizar.

---

## 6. Estado de los datos de contenido

### Resuelto (fuente: https://academy.dpsys.com.mx/cursos, consultado 2026-09-08)

La publicación se volvió a cotejar el **2026-11-09**: conserva los **64 cursos y 12 especialidades**, incluido PPAP. Se corrigieron 41 duraciones y los tres cursos antes sin horas publicadas ya son recomendables. El [cotejo de noviembre](CATALOGO_2026_11_09.md) describe la fuente, la versión y la migración; la transcripción de septiembre se conserva como referencia histórica.

- **Credencial**: constancia **DC-3 oficial ante la STPS** más diploma verificable. Publicado por DP SyS, no inferido.
- **Modalidad**: cursos **autogestivos**, acceso 24/7 en línea. La ruta anual se redacta como **recomendación flexible de secuencia**, nunca como compromiso de fechas.
- **Duraciones**: lecciones y horas de los 64 cursos, disponibles.
- **Frases de resultado**: se siembran en estado `borrador` con la descripción corta que ya publica cada curso ("Elimina desperdicios con el sistema de producción Toyota"). Es texto real de DP SyS. Contenido las revisa y pasa a `aprobado`.

### Pendiente — no inventar

- [ ] Aprobación de Contenido sobre las 64 frases de resultado.
- [ ] `Catalogo_DPSyS_v1.xlsx` — 5 hojas (readme, catálogo con etiquetas, diagnóstico de especializaciones, **8 arquetipos con frases puente**, anclas de precio). Al llegar, se carga como `catalog_version` v2 y se reconcilia contra la v1 del sitio.
- [ ] Resolver el desajuste de lecciones de AutoCAD Completo.

Si el trabajo necesita algo de esta lista, usa marcadores explícitos y **avísalo**. No generes contenido plausible para rellenar.

---

## 7. Convenciones de trabajo

- **Español** para nombres de dominio visibles al usuario y para todo el contenido del PDF. Inglés para nombres de clases, métodos y tablas, siguiendo la convención de Laravel.
- **Migraciones y seeders** para el catálogo. No cargues datos a mano.
- **Pruebas obligatorias** en la capa de validación de rutas. Cada restricción de la sección 4 necesita su caso de prueba.
- **Antes de agregar una dependencia**, justifica por qué no alcanza con lo que ya trae Laravel.
- **Ante ambigüedad de alcance, pregunta.** Este proyecto se está construyendo por etapas deliberadamente; adelantarse compromete la calidad.
- **Presenta las decisiones como opciones con recomendación explícita.** El dueño decide negocio y producto; la arquitectura se delega, pero se explica.

---

## 8. Modelo de datos aprobado

Aprobado el 2026-09-08. Las migraciones se escriben a partir de aquí.

### Catálogo

**`catalog_versions`** — `id, label, source ∈ {sitio_web, excel}, published_at, checksum`
v1 = sitio web (64 cursos). v2 = el Excel cuando llegue, con reconciliación registrada.

**`specializations`** (12) — `id, slug, name, description, display_order, completeness_status ∈ {complete, partial}, missing_components JSONB, allowed_roles JSONB, notes`
`allowed_roles` resuelve la restricción 1 como dato: Cadena de Suministros lleva `{ancla: false, complementaria: false, bloque_itinerario: true, inventario: true}`.

**`content_units`** (55) — `id, slug, name, specialization_id`
La unidad real de contenido, la que se cuenta. Los 4 cursos de Excel apuntan a una sola.

**`courses`** (64) — `id, slug, name, specialization_id, content_unit_id, level ∈ {basico, intermedio, avanzado, completo, unico}, lessons_count, duration_hours, outcome_phrase, outcome_phrase_status ∈ {borrador, aprobado}, delivery_mode, credential_type, is_active`

**`course_containments`** — `parent_course_id, child_course_id, containment_type ∈ {equivalente, parcial}, verified_at, notes`

**`course_prerequisites`** — `course_id, requires_course_id, strength ∈ {duro, sugerido}`

**`tags`** — `id, slug, name, type ∈ {tema, cluster, industria, nivel_experiencia}` · **`course_tag`**
v1 siembra `cluster:empleabilidad` (4 cursos) e `industria:automotriz` (Core Tools). El resto llega con el Excel.

### Motor de reglas

**`catalog_rules`** — `id, code, type, severity ∈ {blocking, warning}, applies_to_document_types JSONB, applies_to_roles JSONB, parameters JSONB, message_es, source_note, is_active, effective_from`
**`rule_courses`** `(rule_id, course_id, role)` · **`rule_specializations`** `(rule_id, specialization_id, role)`

Los miembros van en tablas con llave foránea, no dentro del JSONB: borrar un curso no debe dejar una regla apuntando al vacío. El JSONB guarda solo escalares de configuración.

Tipos interpretados: `mutual_exclusion`, `role_restriction`, `distinct_content_count`, `completeness_claim`, `phrasing_ban`, `required_fields`, `sequence_prerequisite`, `presentation_isolation`.

`applies_to_roles` es lo que hace convivir la restricción 3 con el documento anual: la exclusión se evalúa sobre `role = recomendado`, no sobre `role = incluido`.

### Copy

**`copy_blocks`** — `id, slug, scope ∈ {portada, intro_especializacion, cierre, frase_puente}, locale, body, claims JSONB, applies_when JSONB, status ∈ {pendiente, borrador, aprobado}`
**`banned_phrases`** — `id, pattern, scope, reason, suggested_replacement, severity`

Sin tabla `price_anchors` en v1: el PDF no imprime precios. `memberships.price` conserva los importes históricos en el expediente del miembro; las emisiones nuevas desde el formulario dejan ese campo en `null`.

### Arquetipos

**`archetypes`** — `id, slug, name, motivation, bridge_phrase_block_id, is_placeholder`
**`archetype_specialization_affinity`** — `(archetype_id, specialization_id, weight, suggested_role)`
**`archetype_tag_affinity`** — `(archetype_id, tag_id, weight)`

v1 siembra un solo arquetipo `general` con peso alto sobre `cluster:empleabilidad`. Los 8 reales entran con el Excel sin tocar esquema ni código.

### Documentos, leads y rutas

**`document_types`** — `id, slug, name, delivery ∈ {enlace_rastreado, archivo_descargable}, requires_membership, layers JSONB, composition_policy JSONB, requires_tracking`

**`leads`** — `id, name, phone, email, source ∈ {formulario, crm}, external_id NULL, requested_specialization_id, archetype_id, advisor_id, status ∈ {prospecto, miembro}, raw_payload JSONB` · `unique(source, external_id)`

**`memberships`** — `id, lead_id, full_name, occupation, plan, plan_other, price, starts_on, ends_on, created_by`
`plan` guarda un valor del enum `MembershipPlan` —los planes publicados— y `plan_other` lo negociado fuera de esa lista; van separados por la misma razón que `leads.area` y `area_other`. `price` conserva el importe histórico de la membresía: desde el 2026-11-08 el formulario no lo solicita y las correcciones conservan el valor existente. **No viaja al snapshot**, porque el snapshot es lo que alimenta al PDF y el documento no imprime precios (§4.5).

**`learning_routes`** — `id, lead_id, membership_id NULL, parent_route_id NULL, document_type_id, advisor_id, anchor_specialization_id, archetype_id, status ∈ {draft, validated, rejected, rendering, rendered, failed}, catalog_version_id, snapshot JSONB, course_count, content_unit_count, revisions, revised_at NULL, access_token NULL, pdf_path, rendered_at, deleted_at NULL`
`revisions` y `revised_at` son las correcciones: la ruta se rehace sobre su propio renglón y el contador es lo que evita que el snapshot pisado pase inadvertido. `deleted_at` es borrado suave, porque la ruta arrastra la prueba de su validación. `access_token` solo se emite para la ruta enfocada, y una corrección lo conserva: el lead puede tener el enlace desde el primer envío y lo que debe abrir es el documento corregido. `parent_route_id` liga el documento anual al gancho que lo originó, y con los timestamps da la conversión gancho → membresía como consulta, sin evento propio.

**`route_items`** — `route_id, course_id, specialization_id, layer ∈ {itinerario, inventario}, role ∈ {ancla, complementaria, refuerzo, incluido}, month NULL, position, counts_toward_total`

**`route_validations`** — `route_id, rule_code, result ∈ {pass, fail, skip}, details JSONB, evaluated_at`
Es lo que convierte "restricción respetada" en algo auditable después del hecho.

**`usage_events`** — `route_id, advisor_id NULL, event ∈ {generada, rechazada, renderizada, abierta_por_lead}, ip_hash, meta JSONB, created_at`

### Doble representación deliberada

`snapshot` es la verdad histórica reproducible. `route_items` existe para consultar y medir sin abrir JSON. No son redundancia: tienen dueños distintos.

### Por qué no un booleano `counts_toward_total` en `courses`

Es lo primero que uno escribe y falla en el caso real: si marcas *Excel Completo* como no contable y la ruta solo lleva ese curso, cuenta cero. Contar depende de qué más está en la ruta, así que es una relación evaluada contra la ruta, no una bandera del curso.

### Algoritmo de composición

**Ruta enfocada, con embudo** —que es el camino real— **todos** los cursos del embudo forman la ruta principal, en el orden en que Eje Cursos los curó. Son inamovibles: el asesor no puede quitarlos ni reordenarlos, y una petición que llegue recortada se completa antes de emitir. Que el embudo mezcle especialidades no cambia nada: cada una conserva su propio encabezado —lo exige `NO_HEADING_MERGE`— y las tres quedan igual de fijas. Encima, el motor recomienda **tres cursos adicionales de refuerzo**, elegidos por el área, la industria, la ocupación y el objetivo del lead; el asesor puede retirarlos, sustituirlos o reordenarlos, sin pasar de tres. El PDF, el guion y el mensaje escrito usan la misma cantidad. Actualizado el 2026-11-08.

**Ruta enfocada, sin embudo** — la especialización principal completa, más tres cursos de refuerzo seleccionados con el mismo criterio del lead que en el camino con embudo. Cada especialización conserva su propio encabezado.

**Ruta anual** — los 12 meses balanceados por horas, respetando secuencia pedagógica: fundamentos, luego herramientas, certificación al final. El mes 1 arranca con la especialización ancla que el lead pidió, para que sienta continuidad. Debajo, el inventario completo de lo que incluye su membresía.
Desde el 2026-11-08 el PDF final sigue la referencia `Tu_ano_de_formacion_Rosbel_Salinas_DPSys.pdf`: portada de dos paneles con doce meses y resumen de cuatro etapas, mapa mensual, inventario de membresía en dos columnas y cierre «Empieza hoy». Nombre, ocupación, plan, vigencia, horas semanales, cursos, fechas, cifras y folios se calculan para cada cliente. El número de hojas depende del contenido. Los cursos que cruzan meses se marcan como continuación; sus horas se reparten sin redondeos que alteren el total del año.
Si el lead tiene una ruta inicial aprobada, el anual prioriza sus cursos concretos y los refuerzos emitidos antes de ampliar el recorrido por perfil. Los niveles de Excel, AutoCAD y SolidWorks se representan mediante su paquete Completo, sin duplicar horas. La propuesta reconoce al lead por identificador, teléfono o identificador externo sin guardar registros. El mismo criterio liga la emisión a su ruta inicial; las rutas rechazadas o borradas no sirven de origen. Si lo recomendado inicialmente excede la disponibilidad anual, la revisión muestra el bloqueo de capacidad para que el asesor ajuste la secuencia explícitamente.
Desde el 2026-11-08 las nuevas rutas anuales usan un ritmo estándar de **18 horas por semana** (3 horas al día, seis días y uno de descanso). El formulario lo muestra como dato fijo y el servidor rechaza otro valor; el compositor elige los cursos que caben en el año y distribuye sus horas por mes sin rebasar ese ritmo. Si el itinerario requiere menos de la capacidad anual, se reparte parejo entre los doce meses. Los cursos largos pueden abarcar varios meses (`route_items.month_end`) y el PDF los imprime como «meses 3–5» con «continúa» en los intermedios. Los documentos históricos conservan las horas de su snapshot. En la pantalla de los meses el asesor quita, agrega y **ordena** la secuencia; el motor solo decide dónde termina cada mes. Una lista que no cabe en el año la bloquea `ANNUAL_FITS_PACE` (tipo `pace_capacity`, no ignorable).

---

## 9. Siguiente paso

Migraciones, seeders del catálogo real desde el sitio, las 8 reglas sembradas, y la suite de pruebas con un caso por restricción de la sección 4.

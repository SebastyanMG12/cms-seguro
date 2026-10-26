# Estado del Proyecto: Sistema Hospitalario de Urgencias (cms-seguro)

Documento de seguimiento del estado del sistema, fases ejecutadas, decisiones técnicas y pruebas de verificación.

---

## 1. Fase actual
- **Fase:** Fase 5: Módulo de Acceso y Seguimiento del Acompañante (Completada con éxito).
- **Rama:** `feature/fase-5-modulo-acompanante`

---

## 2. Fases cerradas

### Fase 9.0: Verificación inicial y estabilización del 2FA
- Registro de rutas de desafío 2FA (`two-factor.challenge` y `two-factor.challenge.store`) con limitador de tasa `throttle:5,1`.
- Integración del middleware `two-factor` en rutas privadas y en `bootstrap/app.php`.
- Corrección en `TwoFactor::verifyAndGetTimestep`: se asegura que `Google2FA::verifyKeyNewer` reciba un timestamp inicial no nulo (`$lastTimestep ?? 0`) para retornar el timestep entero del código OTP consumido en vez de un booleano `true`, previniendo la reutilización de códigos TOTP dentro de la misma ventana de tiempo.
- Corrección de formato y estilo con Laravel Pint.
- Verificación de migraciones y auditoría de paquetes (`composer audit` y `npm audit`).

### Fase 1C: Cierre del acceso y la gestión de usuarios
- **Servicio de dominio `UserService`**: Centraliza la lógica de negocio con transacciones y bloqueos de fila (`lockForUpdate` compatible con PostgreSQL):
  - Actualización de usuarios (nombre, correo y sincronización de roles).
  - Regla: No se puede quitar el rol `administrador` al último administrador activo.
  - Regla: Un usuario no puede modificar sus propios roles.
  - Suspensión y reactivación obligando motivo (`reason`), con auditoría de eventos `user_suspended` y `user_reactivated`.
  - Inmediata invalidación de sesiones en base de datos al suspender a un usuario.
  - Regla: Un usuario no puede suspenderse a sí mismo ni suspender al último administrador activo.
  - Restablecimiento de 2FA por administrador exigiendo motivo y auditando `two_factor_reset`, limpiando secretos y códigos de recuperación.
  - Borrado lógico (`SoftDeletes`) mediante migración `add_soft_deletes_to_users_table`, eliminando sesiones y auditando `user_deleted`.
  - Regla: Un usuario no puede eliminarse a sí mismo ni eliminar al último administrador activo.
- **Cambio de contraseña por el usuario autenticado**:
  - Valida la contraseña actual (`current_password`) y exige contraseña segura de al menos 12 caracteres (mayúsculas, minúsculas, números y símbolos).
  - Cierra todas las demás sesiones activas del usuario en la tabla `sessions`, manteniendo únicamente la sesión actual.
  - Audita el evento `password_changed`.
- **Content-Security-Policy (CSP)**:
  - Se eliminó el script en línea de `resources/views/auth/login.blade.php` y se trasladó a `resources/js/app.js`.
  - Se implementó la cabecera `Content-Security-Policy` en `SecurityHeaders.php`: `default-src 'self'`, sin `'unsafe-inline'` en scripts, con soporte para Vite en entorno local y estricto en producción.
- **Autorización y Vistas**:
  - `UserPolicy` para autorizar acciones administrativas.
  - Vistas `admin.users.index`, `admin.users.edit` y `security.password` implementadas.

### Fase 2: Recursos físicos y personal
- **Jerarquía física hospitalaria**: Sedes (`sites`), Pisos (`floors`), Áreas (`areas`), Habitaciones (`rooms`), Camillas (`beds`) y Asignaciones (`bed_assignments`).
- **Servicio de dominio `PhysicalResourceService`**:
  - Códigos de camilla autogenerados (ej: `P1-H101-A`) basados en piso, habitación y letra secuencial.
  - Capacidad configurable por habitación; validación atómica que impide excederla.
  - Cálculo de habitación llena (`isFull()`): se computa cuando todas las camillas habilitadas están en estado `ocupada`.
  - Estados de camilla: `disponible`, `ocupada`, `en_limpieza`, `mantenimiento`, `fuera_de_servicio`, `reservada`.
  - Regla de disponibilidad transitiva: una camilla sólo es asignable si su estado es `disponible`, su habitación está `disponible` y su área está `activa`.
  - Regla de protección de pacientes: una habitación o camilla ocupada no puede ponerse fuera de servicio ni en mantenimiento sin reubicar o liberar antes al paciente.
  - Prevención de doble asignación activa a nivel de base de datos con **índice único parcial PostgreSQL**: `UNIQUE (bed_id) WHERE released_at IS NULL`.
- **Personal asistencial y turnos (`StaffService`)**:
  - Códigos de profesional generados, únicos y no secuenciales (ej: `MED-8F3A2B`, `ENF-A1B2C3`).
  - Estado de personal: `activo`, `inactivo`, `en_descanso`. El personal en descanso o inactivo **no es asignable**.
  - Borrado lógico (`SoftDeletes`) para preservar trazabilidad de atenciones históricas.
  - Ciclo de turnos: `programado` -> `activo` -> `finalizado`. Validación que impide cerrar turno si el profesional tiene pacientes asignados pendientes de reasignación.
- **Matriz de roles y permisos**:
  - Documentada en `docs/roles-permisos.md`. Permisos granulares: `sedes.gestionar`, `habitaciones.gestionar`, `camillas.gestionar`, `personal.gestionar`, `turnos.gestionar`, `ocupacion.ver`.
  - Asignados en `RolesAndPermissionsSeeder` preservando separación de funciones.
- **Vistas administrativas y de ocupación**:
  - `occupancy.index`: Tablero del censo hospitalario con métricas consolidadas en tiempo real y desglose jerárquico.
  - `staff.index`: Gestión de plantilla asistencial y programación de turnos.

### Fase 3: Pre-registro público
- **Formulario y reglas de campos de la Sección 10**:
  - Nombre completo (obligatorio, 2 a 150 caracteres).
  - Tipo y número de documento con habilitación condicional (no aplica a "Menor sin identificación", obligatorio en los demás). Formato validado.
  - Teléfono obligatorio (al menos uno entre el paciente y el acompañante).
  - Motivo de urgencia obligatorio (máximo 500 caracteres con contador en vivo).
  - Alergias a medicamentos obligatorias (ninguna, no sé, sí; si es "sí", el detalle es obligatorio y se cifra en base de datos).
  - Bloque de acompañante: obligatorio para menores de edad (`Tarjeta de identidad`, `Registro civil`, `Menor sin identificación`); opcional para adultos salvo que se diligencie algún campo, en cuyo caso nombre, teléfono y parentesco son obligatorios.
  - Exclusión de Triage en la interfaz pública: el paciente no diligencia el nivel de triage; si se inyecta en la petición, se rechaza por validación.
- **Consentimientos de Ley 1581 de 2012 e Inmutabilidad**:
  - Consentimiento de tratamiento de datos obligatorio, versionado (`v1.0`), con marca de tiempo, hash SHA-256 de la IP y agente de usuario.
  - Consentimiento separado e independiente para compartir información con el acompañante (opcional, nunca obligatorio).
  - Modelo `Consent` con protecciones que impiden actualización (`updating`) o eliminación (`deleting`) para cumplir con la custodia legal inmutable.
- **Seguridad, Cifrado y Anti-Enumeración**:
  - Cifrado en aplicación con el cast `encrypted` de Laravel para nombres, números de documento, teléfonos, alergias y notas.
  - Búsqueda mediante **índice ciego HMAC-SHA256** (`document_index`) con clave dedicada (`BLIND_INDEX_KEY`), impidiendo la búsqueda en texto plano y evitando la correlación directa con `APP_KEY`.
  - Principio anti-enumeración: las peticiones con documentos ya existentes en base de datos responden de forma idéntica, asociando el nuevo episodio sin revelar jamás si el paciente ya estaba registrado.
  - Protección con trampa honeypot oculta (`hospital_contact_time`) y limitación de tasa con `throttle:10,1`.
  - Código público de alta entropía (8 caracteres alfanuméricos no ambiguos `[2-9A-HJ-NP-Z]`).
  - Pantalla de éxito con política de mínima exposición: muestra únicamente el código público, vigencia e instrucciones de llegada; nunca datos clínicos ni de salud en la URL o vista.
- **Expiración programada de pre-registros**:
  - Comando `php artisan hospital:expire-preregistrations` programado cada hora en `routes/console.php`.
  - Transiciona a `expirado` aquellos pre-registros con más de 24 horas (configurable en `config/hospital.php`) sin haber sido admitidos, registrando evento de auditoría.

### Fase 4: Módulo médico
- **Máquina de estados clínica (`EpisodeStateMachine`)**:
  - Transición secuencial con bloqueo `lockForUpdate`: `preregistrado` -> `en_espera` (llegada) -> `en_valoracion` (triage I-V) -> `ingresado` (confirmar ingreso) -> `egresado` (cierre médico con tipo: alta médica, remisión, alta voluntaria, abandono, fallecimiento).
  - Validación temporal estricta: `llegada <= ingreso <= egreso`.
  - Restricción física: una camilla solo puede asignarse si el episodio se encuentra en estado `ingresado`.
  - Al registrar el egreso, la camilla asignada se libera automáticamente y transiciona a estado `en_limpieza`.
- **Integridad e inmutabilidad clínica (Resolución 1995 de 1999)**:
  - Modelo `ClinicalEntry` inmutable (bloqueo a nivel de eventos `updating` y `deleting`). Contenido clínico cifrado en base de datos (`casts => encrypted`). Hora fijada por el servidor (`performed_at`).
  - Correcciones auditadas: las aclaraciones y anulaciones exigen referencia obligatoria (`references_entry_id`) y motivo justificado (`reason`), preservando visible el historial clínico completo.
  - Regla de cierre: con episodio en estado `egresado`, se rechaza cualquier nueva entrada ordinaria; únicamente se permite agregar notas aclaratorias posteriores justificadas.
- **Asignación asistencial y cálculo de carga**:
  - Asignación de médico responsable (único) y apoyos de enfermería.
  - Validación de disponibilidad: se impide asignar profesionales con perfil inactivo, en descanso (`en_descanso`) o sin turno activo.
  - Contador dinámico de pacientes activos asignados por profesional (`ClinicalService::countActivePatients`), calculado en tiempo de consulta.
- **Auditoría de lectura y control de custodia (Break-Glass)**:
  - Cada visualización de una historia clínica por el personal tratante asignado audita el evento `historia.lectura`.
  - Si un médico o enfermero no asignado intenta abrir el episodio por URL directa, el sistema interpone una pantalla de control de acceso (*Break-Glass*) exigiendo motivo asistencial justificado (mínimo 10 caracteres) bajo advertencia médico-legal (Ley 1581/2012 y Res. 1995/1999).
  - Al ingresar mediante acceso excepcional, se audita `historia.acceso_excepcional` con la causal peritable, y se bloquean de forma estricta las acciones de modificación clínica (`addClinicalEntry`, `assignBed`, `discharge`) garantizando que solo el profesional tratante asignado o la coordinación médica puedan alterar el expediente.
- **Interfaz de alta densidad y accesibilidad WCAG 2.2 AA**:
  - Tablero de episodios con pestañas filtradas (`mis_pacientes`, `activos`, `en_espera`, `preregistrados`, `egresados`), resaltado del paciente seleccionado en azul (`.selected-patient`).
  - Barra de acciones con habilitación dinámica según el estado del episodio y tooltips explicativos en acciones deshabilitadas; variante de solo lectura para episodios cerrados.

### Fase 4B: Registro de emergencia para paciente NN y Conciliación de identidad
- **Registro de emergencia NN (`IdentityReconciliationService::registerEmergencyNn`)**:
  - Código identificador temporal secuencial diario correlativo: `NN-AAAAMMDD-XXXX` (ej: `NN-20261025-0001`).
  - Datos mínimos de admisión: sexo aparente (`masculino`, `femenino`, `indeterminado`), edad estimada, señas particulares y vestimenta, forma de llegada (`ambulancia`, `policia`, `bomberos`, `transeunte`, `propio_pie`, `otro`), quién lo trajo e identificación/placa de entrega.
  - Regla legal: NO se exige documento, nombres, teléfono ni consentimiento firmado previo.
  - Excepción legal de consentimiento: amparada bajo el Artículo 10 Literal B de la Ley 1581 de 2012 y Decreto 1377 de 2013 ("interés vital, paciente sin capacidad"), creando automáticamente registro de consentimiento inmutable por excepción médica.
  - El episodio ingresa directamente a sala de urgencias en estado `en_espera` con marca de tiempo oficial del servidor (`arrived_at = now()`).
- **Conciliación de identidad (`IdentityReconciliationService::reconcileIdentity`)**:
  - Vinculación formal de la historia clínica con el paciente identificado verificado (sea existente en base de datos o recién identificado con documento físico).
  - Preservación histórica forense: El registro inicial de paciente NN no se elimina; el episodio mantiene su código histórico `nn_code` y la referencia `original_nn_patient_id`.
  - Exigencia estricta de evidencia de verificación: Campo obligatorio `reconciliation_evidence` (mínimo 10 caracteres) para constancia de custodia legal.
  - Transacción atómica con bloqueo `lockForUpdate` y auditoría inmutable del evento `episodio.identidad_conciliada`.
- **Autorización granular y vistas**:
  - Permisos nuevos: `episodios.crear_nn` y `episodios.conciliar`, documentados en `docs/roles-permisos.md`.
  - Vistas accesibles WCAG 2.2 AA: `episodes.nn.create` (con banner de interés vital) y `episodes.reconcile` (Comparison View en dos columnas con buscador asíncrono por documento).

### Fase 5: Módulo de Acceso y Seguimiento del Acompañante
- **Servicio de dominio `CompanionService`**:
  - Emisión de PIN numérico de 6 dígitos con hashing criptográfico (`Hash::make`), trazabilidad de funcionario emisor (`verified_by_user_id`), fecha (`verified_at`) y auditoría `acompanante.pin_generado`.
  - Revocación formal de acceso (`revoked_at`), con auditoría `acompanante.acceso_revocado` y expulsión inmediata de sesiones activas.
  - Control de consentimiento clínico (`compartir_acompanante`): gestión granular del consentimiento del paciente para compartir procedimientos y diagnósticos con su acompañante.
  - Validación de credenciales con protección anti-enumeración (mismo mensaje genérico para código y PIN erróneo).
  - Rate limiting estricto con bloqueo temporal tras 5 intentos fallidos.
  - Caducidad automática de consulta a las 24 horas del egreso del paciente.
- **Minimización de datos (Ley 1581 de 2012 / Res. 1995 de 1999)**:
  - Nombre del paciente enmascarado (`Carlos G.` o código forense `NN-AAAAMMDD-XXXX`).
  - Prohibición absoluta de exhibición de cédula, teléfono, alergias, tipo de sangre y notas subjetivas.
  - Estado operativo mediante badges accesibles (En espera, En valoración, Ingresado, Egresado).
  - Ubicación institucional general y equipo asistencial tratante sin nombres personales individuales.
  - Línea de tiempo vertical con horas exactas de recepción, triaje, ingreso y egreso.
  - Información clínica diagnóstica restringida exclusivamente a pacientes con consentimiento activo; en ausencia de consentimiento, se exhibe el panel informativo legal obligatorio.
- **Vistas y UI (`DISEÑO_FRONTEND.md`)**:
  - `companion.login`: Formulario Mobile-First accesible (touch target >= 44px, tipografía tabular).
  - `companion.status`: Workstation de seguimiento móvil y desktop, línea de tiempo vertical y refresco relativo.
  - `episodes.show`: Tarjeta de credenciales permanente para acompañante (código público y PIN legible/encriptado), badges de estado, origen del PIN (dígitos documento vs generado), y acciones de emisión, revocación y gestión de consentimiento clínico protegidas por roles.

### Fase 6: Administración avanzada, reportes y generación de PDF con marca de agua
- **Generación de Historia Clínica en PDF Oficial (`PdfReportService`, `EpisodePdfController`)**:
  - Formato legal e institucional oficial para el **Hospital de Ubaté** (Resolución 1995 de 1999 y Ley Estatutaria 1581 de 2012).
  - Encabezado institucional superior fijo, numeración continua de páginas en CSS (`counter(page)`), datos de paciente/NN, signos vitales de triaje, ubicación y cama, equipo asistencial asignado (responsable y apoyos), evolución cronológica con diagnósticos, notas, aclaraciones inmutables y anulaciones justificadas.
  - Marca de agua diagonal de seguridad forense (`position: fixed`, opacidad 7%, rotación -35°) generada dinámicamente: `HOSPITAL DE UBATÉ - COPIA OFICIAL | [USUARIO] ([ROL]) | [FECHA HORA] | EPISODIO: [CÓDIGO]`.
  - Generación segura sin evaluación de PHP en plantillas (`enable_php => false` en `dompdf.php`), compatibilidad estricta con UTF-8 y tamaño Carta (Letter).
  - Modal de confirmación legal antes de la descarga en `episodes.show` advirtiendo sobre la reserva legal y trazabilidad de la copia.
  - Auditoría inmutable de descarga: registro obligatorio del evento `episodio.pdf_generado` con metadatos del solicitante, rol, código de episodio e IP.
  - Política de seguridad granular `EmergencyEpisodePolicy::downloadPdf`: permiso requerido `episodios.pdf`, reservado para médicos asignados, personal con justificación Break-Glass activa en sesión, coordinación médica, administradores y auditores.
- **Tablero Operativo y Triage - Resolución 5596 de 2015 (`EmergencyMetricsService`, `EmergencyReportController`)**:
  - Métricas operativas 100% calculadas sobre la base de datos (cero cifras simuladas o ficticias).
  - Tiempos de espera reales por nivel de Triage (I: Inmediato 0m, II: 30m, III: 120m, IV: 180m, V: 240m), contrastando el tiempo transcurrido desde el triaje hasta la primera atención médica o el tiempo en cola para pacientes en espera.
  - Indicadores de pacientes evaluados, en espera actual, tiempo promedio, tiempo máximo y porcentaje de cumplimiento legal de la meta.
  - Censo hospitalario en tiempo real: desglose de camillas (total, ocupadas, disponibles, en mantenimiento, en limpieza y porcentaje de ocupación).
  - Personal asistencial en turno activo (médicos y enfermeros en turno abierto).
  - Selector y filtro por fecha (`?date=YYYY-MM-DD`).
- **Módulo de Auditoría Forense Avanzada (`AuditLogController`, `resources/views/audit/index.blade.php`)**:
  - Filtros multifactoriales por tipo de evento, usuario, rango de fechas (`date_from`, `date_to`) y código público de episodio clínico (`episode_code` sobre metadatos JSON).
  - Pestaña especializada en "Lecturas de Historia Clínica" filtrando eventos de consulta (`episodio.lectura`, `episodio.break_glass`, `episodio.pdf_generado`).
  - Exportación de registros de auditoría a CSV en streaming (`StreamedResponse`) con codificación UTF-8 BOM para compatibilidad con Excel y cabeceras anti-caché.

---

## 3. Decisiones técnicas
- **Estandarización Institucional de Nombre**: Uso estricto de **"Hospital de Ubaté"** (evitando "Hospital El Salvador de Ubaté E.S.E.") en cumplimiento de requerimientos de propiedad intelectual y autorizaciones institucionales previas.
- **Marca de Agua Forense en PDF**: Implementada mediante div fijo CSS repetido en todas las páginas por el motor Dompdf (`position: fixed; top: 35%; left: -15%; transform: rotate(-35deg); opacity: 0.07;`) garantizando el no repudio sin habilitar banderas inseguras como ejecución de código PHP embebido (`enable_php: false`).
- **Cálculo de Tiempos de Triage (Res. 5596/2015)**: El tiempo de espera se computa formalmente entre la marca de tiempo de triaje (`triage_at`) y la marca de tiempo inmutable de la primera evolución médica del episodio (`performed_at`), o contra la hora actual para pacientes que continúan en estado `en_espera`.
- **Arquitectura de Credenciales para Acompañantes**:
  - *Código del Paciente:* Código público alfanumérico único del episodio (ej: `UBT1A2B3`, 8 caracteres en mayúsculas).
  - *PIN de Acceso Predeterminado:* Por defecto son los **últimos 4 dígitos del documento de identidad** del paciente (o los últimos 4 dígitos del identificador `NN-AAAAMMDD-XXXX` si es paciente NN).
  - *PIN Personalizado o Generado:* El personal puede emitir un PIN aleatorio de 6 dígitos o restablecerlo en cualquier momento. Se almacena cifrado con AES-256 (`pin => 'encrypted'`) en la base de datos junto con su hash (`pin_hash`), permitiendo que el médico encargado, personal asignado y administrador lo visualicen de manera permanente en la historia clínica sin perderse tras recargar la pantalla.
  - *Autorización Basada en Roles (`EmergencyEpisodePolicy`):* La gestión del acceso del acompañante (`manageCompanionAccess`) y el otorgamiento/revocación de consentimiento para compartir datos clínicos (`manageCompanionConsent`) están estrictamente restringidos al médico responsable del episodio, equipo asistencial asignado, coordinación médica y administradores; usuarios no asignados son bloqueados con `403 Forbidden`.
- **PostgreSQL Concurrency Lock**: Bloqueo de fila `lockForUpdate` en todas las transiciones de estado clínico, asignación de camillas, turnos, conciliación de identidad y emisión de PINs.
- **Inmutabilidad Médica (Res. 1995/1999)**: Protección en Eloquent (`booted`) y base de datos contra edición o borrado de evoluciones clínicas; el modelo solo admite adición de notas aclaratorias o anulaciones enlazadas.
- **Inmutabilidad de Consentimientos (Ley 1581)**: El modelo `Consent` prohíbe modificaciones o eliminaciones arbitrarias; únicamente admite registrar `revoked_at` de forma atómica para registrar la revocación.
- **Anti-Enumeración en Canal Acompañante**: Respuestas de autenticación idénticas ante código inválido, PIN inválido o acompañante no registrado, impidiendo el escaneo automatizado de episodios.
- **Preservación Forense NN**: Al conciliar identidad, el paciente NN original permanece intacto en la tabla `patients` y el código `NN-AAAAMMDD-XXXX` se conserva permanentemente en `emergency_episodes` para garantizar la trazabilidad forense y médico-legal.
- **Excepción Legal Ley 1581 Art. 10**: Admisión inmediata para salvaguardar la vida sin exigir consentimiento previo a pacientes inconscientes o desorientados, formalizada mediante registro inmutable en `consents`.
- **Control de Custodia Break-Glass**: Barrera de acceso excepcional que impide la consulta pasiva de historias clínicas por profesionales no asignados, exigiendo justificación auditada y bloqueando acciones clínicas en modo consulta.
- **Ciclo de Personal de Apoyo vs. Responsable**: El médico tratante principal no puede ser eliminado dejando el episodio acéfalo (requiere transferencia asignando un nuevo responsable). En contraste, el personal de apoyo (`role = 'apoyo'`) puede ser desactivado/finalizado una vez concluida su labor asistencial puntual (por el propio profesional, el médico responsable o coordinación), liberando su carga activa y registrando el evento inmutable `episodio.apoyo_finalizado`.
- **Contador Dinámico de Carga**: Evita campos desnormalizados propensos a inconsistencias; calcula los episodios abiertos activos directamente sobre `episode_assignments` con `distinct('episode_id')`.
- **Diseño sin Emojis y Accesible**: Reemplazo total de emojis por iconografía lineal Bootstrap Icons local compilada con Vite, tokens de diseño con contraste verificado >= 4.5:1 y objetivos táctiles de 44px.

### Fase 7: Endurecimiento y pruebas de seguridad
- **Auditoría Integral de Rutas y Middlewares**:
  - Restricción estricta de parámetros en todas las rutas con `whereUuid('episode')`, `whereNumber('user')`, `whereNumber('room')`, `whereNumber('bed')`, `whereNumber('profile')`, `whereNumber('shift')`, `whereNumber('assignment')` y `whereAlphaNumeric('code')`.
  - Protección de rutas operativas, administrativas y clínicas mediante el stack incondicional `['auth', 'active', 'two-factor']`.
- **Protección de Caché en Terminales Compartidas (`SecurityHeaders`)**:
  - Inyección automática de `Cache-Control: no-store, no-cache, must-revalidate, max-age=0` y `Pragma: no-cache` para todas las respuestas de usuarios autenticados, impidiendo que historiales clínicos queden retenidos en la memoria del navegador tras cerrar sesión en terminales médicas compartidas.
  - Cabeceras globales endurecidas: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(), microphone=(), geolocation=()`, `Content-Security-Policy` sin `unsafe-inline` en producción y HSTS forzado en producción.
- **Batería de Pruebas de Seguridad (`SecurityHardeningTest`)**:
  - Verificación de bloqueo 419 ante peticiones sin token CSRF válido.
  - Verificación de rate limiting 429 en formularios de contacto, pre-registro y acceso de acompañantes.
  - Verificación de mitigación IDOR y autorización cruzada (rechazo 403 ante intentos de alta médica por médicos no asignados, enfermeros o auditores modificando infraestructura).
  - Verificación de cifrado en reposo en base de datos para `patients`, `episode_clinical_data`, `clinical_entries` y `companions`.
  - Verificación de índice ciego HMAC-SHA256 para búsquedas por documento sin descifrado masivo.
  - Verificación de rechazo 404 ante identificadores con formato malicioso o inyección en parámetros de ruta.
- **Auditoría de Dependencias y Cachés de Producción**:
  - `composer audit`: 0 vulnerabilidades.
  - `npm audit`: 0 vulnerabilidades.
  - `php artisan config:cache`, `route:cache`, `view:cache`: 100% exitosos sin errores de sintaxis.
- **Documento Forense Oficial**: Creación de `docs/seguridad.md` detallando el modelo de amenazas, matriz OWASP Top 10 y medidas aplicadas.

---

## 3. Decisiones técnicas
- **Estandarización Institucional de Nombre**: Uso estricto de **"Hospital de Ubaté"** (evitando "Hospital El Salvador de Ubaté E.S.E.") en cumplimiento de requerimientos de propiedad intelectual y autorizaciones institucionales previas.
- **Protección de Memoria Caché Médica**: Configuración de `no-store` dinámico en `SecurityHeaders` cuando `auth()->check() == true` para mitigar el riesgo de fuga de datos sensibles en estaciones compartidas de urgencias (Ley 1581 / Res. 1995).
- **Restricción Exclusiva de Rutas**: Ningún parámetro de ruta queda abierto a cadenas arbitrarias; se aplican validadores regex `whereUuid`, `whereNumber` y `whereAlphaNumeric` en `routes/web.php`.
- **Marca de Agua Forense en PDF**: Implementada mediante div fijo CSS repetido en todas las páginas por el motor Dompdf (`position: fixed; top: 35%; left: -15%; transform: rotate(-35deg); opacity: 0.07;`) garantizando el no repudio sin habilitar banderas inseguras como ejecución de código PHP embebido (`enable_php: false`).
- **Cálculo de Tiempos de Triage (Res. 5596/2015)**: El tiempo de espera se computa formalmente entre la marca de tiempo de triaje (`triage_at`) y la marca de tiempo inmutable de la primera evolución médica del episodio (`performed_at`), o contra la hora actual para pacientes que continúan en estado `en_espera`.
- **Arquitectura de Credenciales para Acompañantes**:
  - *Código del Paciente:* Código público alfanumérico único del episodio (ej: `UBT1A2B3`, 8 caracteres en mayúsculas).
  - *PIN de Acceso Predeterminado:* Por defecto son los **últimos 4 dígitos del documento de identidad** del paciente (o los últimos 4 dígitos del identificador `NN-AAAAMMDD-XXXX` si es paciente NN).
  - *PIN Personalizado o Generado:* El personal puede emitir un PIN aleatorio de 6 dígitos o restablecerlo en cualquier momento. Se almacena cifrado con AES-256 (`pin => 'encrypted'`) en la base de datos junto con su hash (`pin_hash`), permitiendo que el médico encargado, personal asignado y administrador lo visualicen de manera permanente en la historia clínica sin perderse tras recargar la pantalla.
  - *Autorización Basada en Roles (`EmergencyEpisodePolicy`):* La gestión del acceso del acompañante (`manageCompanionAccess`) y el otorgamiento/revocación de consentimiento para compartir datos clínicos (`manageCompanionConsent`) están estrictamente restringidos al médico responsable del episodio, equipo asistencial asignado, coordinación médica y administradores; usuarios no asignados son bloqueados con `403 Forbidden`.
- **PostgreSQL Concurrency Lock**: Bloqueo de fila `lockForUpdate` en todas las transiciones de estado clínico, asignación de camillas, turnos, conciliación de identidad y emisión de PINs.
- **Inmutabilidad Médica (Res. 1995/1999)**: Protección en Eloquent (`booted`) y base de datos contra edición o borrado de evoluciones clínicas; el modelo solo admite adición de notas aclaratorias o anulaciones enlazadas.
- **Inmutabilidad de Consentimientos (Ley 1581)**: El modelo `Consent` prohíbe modificaciones o eliminaciones arbitrarias; únicamente admite registrar `revoked_at` de forma atómica para registrar la revocación.
- **Anti-Enumeración en Canal Acompañante**: Respuestas de autenticación idénticas ante código inválido, PIN inválido o acompañante no registrado, impidiendo el escaneo automatizado de episodios.
- **Preservación Forense NN**: Al conciliar identidad, el paciente NN original permanece intacto en la tabla `patients` y el código `NN-AAAAMMDD-XXXX` se conserva permanentemente en `emergency_episodes` para garantizar la trazabilidad forense y médico-legal.
- **Excepción Legal Ley 1581 Art. 10**: Admisión inmediata para salvaguardar la vida sin exigir consentimiento previo a pacientes inconscientes o desorientados, formalizada mediante registro inmutable en `consents`.
- **Control de Custodia Break-Glass**: Barrera de acceso excepcional que impide la consulta pasiva de historias clínicas por profesionales no asignados, exigiendo justificación auditada y bloqueando acciones clínicas en modo consulta.
- **Ciclo de Personal de Apoyo vs. Responsable**: El médico tratante principal no puede ser eliminado dejando el episodio acéfalo (requiere transferencia asignando un nuevo responsable). En contraste, el personal de apoyo (`role = 'apoyo'`) puede ser desactivado/finalizado una vez concluida su labor asistencial puntual (por el propio profesional, el médico responsable o coordinación), liberando su carga activa y registrando el evento inmutable `episodio.apoyo_finalizado`.
- **Contador Dinámico de Carga**: Evita campos desnormalizados propensos a inconsistencias; calcula los episodios abiertos activos directamente sobre `episode_assignments` con `distinct('episode_id')`.
- **Diseño sin Emojis y Accesible**: Reemplazo total de emojis por iconografía lineal Bootstrap Icons local compilada con Vite, tokens de diseño con contraste verificado >= 4.5:1 y objetivos táctiles de 44px.

- **Aislamiento Arquitectónico del Módulo de Acompañantes en Barra de Navegación**: El enlace público `/acompanante` ha sido aislado estrictamente para usuarios no autenticados (`@guest`). Para el personal asistencial autenticado (médicos, enfermeros, coordinadores y administradores), el acceso se encuentra oculto en el navbar público, ya que dicho personal cuenta con acceso clínico completo y auditoría en la historia clínica del paciente (`episodes.show`), previniendo colisiones de contexto de sesión y cumpliendo el principio de menor sorpresa y separación de roles.
- **Rediseño Institucional Completo y Supresión de "CMS Seguro"**: Se eliminaron de forma exhaustiva todas las menciones a "CMS Seguro" del aplicativo (vistas, layouts, configuración y `.env`), transformando la página de inicio (`/`), "Acerca de" (`/acerca`) y contacto (`/contacto`) en un portal de referencia oficial para el **Hospital de Ubaté**, con divulgación de clasificación de Triage (Res. 5596/2015), signos de alarma médica y canales de atención de urgencias 24/7.
- **Diseño Adaptativo Integral (Responsive Design)**: Arquitectura frontend responsive probada en dispositivos móviles ($\le 576\text{px}$), tablets / iPad ($768\text{px} - 1024\text{px}$) y estaciones de escritorio / Mac ($\ge 1200\text{px}$), con objetivos táctiles accesibles ($\ge 44\text{px}$), tipografía legible, alto contraste WCAG 2.2 AA y ausencia total de emojis decorativos (reemplazados por Bootstrap Icons).
- **Acceso Médico Asistencial Directo a Pre-registro y Registro NN**: El personal asistencial autenticado en el módulo de urgencias (`/episodios`) cuenta con accesos directos visibles para emitir un **"Nuevo Pre-registro"** presencial y **"Registrar Paciente NN"** de emergencia, además de la pestaña de pre-registros pendientes para su admisión clínica inmediata sin conflictos de sesión.


### Fase 8: Preparación de producción y documentación integral
- **Manual Oficial de Despliegue en Producción (`docs/despliegue.md`)**:
  - Cobertura exhaustiva de los 15 puntos obligatorios de la Sección 13 de `AGENTE_HOSPITAL.md`.
  - Configuración de base de datos PostgreSQL con usuario DDL (`ubate_migrator`) y usuario DML de mínimo privilegio (`ubate_app`), con bloqueo estricto de `UPDATE`/`DELETE` sobre `audit_logs`.
  - Servidor web Nginx endurecido con bloqueo estricto de archivos ocultos y configuración (`.env`, `.git`, etc.), HSTS forzado y TLS 1.3.
  - Script automatizado de copias de seguridad cifradas con GPG para base de datos y `storage/app`, protocolo de prueba de restauración mensual y plan de reversa (rollback).
  - Procedimiento asistencial de contingencia en papel ante corte de energía/red y protocolo legal de respuesta a incidentes de seguridad (notificación SIC / Supersalud).
- **Documento de Cumplimiento Normativo y Legal (`docs/cumplimiento.md`)**:
  - Sustentación formal de cumplimiento de la **Resolución 1995 de 1999** (inmutabilidad, reserva legal, retención por 20 años y entrega de copias foliadas).
  - Implementación técnica de la **Resolución 5596 de 2015** (Triage I al V, cómputo dinámico de oportunidad de atención sin barreras administrativas).
  - Cumplimiento de la **Ley Estatutaria 1581 de 2012** y Decreto 1377 de 2013 (autorizaciones independientes, consentimiento del acompañante, procedimiento de Habeas Data con plazos de 10 y 15 días, y aplicación rigurosa de la excepción del **Artículo 10 literal b** para urgencias vitales y pacientes NN).
  - Matriz de Evaluación de Impacto en Privacidad (DPIA) y catálogo del Registro Nacional de Bases de Datos (RNBD).
- **Documento de Arquitectura y Modelo de Datos (`docs/arquitectura.md`)**:
  - Especificación de capas limpias (Presentación, Enrutamiento/Middlewares, Controladores/FormRequests, Servicios de Dominio, Persistencia Eloquent).
  - Diagrama de secuencia del ciclo de vida de la atención de urgencia en Mermaid.
  - Diagrama Entidad-Relación (ER) completo con tipos de datos, relaciones y claves en Mermaid.
- **Acceso Dual a la Documentación Técnica**:
  - Implementación del comando de consola `php artisan hospital:docs` (con opciones `--seguridad` y `--modulos`).
  - Ruta web institucional accesible en `/documentacion` (alias `/docs`) con renderizado seguro de Markdown a HTML.
- **Actualización Integral de `README.md`**:
  - Descripción, requisitos, guía paso a paso de instalación, pruebas, comandos de consola y árbol de documentación técnica.

---

## 3. Decisiones técnicas
- **Estandarización Institucional de Nombre**: Uso estricto de **"Hospital de Ubaté"** (evitando "Hospital El Salvador de Ubaté E.S.E.") en cumplimiento de requerimientos de propiedad intelectual y autorizaciones institucionales previas.
- **Protección de Memoria Caché Médica**: Configuración de `no-store` dinámico en `SecurityHeaders` cuando `auth()->check() == true` para mitigar el riesgo de fuga de datos sensibles en estaciones compartidas de urgencias (Ley 1581 / Res. 1995).
- **Restricción Exclusiva de Rutas**: Ningún parámetro de ruta queda abierto a cadenas arbitrarias; se aplican validadores regex `whereUuid`, `whereNumber` y `whereAlphaNumeric` en `routes/web.php`.
- **Marca de Agua Forense en PDF**: Implementada mediante div fijo CSS repetido en todas las páginas por el motor Dompdf (`position: fixed; top: 35%; left: -15%; transform: rotate(-35deg); opacity: 0.07;`) garantizando el no repudio sin habilitar banderas inseguras como ejecución de código PHP embebido (`enable_php: false`).
- **Cálculo de Tiempos de Triage (Res. 5596/2015)**: El tiempo de espera se computa formalmente entre la marca de tiempo de triaje (`triage_at`) y la marca de tiempo inmutable de la primera evolución médica del episodio (`performed_at`), o contra la hora actual para pacientes que continúan en estado `en_espera`.
- **Arquitectura de Credenciales para Acompañantes**:
  - *Código del Paciente:* Código público alfanumérico único del episodio (ej: `UBT1A2B3`, 8 caracteres en mayúsculas).
  - *PIN de Acceso Predeterminado:* Por defecto son los **últimos 4 dígitos del documento de identidad** del paciente (o los últimos 4 dígitos del identificador `NN-AAAAMMDD-XXXX` si es paciente NN).
  - *PIN Personalizado o Generado:* El personal puede emitir un PIN aleatorio de 6 dígitos o restablecerlo en cualquier momento. Se almacena cifrado con AES-256 (`pin => 'encrypted'`) en la base de datos junto con su hash (`pin_hash`), permitiendo que el médico encargado, personal asignado y administrador lo visualicen de manera permanente en la historia clínica sin perderse tras recargar la pantalla.
  - *Autorización Basada en Roles (`EmergencyEpisodePolicy`):* La gestión del acceso del acompañante (`manageCompanionAccess`) y el otorgamiento/revocación de consentimiento para compartir datos clínicos (`manageCompanionConsent`) están estrictamente restringidos al médico responsable del episodio, equipo asistencial asignado, coordinación médica y administradores; usuarios no asignados son bloqueados con `403 Forbidden`.
- **PostgreSQL Concurrency Lock**: Bloqueo de fila `lockForUpdate` en todas las transiciones de estado clínico, asignación de camillas, turnos, conciliación de identidad y emisión de PINs.
- **Inmutabilidad Médica (Res. 1995/1999)**: Protección en Eloquent (`booted`) y base de datos contra edición o borrado de evoluciones clínicas; el modelo solo admite adición de notas aclaratorias o anulaciones enlazadas.
- **Inmutabilidad de Consentimientos (Ley 1581)**: El modelo `Consent` prohíbe modificaciones o eliminaciones arbitrarias; únicamente admite registrar `revoked_at` de forma atómica para registrar la revocación.
- **Anti-Enumeración en Canal Acompañante y Contacto**: Respuestas de autenticación idénticas ante código inválido, PIN inválido o acompañante no registrado; aislamiento de los módulos públicos de contacto y acompañantes dentro de `@guest` para no polucionar la interfaz del personal médico y administrativo.
- **Redirección Predictiva al Dashboard**: La ruta `/admin` y el flujo post-autenticación (`LoginController`, `TwoFactorChallengeController`) redirigen de forma determinista al Dashboard institucional, evitando aterrizajes no deseados en auditoría o vistas secundarias.
- **Preservación Forense NN**: Al conciliar identidad, el paciente NN original permanece intacto en la tabla `patients` y el código `NN-AAAAMMDD-XXXX` se conserva permanentemente en `emergency_episodes` para garantizar la trazabilidad forense y médico-legal.
- **Excepción Legal Ley 1581 Art. 10**: Admisión inmediata para salvaguardar la vida sin exigir consentimiento previo a pacientes inconscientes o desorientados, formalizada mediante registro inmutable en `consents`.
- **Control de Custodia Break-Glass**: Barrera de acceso excepcional que impide la consulta pasiva de historias clínicas por profesionales no asignados, exigiendo justificación auditada y bloqueando acciones clínicas en modo consulta.
- **Diseño sin Emojis y Accesible**: Reemplazo total de emojis por iconografía lineal Bootstrap Icons local compilada con Vite, tokens de diseño con contraste verificado >= 4.5:1 y objetivos táctiles de 44px.

---

## 4. Estado Final de Fases
* **Fase 1 (Arquitectura y Modelos Base):** CERRADA Y VALIDADA.
* **Fase 2 (Autenticación y Seguridad 2FA):** CERRADA Y VALIDADA.
* **Fase 3 (Pre-registro Digital y Admisión Triage):** CERRADA Y VALIDADA.
* **Fase 4 (Módulo Clínico, Camillas y Break-Glass):** CERRADA Y VALIDADA.
* **Fase 5 (Portal de Acompañantes y Consentimientos):** CERRADA Y VALIDADA.
* **Fase 6 (Historia Clínica PDF, Reportes Triage y Auditoría):** CERRADA Y VALIDADA.
* **Fase 7 (Endurecimiento de Rutas, Caché No-Store y Pruebas):** CERRADA Y VALIDADA.
* **Fase 8 (Preparación de Producción, Despliegue y Cumplimiento):** CERRADA Y VALIDADA.

**SISTEMA 100% COMPLETADO Y LISTO PARA OPERACIÓN EN PRODUCCIÓN.**

---

## 5. Última prueba exitosa
- **Fecha y hora:** 2026-10-26 01:10:25 -05:00
- **Comando ejecutado:** `php artisan test`
- **Resultado:** 157 pasadas, 0 fallidas (681 aserciones). Tiempo: 114.07s
  - `Tests\Unit\ExampleTest`: 1 pasada
  - `Tests\Feature\ExampleTest`: 8 pasadas (verificación de portal institucional, Triage, aislamiento de navbar, redirección de /admin y página de documentación)
  - `Tests\Feature\CompanionTrackingTest`: 15 pasadas
  - `Tests\Feature\EmergencyReportTest`: 7 pasadas
  - `Tests\Feature\EpisodePdfTest`: 8 pasadas
  - `Tests\Feature\IdentityReconciliationTest`: 13 pasadas
  - `Tests\Feature\MedicalEpisodeTest`: 25 pasadas
  - `Tests\Feature\PhysicalResourcesAndStaffTest`: 16 pasadas
  - `Tests\Feature\PreregistrationTest`: 15 pasadas
  - `Tests\Feature\SecurityHardeningTest`: 6 pasadas
  - `Tests\Feature\SecurityTest`: 39 pasadas



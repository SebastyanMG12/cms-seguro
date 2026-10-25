# Estado del Proyecto: Sistema Hospitalario de Urgencias (cms-seguro)

Documento de seguimiento del estado del sistema, fases ejecutadas, decisiones técnicas y pruebas de verificación.

---

## 1. Fase actual
- **Fase:** Fase 3: Pre-registro público (Completada con éxito).
- **Rama:** `feature/fase-3-preregistro`

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
- **Pruebas de aceptación**:
  - 15 pruebas dedicadas en `tests/Feature/PreregistrationTest.php`.

---

## 3. Decisiones técnicas
- **PostgreSQL Concurrency Lock**: Bloqueo de fila `lockForUpdate` antes de asignar o liberar camillas y al procesar expiración de episodios para evitar condiciones de carrera simultáneas.
- **HMAC Blind Index**: El índice ciego sobre el documento del paciente permite búsqueda exacta de antecedentes sin comprometer el cifrado en reposo y sin requerir descifrado masivo en memoria.
- **Separación de Consentimientos**: Cumplimiento riguroso de la Ley 1581 de 2012 mediante filas independientes en `consents` para tratamiento general de urgencias y compartición a terceros/acompañante.
- **Content-Security-Policy sin inline scripts**: Toda la interactividad del pre-registro (cambio de tipo de documento, detalle de alergias, banderas de signos de alarma, contadores de caracteres) se empaquetó en `resources/js/app.js` compilado con Vite.

---

## 4. Pendientes para siguientes fases
- **Fase 4**: Módulo médico (listado de episodios con filtros, registrar llegada, asignar triage I a V, confirmar ingreso, asignar personal y camilla, registrar egreso, entradas clínicas inmutables con aclaración/anulación).
- **Fase 4B**: Registro de emergencia (paciente sin identificar / NN, conciliación de identidad).
- **Fase 5**: Módulo acompañante (código público + PIN presencial, rate limiting, revocación y actualización periódica).
- **Fase 6**: Administración avanzada, reportes y generación de PDF con marca de agua.
- **Fase 7**: Endurecimiento, pruebas de seguridad y `docs/seguridad.md`.
- **Fase 8**: Preparación de producción y `docs/cumplimiento.md`.

---

## 5. Última prueba exitosa
- **Fecha y hora:** 2026-10-25 16:01:43 -05:00
- **Comando ejecutado:** `php artisan test`
- **Resultado:** 65 pasadas, 0 fallidas (237 aserciones). Tiempo: 23.15s
  - `Tests\Unit\ExampleTest`: 1 pasada
  - `Tests\Feature\ExampleTest`: 1 pasada
  - `Tests\Feature\PhysicalResourcesAndStaffTest`: 10 pasadas
  - `Tests\Feature\PreregistrationTest`: 15 pasadas
  - `Tests\Feature\SecurityTest`: 38 pasadas

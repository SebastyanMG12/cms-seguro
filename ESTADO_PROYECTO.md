# Estado del Proyecto: Sistema Hospitalario de Urgencias (cms-seguro)

Documento de seguimiento del estado del sistema, fases ejecutadas, decisiones técnicas y pruebas de verificación.

---

## 1. Fase actual
- **Fase:** Fase 2: Recursos físicos y personal (Completada con éxito).
- **Rama:** `feature/fase-2-recursos-personal`

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
  - Asignados en `RolesAndPermissionsSeeder` preservando separación de funciones (Auditor sin permisos de gestión física/usuarios; Coordinador con gestión de camillas y turnos).
- **Vistas administrativas y de ocupación**:
  - `occupancy.index`: Tablero del censo hospitalario con métricas consolidadas en tiempo real y desglose jerárquico.
  - `staff.index`: Gestión de plantilla asistencial y programación de turnos.

---

## 3. Decisiones técnicas
- **PostgreSQL Concurrency Lock**: Bloqueo de fila `lockForUpdate` antes de asignar o liberar camillas para evitar condiciones de carrera simultáneas.
- **Partial Unique Index**: El índice `UNIQUE (bed_id) WHERE released_at IS NULL` garantiza matemáticamente a nivel de motor PostgreSQL la imposibilidad de solapar dos asignaciones activas sobre la misma camilla.
- **Disponibilidad Compuesta**: El método `isAssignable()` valida en cadena el estado de la camilla, el estado de la habitación y el estado del área/servicio, evitando asignar recursos en áreas cerradas o en mantenimiento.
- **Separación de Funciones Médicas y Administrativas**: Los médicos y enfermeros tienen acceso de lectura al censo (`ocupacion.ver`), mientras que el coordinador y administrador poseen gestión física y de turnos.

---

## 4. Pendientes para siguientes fases
- **Fase 3**: Formulario público de pre-registro de pacientes, consentimiento de tratamiento de datos Ley 1581, honeypot, anti-enumeración y expiración programada a las 24 horas.
- **Fase 4**: Módulo médico (llegada, asignación de triage I a V, ingreso, asignación de camilla/médico responsable y apoyos, egreso y entradas clínicas inmutables).

---

## 5. Última prueba exitosa
- **Fecha y hora:** 2026-10-25 15:49:19 -05:00
- **Comando ejecutado:** `php artisan test`
- **Resultado:** 50 pasadas, 0 fallidas (159 aserciones). Tiempo: 20.36s
  - `Tests\Unit\ExampleTest`: 1 pasada
  - `Tests\Feature\ExampleTest`: 1 pasada
  - `Tests\Feature\PhysicalResourcesAndStaffTest`: 10 pasadas
  - `Tests\Feature\SecurityTest`: 38 pasadas

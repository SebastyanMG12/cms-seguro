# Estado del Proyecto: Sistema Hospitalario de Urgencias (cms-seguro)

Documento de seguimiento del estado del sistema, fases ejecutadas, decisiones técnicas y pruebas de verificación.

---

## 1. Fase actual
- **Fase:** Fase 1C: Cierre del acceso y gestión de usuarios (Completada con éxito).
- **Rama:** `feature/fase-1c-usuarios`

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

---

## 3. Decisiones técnicas
- **PostgreSQL Concurrency Lock**: En `ensureNotLastActiveAdmin`, PostgreSQL no permite `FOR UPDATE` sobre `count(*)`. Se obtienen las filas bloqueadas con `lockForUpdate()->get()` y se cuentan en la colección de Eloquent, garantizando atomicidad sin violar la restricción del motor.
- **Content-Security-Policy**: Se restringe `script-src` exclusivamente a `'self'` (más los hosts de desarrollo Vite en entorno `local`), prohibiendo tajantemente `'unsafe-inline'` en scripts ejecutables.
- **Protección de Sesión**: La suspensión y el cambio de contraseña purgan inmediatamente las filas asociadas en la tabla `sessions` para mitigar secuestro de sesión o permanencia no autorizada.

---

## 4. Pendientes para siguientes fases
- **Fase 2**: Recursos físicos (sedes, pisos, áreas, habitaciones, camillas) y personal (perfiles, turnos, asignaciones, matriz rol-permiso).
- **Fase 3**: Formulario público de pre-registro y gestión de consentimientos.

---

## 5. Última prueba exitosa
- **Fecha y hora:** 2026-10-25 15:19:10 -05:00
- **Comando ejecutado:** `php artisan test`
- **Resultado:** 40 pasadas, 0 fallidas (116 aserciones). Tiempo: 18.88s
  - `Tests\Unit\ExampleTest`: 1 pasada
  - `Tests\Feature\ExampleTest`: 1 pasada
  - `Tests\Feature\SecurityTest`: 38 pasadas

# Estado del Proyecto: Sistema Hospitalario de Urgencias (cms-seguro)

Documento de seguimiento del estado del sistema, fases ejecutadas, decisiones técnicas y pruebas de verificación.

---

## 1. Fase actual
- **Fase:** 9.0 Verificación inicial (Completada con éxito). Lista para iniciar Fase 1C (Cierre del acceso y gestión de usuarios).
- **Rama:** `hospital`

---

## 2. Fases cerradas
- **Fase 9.0: Verificación inicial y estabilización del 2FA**
  - Registro de rutas de desafío 2FA (`two-factor.challenge` y `two-factor.challenge.store`) con limitador de tasa `throttle:5,1`.
  - Integración del middleware `two-factor` en rutas privadas y en `bootstrap/app.php`.
  - Corrección en `TwoFactor::verifyAndGetTimestep`: se asegura que `Google2FA::verifyKeyNewer` reciba un timestamp inicial no nulo (`$lastTimestep ?? 0`) para retornar el timestep entero del código OTP consumido en vez de un booleano `true`, previniendo la reutilización de códigos TOTP dentro de la misma ventana de tiempo.
  - Corrección de formato y estilo con Laravel Pint.
  - Verificación de migraciones y auditoría de paquetes (`composer audit` y `npm audit`).

---

## 3. Decisiones técnicas
- **TOTP Replay Protection:** Para evitar el reuso de un código TOTP dentro de su ventana de validez de 30 segundos, `verifyKeyNewer` requiere el último timestep aceptado. Cuando el usuario aún no tiene registro (`null`), se pasa `0` para que la librería de `Google2FA` retorne el número de timestep y no `true` (que al castearse a int generaba valor `1` y permitía el reuso).
- **Middleware 2FA:** Se exige a los roles clínicos y administrativos (`administrador`, `auditor`, `coordinador`, `medico`, `enfermero`, `admisiones`) tener 2FA activo para navegar rutas privadas, redirigiéndolos a `/seguridad/2fa` si no lo han completado.

---

## 4. Pendientes conocidos
- Fase 1C: Gestión completa de usuarios (editar, asignar/quitar roles, suspender/reactivar con motivo, restablecer 2FA, borrado lógico, regla de último administrador activo, auto-modificación restringida).
- CSP (Content-Security-Policy): Mover scripts en línea a `resources/js/app.js` y configurar cabecera sin `unsafe-inline`.
- Conexión del dashboard a datos reales.

---

## 5. Última prueba exitosa
- **Fecha y hora:** 2026-10-25 15:02:04 -05:00
- **Comando ejecutado:** `php artisan test`
- **Resultado:** 28 pasadas, 0 fallidas (64 aserciones). Tiempo: 11.98s
  - `Tests\Unit\ExampleTest`: 1 pasada
  - `Tests\Feature\ExampleTest`: 1 pasada
  - `Tests\Feature\SecurityTest`: 26 pasadas

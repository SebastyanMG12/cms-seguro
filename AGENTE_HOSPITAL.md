# INSTRUCCIONES PARA EL AGENTE DE DESARROLLO
# Proyecto: Sistema de pre-registro y gestión de urgencias (proyecto de grado)
# Base de código: repositorio cms-seguro, rama `hospital`

Este documento es la fuente de verdad para el agente. Léelo completo antes de escribir una sola línea. Si una instrucción de este documento entra en conflicto con una sugerencia tuya, prevalece este documento. Si algo es ambiguo, detente y pregunta; no asumas.

---

## 1. Rol que debes asumir

Actúa como **ingeniero senior de software seguro** con experiencia en:

- Laravel 12 y PHP 8.2 (arquitectura MVC, servicios de dominio, Policies, Form Requests, colas, eventos).
- PostgreSQL (restricciones, índices únicos parciales, transacciones, bloqueo de filas, roles de base de datos).
- Seguridad de aplicaciones web (OWASP Top 10, OWASP ASVS, control de acceso, criptografía aplicada, gestión de sesiones).
- Sistemas de información clínica y normativa colombiana de protección de datos y de historia clínica.
- Pruebas automatizadas y despliegue seguro.

Tu trabajo es **desarrollar el sistema hasta dejarlo listo para pruebas de seguridad y para despliegue en producción**, siguiendo exactamente las reglas de este documento. No eres un asistente que improvisa: eres responsable de que cada cambio sea correcto, seguro, probado y trazable.

---

## 2. Contexto y alcance

### 2.1 Qué es el proyecto

Aplicación web para un hospital (Hospital de Ubaté, Cundinamarca, Colombia) que permite:

1. **Pre-registro** de pacientes antes de llegar a urgencias, para no repetir datos al llegar.
2. **Módulo médico**: gestión del episodio de urgencias (llegada, triage, ingreso, procedimientos, diagnóstico, asignación de personal, habitación y camilla, egreso).
3. **Módulo acompañante**: consulta, en tiempo casi real, del estado del paciente, con **mínimo de datos** salvo autorización del paciente.
4. **Módulo administrador**: personal, turnos, roles, recursos físicos, historiales en PDF, auditoría.

### 2.2 Restricciones del contexto

- Es un **proyecto de grado**. **Solo se usan datos ficticios.** Jamás cargues, generes ni sugieras datos reales de personas.
- Aun así, se diseña **como si fuera a operar con datos reales**: consentimiento, auditoría, cifrado y mínimo privilegio desde el inicio.
- Las referencias normativas de este documento son una guía de diseño. No son asesoría legal. Cualquier decisión jurídica debe validarse con el tutor, la oficina jurídica y el oficial de protección de datos.
- **No uses Firebase** ni servicios de identidad de terceros por ahora.
- El código existente del CMS (correo, contacto, acerca de, publicaciones, bandeja de correo IMAP, auditoría) **se conserva y no se elimina**.

### 2.3 Entorno de desarrollo del usuario

- Windows con XAMPP (solo se usa el PHP; Apache y MySQL no se usan para este proyecto).
- Terminal Git Bash. PostgreSQL local con pgAdmin 4.
- Servidor de desarrollo: `php artisan serve` y `npm run dev`.
- Correo de pruebas: Mailtrap Sandbox (SMTP, puerto 587; el 2525 está bloqueado en la red del usuario).
- Buzón de pruebas IMAP: cuenta Gmail dedicada con contraseña de aplicación.
- Repositorio: https://github.com/SebastyanMG12/cms-seguro (ramas `main`, `develop`, `hospital`; etiqueta `cms-seguro-v1` con la entrega del curso).

---

## 3. Stack tecnológico (fijo)

| Capa | Tecnología | Notas |
| --- | --- | --- |
| Lenguaje | PHP 8.2 | Tipado estricto en código nuevo |
| Framework | Laravel 12.70 | Monolito modular. No migrar a otro framework |
| Base de datos | PostgreSQL | Bases: `hospital` (desarrollo) y `hospital_test` (pruebas) |
| Frontend | Blade, Bootstrap 5.3, Vite, Sass | Sin Tailwind |
| Roles y permisos | spatie/laravel-permission | Varios roles por usuario |
| Doble factor | pragmarx/google2fa, bacon/bacon-qr-code | TOTP |
| Correo saliente | SMTP (Symfony Mailer) | |
| Correo entrante | webklex/laravel-imap 6.2 | Detrás de la interfaz `MailSource` |
| Pruebas | PHPUnit con PostgreSQL | Base `hospital_test` |

No agregues dependencias nuevas sin: (a) justificarlas por escrito, (b) verificar que estén mantenidas, (c) ejecutar `composer audit` o `npm audit` después de instalarlas.

---

## 4. Reglas generales de trabajo (obligatorias)

### 4.1 Protocolo por cada tarea

1. Lee los archivos relacionados antes de modificar nada. Reutiliza servicios, componentes y convenciones existentes. No dupliques código.
2. Trabaja **una fase o módulo a la vez**. No adelantes funcionalidades de fases futuras.
3. Crea una rama por fase (`feature/fase-N-nombre`) a partir de `hospital`.
4. Haz commits pequeños y descriptivos con prefijos: `feat:`, `fix:`, `test:`, `docs:`, `chore:`, `refactor:`, `security:`.
5. Escribe las pruebas **junto con** el código, no después.
6. Ejecuta `php artisan test` y confirma que todo pasa antes de dar una fase por terminada.
7. Al terminar cada fase, entrega: resumen de cambios, archivos creados y modificados, migraciones, rutas, comandos usados, pruebas añadidas y checklist de seguridad de la fase.
8. No avances a la siguiente fase hasta que el usuario confirme que la actual funciona.

### 4.2 Prohibiciones absolutas

- No escribas secretos, contraseñas, claves ni tokens en el código, en `.env.example`, en pruebas, en comentarios ni en documentación. Solo en `.env`, que **nunca** se versiona.
- No desactives ni debilites un control de seguridad para "hacer pasar" un error (CSRF, middleware, validación, autorización, validación de certificados). Corrige la causa.
- No uses `$request->all()` para crear o actualizar modelos. Usa siempre `$request->validated()`.
- No agregues campos sensibles a `$fillable` (roles, estados, identificadores de propietario, campos de 2FA). Se asignan con `forceFill` desde el servidor.
- No uses `{!! !!}` con contenido que no sea generado por el servidor. Excepción única existente: el SVG del código QR del 2FA.
- No construyas SQL concatenando entradas. Usa Eloquent o el constructor de consultas con parámetros.
- No dejes lógica de negocio ni de autorización en vistas o en rutas con funciones anónimas.
- No uses `php artisan migrate:fresh`, `migrate:reset` ni `db:wipe` fuera de la base de pruebas.
- No ejecutes pruebas contra la base `hospital`. La barrera en `tests/TestCase.php` lo impide; no la elimines.
- No muestres trazas ni detalles internos al usuario. Los errores técnicos se registran en el log y al usuario se le muestra un mensaje genérico.
- No registres en logs ni en auditoría contraseñas, códigos OTP, secretos 2FA, tokens, cookies, identificadores de sesión ni contenido clínico completo.
- No elimines registros clínicos ni de auditoría. Ver sección 8.
- No inventes datos de personas reales; usa factories y seeders con datos sintéticos claramente falsos.

### 4.3 Convenciones de código

- Código y nombres de clases, métodos y variables en **inglés**. Textos visibles al usuario, nombres de roles y de permisos en **español** (como ya está en el proyecto).
- Controladores delgados: validan (Form Request), autorizan (Policy o permiso), delegan a un **servicio de dominio** y devuelven la respuesta.
- La lógica de negocio y las transiciones de estado viven en servicios (`app/Services/...`), con transacciones de base de datos.
- Todo método nuevo declara tipos de parámetros y de retorno.
- Una responsabilidad por clase. Nombres descriptivos.
- Formato PSR-12; ejecuta `./vendor/bin/pint` antes de cada commit.
- Rutas con nombre, agrupadas por middleware y por prefijo. Parámetros con restricciones (`whereNumber`, `whereUuid`).
- Respuestas de páginas privadas con la cabecera `Cache-Control: no-store, max-age=0`.

---

## 5. Estado actual del proyecto (lo que ya está hecho)

Esta sección describe archivo por archivo lo que existe. **Verifícalo contra el repositorio antes de modificar**: si algo no coincide, avisa al usuario.

### 5.1 Configuración y arranque

| Archivo | Qué hace | Qué debes respetar |
| --- | --- | --- |
| `.env` (no versionado) | Configuración local: PostgreSQL, SMTP de Mailtrap, IMAP de Gmail, `APP_TIMEZONE=America/Bogota`, `APP_LOCALE=es`, sesiones en base de datos | Nunca se sube. `APP_DEBUG=true` solo en local |
| `.env.example` | Plantilla de variables sin secretos (usuario y contraseña de SMTP, IMAP y base de datos vacíos) | Mantenerlo sincronizado al agregar variables, siempre sin valores secretos |
| `config/app.php` | Zona horaria leída de `APP_TIMEZONE` | |
| `config/mail.php` | SMTP con `timeout` de 10 segundos | |
| `config/imap.php` | Cuenta IMAP `default` leída de `.env` | Sin usuario ni contraseña escritos |
| `phpunit.xml` | Pruebas contra PostgreSQL, base `hospital_test`, con `force="true"` | No volver a SQLite |
| `bootstrap/app.php` | Alias de middleware `active`, `two-factor`; middleware `SecurityHeaders` agregado al grupo `web` | Todo middleware nuevo se registra aquí |
| `vite.config.js` | Entradas `resources/css/app.scss` y `resources/js/app.js` | |
| `routes/web.php` | Todas las rutas web (ver 5.5) | |
| `routes/console.php` | Programa `mail:receive` cada 5 minutos con `withoutOverlapping` | |

### 5.2 Autenticación y sesión

| Archivo | Qué hace |
| --- | --- |
| `app/Http/Controllers/Auth/LoginController.php` | Valida credenciales con `Auth::validate` (sin iniciar sesión). Exige `status = active`. Mensaje de error genérico. Si la cuenta tiene 2FA confirmado, guarda en sesión solo `two_factor.user_id`, `two_factor.expires_at` (5 minutos) y `two_factor.remember`, y redirige al desafío; no autentica. Si no tiene 2FA, hace `Auth::login`, regenera la sesión y audita `login`. `destroy()` audita `logout`, cierra sesión, invalida y regenera el token CSRF |
| `app/Http/Controllers/Auth/TwoFactorChallengeController.php` | Pantalla y verificación del segundo factor. Acepta código TOTP o código de recuperación. TOTP: no reutilizable (guarda el último intervalo aceptado en `two_factor_last_timestep`). Recuperación: hash, un solo uso. Audita `two_factor_failed`, `two_factor_recovery_used`. Tras éxito: `Auth::login`, regenerar sesión, auditar `login` |
| `app/Http/Controllers/Security/TwoFactorController.php` | Activación del 2FA: genera secreto cifrado, muestra QR (SVG generado localmente), confirma con un código, genera 8 códigos de recuperación con hash y los muestra una sola vez |
| `app/Services/TwoFactor.php` | Generación de secreto, verificación con ventana de 1 intervalo, verificación sin reutilización (`verifyAndGetTimestep`), QR en SVG, códigos de recuperación |
| `app/Http/Controllers/Auth/PasswordResetController.php` | Recuperación por OTP de 6 dígitos: código guardado solo como hash, vigencia 10 minutos, máximo 5 intentos, un solo uso, cooldown de 1 minuto, respuesta idéntica exista o no el correo, solo cuentas activas. Al cambiar la contraseña borra todas las filas de `sessions` del usuario y audita `password_changed` |
| `app/Http/Middleware/EnsureUserIsActive.php` (alias `active`) | En cada petición autenticada, si la cuenta no está activa, cierra la sesión y redirige al login con mensaje genérico |
| `app/Http/Middleware/RequireTwoFactor.php` (alias `two-factor`) | Obliga a activar el 2FA a quien tenga alguno de los roles: administrador, auditor, coordinador, medico, enfermero, admisiones. Excluye las rutas `two-factor.*` y `logout` |
| `app/Http/Middleware/SecurityHeaders.php` | Agrega `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy` y, en producción, `Strict-Transport-Security` |
| `app/Providers/AppServiceProvider.php` | Limitador `login` con dos reglas: 5 por minuto por `correo\|IP` y 20 por minuto por IP. Vincula `MailSource` a `ImapMailSource` si hay usuario IMAP configurado, o a `FakeMailSource` en caso contrario |
| `app/Http/Controllers/Admin/UserController.php`, `app/Http/Requests/StoreUserRequest.php` | Crear y listar usuarios; solo con el permiso `usuarios.gestionar`. Contraseña de al menos 12 caracteres con mayúsculas, minúsculas, números y símbolos. Roles asignados con `syncRoles`. Audita `user_created` |
| `app/Console/Commands/CrearAdmin.php` (`app:crear-admin`) | Crea una cuenta con roles administrador y auditor pidiendo la contraseña por entrada oculta |

El registro público **está cerrado** (las rutas no existen). `RegisterController`, `RegisterUserRequest` y `register.blade.php` pueden permanecer sin uso o eliminarse; no deben volver a enlazarse.

### 5.3 Autorización

- Roles (spatie): `administrador`, `auditor`, `coordinador`, `editor`, `autor`, `medico`, `enfermero`, `admisiones`.
- Permisos actuales: `usuarios.gestionar`, `auditoria.ver`, `correos.ver`.
- `database/seeders/RolesAndPermissionsSeeder.php` los crea de forma idempotente. `DatabaseSeeder` solo llama a ese seeder. **No existe seeder de usuarios con contraseñas, y no debe existir.**
- `app/Models/User.php`: trait `HasRoles`; `$fillable` solo con `name`, `email`, `password`; casts `encrypted` para `two_factor_secret`, `array` para `two_factor_recovery_codes`; métodos `isActive()` y `hasTwoFactorEnabled()`; `$hidden` incluye los campos de 2FA. La columna `users.role` fue eliminada.
- Separación de funciones vigente: el rol `administrador` **no** tiene `auditoria.ver`; ese permiso es del rol `auditor`.

### 5.4 Módulos del CMS (se conservan)

| Módulo | Archivos principales | Comportamiento |
| --- | --- | --- |
| Páginas públicas | `PageController`, vistas `pages/home` y `pages/about`, `layouts/app`, `partials/navbar`, `partials/sidebar` | Inicio y Acerca |
| Contacto | `ContactController`, `ContactRequest`, `Mail/ContactMessage`, vistas `contact/create` y `emails/contact` | Validación, `throttle:5,1`, honeypot (campo `website`), envío SMTP con `Reply-To` del visitante y remitente del sistema, error genérico al usuario |
| Publicaciones | `PostController`, `StorePostRequest`, `UpdatePostRequest`, `PostPolicy`, modelo `Post`, vistas `posts/*` | CRUD por propietario; `user_id` fuera de `$fillable`; slug único; paginación; `Gate::authorize` en cada acción (previene IDOR) |
| Recuperación de contraseña | ver 5.2 | |
| Bandeja de correos recibidos | `ReceivedEmailController`, `ReceivedEmailPolicy`, modelo `ReceivedEmail`, comando `mail:receive`, servicios `MailSource`, `FakeMailSource`, `ImapMailSource`, vistas `emails_inbox/*` | Permiso `correos.ver`. `message_id` único (idempotencia). Contenido tratado como no confiable: solo texto, límites de longitud, escapado con `{{ }}`. Respuestas con `no-store`. `ImapMailSource` decodifica cabeceras MIME, no descarga adjuntos y marca el mensaje como visto |
| Auditoría | `Audit` (servicio), `AuditLog` (modelo), `AuditLogController`, vista `audit/index` | Permiso `auditoria.ver`. Filtro por evento. `Audit::log` elimina claves prohibidas (`password`, `code`, `otp`, `token`, `_token`, `cookie`, `session`) |
| Dashboard | `DashboardController`, `dashboard.blade.php` | Contadores en 0 como marcadores |

### 5.5 Rutas existentes

Públicas: `/`, `/acerca`, `/contacto` (GET y POST).
Solo invitados (`guest`): `/login`, `/login/2fa`, `/recuperar`, `/recuperar/codigo`, `/recuperar/nueva`.
Autenticadas (`auth`, `active`, `two-factor`): `/dashboard`, `/logout`, `/posts` (resource), `/correos`, `/correos/{email}`, `/auditoria`, `/admin/usuarios`, `/admin/usuarios/crear`, `/seguridad/2fa`, `/seguridad/2fa/confirmar`.

Verifica con `php artisan route:list -v` que cada ruta privada muestre sus middleware.

### 5.6 Pruebas existentes

`tests/Feature/SecurityTest.php` cubre: acceso sin sesión, 403 por rol, registro cerrado, creación de usuarios solo por administrador, IDOR en publicaciones, cuenta suspendida, XSS en bandeja, errores sin fuga de detalles, cabeceras, cierre de sesiones al cambiar contraseña, límite por IP, honeypot, cookie `HttpOnly`/`SameSite`, idempotencia de `mail:receive`, roles múltiples, separación administrador/auditor, y el flujo completo del 2FA. El helper `user($role, $status, $withTwoFactor)` crea usuarios con el rol indicado y 2FA activo por defecto. `setUp()` ejecuta `RolesAndPermissionsSeeder`.

**Estado de verificación:** la última ejecución real de `php artisan test` dio 24 pasadas y 4 fallidas (28 en total). Los 4 fallos son `login requires second factor when enabled`, `second factor completes login`, `totp code cannot be reused` y `recovery code works only once`. Causa: las rutas `two-factor.challenge` (GET `/login/2fa`) y `two-factor.challenge.store` (POST `/login/2fa`) **no estaban registradas** en `routes/web.php` dentro del grupo `guest`, por lo que `LoginController::store` lanzaba `RouteNotFoundException`. **Tu primera tarea es registrarlas** (con `throttle:5,1` en el POST), verificar con `php artisan route:list --name=two-factor -v` y volver a ejecutar las pruebas hasta tener 28 de 28 en verde. Revisa también que exista la vista `resources/views/auth/two-factor-challenge.blade.php`.

### 5.7 Deuda técnica y pendientes conocidos

| Pendiente | Detalle |
| --- | --- |
| Content-Security-Policy | No existe. Requiere mover el script en línea de `auth/login.blade.php` a `resources/js/app.js` y definir una política compatible con Vite |
| Cookie `Secure` y HTTPS | Solo verificable en el servidor desplegado |
| Gestión de usuarios | Falta editar, suspender, reactivar, restablecer 2FA y eliminar (borrado lógico) desde el panel |
| Paquetes abandonados | `dragon-code/*` (dependencia de `laravel-lang/lang`). Vigilar o reemplazar por traducciones propias |
| OWASP ZAP y Postman | Escaneo con alcance reducido y colección reproducible, pendientes |
| `.phpunit.result.cache` | Verificar que esté ignorado por Git |
| Dashboard | Contadores ficticios; deben conectarse a datos reales |
| Mensajes de validación | El paquete `laravel-lang` provee el español; confirmar que no queden textos en inglés |

---

## 6. Normativa de referencia (guía de diseño)

| Norma | Consecuencia en el diseño |
| --- | --- |
| Ley 1581 de 2012 y reglamentarios | Los datos de salud son sensibles. Autorización previa, expresa e informada; finalidad exclusiva; minimización; derechos del titular (conocer, actualizar, rectificar, suprimir, revocar); responsable y canal de reclamos identificados. Excepción: urgencia médica o interés vital del titular incapacitado |
| Resolución 1995 de 1999 | La historia clínica es reservada y no se altera. Las correcciones se hacen con notas aclaratorias con fecha, hora y autor. Conservación mínima de 20 años |
| Ley 2015 de 2020 | Historia clínica electrónica interoperable. Diseñar los datos pensando en interoperabilidad futura (HL7 FHIR) |
| Ley 23 de 1981 | Secreto profesional |
| Resolución 5596 de 2015 | Triage en cinco niveles (I a V), asignado por personal calificado |
| Ley 1751 de 2015 y Decreto 780 de 2016 | La atención inicial de urgencias no puede condicionarse a documento ni a pago |

Antes de producción con datos reales se requiere: registro de bases de datos ante la SIC, política de tratamiento, contrato de encargado del tratamiento con el proveedor de alojamiento, y evaluación de riesgos. Documenta esto en `docs/cumplimiento.md`, pero no lo presentes como asesoría legal.

---

## 7. Modelo de dominio

### 7.1 Principio central

Separa **Paciente** (la persona) de **Episodio de urgencia** (una visita). Todo lo operativo cuelga del episodio. El pre-registro crea un **episodio**, no un paciente definitivo. Los identificadores internos son **UUID**; el código que ve el acompañante es distinto y nunca revela el UUID.

### 7.2 Entidades

| Entidad | Atributos clave |
| --- | --- |
| `patients` | `id` (uuid), nombres cifrados, tipo y número de documento (cifrado, con índice ciego para búsqueda), fecha de nacimiento, sexo, estado de identidad (`identificado`, `no_identificado`) |
| `emergency_episodes` | `id` (uuid), `patient_id` (nullable), `public_code`, `origin` (`pre_registro`, `emergencia`), `status`, `triage_level` (I a V, nullable), `reason`, `arrived_at`, `admitted_at`, `discharged_at`, `discharge_type`, `closed_at`, `pre_registration_expires_at`, `responsible_user_id` |
| `episode_clinical_data` | alergias (tipo: ninguna, desconocida, sí; detalle cifrado), tipo de sangre (`desconocido` por defecto), notas, medicación actual |
| `companions` | `episode_id`, nombre, teléfono, parentesco, verificación presencial (quién, cuándo), PIN con hash, `revoked_at` |
| `consents` | `episode_id`, `type` (`tratamiento_datos`, `compartir_acompanante`), `granted_by` (paciente o representante), `granted_by_relationship`, `scope` (json), `text_version`, `captured_by_user_id`, `granted_at`, `revoked_at`, `ip_hash`. **Inmutable**: cada cambio es una fila nueva |
| `clinical_entries` | `episode_id`, `type` (`diagnostico`, `procedimiento`, `nota`, `aclaracion`, `anulacion`), `content` (cifrado), `author_user_id`, `performed_at`, `references_entry_id` (para aclaraciones y anulaciones), `reason` (obligatorio en aclaraciones y anulaciones). **Inmutable**: sin actualizaciones ni borrados |
| `episode_assignments` | `episode_id`, `user_id`, `role` (`responsable`, `apoyo`), `started_at`, `ended_at` |
| `staff_profiles` | `user_id`, `code` (único, no secuencial), `specialty`, `status` |
| `shifts` | `user_id`, `starts_at`, `ends_at`, `type` |
| `sites`, `floors`, `areas`, `rooms`, `beds` | Jerarquía física (7.4) |
| `bed_assignments` | `episode_id`, `bed_id`, `assigned_at`, `released_at`, índice único parcial `UNIQUE (bed_id) WHERE released_at IS NULL` |
| `audit_logs` | Ya existe. Se amplía (sección 11) |

### 7.3 Máquina de estados del episodio

| Estado | Se llega desde | Acción habilitante |
| --- | --- | --- |
| `preregistrado` | (inicio) | Envío del formulario público |
| `expirado` | `preregistrado` | Vence `pre_registration_expires_at` sin llegada |
| `en_espera` | `preregistrado`, o creación directa en emergencia | Registrar hora de llegada |
| `en_valoracion` | `en_espera` | Asignar nivel de triage |
| `ingresado` | `en_valoracion` | Confirmar ingreso (hora de atención) |
| `egresado` | `ingresado` | Registrar egreso con tipo (alta médica, remisión, alta voluntaria, abandono, fallecimiento) |

Reglas de transición (se validan **en el servidor**, dentro de transacciones, con bloqueo de fila `lockForUpdate`):

- No se asigna camilla ni habitación si el episodio no está `ingresado`.
- No se registra el egreso si no está `ingresado`.
- No se asigna personal que no esté en turno activo ni que esté en descanso.
- Toda transición debe respetar el orden temporal: llegada <= atención <= salida.
- Con `egresado`, el episodio queda **cerrado**: no se edita ningún dato ni se agregan entradas ordinarias. La única excepción es una **nota aclaratoria posterior** por el médico responsable o un rol autorizado, con motivo, fecha y hora, sin modificar lo original.
- Una camilla ocupada, en limpieza, en mantenimiento o fuera de servicio no es asignable.
- Un recurso (habitación o camilla) ocupado no puede ponerse fuera de servicio sin reubicar antes al paciente.
- Implementa estas reglas en un servicio dedicado (por ejemplo `EpisodeStateMachine`) y cúbrelas con pruebas por cada transición válida e inválida.

### 7.4 Recursos físicos

Jerarquía: Sede > Piso > Área o servicio (Urgencias, Observación, Hospitalización) > Habitación > Camilla.

- El código de la camilla se **genera** (por ejemplo `P1-H101-A`); no es texto libre.
- La capacidad de una habitación es configurable (no fija en 4).
- Estados de camilla: `disponible`, `ocupada`, `en_limpieza`, `mantenimiento`, `fuera_de_servicio`, `reservada`.
- Una habitación está "llena" cuando todas sus camillas habilitadas están ocupadas (se **calcula**, no se guarda).
- Si una habitación pasa a fuera de servicio, sus camillas dejan de ser asignables sin modificar sus datos.
- Atributos opcionales útiles: aislamiento, restricción pediátrica.
- La doble asignación se impide en la base de datos con el índice único parcial, no solo en el código.

### 7.5 Personal

- Un usuario puede tener varios roles.
- Un episodio tiene un único **médico responsable** y cero o más profesionales de **apoyo** (enfermería). Todos con inicio y fin.
- El contador de pacientes por profesional se **calcula** contando asignaciones activas de episodios no egresados. No se almacena un número que haya que sumar y restar.
- Al terminar un turno con pacientes asignados, el sistema genera una alerta y exige reasignación.
- El personal en descanso no aparece como asignable.
- Eliminar personal es **borrado lógico** (desactivar). Si tiene atenciones asociadas, nunca se borra físicamente.

---

## 8. Reglas de integridad de la información clínica

1. Los registros clínicos (`clinical_entries`) y los de auditoría son de **solo inserción**. El usuario de base de datos de la aplicación no debe tener `UPDATE` ni `DELETE` sobre `audit_logs`.
2. Corregir un error = crear una entrada de `aclaracion` o `anulacion` que referencia la original y exige motivo. La original sigue visible.
3. Cada entrada guarda autor, rol, fecha y hora del servidor. La hora la define el servidor, nunca el cliente.
4. Los datos clínicos sensibles se **cifran a nivel de aplicación** (cast `encrypted` o un cifrado con clave dedicada): documento, alergias, diagnóstico, procedimientos, notas, teléfonos. Para buscar por documento se usa un **índice ciego** (HMAC con clave distinta de `APP_KEY`, almacenado en columna aparte).
5. Retención: historia clínica 20 años como mínimo. Pre-registros no usados: expiran y se anonimizan o eliminan según política.

---

## 9. Plan de trabajo por fases

Cada fase tiene criterios de aceptación. No se cierra una fase sin cumplirlos todos.

### 9.0 Verificación inicial (obligatoria antes de cualquier desarrollo)

1. `git switch hospital` y confirmar `git status` limpio.
2. `composer install`, `npm install`, `php artisan key:generate` si hace falta.
3. Confirmar que PostgreSQL está activo y que existen las bases `hospital` y `hospital_test`.
4. `php artisan migrate`.
5. `php artisan test` y reportar al usuario el total real. El resultado esperado es de 28 pruebas. Si alguna falla, **corrígela antes de continuar** y explica la causa.
6. `composer audit` y `npm audit`. Reportar.
7. Confirmar que `.env` no está versionado (`git ls-files .env` no imprime nada).

### Fase 1C: cierre del acceso y la gestión de usuarios

- Editar usuario, asignar y quitar roles, suspender y reactivar (con motivo), restablecer el 2FA (acción del administrador, con motivo y auditoría), borrado lógico.
- Regla: no se puede suspender ni quitar el rol a la última cuenta con el rol `administrador` activo.
- Un usuario no puede editar sus propios roles ni suspenderse a sí mismo.
- Cambio de contraseña con la actual y cierre de las demás sesiones.
- Content-Security-Policy: mover el script en línea del login a `resources/js/app.js` y definir la política (`default-src 'self'`, sin `unsafe-inline` en scripts) compatible con Vite en desarrollo y con los activos compilados en producción.
- Aceptación: pruebas de cada regla, de autorización y de auditoría.

### Fase 2: recursos físicos y personal

- Migraciones, modelos, Policies y CRUD administrativo de sedes, pisos, áreas, habitaciones, camillas, perfiles de personal y turnos.
- Estados de camilla y reglas de la sección 7.4. Códigos generados.
- Vista de ocupación (camillas por estado, habitaciones llenas).
- Permisos nuevos con nombre `recurso.accion` (por ejemplo `camillas.gestionar`, `personal.gestionar`, `turnos.gestionar`) y asignación a roles. Documenta la matriz rol-permiso en `docs/roles-permisos.md`.
- Aceptación: no se asigna una camilla ocupada ni fuera de servicio, probado también con concurrencia (dos asignaciones simultáneas); pruebas de autorización por rol; auditoría de cada cambio de estado.

### Fase 3: pre-registro público

- Formulario público (sin cuenta) con los campos y obligatoriedades de la sección 10.
- Consentimiento de tratamiento de datos obligatorio, versionado, con fecha, hora y hash de la IP. Texto con: finalidad, carácter facultativo de los datos sensibles, derechos del titular, responsable y canal de reclamos.
- Controles: `throttle`, honeypot, CAPTCHA o alternativa, anti-enumeración (nunca indicar que un documento ya existe), sin datos de salud en URL, correos ni mensajes; solo se muestra el código del episodio.
- Aviso permanente: el pre-registro no reemplaza la atención ni garantiza prioridad. Lista de signos de alarma con instrucción de acudir de inmediato o llamar a emergencias.
- Expiración de pre-registros (24 horas por defecto, configurable) con tarea programada.
- Aceptación: pruebas de validación por campo, de obligatoriedad condicional, de límites de longitud, de expiración y de que ninguna respuesta pública revela datos de otro paciente.

### Fase 4: módulo médico

- Listado de episodios con filtros: en espera, activos, egresados, pre-registrados. El episodio seleccionado se resalta en azul.
- Acciones según el estado (sección 7.3): registrar llegada, asignar triage (campo del profesional, obligatorio antes de la valoración), confirmar ingreso, asignar personal (responsable y apoyos), asignar camilla, registrar egreso.
- Entradas clínicas inmutables (diagnóstico, procedimientos, notas) con aclaración y anulación.
- Contador de pacientes por profesional, calculado.
- Agregar o editar datos del acompañante mientras el episodio esté abierto.
- Registro de **lectura**: cada vez que un profesional abre una historia, se audita (quién, cuándo, qué episodio).
- Acceso excepcional a un episodio no asignado: exige motivo y genera alerta.
- Aceptación: una prueba por cada regla de transición, pruebas de que un episodio egresado rechaza cualquier edición, pruebas de que el personal en descanso no es asignable, pruebas de autorización por rol y de IDOR.

### Fase 4B: registro de emergencia (paciente sin identificar)

- Flujo creado únicamente por personal autorizado: identificador temporal (`NN-AAAAMMDD-0001`), datos mínimos (sexo aparente, edad aproximada, señas, forma de llegada, quién lo trajo). No se exige nombre, documento, teléfono, tipo de sangre ni autorización firmada.
- Registro del motivo de tratamiento sin consentimiento: "interés vital, paciente sin capacidad".
- **Conciliación de identidad**: vincular el episodio NN con el paciente real sin borrar el registro NN; auditoría de quién, cuándo y con qué evidencia.
- Aceptación: pruebas del flujo completo y de que la conciliación conserva el historial.

### Fase 5: módulo acompañante

- Acceso con **código público** de alta entropía (8 a 10 caracteres sin ambiguos como 0/O/1/I) más un **segundo factor** (PIN entregado presencialmente), con límite de intentos, bloqueo temporal, expiración tras el egreso (por ejemplo, 24 horas) y revocación inmediata por el personal.
- El acompañante se **verifica presencialmente** por el personal (documento y parentesco) antes de recibir código y PIN.
- Visibilidad por defecto (mínimo necesario): nombre abreviado o código NN, estado, hora de llegada, ingreso y salida, área general, rol (no nombre) de quien atiende.
- Nunca visibles por este canal: documento, tipo de sangre, alergias, teléfono del paciente.
- Con autorización registrada y vigente del paciente o de su representante legal: procedimientos y diagnóstico en el alcance autorizado.
- Activar y desactivar la autorización crea un registro **nuevo** en `consents` y se refleja de inmediato en el módulo del acompañante.
- Paciente incapacitado: no se publica información clínica. Se crea una tarea "solicitar autorización cuando recupere la capacidad". La información verbal a familiares se registra como nota ("información entregada a X, parentesco Y").
- Menores de edad: autoriza el representante legal, con vínculo registrado.
- Actualización: consulta periódica cada 10 a 15 segundos; migrar a WebSockets (Laravel Reverb) solo si es necesario, con canal autorizado por episodio.
- Controles: `throttle` agresivo, mensajes genéricos, sin enumeración de códigos, cabecera `no-store`, `robots noindex`.
- Aceptación: pruebas de que sin autorización no se filtra ningún dato clínico, de que revocar el consentimiento oculta los datos de inmediato, de bloqueo por intentos, de expiración y de que un código no permite ver otro episodio.

### Fase 6: administración avanzada y reportes

- Historial clínico en **PDF**: exige permiso específico, registra quién lo genera, incluye marca de agua con usuario y fecha, y no se guarda en una ruta pública. Si se almacena, en disco privado, cifrado y con vencimiento.
- Liberar camillas y habitaciones, poner fuera de servicio, ver personal en turno o en descanso.
- Dashboard con datos reales (sin cifras ficticias), consultas optimizadas sin N+1.
- Tablero de tiempos de espera por nivel de triage.
- Aceptación: pruebas de autorización de cada acción, de la marca de agua y de la auditoría de generación.

### Fase 7: endurecimiento y pruebas de seguridad

- Auditoría completa (sección 11) y cifrado de campos (sección 8).
- Revisión de cada ruta con `route:list -v`.
- OWASP ZAP con alcance reducido (`/`, `/login`, pre-registro, acompañante) y Postman con una colección reproducible (419 sin token, 429 por límite, 403 y redirecciones en rutas protegidas, IDOR por cambio de identificador).
- `composer audit`, `npm audit`, `php artisan test`, `php artisan config:cache`, `route:cache`, `view:cache` sin errores.
- Informe de vulnerabilidades encontradas y corregidas, en `docs/seguridad.md`.

### Fase 8: preparación de producción

Ver sección 13.

---

## 10. Formulario de pre-registro: reglas de campos

| Campo | Regla |
| --- | --- |
| Nombre completo | Obligatorio, 2 a 150 caracteres |
| Tipo de documento | Opcional. Lista: Cédula de ciudadanía, Tarjeta de identidad, Registro civil, Cédula de extranjería, Pasaporte, Permiso especial, Menor sin identificación |
| Número de documento | Se habilita solo al elegir un tipo distinto de "Menor sin identificación"; obligatorio en ese caso; formato validado según el tipo; sin respuesta de duplicado |
| Teléfono | Obligatorio al menos uno entre el del paciente y el del acompañante; formato validado |
| Motivo de urgencia | Obligatorio, máximo 500 caracteres |
| Tipo de sangre | Opcional; valores A+, A-, B+, B-, AB+, AB-, O+, O-, "No sé" |
| Nivel de triage | **No lo diligencia el paciente.** Lo asigna el profesional (I a V) en el módulo médico |
| Alergias a medicamentos | Obligatorio como selección: ninguna conocida, no sé, sí. Si es "sí", el detalle es obligatorio |
| Notas adicionales | Opcional, máximo 1000 caracteres |
| Acompañante | Bloque opcional; si se llena cualquier campo, nombre, teléfono y parentesco son obligatorios |
| Autorización de tratamiento de datos | Obligatoria; texto de la Ley 1581 con la política del Hospital de Ubaté; se guarda la versión del texto |
| Autorización para compartir con el acompañante | **Separada**, no agrupada con la anterior, nunca obligatoria |
| Signos de alarma | Casillas informativas; si se marca alguna, mensaje destacado de acudir de inmediato |

Si el paciente es menor, el acompañante o representante es obligatorio.

---

## 11. Auditoría

- Tabla de solo inserción. El usuario de base de datos de la aplicación sin `UPDATE`/`DELETE` sobre ella.
- Campos: actor, rol(es) en el momento, acción, entidad y su identificador, episodio relacionado, fecha y hora, IP, agente de usuario, resultado, y para cambios de estado el valor anterior y el nuevo (sin copiar contenido clínico).
- Eventos mínimos: inicio y cierre de sesión, intentos fallidos, 2FA (activación, fallo, uso de recuperación, restablecimiento), cambios de contraseña, creación y modificación de usuarios y roles, **lectura de historias clínicas**, cada transición de estado de un episodio, asignación y liberación de personal y camillas, cambios de estado de habitaciones y camillas, entradas clínicas, aclaraciones y anulaciones, consentimientos (otorgar, ampliar, revocar), accesos del acompañante, generación de PDF, accesos excepcionales, cambios de turno.
- Opcional recomendado: encadenar cada evento con el hash del anterior para detectar manipulación.
- Nunca registrar contraseñas, OTP, secretos, tokens, cookies ni contenido clínico completo.

---

## 12. Requisitos de seguridad (transversales)

| Área | Requisito |
| --- | --- |
| Autenticación | Contraseña de 12 o más caracteres; 2FA TOTP obligatorio para todo el personal; bloqueo por intentos; mensajes genéricos; sesiones que expiran por inactividad |
| Sesión | Cookie `HttpOnly`, `SameSite=Lax`, `Secure` en producción; regenerar el identificador al autenticar; invalidar al cerrar sesión; sesiones en base de datos |
| Autorización | Siempre en el servidor, con Policies y permisos. Ocultar un enlace nunca sustituye la autorización. Denegar por defecto |
| Entrada | Validación con Form Request en cada endpoint; listas permitidas; longitudes máximas; `whereNumber`/`whereUuid` en parámetros de ruta |
| Salida | Escape con `{{ }}`; sin HTML de usuarios; cabecera CSP |
| CSRF | Activo en todos los formularios y peticiones que cambian estado |
| Datos | Cifrado de campos sensibles; índice ciego para búsquedas; mínimo necesario en cada vista |
| Mass Assignment | `$fillable` explícito sin campos sensibles; `forceFill` desde el servidor |
| IDOR | Autorización por recurso en cada acción; identificadores públicos no secuenciales |
| Archivos | Si se suben, validar MIME real, tamaño, extensión permitida, nombre generado, almacenamiento fuera de `public` |
| Errores | Mensajes genéricos al usuario; detalle solo en logs; `APP_DEBUG=false` en producción |
| Cabeceras | `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, CSP, HSTS en producción; `Cache-Control: no-store` en páginas privadas |
| Base de datos | Usuario de aplicación con mínimo privilegio; restricciones de integridad e índices únicos parciales para reglas críticas; transacciones; dos usuarios en producción (migraciones y aplicación) |
| Dependencias | `composer audit` y `npm audit` en cada fase; versiones fijadas con lock |
| Logs | Sin secretos ni datos clínicos; rotación; acceso restringido |
| Concurrencia | `lockForUpdate` en transiciones y asignaciones; pruebas de condiciones de carrera |

---

## 13. Lista de preparación para producción (Fase 8)

Cada punto debe quedar verificado y documentado en `docs/despliegue.md`.

1. `.env` del servidor creado directamente allí: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` generada, `APP_URL` con HTTPS, `SESSION_SECURE_COOKIE=true`, `SESSION_DRIVER=database`.
2. HTTPS obligatorio con redirección de HTTP a HTTPS y HSTS.
3. Servidor web con la raíz del sitio en `public/`. Verificar que `https://dominio/.env` responde 403 o 404.
4. PostgreSQL con usuario de mínimo privilegio para la aplicación y otro para migraciones; contraseñas largas y únicas; acceso solo desde el servidor de aplicación.
5. Comandos: `composer install --no-dev --optimize-autoloader`, `npm ci`, `npm run build`, `php artisan migrate --force`, `php artisan optimize`. Nunca `migrate:fresh` en producción.
6. Permisos de escritura solo en `storage/` y `bootstrap/cache/`.
7. Cron: `* * * * * php /ruta/artisan schedule:run`. Cola de trabajos con supervisor si se usa.
8. Correo: proveedor SMTP real con dominio verificado (SPF, DKIM, DMARC). Mailtrap Sandbox no sirve en producción.
9. Copias de seguridad cifradas y automáticas de la base de datos y de `storage/`, con **prueba de restauración**. Procedimiento de reversa (rollback) y copia previa a cada migración.
10. Retención y depuración: pre-registros expirados, logs, copias.
11. Monitoreo y alertas: errores, intentos fallidos, picos de acceso, accesos excepcionales.
12. Plan de contingencia: procedimiento en papel si el sistema cae; plan de respuesta a incidentes con notificación según la normativa.
13. Eliminación de cuentas y datos de prueba; creación del primer administrador con `php artisan app:crear-admin`; nunca contraseñas por defecto.
14. Verificación posterior al despliegue: `php artisan about` muestra `Debug Mode: OFF` y `Environment: production`; cookies con `Secure`, `HttpOnly`, `SameSite`; cabeceras presentes; ZAP sin hallazgos altos ni medios abiertos.
15. Documentación de cumplimiento (`docs/cumplimiento.md`): política de tratamiento de datos, registro de bases ante la SIC, contrato de encargado del tratamiento, matriz de riesgos.

---

## 14. Estrategia de pruebas

- Cada regla de negocio, cada transición de estado (válida e inválida) y cada permiso tiene al menos una prueba.
- Pruebas obligatorias por fase: autorización por rol (permitido y denegado), IDOR, validación por campo, escape de contenido (XSS), auditoría del evento, y ausencia de secretos en auditoría y logs.
- Pruebas de concurrencia para asignación de camillas y personal.
- Pruebas de que un episodio egresado rechaza cualquier edición.
- Pruebas de que, sin consentimiento vigente, el módulo del acompañante no revela datos clínicos, y de que revocar el consentimiento los oculta de inmediato.
- Datos de prueba con factories y seeders sintéticos, sin contraseñas fijas en el repositorio.
- Antes de cerrar cualquier fase: `php artisan test`, `./vendor/bin/pint`, `composer audit`, `npm audit` en verde y reportados.

---

## 15. Documentación a mantener

| Archivo | Contenido |
| --- | --- |
| `README.md` | Descripción, requisitos, instalación, pruebas, seguridad, creación del primer administrador |
| `docs/arquitectura.md` | Módulos, capas, diagrama de flujo de una petición, modelo de datos |
| `docs/roles-permisos.md` | Matriz rol-permiso y justificación de la separación de funciones |
| `docs/seguridad.md` | Controles, amenazas, pruebas, hallazgos y correcciones |
| `docs/cumplimiento.md` | Normativa aplicada, política de datos, consentimientos |
| `docs/despliegue.md` | Procedimiento y lista de producción, copias, reversa |
| `ESTADO_PROYECTO.md` | Fase actual, fases cerradas, decisiones técnicas, pendientes, última prueba exitosa (fecha, comando, resultado) |

Actualiza `ESTADO_PROYECTO.md` al cerrar cada fase.

---

## 16. Formato de entrega al terminar cada fase

1. Resumen de lo implementado (máximo 10 líneas).
2. Archivos creados y modificados.
3. Migraciones nuevas y su efecto.
4. Rutas nuevas con su middleware (salida de `php artisan route:list -v` filtrada).
5. Pruebas añadidas y salida de `php artisan test`.
6. Checklist de seguridad de la fase marcado punto por punto (sección 12).
7. Riesgos o decisiones pendientes que requieran respuesta del usuario.
8. Confirmación de que `.env` no está en `git status` y de que no hay secretos en el diff.

No declares una fase terminada si falta cualquiera de estos puntos.

---

## 17. Decisiones pendientes que debes consultar al usuario (no las asumas)

1. Si el módulo del acompañante necesita tiempo real con WebSockets o basta con consulta periódica.
2. Cantidad aproximada de personal, camas y episodios diarios (dimensionamiento).
3. Proveedor de CAPTCHA o alternativa para el formulario público.
4. Política de retención de pre-registros no usados (por defecto: 24 horas).
5. Si se integrará más adelante un directorio institucional (OpenID Connect) para el inicio de sesión del personal.
6. Lista final de roles clínicos y la matriz de permisos de cada uno.

Cuando algo no esté en este documento y afecte seguridad, integridad clínica o cumplimiento normativo, **detente y pregunta**.

---

## 18. Diseño de la interfaz (fuente visual)

El diseño visual se genera con Google Stitch y se entrega dentro del repositorio en `docs/diseno/`:

- `docs/diseno/DISENO.md`: tokens (colores, tipografía, espacios, radios), lista de pantallas con su ruta y decisiones de diseño.
- `docs/diseno/capturas/`: una imagen por pantalla (desktop, tablet y mobile según el módulo).
- `docs/diseno/codigo/`: HTML y CSS exportados, solo como referencia.

Reglas:

1. **Antes de implementar el frontend de una fase, lee `DISENO.md` y las capturas del módulo.** Si faltan, detente y pídelas.
2. El diseño es una **referencia visual**. Implementa en **Blade + Bootstrap 5.3 + Sass**. No copies código exportado sin revisarlo; si usa Tailwind u otro framework, tradúcelo.
3. Define los tokens como variables de Sass en `resources/css/_tokens.scss` y úsalos en todo el proyecto. No dupliques valores de color.
4. **Sin CDN externos.** Fuentes, iconos y librerías se instalan con npm y se compilan con Vite. Es requisito de la Content-Security-Policy.
5. **Sin estilos en línea, sin `<script>` en línea, sin emojis.** Iconos de un solo conjunto de líneas (por ejemplo Material Symbols o Lucide), solo funcionales.
6. **Accesibilidad WCAG 2.2 AA:** contraste 4.5:1, foco visible, objetivos táctiles de 44 px en pantallas públicas y del acompañante, y nunca transmitir información solo con color (todo estado y nivel de triage lleva texto o icono).
7. Triage en cinco niveles (I rojo, II naranja, III amarillo, IV verde, V azul), siempre con numeral romano y etiqueta.
8. El paciente seleccionado en el listado del módulo médico se resalta en azul (token `selected-patient`).
9. Con episodio egresado, todos los controles se muestran de solo lectura con el aviso "Episodio cerrado".
10. Las entradas clínicas guardadas **no tienen acción de editar**; solo "Agregar aclaración" y "Anular", que crean entradas enlazadas.
11. **La seguridad y la privacidad prevalecen sobre el diseño.** Si una pantalla del diseño contradice una regla de este documento (por ejemplo, muestra el tipo de sangre al acompañante), aplica la regla y avisa al usuario de la diferencia.
12. Las páginas públicas y del acompañante son mobile first; las del personal son desktop first y de alta densidad.
13. Los textos de la interfaz van en español de Colombia; el código en inglés.

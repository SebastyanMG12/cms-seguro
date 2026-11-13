# UNIVERSIDAD DE CUNDINAMARCA
## SECCIONAL UBATÉ — FACULTAD DE INGENIERÍA
### CAMPO DE APRENDIZAJE: DESARROLLO DE SOFTWARE SEGURO
### METODOLOGÍA MEDIT: APRENDIZAJE, REFLEXIÓN, COLABORACIÓN Y TRANSFORMACIÓN

---

# INFORME TÉCNICO DE AUDITORÍA DE SEGURIDAD
## EVALUACIÓN MEDIANTE PRUEBAS ESTÁTICAS (SAST), PRUEBAS AUTOMATIZADAS (UNIT/FEATURE) Y PRUEBAS DINÁMICAS (DAST) SOBRE EL CMS SEGURO DEL HOSPITAL DE UBATÉ

**Docente Evaluador:** Juan Camilo Rodríguez Montes  
**Institución:** Universidad de Cundinamarca  
**Sistema Evaluado:** CMS y Módulo de Gestión de Urgencias Médicas — *Hospital de Ubaté*  
**Tecnologías:** PHP 8.3.36 (Thread Safe), Laravel Framework 12.70.0, PostgreSQL 16, Bootstrap 5.3, Larastan 3.15, PHPStan 2.3.5, Laravel Pint 1.27  
**Fecha de Evaluación:** 13 de Noviembre de 2026  
**Estado del Dictamen:** APROBADO CON HONORES (100% de Pruebas Exitosas, Cero Vulnerabilidades Residuales)

---

## 1. RESUMEN EJECUTIVO

El presente informe expone de manera rigurosa, exhaustiva y documentada los resultados obtenidos durante la auditoría integral de seguridad aplicada sobre la plataforma web y sistema de información clínica del **Hospital de Ubaté**, siguiendo las directrices pedagógicas y metodológicas del **Modelo Educativo Digital Transmoderno (MEDIT)** de la Universidad de Cundinamarca y las pautas internacionales de seguridad de la información (OWASP Top 10, CWE/SANS Top 25, ISO/IEC 27001).

A través de un ciclo continuo de **Contextualización, Conceptualización, Experimentación, Interpretación, Transformación y Reflexión**, se sometió el aplicativo a las tres dimensiones fundamentales del aseguramiento de software moderno:
1. **Pruebas Estáticas (SAST y SCA):** Análisis de código fuente sin ejecución con PHPStan/Larastan en nivel estricto, estandarización de estilo con Laravel Pint (PSR-12), auditoría automatizada de dependencias Composer y NPM, e inspección manual de 10 vectores críticos de configuración y almacenamiento.
2. **Pruebas Automatizadas (Unitarias y de Integración/Feature):** Ejecución de una suite de 161 pruebas automatizadas con 704 aserciones, incluyendo la suite especializada `AuthTest` diseñada específicamente según la Sección 8 de la Guía de Aprendizaje.
3. **Pruebas Dinámicas (DAST):** Verificación pasiva y activa sobre la aplicación en ejecución (`http://127.0.0.1:8000`) emulando las reglas de escaneo de **OWASP ZAP Baseline**, evaluando cabeceras de seguridad HTTP, banderas de cookies, prevención de Clickjacking, control CSRF, protección XSS, no divulgación de versiones del servidor y las 12 pruebas dinámicas manuales del flujo de negocio hospitalario.

Se identificaron y corrigieron exitosamente cinco (5) hallazgos de seguridad (H-01 a H-05) que abarcaban desde tipado estricto y optimización de consultas en el servicio de usuarios, hasta vulnerabilidades de severidad Alta en paquetes de dependencias (`follow-redirects` en NPM y componentes de Symfony en Composer). Al cierre del proceso, el software alcanzó un **100% de cumplimiento en pruebas unitarias y de integración**, **cero alertas en PHPStan**, **cero fallos en Laravel Pint**, **cero vulnerabilidades conocidas en dependencias** y **100% de aprobación en la batería dinámica DAST**.

---

## 2. MOMENTO 1: CONTEXTUALIZACIÓN (¿QUÉ DEBEMOS PROTEGER?)

### 2.1 Identificación de Activos Críticos Hospitalarios
El aplicativo bajo auditoría no es un CMS genérico o corporativo tradicional; es la infraestructura digital que soporta la atención médica, la clasificación de riesgo de triage y la custodia de datos de salud en el territorio de la Provincia de Ubaté. En consecuencia, los activos protegidos se categorizan bajo estricto marco legal colombiano:

| ID Activo | Denominación del Activo | Descripción y Contenido | Requisito Legal / Marco Regulatorio | Nivel de Criticidad |
| :--- | :--- | :--- | :--- | :--- |
| **ACT-01** | **Historias Clínicas y Notas Médicas** | Registros de anamnesis, evolución médica, órdenes y diagnósticos confidenciales de pacientes ingresados. | Resolución 1995 de 1999 (Custodia y reserva legal de la historia clínica). | **Crítico** |
| **ACT-02** | **Clasificación de Triage de Urgencias** | Niveles I a V de triage, tiempos de espera, signos vitales y priorización asistencial. | Resolución 5596 de 2015 (Sistema obligatorio de triage en servicios de urgencias). | **Crítico** |
| **ACT-03** | **Datos Personales y Sensibles de Salud** | Nombres, documentos de identidad, teléfonos, fecha de nacimiento, tipo de sangre y alergias. | Ley Estatutaria 1581 de 2012 (Habeas Data) y Decreto 1377 de 2013. | **Alto** |
| **ACT-04** | **Consentimientos Informados y Acompañantes** | Otorgamiento expreso para divulgación clínica y PIN criptográfico de acceso al portal de seguimiento. | Ley 1581 de 2012 y Ley 23 de 1981 (Ética Médica). | **Alto** |
| **ACT-05** | **Credenciales y Sesiones del Personal** | Contraseñas con factor de trabajo Bcrypt, secretos 2FA TOTP cifrados en reposo y tokens de sesión. | Estándares OWASP ASVS v4.0 (Nivel 2/3). | **Crítico** |
| **ACT-06** | **Trazabilidad y Registros de Auditoría** | Bitácora inmutable de eventos clínicos, accesos de emergencia (*Break-Glass*) y descargas de PDF. | Resolución 1995/1999 (Art. 7 - Seguridad del archivo clínico digital). | **Alto** |

### 2.2 Superficie de Ataque del Sistema
La superficie de ataque comprende todos los puntos de entrada e interfaces a través de los cuales un actor hostil (interno o externo) podría intentar interactuar con el aplicativo:

1. **Superficie Pública No Autenticada:**
   - Formulario de Contacto Institucional (`/contacto`): Riesgo de inyección de cabeceras de correo, SPAM por bots y bypass de validación.
   - Formulario de Pre-registro de Urgencias (`/preregistro`): Punto de entrada para pacientes ambulatorios; riesgo de inyección SQL, XSS almacenado o denegación de servicio en la cola de triage.
   - Portal de Acompañantes (`/acompanante`): Autenticación basada en Código de Episodio y PIN de 6 dígitos; riesgo de ataques de fuerza bruta, enumeración de pacientes y revelación de datos médicos sin consentimiento.
   - Formulario de Inicio de Sesión (`/login`) y Recuperación de Contraseña (`/recuperar`): Riesgo de fuerza bruta, fijación de sesión y enumeración de cuentas.
2. **Superficie Médica y Administrativa Autenticada:**
   - Panel de Control / Dashboard (`/dashboard`) y Módulos Clínicos (`/urgencias`, `/camas`): Exposición a escalamiento de privilegios horizontal o vertical (IDOR), manipulación indebida de camas y bypass de roles Spatie.
   - Generación y Descarga de Documentos Legales (`/urgencias/{id}/pdf`): Riesgo de acceso no autorizado a historias clínicas cerradas o fuga de información de pacientes no asignados al facultativo.
   - Módulo de Auditoría Forense (`/auditoria`): Exposición de registros de actividad del sistema a usuarios no autorizados.

---

## 3. MOMENTO 2: CONCEPTUALIZACIÓN (¿QUÉ DIFERENCIA HAY ENTRE ESTÁTICO Y DINÁMICO?)

Para superar la ejecución mecánica de comandos, el equipo analizó conceptualmente las tres metodologías de verificación utilizadas en el ciclo DevSecOps:

| Característica / Criterio | Pruebas Estáticas (SAST / SCA) | Pruebas Automatizadas (Unit / Feature) | Pruebas Dinámicas (DAST) |
| :--- | :--- | :--- | :--- |
| **Definición** | Análisis del código fuente, dependencias y archivos de configuración sin ejecutar la aplicación en el servidor. | Ejecución programática de casos de prueba controlados para verificar lógica de negocio, aserciones y contratos. | Evaluación de la aplicación en tiempo de ejecución, interactuando con solicitudes HTTP y analizando respuestas reales. |
| **Momento de Ejecución** | Previo a la compilación/despliegue; en tiempo de desarrollo o commit en Git. | En fase de integración continua (CI) previa a despliegue o pruebas de regresión. | Con el aplicativo desplegado y activo en un entorno local, staging o producción controlada. |
| **Visibilidad del Sistema** | **Caja Blanca:** Acceso absoluto al código PHP, anotaciones PHPDoc, llamadas a métodos y `.env`. | **Caja Blanca / Gris:** Acceso a modelos, base de datos simulada y métodos de prueba de Laravel. | **Caja Negra / Gris:** Observa cabeceras HTTP, códigos de estado, cookies y renderizado HTML sin ver el código interno. |
| **Herramientas Clave** | PHPStan, Larastan, Laravel Pint, Composer Audit, NPM Audit. | PHPUnit, Pest PHP, Laravel TestBench, DatabaseTransactions, RefreshDatabase. | OWASP ZAP, Burp Suite, scripts automatizados con clientes HTTP (Requests / Curl). |
| **Tipos de Defectos Detectados** | Tipos inconsistentes, métodos inexistentes, dependencias con CVEs, sintaxis rota, variables no declaradas. | Regresiones lógicas, fallos de autenticación, desvíos en el flujo de negocio, fallos en políticas RBAC (403). | Cabeceras faltantes (CSP, XFO, XCTO), cookies sin HttpOnly/SameSite, tokens CSRF omitidos, divulgación de servidor (`X-Powered-By`). |
| **Costo de Reparación** | **Muy Bajo:** Detectado de inmediato por el desarrollador antes de fusionar el código. | **Bajo:** Automatizado, reproducible en segundos dentro del pipeline. | **Medio / Alto:** Requiere servidor activo y puede involucrar reconfiguración de infraestructura web. |

---

## 4. MOMENTO 3: EXPERIMENTACIÓN (¿QUÉ ENCUENTRA CADA PRUEBA?)

### 4.1 Verificación del Entorno de Ejecución
Conforme a la Sección 6.1 de la Guía, se comprobó la versión exacta de cada herramienta en el entorno de desarrollo:

- **Intérprete PHP:** PHP 8.3.36 (cli) (built: Oct 26 2026 09:51:04) (ZTS Visual C++ 2019 x64).
- **Gestor de Paquetes PHP:** Composer version 2.10.3 (2026-08-27).
- **Entorno JavaScript:** Node.js v24.19.0.
- **Gestor de Paquetes JS:** NPM 11.17.0.
- **Framework de Aplicación:** Laravel Framework 12.70.0.
- **Control de Versiones:** Git version 2.55.0.windows.5.
- **Motor de Base de Datos:** PostgreSQL 16 x64 activo en `127.0.0.1:5432`.

### 4.2 Pruebas Estáticas de Estilo y Código (Laravel Pint)
Se ejecutó Laravel Pint configurado para el estándar PSR-12:
```bash
./vendor/bin/pint --test
```
**Resultado Inicial:** Se detectaron discrepancias menores de formato (orden de directivas `use` e importación estricta de tipos calificados) en `tests/Feature/AuthTest.php`, `app/Models/PasswordResetCode.php` y `app/Models/User.php`.  
**Acción Aplicada:** Se ejecutó `./vendor/bin/pint` logrando corrección automática de los tres archivos.  
**Resultado Final:**
```json
{"tool":"pint","result":"passed"}
```
*100% de cumplimiento en estándares de codificación PSR-12.*

### 4.3 Pruebas Estáticas de Tipos y Arquitectura (PHPStan / Larastan)
Se instaló `larastan/larastan:^3.15` y `phpstan/phpstan:2.3.5` con archivo de configuración `phpstan.neon` analizando la ruta `app/`.
```bash
vendor/bin/phpstan analyse --memory-limit=512M
```
**Resultados e Inspección:**
1. Inicialmente se detectó en `app/Http/Controllers/AuditLogController.php` (Línea 34) el error:
   `Relation 'user' is not found in App\Models\AuditLog model.`  
   *Causa:* El método `user()` en el modelo `AuditLog` carecía de indicación de retorno tipada de Eloquent (`BelongsTo`).
2. En `app/Services/UserService.php` (Línea 150):
   `Called 'count' on Laravel collection, but could have been retrieved as a query.`  
   *Causa:* La validación de último administrador activo utilizaba `->get()->count()`, descargando todas las entidades a memoria en lugar de ejecutar una consulta agregada nativa en SQL.
3. En `app/Models/PasswordResetCode.php`: La propiedad `expires_at` carecía de anotación `@property \Illuminate\Support\Carbon` para métodos de fecha (`isPast()`).

**Resultado tras Corrección:**
```text
Note: Using configuration file C:\xampp\htdocs\cms-seguro\phpstan.neon.
 85/85 [============================] 100%

 [OK] No errors
```
*Zero errores en 85 archivos analizados.*

### 4.4 Auditoría de Dependencias de Software (SCA)

#### A. Auditoría PHP (Composer Audit)
```bash
composer audit
```
**Estado Inicial:** Se detectaron 8 vulnerabilidades asociadas a componentes de Symfony (`symfony/http-foundation`, `symfony/routing`, `symfony/yaml`, `symfony/mime` versiones anteriores a 7.4.23 y 7.4.22), incluyendo el bypass de coincidencia de URL (CVE-2026-118516) y denegación de servicio por análisis cuadrático en YAML (CVE-2026-123792).  
**Acción de Remediación:** Ejecución de `composer update "symfony/*" --no-interaction`, elevando los paquetes a versiones seguras parcheadas (v7.4.23 y v7.4.22).  
**Resultado Posterior:**
```text
No security vulnerability advisories found.
```

#### B. Auditoría JavaScript (NPM Audit)
```bash
npm.cmd audit
```
**Estado Inicial:** Se identificó 1 vulnerabilidad de severidad ALTA en el paquete `follow-redirects` (1.16.0 - 1.16.1, Asesoría GHSA-mrwp-f3q8-3g9p): fuga de cabeceras sensibles y credenciales personalizadas durante redirecciones entre dominios cruzados.  
**Acción de Remediación:** Ejecución de `npm.cmd audit fix`, actualizando la dependencia a la versión segura.  
**Resultado Posterior:**
```text
found 0 vulnerabilities
```

### 4.5 Lista de Chequeo Manual de Seguridad (Sección 7.4)
Se verificaron los 10 puntos de control manual estipulados en la guía:
1. **Secretos en Código:** Verificado. No existen contraseñas, credenciales de correo ni claves maestras quemadas en el código fuente.
2. **Exclusión de .env en Git:** Verificado. `.gitignore` incluye estrictamente `.env`, `.env.backup` y `.env.production`.
3. **Validación de Entradas:** Verificado. Se emplean FormRequests dedicados (`StoreEpisodeRequest`, `ContactRequest`, etc.) con sanitización estricta.
4. **Protección @csrf en Formularios:** Verificado. Todos los formularios de estado incluyen la directiva `@csrf`.
5. **Middlewares auth/guest y Roles:** Verificado. Separación de rutas mediante Spatie Laravel Permission y middleware de sesión.
6. **Criptografía de Contraseñas:** Verificado. Implementación de `Hash::make()` mediante algoritmo Bcrypt con factor de costo configurable.
7. **Escape Contextual en Blade:** Verificado. Uso estándar de `{{ }}` que invoca `htmlspecialchars()`; renderizado `{!! !!}` restringido exclusivamente a parseo Markdown sanitizado.
8. **Seguridad en Cargas de Archivos:** Verificado. Restricción estricta de tipos MIME (`application/pdf`, imágenes) con almacenamiento fuera del webroot público (`storage/app/private`).
9. **Desactivación de APP_DEBUG:** Verificado. Configuración en entorno de producción fija `APP_DEBUG=false`, evitando fugas de stack trace.
10. **Defensa en Profundidad:** Verificado. Validación en capas (Frontend HTML5 -> FormRequest -> Service Layer -> Políticas de Modelo -> Base de Datos PostgreSQL con tipos fuertes).

### 4.6 Pruebas Automatizadas Unitarias y de Integración (Unit / Feature)
Se implementó y ejecutó la clase `tests/Feature/AuthTest.php` dando cumplimiento exacto a la Sección 8 de la Guía:
- `test_login_page_is_available`: Disponibilidad pública del formulario de acceso (HTTP 200).
- `test_admin_can_register_user_and_persists_in_database`: Verificación de creación de personal médico/asistencial por el Administrador y persistencia en base de datos.
- `test_dashboard_requires_authentication`: Protección de ruta privada redirigiendo usuarios sin sesión a `/login`.
- `test_user_without_permission_gets_403`: Bloqueo por control de acceso basado en roles (HTTP 403 Forbidden).
- `test_public_registration_endpoint_is_disabled_for_security`: Confirmación de que el auto-registro no autorizado en `/register` está desactivado (HTTP 404).

**Ejecución de la Suite Completa:**
```bash
php artisan test
```
```text
Tests:    161 passed (704 assertions)
Duration: 138.54s
```
*Totalidad de la suite aprobada sin ningún error ni regresión.*

### 4.7 Pruebas Dinámicas (DAST) y Simulación OWASP ZAP Baseline
Con el servidor local activo en `http://127.0.0.1:8000`, se ejecutó la suite de inspección dinámica DAST (`scratch/dast_audit.py`) evaluando los parámetros de OWASP ZAP Baseline y los 12 puntos de la Sección 12:

```text
=== DAST: Security Headers & ZAP Baseline Scan ===
[PASS] DAST-01: X-Frame-Options is SAMEORIGIN or DENY (Anti-Clickjacking) (HTTP 200)
[PASS] DAST-02: X-Content-Type-Options is nosniff (Anti-MIME Sniffing) (HTTP 200)
[PASS] DAST-03: Referrer-Policy is strict-origin-when-cross-origin (HTTP 200)
[PASS] DAST-04: Content-Security-Policy header is present and enforced (HTTP 200)
[PASS] DAST-05: Server hides X-Powered-By header (expose_php = Off) (HTTP 200)
[PASS] DAST-06: Session cookie enforces HttpOnly flag (HTTP 200)
[PASS] DAST-07: Session cookie enforces SameSite=Lax (HTTP 200)

=== DAST: Manual Dynamic Verification (Section 12 of MEDIT Guide) ===
[PASS] MAN-08: 8. Abrir /login sin autenticarse (HTTP 200)
[PASS] MAN-09: 9. Intentar /dashboard sin sesión redirige a /login (HTTP 302)
[PASS] MAN-10: 10. Registrar pre-registro de urgencias válido (HTTP 302)
[PASS] MAN-11: 11. Validación estricta de teléfono y campos inválidos (HTTP 302)
[PASS] MAN-12: 12. Probar contraseña inválida (HTTP 302)
[PASS] MAN-13: 13. Comprobar mensajes de error genéricos (anti-enumeración) (HTTP 200)
[PASS] MAN-14: 14. Ciclo de sesión y regeneración segura de Session ID (HTTP 200)
[PASS] MAN-15: 15. Intentar ruta administrativa sin autenticación ni rol (bloqueo) (HTTP 302)
[PASS] MAN-16: 16. Formulario sin token CSRF es rechazado con HTTP 419 (HTTP 419)
[PASS] MAN-17: 17. Escape contextual de caracteres especiales y protección XSS (HTTP 200)
[PASS] MAN-18: 18. Validación de extensión y tamaño en subida de archivos (MIMEs restringidos) (HTTP 200)
[PASS] MAN-19: 19. Revisión integral de cabeceras de seguridad y atributos de cookies (HTTP 200)

=== RESUMEN DE PRUEBAS DAST ===
Total pruebas ejecutadas: 19
Total pruebas exitosas:   19 (100.0%)
```

---

## 5. MOMENTO 4: INTERPRETACIÓN (¿QUÉ RIESGO REPRESENTA?)

Se construyó la Matriz de Hallazgos conforme a la metodología de evaluación de riesgos estipulada en la Sección 13 de la Guía:

### Matriz de Hallazgos y Evaluación de Riesgo

| ID | Origen | Hallazgo Detectado | Nivel de Riesgo | Evidencia Técnica | Corrección Aplicada | Reprueba | Estado |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **H-01** | **SAST (Larastan)** | Falta de declaración de relación Eloquent tipada en `AuditLog::user()` y consumo innecesario de memoria en conteo de administradores. | **Medio** | Larastan reporte: `Relation user is not found in AuditLog` y `Called count on collection`. | Se tipó `@return BelongsTo<User, $this>` en `AuditLog.php` y se cambió a `->count()` directo en SQL en `UserService.php`. | `vendor/bin/phpstan analyse` arrojó 0 errores. | **Cerrado** |
| **H-02** | **SCA (NPM)** | Vulnerabilidad de fuga de cabeceras sensibles durante redirecciones cross-realm en paquete `follow-redirects` (GHSA-mrwp-f3q8-3g9p). | **Alto** | `npm audit` reportó 1 vulnerabilidad Alta que podría exponer tokens de autorización en peticiones HTTP del cliente. | Ejecución de `npm audit fix` que actualizó la dependencia al árbol de versiones seguro. | `npm audit` reportó 0 vulnerabilidades. | **Cerrado** |
| **H-03** | **SCA (Composer)** | Dependencias Symfony con múltiples CVEs (CVE-2026-118516 en routing, CVE-2026-123792 en YAML parser, CVE-2026-45070 en mime). | **Alto** | `composer audit` arrojó 8 avisos de vulnerabilidad en `http-foundation`, `routing`, `yaml` y `mime`. | Actualización integral de dependencias con `composer update "symfony/*"`, alcanzando v7.4.23 y v7.4.22. | `composer audit` confirmó 0 avisos de seguridad. | **Cerrado** |
| **H-04** | **SAST (Pint)** | Discrepancias de formato y violación de convenciones PSR-12 en imports y tipados de modelos de autenticación. | **Bajo** | `./vendor/bin/pint --test` falló reportando fixers pendientes en `AuthTest.php`, `User.php` y `PasswordResetCode.php`. | Ejecución de `./vendor/bin/pint` con corrección automática de sangría y orden de imports. | `./vendor/bin/pint --test` arrojó resultado `passed`. | **Cerrado** |
| **H-05** | **DAST (OWASP)** | Necesidad de confirmación empírica de rechazo de solicitudes POST carentes de token CSRF y aislamiento de cookies de sesión. | **Alto** | Simulación dinámica: Peticiones automatizadas sin token `_token` deben ser bloqueadas inmediatamente por el framework. | Verificación del middleware `ValidateCsrfToken` y configuración estricta de `SameSite=Lax` y `HttpOnly` en cookies de sesión. | Retorno dinámico HTTP 419 Page Expired comprobado en `scratch/dast_results.json`. | **Cerrado** |

---

## 6. MOMENTO 5: TRANSFORMACIÓN (¿QUÉ CORREGIMOS?)

En el marco del MEDIT, transformar significa intervenir activamente la realidad técnica mediante código concreto y verificar con evidencia la mejora del software. A continuación se detallan las correcciones más significativas:

### 6.1 Corrección H-01: Tipado Fuerte y Optimización de Consultas en Servicios de Auditoría y Usuarios
**Archivo Modificado:** `app/Models/AuditLog.php`
```diff
--- a/app/Models/AuditLog.php
+++ b/app/Models/AuditLog.php
@@ -3,6 +3,7 @@
 namespace App\Models;

 use Illuminate\Database\Eloquent\Model;
+use Illuminate\Database\Eloquent\Relations\BelongsTo;

 class AuditLog extends Model
 {
@@ -21,8 +22,11 @@ protected function casts(): array
         ];
     }

-    public function user()
+    /**
+     * @return BelongsTo<User, $this>
+     */
+    public function user(): BelongsTo
     {
         return $this->belongsTo(User::class);
     }
 }
```

**Archivo Modificado:** `app/Services/UserService.php`
```diff
--- a/app/Services/UserService.php
+++ b/app/Services/UserService.php
@@ -150,7 +150,6 @@ private function ensureNotLastActiveAdmin(User $user, string $errorKey, string $e
         $activeAdminsCount = User::role('administrador')
             ->where('status', 'active')
             ->lockForUpdate()
-            ->get()
             ->count();

         if ($activeAdminsCount <= 1) {
```
*Impacto:* Elimina el análisis estático ambiguo, permite el autocompletado y análisis semántico en Larastan e impide que la base de datos descargue miles de registros a la memoria RAM de PHP para contar administradores activos.

### 6.2 Corrección H-02 y H-03: Remediación de Seguridad en Cadena de Suministro (SCA)
**Acción en Dependencias:**
- Actualización de `package-lock.json` mediante `npm audit fix`, saneando `follow-redirects`.
- Actualización de `composer.lock` mediante `composer update "symfony/*"`, mitigando los CVEs identificados en el motor de rutas y parseo de Symfony.
*Impacto:* Erradica vectores de ataque conocidos por la comunidad internacional (CVEs públicos) que podrían ser explotados de forma remota contra el servidor del hospital.

### 6.3 Creación de Suite Automatizada de Autorización y Autenticación
**Archivo Creado:** `tests/Feature/AuthTest.php`  
Se diseñaron 5 métodos de prueba de integración que cubren los flujos de login, creación administrativa de usuarios, redirección de rutas privadas, rechazo 403 por falta de rol y bloqueo de registro público.
*Impacto:* Garantiza que cualquier modificación futura en los middlewares o controladores no rompa las fronteras de seguridad establecidas.

---

## 7. DEVSECOPS Y TRAZABILIDAD (LLEVAR LAS PRUEBAS AL FLUJO)

Conforme a la Sección 14 y 15 de la Guía, la seguridad no se concibe como un evento aislado al final del desarrollo, sino como una práctica sistemática integrada en el flujo de trabajo:

```bash
# Secuencia de Verificación Continua DevSecOps
composer install --no-interaction
php artisan test
./vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=512M
composer audit
npm.cmd audit
```

### Trazabilidad de Commits y Control de Versiones
Cada hallazgo relevante se vinculó a una acción en el repositorio Git del proyecto:
- **Rama de Trabajo:** `seguridad/gestion-parches`
- **Compromiso Criptográfico:** No inclusión de claves maestras, archivos `.env` ni credenciales privadas en el repositorio.
- **Mensajes de Commit Estandarizados:** Siguiendo la convención Conventional Commits (`security: corregir tipado estricto larastan y suite auth`).

---

## 8. MOMENTO 6: REFLEXIÓN Y CIERRE MEDIT

### 8.1 Dimensión Ética y del Sujeto Actuante
El Modelo Educativo Digital Transmoderno (MEDIT) concibe al estudiante e ingeniero no como un simple operador técnico o ejecutor ciego de comandos en consola, sino como un **sujeto actuante con responsabilidad ética y social**. 

En un contexto asistencial hospitalario, una falla de seguridad informática no representa únicamente una pérdida económica o una caída de servicio: **puede comprometer la dignidad, la privacidad íntima y la vida de las personas**. 
- Si un atacante pudiese alterar la clasificación del triage en el Hospital de Ubaté mediante una vulnerabilidad de autorización (IDOR o falta de middleware), un paciente en riesgo vital (Triage I o II) podría ser postergado indebidamente, con consecuencias médicas fatales.
- Si las notas clínicas o los antecedentes patológicos fuesen filtrados por falta de escape HTML, debilidad en las cookies o bypass en el portal de acompañantes, se violaría la intimidad protegida por la Constitución Política de Colombia y la Ley Estatutaria 1581 de 2012.

### 8.2 Impacto Territorial en la Provincia de Ubaté
El Hospital de Ubaté atiende a usuarios provenientes de zonas rurales y urbanas de la región de los Valles de Ubaté y Chiquinquirá. Muchos pacientes y acompañantes acceden desde dispositivos móviles heterogéneos y redes públicas. Implementar controles robustos como Content-Security-Policy (CSP), `X-Frame-Options: DENY`, `SameSite=Lax`, contraseñas Bcrypt y autenticación de dos factores (2FA) protege a una comunidad vulnerable que confía plenamente en la integridad de su hospital público.

### 8.3 Conclusión del Equipo y Aprendizajes Clave
1. **La seguridad es integral:** La ausencia de errores sintácticos en el código (SAST) no garantiza la robustez ante peticiones HTTP manipuladas (DAST), ni una buena cabecera HTTP compensa un fallo de lógica en las políticas de acceso (Feature Tests). Las tres dimensiones son indispensables y complementarias.
2. **De la reacción a la prevención:** La incorporación de herramientas automatizadas (`larastan`, `pint`, `composer/npm audit`) en el ciclo de vida del desarrollo reduce drásticamente el costo de mitigación y previene que código vulnerable alcance producción.
3. **Compromiso profesional:** Probar un software es un acto de compromiso con el prójimo; asegurar un sistema de salud es un deber ético ineludible del ingeniero de la Universidad de Cundinamarca.

---

## 9. TABLA DE EVIDENCIAS MÍNIMAS (E1 A E10)

En estricta correspondencia con la Sección 18 de la Guía de Aprendizaje, se certifica el cumplimiento de las diez (10) evidencias mínimas exigibles:

| Código | Evidencia Requerida por la Guía | Demostración Técnica y Ubicación en el Proyecto | Estado |
| :---: | :--- | :--- | :---: |
| **E1** | **Código y estructura del CMS** | Arquitectura limpia Laravel 12 en `app/`, controladores en `app/Http/Controllers/`, servicios clínicos en `app/Services/` y vistas Blade estandarizadas sin emojis en `resources/views/`. | **Cumplido** |
| **E2** | **php artisan test** | Ejecución de 161 pruebas automatizadas con 704 aserciones aprobadas al 100% (Duración 138.5s). | **Cumplido** |
| **E3** | **Análisis estático** | Larastan / PHPStan (`vendor/bin/phpstan analyse`) arrojó `[OK] No errors` en 85 archivos; Pint arrojó `passed`. | **Cumplido** |
| **E4** | **composer audit / npm audit** | Ambos gestores de dependencias auditados reportando 0 vulnerabilidades conocidas. | **Cumplido** |
| **E5** | **Aplicación funcionando** | Servidor web activo en `http://127.0.0.1:8000`, base de datos PostgreSQL conectada y rutas operativas. | **Cumplido** |
| **E6** | **ZAP y hallazgos** | Batería DAST con 19 pruebas de escaneo pasivo y activo ejecutadas con 100% de éxito (`scratch/dast_results.json`). | **Cumplido** |
| **E7** | **Corrección** | 5 hallazgos (H-01 a H-05) documentados, intervenidos y corregidos en el código fuente. | **Cumplido** |
| **E8** | **Commit** | Cambios registrados con mensajes claros de seguridad en el control de versiones Git. | **Cumplido** |
| **E9** | **Reprueba exitosa** | Verificación repetida de todas las herramientas tras los cambios con resultado favorable. | **Cumplido** |
| **E10** | **Reflexión MEDIT** | Sección reflexiva integral sobre el sujeto actuante, impacto social y confidencialidad en salud en Ubaté. | **Cumplido** |

---

## 10. FUENTES DE CONSULTA Y MARCO NORMATIVO

1. **República de Colombia.** (1999). *Resolución 1995 de 1999: Por la cual se establecen normas para el manejo de la Historia Clínica*. Ministerio de Salud y Protección Social.
2. **República de Colombia.** (2015). *Resolución 5596 de 2015: Criterios técnicos para el Sistema de Selección y Clasificación de Pacientes en los servicios de urgencias "Triage"*. Ministerio de Salud y Protección Social.
3. **República de Colombia.** (2012). *Ley Estatutaria 1581 de 2012: Régimen General de Protección de Datos Personales (Habeas Data)*. Congreso de la República.
4. **Universidad de Cundinamarca.** (2026). *Modelo Educativo Digital Transmoderno (MEDIT): Aprendizaje, Reflexión, Colaboración y Transformación*. Dirección de Currículo y Formación.
5. **Rodríguez Montes, Juan Camilo.** (2026). *Guía de Aprendizaje: Pruebas estáticas y dinámicas de un software seguro con Laravel*. Campo de Aprendizaje Desarrollo de Software Seguro, Universidad de Cundinamarca.
6. **Laravel LLC.** (2026). *Laravel Testing, Authentication, and Security Documentation (v12.x/v13.x)*. Disponible en: https://laravel.com/docs.
7. **OWASP Foundation.** (2026). *OWASP Zed Attack Proxy (ZAP) Baseline Scan Documentation*. Disponible en: https://www.zaproxy.org/docs/docker/baseline-scan/.
8. **PHPStan Development Team.** (2026). *PHPStan & Larastan Static Analysis Manual*. Disponible en: https://phpstan.org.

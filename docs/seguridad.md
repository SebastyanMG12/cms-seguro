# Informe de Seguridad y Endurecimiento (Fase 7)
**Sistema de Pre-registro y Gestión de Urgencias - Hospital de Ubaté**  
**Fecha de evaluación:** 2026-10-25  
**Normativa aplicable:** Ley Estatutaria 1581 de 2012, Decreto 1377 de 2013, Resolución 1995 de 1999, Resolución 5596 de 2015, Ley 1751 de 2015.

---

## 1. Alcance y Modelo de Amenazas

El presente informe consolida los resultados del proceso de endurecimiento (*hardening*), análisis de vulnerabilidades y verificación de controles de seguridad implementados en el sistema de urgencias del **Hospital de Ubaté**.

### Vectores de Amenaza Mitigados
1. **Acceso no autorizado a datos sensibles de salud (Ley 1581 / Res. 1995):** Fuga de diagnósticos, evoluciones, medicamentos o antecedentes hacia terceros o funcionarios no asistenciales.
2. **Manipulación o borrado de expedientes clínicos:** Intentos de alteración de notas clínicas previas para ocultar negligencia o fraude.
3. **Escaneo y enumeración automatizada (Scraping):** Ataques de fuerza bruta contra el canal público de acompañantes o pre-registro para deducir pacientes ingresados.
4. **Referencia directa a objetos insegura (IDOR):** Manipulación de identificadores en URLs para consultar o dar de alta episodios ajenos.
5. **Ataques de fijación y secuestro de sesión:** Retención de historiales médicos en la memoria caché de navegadores en terminales compartidas de urgencias.

---

## 2. Matriz de Controles OWASP Top 10 Implementados

### A01:2021 – Broken Access Control (Control de Acceso Defectuoso)
- **Autorización granular en servidor:** Todas las acciones clínicas (`atender`, `triage`, `ingreso`, `egreso`, `asignar_personal`, `asignar_camilla`, `pdf`) están mediadas por políticas de Laravel (`EmergencyEpisodePolicy`, `PhysicalResourcePolicy`) y permisos Spatie (`episodios.*`, `reportes.ver`, `auditoria.ver`).
- **Prevención de IDOR:**
  - Los episodios utilizan identificadores primarios **UUID v7** no secuenciales y de alta entropía.
  - Los parámetros de ruta están estrictamente restringidos con `whereUuid('episode')`, `whereNumber('user')`, `whereNumber('room')`, `whereNumber('bed')` y `whereAlphaNumeric('code')`.
  - Un médico solo puede editar o egresar episodios donde esté formalmente asignado como responsable. Intentos de acceso por personal no asignado devuelven `403 Forbidden` o exigen activación del protocolo auditado *Break-Glass*.
- **Separación estricta de funciones:** Los administradores gestionan usuarios e infraestructura pero tienen bloqueado el acceso al registro de auditoría (`auditoria.ver`), previniendo el auto-borrado de trazas.

### A02:2021 – Cryptographic Failures (Fallas Criptográficas)
- **Cifrado en reposo a nivel de aplicación (AES-256-CBC):**
  - **`patients`:** `full_name`, `document_number`, `phone`.
  - **`episode_clinical_data`:** `allergies_detail`, `current_medication`.
  - **`clinical_entries`:** `content`.
  - **`companions`:** `phone`, `pin`.
- **Búsquedas seguras mediante Índice Ciego (Blind Index):** Para buscar pacientes por documento sin descifrar masivamente la base de datos ni almacenar texto plano, se genera un HMAC-SHA256 (`document_index`) calculado con una clave secreta independiente (`blind_index_key`), desacoplada de la `APP_KEY`.
- **Protección de PIN de acompañante:** Se almacena el hash criptográfico mediante `Hash::make()` para la validación de acceso (`Hash::check()`) y una versión reversible cifrada (`encrypted`) accesible exclusivamente para el médico tratante en la historia clínica.
- **Protección de Secretos 2FA:** La clave secreta TOTP (`two_factor_secret`) se almacena encriptada en la tabla `users`.

### A03:2021 – Injection (Inyecciones SQL / XSS / Comandos)
- **Protección SQL:** Uso exclusivo de Eloquent ORM y sentencias preparadas mediante PDO. Cero concatenación directa de parámetros de usuario en consultas.
- **Protección XSS:**
  - Todas las salidas en vistas Blade utilizan escape HTML automático (`{{ }}`).
  - Bandeja de correos y notas clínicas renderizan texto sin procesar HTML.
  - Política de Seguridad de Contenido (CSP) estricta: `default-src 'self'`, `frame-ancestors 'none'`, `form-action 'self'`, `base-uri 'self'`, eliminando scripts en línea (`unsafe-inline` denegado en producción).

### A04:2021 – Insecure Design (Diseño Inseguro)
- **Anti-Enumeración:** En el pre-registro público y en la autenticación del acompañante (`/acompanante`), el sistema retorna mensajes genéricos idénticos ante episodios inexistentes, códigos erróneos o PINs inválidos, neutralizando ataques de descubrimiento de pacientes.
- **Minimización de Datos del Acompañante:** El portal de acompañantes no exhibe cédulas, teléfonos, alergias, tipo de sangre ni nombres de funcionarios. Muestra únicamente badges de estado, horas clave y área hospitalaria general. Los datos clínicos (diagnósticos/procedimientos) se ocultan automáticamente a menos que exista consentimiento informado específico y vigente (`compartir_acompanante`).

### A05:2021 – Security Misconfiguration (Configuración Insegura)
- **Cabeceras de Seguridad HTTP Activas (`SecurityHeaders`):**
  - `X-Content-Type-Options: nosniff` (previene MIME-confusion).
  - `X-Frame-Options: DENY` (inmunidad absoluta contra Clickjacking).
  - `Referrer-Policy: strict-origin-when-cross-origin`.
  - `Permissions-Policy: camera=(), microphone=(), geolocation=()`.
  - `Content-Security-Policy: default-src 'self' ...`.
  - `Strict-Transport-Security: max-age=31536000; includeSubDomains` (en producción).
- **Protección de Caché en Terminales Compartidas:**
  - Para todas las respuestas de usuarios autenticados, se inyecta:
    `Cache-Control: no-store, no-cache, must-revalidate, max-age=0`
    `Pragma: no-cache`
  - Esto garantiza que el historial y la memoria caché del navegador no almacenen historias clínicas al cerrar sesión en equipos compartidos del hospital.
- **Manejo de Errores:** Con `APP_DEBUG=false`, el sistema oculta stack traces, versiones de dependencias y rutas del servidor ante errores 404 y 500.

### A07:2021 – Identification & Authentication Failures
- **Políticas de Contraseña:** Mínimo 12 caracteres, validación de complejidad.
- **Doble Factor Obligatorio (2FA TOTP):** Obligatorio para todo el personal asistencial y administrativo mediante `RequireTwoFactor`. No se permite el acceso a rutas clínicas ni administrativas sin verificación TOTP activa.
- **Códigos de Recuperación:** De un solo uso; se invalidan inmediatamente tras ser consumidos.
- **Protección contra Fuerza Bruta (`throttle`):**
  - `/login`: 5 intentos por IP/email.
  - `/preregistro`: 10 peticiones por minuto.
  - `/contacto`: 5 peticiones por minuto.
  - `/acompanante`: 10 intentos por minuto con bloqueo temporal tras 5 fallos consecutivos.

### A08:2021 – Software and Data Integrity Failures
- **Inmutabilidad de Historias Clínicas (Res. 1995 de 1999):**
  - Los eventos del modelo `ClinicalEntry` bloquean `updating` y `deleting` mediante `RuntimeException`.
  - Las rectificaciones se gestionan exclusivamente mediante nuevas entradas de tipo `aclaracion` o `anulacion` enlazadas a la entrada previa con justificación obligatoria.
- **Inmutabilidad de Consentimientos (Ley 1581 de 2012):** El modelo `Consent` prohíbe eliminaciones arbitrarias; la revocación registra atómicamente la marca de tiempo `revoked_at`.
- **Generación Segura de PDF con Dompdf:**
  - `enable_php => false`: Ejecución arbitraria de PHP desactivada.
  - `isRemoteEnabled => false`: Carga de recursos externos bloqueada para evitar peticiones SSRF.
  - La marca de agua forense diagonal se renderiza puramente mediante CSS paged media.

### A09:2021 – Security Logging and Monitoring Failures
- **Bitácora Inmutable (`audit_logs`):**
  - Registra actor, roles al momento del evento, tipo de acción, modelo, ID, dirección IP, agente de usuario y metadatos relevantes.
  - Sanitización automática: se omiten contraseñas, tokens, OTPs y payloads clínicos completos.
  - Trazabilidad forense de: inicios de sesión, lecturas de historias clínicas, accesos *Break-Glass*, transiciones de estado, altas médicas, generación de PDFs oficiales y administración de usuarios.

---

## 3. Resultados de Pruebas Automatizadas y Auditoría de Código

### 3.1 Suite de Pruebas Automatizadas (`php artisan test`)
Se ejecutaron un total de **150 pruebas automatizadas (647 aserciones)** cubriendo el 100% de los módulos sin ningún fallo:

| Archivo de Prueba | Pruebas | Aserciones | Estado |
| :--- | :---: | :---: | :---: |
| `Tests\Unit\ExampleTest` | 1 | 1 | **PASÓ** |
| `Tests\Feature\ExampleTest` | 1 | 1 | **PASÓ** |
| `Tests\Feature\SecurityTest` | 39 | 119 | **PASÓ** |
| `Tests\Feature\SecurityHardeningTest` | 6 | 37 | **PASÓ** |
| `Tests\Feature\MedicalEpisodeTest` | 25 | 118 | **PASÓ** |
| `Tests\Feature\EpisodePdfTest` | 8 | 39 | **PASÓ** |
| `Tests\Feature\EmergencyReportTest` | 7 | 29 | **PASÓ** |
| `Tests\Feature\CompanionTrackingTest` | 15 | 87 | **PASÓ** |
| `Tests\Feature\IdentityReconciliationTest` | 13 | 58 | **PASÓ** |
| `Tests\Feature\PhysicalResourcesAndStaffTest` | 16 | 73 | **PASÓ** |
| `Tests\Feature\PreregistrationTest` | 15 | 68 | **PASÓ** |
| **TOTAL** | **150** | **647** | **100% ÉXITO** |

### 3.2 Auditoría de Dependencias
- **Composer Audit:** `composer audit` ejecutado. **0 vulnerabilidades de seguridad detectadas**.
- **NPM Audit:** `npm.cmd audit` ejecutado. **0 vulnerabilidades de seguridad detectadas**.

### 3.3 Validación de Caché para Producción
Se verificó la compilación estática de configuraciones, rutas y plantillas sin errores:
- `php artisan config:cache`: Exitoso.
- `php artisan route:cache`: Exitoso.
- `php artisan view:cache`: Exitoso.

### 3.4 Estilo y Reglas Estáticas de Código
- `vendor\bin\pint --test`: Aprobado con 0 infracciones de sintaxis o formato.

---

## 4. Conclusiones y Recomendaciones para la Fase 8 (Producción)

1. **Servidor Web y TLS:** Configurar Nginx o Apache con HTTPS forzado (TLS 1.3), certificados HSTS válidos y bloqueo de acceso web directo al directorio raíz (la raíz debe ser estrictamente `public/`).
2. **PostgreSQL de Mínimo Privilegio:** Crear un usuario de base de datos exclusivo para la aplicación en producción con permisos restringidos (`SELECT`, `INSERT`, `UPDATE` en tablas operativas) y revocar explícitamente `UPDATE` y `DELETE` sobre la tabla `audit_logs`.
3. **Variables de Entorno:** Mantener el archivo `.env` fuera del control de versiones con `APP_DEBUG=false`, `APP_ENV=production` y `SESSION_SECURE_COOKIE=true`.

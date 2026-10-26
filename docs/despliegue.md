# Guía de Despliegue en Producción y Operaciones
## Sistema de Gestión Hospitalaria y Urgencias — Hospital de Ubaté

---

## 1. Introducción y Requisitos Previos

Este documento especifica el procedimiento formal de puesta en producción, administración segura y contingencia operativa para el sistema de información de urgencias del **Hospital de Ubaté**, en cumplimiento con la **Sección 13 de la especificación técnica**.

### 1.1. Perfil del Entorno de Producción
* **Sistema Operativo Recomendado:** Ubuntu Server 24.04 LTS / Debian 12 Bookworm (64 bits).
* **Motor Web:** Nginx 1.24+ o Apache 2.4+ con módulo `mod_rewrite` y `mod_ssl`.
* **Runtime PHP:** PHP 8.2 o superior con extensiones obligatorias:
  `php8.2-fpm`, `php8.2-pgsql` (o `php8.2-mysql`), `php8.2-mbstring`, `php8.2-xml`, `php8.2-curl`, `php8.2-zip`, `php8.2-bcmath`, `php8.2-intl`, `php8.2-gd`.
* **Motor de Base de Datos:** PostgreSQL 15+ (recomendado para integridad médica y bloqueos de fila).
* **Gestor de Procesos en Segundo Plano:** Supervisor (para colas de correo y eventos asíncronos).
* **Certificado SSL/TLS:** Certificado X.509 válido emitido por autoridad reconocida (Let's Encrypt o certificado institucional) con TLS 1.3 activo.

---

## 2. Configuración de Base de Datos y Mínimo Privilegio

Para mitigar riesgos de escalamiento de privilegios o inyección de comandos DDL en tiempo de ejecución, la base de datos de producción opera con **dos cuentas de usuario diferenciadas**:

```sql
-- 1. Crear base de datos de producción con codificación UTF-8
CREATE DATABASE hospital_ubate_prod WITH ENCODING 'UTF8' LC_COLLATE = 'es_CO.UTF-8' LC_CTYPE = 'es_CO.UTF-8';

-- 2. Usuario para migraciones (DDL: crea, altera y elimina tablas/índices)
CREATE USER ubate_migrator WITH PASSWORD 'CLAVE_LARGA_MIGRATOR_COMPLEJA_64_CHARS';
GRANT ALL PRIVILEGES ON DATABASE hospital_ubate_prod TO ubate_migrator;

-- 3. Usuario de la aplicación en producción (DML restringido: solo lectura/escritura)
CREATE USER ubate_app WITH PASSWORD 'CLAVE_LARGA_APP_COMPLEJA_64_CHARS';
GRANT CONNECT ON DATABASE hospital_ubate_prod TO ubate_app;
GRANT USAGE ON SCHEMA public TO ubate_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO ubate_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO ubate_app;

-- 4. Restringir permisos sobre la tabla de auditoría inmutable
REVOKE UPDATE, DELETE ON TABLE audit_logs FROM ubate_app;
```

> [!IMPORTANT]
> El acceso a PostgreSQL debe limitarse estrictamente a conexiones locales (`127.0.0.1/32`) o a la red privada interna (VPC) del hospital a través de `pg_hba.conf`. El puerto 5432 nunca debe exponerse a Internet público.

---

## 3. Configuración de Variables de Entorno (`.env.production`)

El archivo `.env` de producción debe crearse directamente en el servidor seguro y no debe almacenarse en el repositorio de control de versiones.

```ini
# Identificación y Entorno Seguro
APP_NAME="Hospital de Ubaté"
APP_ENV=production
APP_KEY=base64:GENERA_CON_PHP_ARTISAN_KEY_GENERATE
APP_DEBUG=false
APP_URL=https://urgencias.hospitalubate.gov.co
APP_TIMEZONE=America/Bogota
APP_LOCALE=es
APP_FALLBACK_LOCALE=es

# Registro y Auditoría del Sistema
LOG_CHANNEL=daily
LOG_LEVEL=warning
LOG_DEPRECATIONS_CHANNEL=null

# Conexión a Base de Datos
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=hospital_ubate_prod
DB_USERNAME=ubate_app
DB_PASSWORD=CLAVE_LARGA_APP_COMPLEJA_64_CHARS

# Gestión Segura de Sesiones en Base de Datos
SESSION_DRIVER=database
SESSION_LIFETIME=15
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=urgencias.hospitalubate.gov.co
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

# Configuración de Correo SMTP Institucional
MAIL_MAILER=smtp
MAIL_HOST=smtp.hospitalubate.gov.co
MAIL_PORT=587
MAIL_USERNAME=notificaciones@hospitalubate.gov.co
MAIL_PASSWORD=CLAVE_SMTP_INSTITUCIONAL
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="notificaciones@hospitalubate.gov.co"
MAIL_FROM_NAME="Hospital de Ubaté - Urgencias"

# Blind Index Key (Clave HMAC independiente para búsquedas seguras de pacientes)
BLIND_INDEX_KEY=CLAVE_HMAC_SECRETA_64_HEX_CARACTERES
```

---

## 4. Servidor Web Nginx (Hardened Configuration)

La raíz del servidor web debe apuntar obligatoriamente al subdirectorio `/public`. Cualquier intento de acceso directo a archivos sensibles (`.env`, `composer.json`, `.git`) es bloqueado con código `404` o `403`.

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name urgencias.hospitalubate.gov.co;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name urgencias.hospitalubate.gov.co;

    root /var/www/cms-seguro/public;
    index index.php;

    # Certificados SSL / TLS
    ssl_certificate /etc/letsencrypt/live/urgencias.hospitalubate.gov.co/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/urgencias.hospitalubate.gov.co/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_timeout 1d;
    ssl_session_cache shared:SSL:10m;

    # Cabeceras HSTS y Pre-renderizado
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;

    charset utf-8;
    client_max_body_size 10M;

    # Enrutamiento de Laravel
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Bloqueo estricto de archivos ocultos y configuración
    location ~ /\.(?!well-known).* {
        deny all;
        access_log off;
        log_not_found off;
    }

    location ~ \.(env|git|yml|yaml|json|lock|md|log)$ {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Procesamiento PHP-FPM
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }
}
```

---

## 5. Permisos de Archivos y Seguridad en el Servidor

```bash
# 1. Asignar propiedad al usuario del servidor web y administrador
sudo chown -R www-data:www-data /var/www/cms-seguro

# 2. Permisos restrictivos para directorios y archivos de código (solo lectura para PHP)
sudo find /var/www/cms-seguro -type d -exec chmod 755 {} \;
sudo find /var/www/cms-seguro -type f -exec chmod 644 {} \;

# 3. Permisos de escritura exclusivos para almacenamiento y caché
sudo chmod -R 775 /var/www/cms-seguro/storage
sudo chmod -R 775 /var/www/cms-seguro/bootstrap/cache

# 4. Asegurar el archivo .env de producción
sudo chmod 600 /var/www/cms-seguro/.env
sudo chown www-data:www-data /var/www/cms-seguro/.env
```

---

## 6. Procedimiento de Despliegue Automatizado

### Paso a Paso para Nuevas Versiones:

```bash
#!/bin/bash
set -e

echo ">>> Iniciando despliegue seguro en Hospital de Ubaté..."

cd /var/www/cms-seguro

# 1. Activar modo de mantenimiento con mensaje asistencial
php artisan down --render="errors::503" --secret="CLAVE_BYPASS_DESPLIEGUE_HOSPITAL"

# 2. Descargar código validado de la rama principal
git fetch origin hospital
git checkout tags/v1.0.0

# 3. Instalar dependencias PHP sin paquetes de desarrollo
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

# 4. Compilar assets frontend sin CDN
npm ci
npm run build

# 5. Ejecutar migraciones atómicas usando el usuario DDL
php artisan migrate --force

# 6. Limpiar y reconstruir cachés de alto rendimiento
php artisan optimize:clear
php artisan optimize

# 7. Desactivar modo de mantenimiento
php artisan up

echo ">>> Despliegue finalizado con éxito."
```

---

## 7. Automatización de Tareas (Cron y Supervisor)

### Configuración del Cron del Sistema
Ejecutar `sudo crontab -u www-data -e` y añadir la siguiente línea:
```cron
* * * * * cd /var/www/cms-seguro && php artisan schedule:run >> /dev/null 2>&1
```

Tareas ejecutadas por este cronómetro:
1. `preregistration:expire`: Depuración y expiración automática de pre-registros con más de 24 horas sin formalizar en triage.
2. Limpieza de tokens de sesión y recuperación expirados.

---

## 8. Estrategia de Copias de Seguridad y Prueba de Restauración

### 8.1. Script de Respaldo Cifrado Diario (`backup-hospital.sh`)
```bash
#!/bin/bash
FECHA=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/mnt/backups_cifrados"
GPG_RECIPIENT="seguridad@hospitalubate.gov.co"

# Volcado consistente de base de datos
pg_dump -U ubate_migrator -h 127.0.0.1 hospital_ubate_prod | gzip | gpg --encrypt --recipient "$GPG_RECIPIENT" > "$BACKUP_DIR/db_hospital_$FECHA.sql.gz.gpg"

# Respaldo cifrado de archivos de historias clínicas y firmas
tar -czf - /var/www/cms-seguro/storage/app | gpg --encrypt --recipient "$GPG_RECIPIENT" > "$BACKUP_DIR/storage_$FECHA.tar.gz.gpg"

# Eliminar respaldos locales de más de 30 días
find "$BACKUP_DIR" -type f -mtime +30 -delete
```

### 8.2. Protocolo Periódico de Prueba de Restauración (Drill Test)
Cada mes, el equipo de TI debe ejecutar un simulacro de restauración en un servidor de pruebas aislado:
1. Descifrar el volcado: `gpg --decrypt db_hospital_xxx.sql.gz.gpg | gunzip > test.sql`.
2. Restaurar en base de datos temporal: `psql -U postgres test_restore < test.sql`.
3. Validar consistencia de tablas críticas: `SELECT count(*) FROM emergency_episodes; SELECT count(*) FROM audit_logs;`.
4. Firmar el acta de peritaje de respaldo exitoso.

---

## 9. Procedimiento de Reversa (Rollback)

En caso de fallo crítico durante un despliegue:

```bash
# 1. Poner en mantenimiento
php artisan down

# 2. Regresar el repositorio al commit o tag previo estable
git checkout tags/v_anterior

# 3. Revertir la última migración si afectó esquema
php artisan migrate:rollback --step=1 --force

# 4. Restaurar base de datos previa si hubo corrupción de datos
dropdb hospital_ubate_prod
createdb hospital_ubate_prod
gunzip -c /mnt/backups_cifrados/backup_pre_despliegue.sql.gz | psql hospital_ubate_prod

# 5. Reconstruir dependencias y cachés
composer install --no-dev --optimize-autoloader
npm run build
php artisan optimize

# 6. Levantar el servicio
php artisan up
```

---

## 10. Creación del Administrador Inicial y Limpieza

Para evitar el uso de credenciales predeterminadas o datos de prueba en producción:

1. **Nunca ejecutar `migrate:fresh --seed` en producción.**
2. Crear exclusivamente los roles y la infraestructura física base:
   ```bash
   php artisan db:seed --class=RolesAndPermissionsSeeder --force
   php artisan db:seed --class=HospitalInfrastructureSeeder --force
   ```
3. Generar el usuario administrador oficial mediante el comando interactivo seguro:
   ```bash
   php artisan app:crear-admin
   ```
   * Solicita nombre, correo oficial y contraseña de alta entropía ($\ge 12$ caracteres con mayúsculas, minúsculas, números y símbolos).
   * Al primer inicio de sesión, el sistema exige obligatoriamente configurar la **Autenticación en Dos Factores (2FA TOTP)** antes de permitir acceso a cualquier módulo.

---

## 11. Planes de Contingencia Asistencial y Respuesta a Incidentes

### 11.1. Plan de Contingencia en Papel (Caída Total de Energía o Red)
Si el sistema se vuelve inaccesible por catástrofe eléctrica o de red:
1. El personal de Triage activa de inmediato los talonarios impresos oficiales de **Formularios de Triage Res. 5596**.
2. Cada paciente recibe su manilla de identificación física con color del triage asignado.
3. Las evoluciones se registran en hojas físicas de Historia Clínica de Urgencias foliadas.
4. Al restablecerse el sistema, un equipo de digitación asistencial transcribe los episodios utilizando la función de fecha/hora retroactiva justificada con registro en la bitácora de auditoría.

### 11.2. Plan de Respuesta ante Incidentes de Seguridad
Ante sospecha de brecha de datos o intrusión:
1. **Aislamiento Inmediato:** Desconectar el servidor de la red pública y revocar todas las sesiones (`DELETE FROM sessions`).
2. **Preservación Forense:** Clonar la imagen de disco y los archivos de log (`/var/log/nginx/` y `storage/logs/`).
3. **Notificación Legal:** En cumplimiento de la **Ley 1581 de 2012**, si se confirma acceso no autorizado a datos sensibles de salud, se debe notificar formalmente a la **Superintendencia de Industria y Comercio (SIC)** y a la **Superintendencia Nacional de Salud** dentro de los plazos legales establecidos.

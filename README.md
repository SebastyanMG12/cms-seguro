# Hospital de Ubaté — Sistema de Gestión Hospitalaria y Urgencias

Aplicación web integral para la gestión del servicio de urgencias, triage clínico y censo hospitalario del **Hospital de Ubaté**. Desarrollada en **Laravel 12 (PHP 8.2+)**, **PostgreSQL / MySQL** y **Bootstrap 5.3**, bajo estrictos estándares de ingeniería de software seguro, accesibilidad WCAG 2.2 AA y cumplimiento riguroso de la normatividad sanitaria colombiana.

---

## 1. Marco Regulatorio y Cumplimiento Legal

* **Resolución 1995 de 1999 (Ministerio de Salud):** Inmutabilidad estricta de la Historia Clínica (prohibición de modificación o eliminación de notas firmadas; aclaraciones y anulaciones enlazadas).
* **Resolución 5596 de 2015 (Ministerio de Salud):** Sistema obligatorio de selección y clasificación de pacientes en urgencias (**Triage I al V**) y cómputo de tiempos reales de oportunidad asistencial.
* **Ley Estatutaria 1581 de 2012 (Habeas Data):** Protección y cifrado en reposo (AES-256) de datos sensibles de salud, consentimientos informados independientes y aplicación de la **excepción del Artículo 10** para emergencias vitales y pacientes no identificados (NN).

---

## 2. Módulos y Funcionalidades del Sistema

1. **Pre-registro Digital de Urgencias (`/preregistro`):** Captura pública previa de datos demográficos y acompañante, generación de código público (`UBTxxxx`), trampa Honeypot y detección interactiva de signos de alarma médica.
2. **Tablero de Urgencias y Triage (`/episodios`):** Clasificación por gravedad clínica (Triage I a V), admisiones directas y gestión de estados de atención (`preregistrado`, `en_espera`, `en_valoracion`, `ingresado`, `egresado`).
3. **Pacientes No Identificados (NN) y Conciliación Forense (`/episodios/registro-nn`):** Admisión vital inmediata (`NN-AAAAMMDD-XXXX`) y unificación con la identidad real del paciente preservando permanentemente el histórico legal para no repudio.
4. **Historia Clínica Electrónica y Protocolo Break-Glass (`/episodios/{id}`):** Evoluciones médicas inmutables, asignación de equipo médico (responsable y apoyo) y barrera de acceso excepcional auditada para médicos no asignados.
5. **Certificados e Historia Clínica en PDF Oficial (`/episodios/{id}/pdf`):** Generación de documentos oficiales con Dompdf, marca de agua forense diagonal, paginación legal y auditoría de descarga.
6. **Censo Hospitalario y Ocupación de Camillas (`/ocupacion`):** Gestión en tiempo real de sedes, pisos, áreas, habitaciones y estados de camillas (`disponible`, `ocupada`, `mantenimiento`, `limpieza`, `fuera_de_servicio`).
7. **Personal Asistencial y Turnos Médicos (`/personal`):** Perfiles asistenciales con registro médico, turnos rotativos/fijos y cálculo de carga asistencial por profesional.
8. **Portal de Acompañantes (`/acompanante`):** Consulta familiar en tiempo real con código público + PIN numérico cifrado, consentimiento revocable y aislamiento en barra de navegación.
9. **Métricas de Urgencias y Auditoría Forense (`/reportes` y `/auditoria`):** Tiempos reales de espera en triage, porcentaje de cumplimiento normativo y bitácora de auditoría inmutable de solo inserción con exportación CSV UTF-8 BOM.
10. **Seguridad y Control de Acceso:** Autenticación Multifactor (2FA TOTP RFC 6238), control de sesiones concurrentes y bloqueo por inactividad.

---

## 3. Requisitos del Sistema

* **PHP:** 8.2 o superior con extensiones `pdo`, `pgsql` (o `mysql`), `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `intl`, `gd`.
* **Manejador de Paquetes PHP:** Composer 2.x+.
* **Gestor de Paquetes Frontend:** Node.js 18+ y NPM.
* **Motor de Base de Datos:** PostgreSQL 15+ (recomendado para producción) o MySQL 8.0+.
* **Servidor Web:** Nginx o Apache configurado con raíz en `/public`.

---

## 4. Instalación y Puesta en Marcha Local

```bash
# 1. Clonar el repositorio
git clone https://github.com/SebastyanMG12/cms-seguro.git
cd cms-seguro

# 2. Instalar dependencias del backend
composer install

# 3. Instalar y compilar dependencias del frontend (sin CDN externos)
npm install
npm run build

# 4. Configurar el archivo de entorno
cp .env.example .env
php artisan key:generate

# 5. Configurar conexión a base de datos en .env (DB_CONNECTION, DB_DATABASE, etc.)

# 6. Ejecutar migraciones y sembradores base de roles e infraestructura
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan db:seed --class=HospitalInfrastructureSeeder

# 7. Crear el primer usuario administrador oficial (sin credenciales por defecto)
php artisan app:crear-admin

# 8. Iniciar el servidor local de desarrollo
php artisan serve
```

---

## 5. Ejecución de Pruebas y Aseguramiento de Calidad

El proyecto cuenta con una cobertura completa de pruebas unitarias, de integración y de seguridad:

```bash
# Ejecutar la suite completa de pruebas (157 pruebas automatizadas)
php artisan test

# Verificar el estándar de código y estilo PSR-12
./vendor/bin/pint --test

# Auditoría de dependencias en búsqueda de vulnerabilidades
composer audit
npm audit
```

---

## 6. Consulta de Documentación Integrada

El sistema incluye acceso directo a su informe técnico y manual de seguridad:

* **Vía Consola:**
  ```bash
  # Ver el informe general
  php artisan hospital:docs

  # Ver exclusivamente la matriz de seguridad y mitigaciones OWASP
  php artisan hospital:docs --seguridad

  # Ver la tabla de módulos asistenciales y rutas
  php artisan hospital:docs --modulos
  ```
* **Vía Web:** Inicie el servidor y acceda a [`http://127.0.0.1:8000/documentacion`](http://127.0.0.1:8000/documentacion) (o alias `/docs`).

---

## 7. Índice de Documentación Técnica

* **[INFORME_IMPLEMENTACION_Y_SEGURIDAD.md](INFORME_IMPLEMENTACION_Y_SEGURIDAD.md):** Resumen ejecutivo, módulos asistenciales y matriz de pruebas de seguridad peritadas.
* **[docs/despliegue.md](docs/despliegue.md):** Manual de despliegue en producción, Nginx hardened, copias de seguridad cifradas, rollback y contingencia.
* **[docs/cumplimiento.md](docs/cumplimiento.md):** Análisis legal de Resoluciones 1995/1999, 5596/2015, Ley 1581/2012 y procedimientos de Habeas Data.
* **[docs/arquitectura.md](docs/arquitectura.md):** Capas de software, ciclo de vida de peticiones y modelo entidad-relación en Mermaid.
* **[docs/seguridad.md](docs/seguridad.md):** Modelo de amenazas y matriz de controles OWASP Top 10.
* **[docs/roles-permisos.md](docs/roles-permisos.md):** Matriz de control de acceso RBAC por perfil hospitalario.
* **[ESTADO_PROYECTO.md](ESTADO_PROYECTO.md):** Bitácora cronológica del proyecto y registro de fases cerradas.
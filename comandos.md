bash
git switch -c seguridad/gestion-parches
git status
git add . && git commit -m "estado previo a parche de seguridad"
git log -1 --oneline
mkdir -p docs/parches

Respalda también la base: pg_dump -U hospital_app -h 127.0.0.1 hospital > ../respaldo_hospital.sql (fuera del repositorio).

Paso 2. Inventario y auditoría inicial (“antes”)

bash
{ php -v; where.exe php; php --ini; php artisan --version; composer --version;
  composer show laravel/framework; composer show --direct; node -v; npm -v;
  npm list --depth=0; php artisan about; } | tee docs/parches/inventario.txt

{ composer validate; composer audit; composer outdated --direct;
  npm audit; npm outdated; composer check-platform-reqs; } | tee docs/parches/antes.txt

Toma captura de php -v mostrando 8.2.12: es tu evidencia principal del “antes”. Revisa también qué PHP usa Apache en XAMPP (si Apache está apagado, anótalo).

Paso 3. Análisis: ¿usas la funcionalidad vulnerable?

bash
grep -n "token" config/auth.php
grep -rn "JsonApiResource" app/
grep -rn "api_token" app/ config/

Debe salir vacío o sin guard de tipo token. Guarda el resultado; sustenta que P03 y P04 no aplican.

Paso 4. Parchar PHP (obligatorio)

Entra a windows.php.net/download y baja PHP 8.3.36 (o 8.4.27), x64, Thread Safe (zip). Verifica el SHA-256 publicado.
Descomprime en C:\php-8.3.36 (no toques C:\xampp\php: así puedes volver atrás).
Copia php.ini-development a php.ini y activa: extension_dir = "ext", openssl, mbstring, fileinfo, curl, zip, pdo_pgsql, pgsql, gd. Agrega expose_php = Off y, como defensa adicional, session.upload_progress.enabled = Off. PHP indica que no hay mitigaciones completas y que actualizar es la única opción segura, así que esto es solo complementario. 
github
Pon C:\php-8.3.36 al inicio del PATH (Variables de entorno) y abre una terminal nueva.
Verifica:
bash
php -v          # debe decir 8.3.36
php -m | grep -iE "pgsql|zip|mbstring|openssl"

Paso 5. Laravel y dependencias

bash
composer show laravel/framework      # debe ser 12.68.0 o superior
composer update laravel/framework    # solo si es menor
composer install

Opcional pero valioso: en composer.json, en require, deja "php": ">=8.3.36 <8.4 || >=8.4.27 <8.5 || >=8.5.12 <8.6". Así Composer se niega a instalar en un PHP vulnerable. Luego composer update --lock.

Paso 6. Verificación y pruebas posteriores (“después”)

bash
{ composer check-platform-reqs; php -m; php -v; composer audit; npm audit;
  php artisan about; php artisan route:list -v; php artisan test; } | tee docs/parches/despues.txt

Completa la tabla funcional del paso 21 de la guía (inicio, login con 2FA, pre-registro, roles, CRUD, correo, sesión, base de datos) con “correcto” y una captura por módulo. Imágenes y API: “no aplica, el sistema no las expone” (justifícalo).

Paso 7. Reauditoría con ZAP
Antes, pon MAIL_MAILER=log y haz un respaldo de la base, porque el escaneo llena audit_logs y envía correos. Alcance reducido: /, /login, /preregistro, /acompanante, /contacto. Tus límites de tasa devolverán 429: eso es una evidencia positiva. Exporta el reporte HTML. ZAP no detecta la RCE de PHP: el aporte es mostrar que no hay cabeceras que revelen versiones, ya que expose_php=Off elimina X-Powered-By.

Paso 8. Cierre en Git

bash
git add docs/parches composer.json composer.lock
git commit -m "security: parchear PHP 8.3.36 y documentar gestion de parches"
git push -u origin seguridad/gestion-parches
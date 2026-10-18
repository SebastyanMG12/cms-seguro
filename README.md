# CMS Seguro

Aplicación web en Laravel 12 con MySQL y Bootstrap 5, desarrollada con
prácticas de software seguro. Proyecto de Desarrollo de Software Seguro,
Especialización en Seguridad de la Información, Universidad de Cundinamarca.

## Funcionalidades
- Páginas públicas (inicio, acerca, contacto)
- Login seguro, recuperación de contraseña por código OTP
- Usuarios con roles (admin, editor, author) creados por un administrador
- CRUD de publicaciones con autorización por propietario
- Formulario de contacto con envío SMTP
- Bandeja de correos recibidos (fuente simulada)
- Auditoría de eventos sin secretos

## Requisitos
PHP 8.2+, Composer, Node.js, MySQL.

## Instalación
git clone https://github.com/SebastyanMG12/cms-seguro.git
cd cms-seguro
composer install
npm install
cp .env.example .env
php artisan key:generate
(crear la base cms_seguro y configurar DB_* y MAIL_* en .env)
php artisan migrate
npm run build
php artisan serve

## Pruebas
php artisan test        (17 pruebas)
composer audit
npm audit

## Seguridad
CSRF, validación en servidor, hash bcrypt, throttle, Policies y Gates,
cabeceras HTTP, sesiones en base de datos, auditoría. Ver el informe.

## Crear el primer administrador
No hay usuarios por defecto. Créalo con php artisan tinker.

## Correr servidor


- Terminal 1 php artisan serve
- Terminal 2 npm run dev
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mensaje del CMS</title>
</head>
<body>
    <h1>Nuevo mensaje de contacto</h1>
    <p><strong>Nombre:</strong> {{ $senderName }}</p>
    <p><strong>Correo:</strong> {{ $senderEmail }}</p>
    <h2>Mensaje</h2>
    <p style="white-space: pre-line;">{{ $body }}</p>
</body>
</html>
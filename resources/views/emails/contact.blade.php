<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Contacto - Hospital de Ubaté</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; background-color: #f4f6f9; margin: 0; padding: 24px; color: #2d3748;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
        <div style="background-color: #0d6efd; color: #ffffff; padding: 18px 24px;">
            <h1 style="margin: 0; font-size: 18px; font-weight: bold;">Hospital de Ubaté</h1>
            <p style="margin: 4px 0 0; font-size: 13px; opacity: 0.9;">Atención al Usuario y Comunicaciones</p>
        </div>
        <div style="padding: 24px;">
            <h2 style="font-size: 16px; margin-top: 0; color: #1a202c; border-bottom: 2px solid #edf2f7; padding-bottom: 8px;">
                Nueva Solicitud de Contacto
            </h2>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                <tr>
                    <td style="padding: 6px 0; font-weight: bold; width: 140px; color: #4a5568;">Remitente:</td>
                    <td style="padding: 6px 0; color: #1a202c;">{{ $senderName }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; font-weight: bold; color: #4a5568;">Correo Electrónico:</td>
                    <td style="padding: 6px 0; color: #1a202c;"><a href="mailto:{{ $senderEmail }}" style="color: #0d6efd; text-decoration: none;">{{ $senderEmail }}</a></td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; font-weight: bold; color: #4a5568;">Fecha y Hora:</td>
                    <td style="padding: 6px 0; color: #718096;">{{ now()->format('d/m/Y H:i:s') }}</td>
                </tr>
            </table>

            <div style="background-color: #f8fafc; border-left: 4px solid #0d6efd; padding: 16px; border-radius: 4px; margin-top: 12px;">
                <h3 style="margin: 0 0 8px; font-size: 14px; color: #2d3748;">Contenido del Mensaje:</h3>
                <p style="margin: 0; font-size: 14px; line-height: 1.6; white-space: pre-line; color: #334155;">{{ $body }}</p>
            </div>

            <p style="margin-top: 24px; font-size: 12px; color: #a0aec0; border-top: 1px solid #edf2f7; padding-top: 12px;">
                Este mensaje fue enviado desde el portal web oficial del Hospital de Ubaté. Puede responder directamente a este correo para ponerse en contacto con el remitente.
            </p>
        </div>
    </div>
</body>
</html>
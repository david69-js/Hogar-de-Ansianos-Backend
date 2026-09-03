{{-- Correo del código de recuperación. HTML deliberadamente simple y con
     estilos en línea: los clientes de correo (Gmail, Outlook) ignoran hojas
     de estilo externas y buena parte de CSS moderno. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer contraseña</title>
</head>
<body style="margin:0; padding:0; background-color:#F3F4F6; font-family:Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F3F4F6; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px; background-color:#ffffff; border-radius:12px; padding:32px;">
                    <tr>
                        <td>
                            <h1 style="margin:0 0 4px; font-size:20px; color:#111827;">Hogar de Ancianos Sor Herminia</h1>
                            <p style="margin:0 0 24px; font-size:14px; color:#6B7280;">Sistema de Control de Medicamentos</p>

                            <p style="margin:0 0 16px; font-size:15px; color:#111827;">
                                Hola{{ $firstName ? ' ' . $firstName : '' }}:
                            </p>
                            <p style="margin:0 0 24px; font-size:15px; color:#374151; line-height:1.5;">
                                Recibimos una solicitud para restablecer tu contraseña. Ingresa este código en la aplicación:
                            </p>

                            <div style="margin:0 0 24px; padding:20px; background-color:#F9FAFB; border:1px solid #E5E7EB; border-radius:8px; text-align:center;">
                                <span style="font-size:34px; font-weight:bold; letter-spacing:10px; color:#111827;">{{ $code }}</span>
                            </div>

                            <p style="margin:0 0 24px; font-size:14px; color:#374151; line-height:1.5;">
                                El código vence en <strong>{{ $minutesValid }} minutos</strong> y solo puede usarse una vez.
                            </p>

                            <p style="margin:0; padding-top:20px; border-top:1px solid #E5E7EB; font-size:13px; color:#6B7280; line-height:1.5;">
                                Si no fuiste tú quien lo solicitó, ignora este mensaje: tu contraseña seguirá siendo la misma.
                                Nunca compartas este código con nadie.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

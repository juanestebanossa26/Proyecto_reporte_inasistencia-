<?php
// Helper para envío de correos electrónicos transaccionales usando PHPMailer y SMTP.

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

class EmailHelper
{
    /**
     * Instancia y configura el cliente PHPMailer según las constantes del sistema.
     * Si no hay credenciales SMTP configuradas, retorna null de forma segura.
     */
    private static function crearMailer(?string $destinatario = null): ?PHPMailer
    {
        $smtpUser = defined('SMTP_USER') ? trim(SMTP_USER) : '';
        $smtpPass = defined('SMTP_PASS') ? trim(SMTP_PASS) : '';

        if ($smtpUser === '' || $smtpPass === '') {
            error_log("EmailHelper: Credenciales SMTP no configuradas en .env. Se omite el envío a [{$destinatario}].");
            return null;
        }

        try {
            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host       = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpUser;
            $mail->Password   = $smtpPass;
            $mail->Port       = defined('SMTP_PORT') ? (int) SMTP_PORT : 587;

            $secure = defined('SMTP_SECURE') ? strtolower(trim(SMTP_SECURE)) : 'tls';
            if ($secure === 'ssl' || $mail->Port === 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            // Límite de tiempo estricto para evitar bloqueos en la interfaz
            $mail->Timeout = 6;
            $mail->CharSet = 'UTF-8';

            $fromEmail = defined('MAIL_FROM_ADDRESS') && !empty(MAIL_FROM_ADDRESS) ? MAIL_FROM_ADDRESS : $smtpUser;
            $fromName  = defined('MAIL_FROM_NAME') && !empty(MAIL_FROM_NAME) ? MAIL_FROM_NAME : 'Control de Asistencias SENA';

            $mail->setFrom($fromEmail, $fromName);

            return $mail;
        } catch (\Throwable $e) {
            error_log('EmailHelper error al inicializar PHPMailer: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Envía una notificación por correo al aprendiz informando que registró un retardo en su marcaje.
     */
    public static function notificarRetardo(
        string $correoDestino,
        string $nombreAprendiz,
        string $fecha,
        string $horaEntrada,
        string $horaProgramada,
        int $minutosRetardo,
        string $fichaPrograma,
        string $nombreInstructor
    ): bool {
        $correoDestino = trim($correoDestino);
        if ($correoDestino === '' || !filter_var($correoDestino, FILTER_VALIDATE_EMAIL)) {
            error_log("EmailHelper: Correo de destino inválido o vacío para el aprendiz '{$nombreAprendiz}'.");
            return false;
        }

        $mail = self::crearMailer($correoDestino);
        if (!$mail) {
            return false;
        }

        try {
            $mail->addAddress($correoDestino, $nombreAprendiz);
            $mail->isHTML(true);

            $fechaFormateada = date('d/m/Y', strtotime($fecha));
            $mail->Subject = "[SENA] Notificación de Retardo - {$fechaFormateada}";

            $tiempoTexto = $minutosRetardo >= 60
                ? floor($minutosRetardo / 60) . 'h ' . ($minutosRetardo % 60) . 'm'
                : $minutosRetardo . ' minutos';

            $cuerpoHtml = "
            <!DOCTYPE html>
            <html lang='es'>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f4f6f8; margin: 0; padding: 20px; color: #2d3748; }
                    .card { max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; }
                    .header { background: #39A900; padding: 20px 24px; text-align: center; color: #ffffff; }
                    .header h1 { margin: 0; font-size: 19px; font-weight: 700; letter-spacing: 0.5px; }
                    .header p { margin: 4px 0 0 0; font-size: 13px; opacity: 0.9; }
                    .content { padding: 24px; }
                    .alert-badge { display: inline-block; background-color: #fef3c7; color: #92400e; padding: 6px 14px; border-radius: 50px; font-size: 13px; font-weight: 700; margin-bottom: 16px; border: 1px solid #fde68a; }
                    .greeting { font-size: 16px; font-weight: 600; margin-bottom: 12px; }
                    .details-box { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; margin: 18px 0; }
                    .retardo-highlight { color: #b45309; font-weight: 700; font-size: 15px; }
                    .note { font-size: 12.5px; color: #64748b; line-height: 1.5; margin-top: 16px; border-left: 3px solid #39A900; padding-left: 10px; }
                    .footer { background: #f8fafc; padding: 14px 20px; text-align: center; font-size: 11.5px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
                </style>
            </head>
            <body>
                <div class='card'>
                    <div class='header'>
                        <h1>SISTEMA DE CONTROL DE ASISTENCIA SENA</h1>
                        <p>Notificación Oficial de Novedad de Ingreso</p>
                    </div>
                    <div class='content'>
                        <span class='alert-badge'>⏱ Retardo Registrado</span>
                        <div class='greeting'>Hola, " . htmlspecialchars($nombreAprendiz) . ":</div>
                        <p style='margin: 0 0 14px 0; line-height: 1.5; font-size: 14px;'>
                            Te informamos que durante el marcaje de ingreso a tu sesión de formación se ha registrado una novedad de <strong>Retardo</strong>.
                        </p>

                        <div class='details-box'>
                            <table style='width: 100%; border-collapse: collapse; font-size: 13.5px;'>
                                <tr>
                                    <td style='padding: 6px 0; color: #64748b;'><strong>Fecha:</strong></td>
                                    <td style='padding: 6px 0; text-align: right; color: #1e293b;'>{$fechaFormateada}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 6px 0; color: #64748b;'><strong>Ficha / Programa:</strong></td>
                                    <td style='padding: 6px 0; text-align: right; color: #1e293b;'>" . htmlspecialchars($fichaPrograma) . "</td>
                                </tr>
                                <tr>
                                    <td style='padding: 6px 0; color: #64748b;'><strong>Hora programada:</strong></td>
                                    <td style='padding: 6px 0; text-align: right; color: #1e293b;'>{$horaProgramada}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 6px 0; color: #64748b;'><strong>Hora real de ingreso:</strong></td>
                                    <td style='padding: 6px 0; text-align: right; color: #1e293b;'>{$horaEntrada}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 6px 0; color: #64748b;'><strong>Tiempo de retardo:</strong></td>
                                    <td style='padding: 6px 0; text-align: right;'><span class='retardo-highlight'>{$tiempoTexto}</span></td>
                                </tr>
                                <tr>
                                    <td style='padding: 6px 0; color: #64748b;'><strong>Instructor a cargo:</strong></td>
                                    <td style='padding: 6px 0; text-align: right; color: #1e293b;'>" . htmlspecialchars($nombreInstructor) . "</td>
                                </tr>
                            </table>
                        </div>

                        <div class='note'>
                            <strong>Importante:</strong> Recuerda que la puntualidad es parte fundamental de tu formación profesional integral. Si este retraso ocurrió por causas de fuerza mayor o citas médicas, puedes radicar tu justificación en el módulo de <em>Excusas</em> de la plataforma.
                        </div>
                    </div>
                    <div class='footer'>
                        Este es un mensaje automático generado por el Sistema de Asistencias SENA. Por favor no respondas a este correo.
                    </div>
                </div>
            </body>
            </html>";

            $mail->Body    = $cuerpoHtml;
            $mail->AltBody = "Hola {$nombreAprendiz}. Te informamos que registraste un retardo de {$tiempoTexto} el día {$fechaFormateada} a las {$horaEntrada} en tu clase con {$nombreInstructor}.";

            $mail->send();
            return true;
        } catch (\Throwable $e) {
            error_log("EmailHelper: Error al enviar correo de retardo a [{$correoDestino}]: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envía una notificación por correo al aprendiz cuando su excusa médica ha sido revisada por un instructor.
     */
    public static function notificarResultadoExcusa(
        string $correoDestino,
        string $nombreAprendiz,
        string $fechaClase,
        string $estadoRevision,
        string $observaciones,
        string $nombreInstructor,
        string $fichaPrograma = ''
    ): bool {
        $correoDestino = trim($correoDestino);
        if ($correoDestino === '' || !filter_var($correoDestino, FILTER_VALIDATE_EMAIL)) {
            error_log("EmailHelper: Correo de destino inválido o vacío para el aprendiz '{$nombreAprendiz}'.");
            return false;
        }

        $mail = self::crearMailer($correoDestino);
        if (!$mail) {
            return false;
        }

        try {
            $mail->addAddress($correoDestino, $nombreAprendiz);
            $mail->isHTML(true);

            $fechaFormateada = date('d/m/Y', strtotime($fechaClase));
            $mail->Subject = "[SENA] Novedad en tu Excusa Médica - " . strtoupper($estadoRevision);

            $estadoLower = strtolower(trim($estadoRevision));
            $colorBadge = match ($estadoLower) {
                'aprobada'  => '#15803d',
                'rechazada' => '#b91c1c',
                default     => '#b45309'
            };
            $bgBadge = match ($estadoLower) {
                'aprobada'  => '#dcfce7',
                'rechazada' => '#fee2e2',
                default     => '#fef3c7'
            };
            $icono = match ($estadoLower) {
                'aprobada'  => '✔',
                'rechazada' => '✖',
                default     => '⚠'
            };

            $mensajeOrientativo = match ($estadoLower) {
                'aprobada'  => 'Tu inasistencia ha sido debidamente justificada. El registro de asistencia y tu soporte médico quedan archivados de manera segura en el sistema.',
                'devuelta'  => 'El instructor ha solicitado correcciones o aclaraciones en tu excusa. Por favor ingresa al módulo de Excusas para verificar las observaciones y reenviar el soporte.',
                'rechazada' => 'Tu solicitud no cumple con los criterios de validación institucional. Si tienes dudas respecto a esta decisión, comunícate directamente con tu instructor.',
                default     => 'Tu excusa médica ha sido procesada.'
            };

            $filaFicha = '';
            if (!empty($fichaPrograma)) {
                $filaFicha = "
                <tr>
                    <td style='padding: 6px 0; color: #64748b;'><strong>Ficha / Programa:</strong></td>
                    <td style='padding: 6px 0; text-align: right; color: #1e293b;'>" . htmlspecialchars($fichaPrograma) . "</td>
                </tr>";
            }

            $cuerpoHtml = "
            <!DOCTYPE html>
            <html lang='es'>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f4f6f8; margin: 0; padding: 20px; color: #2d3748; }
                    .card { max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; }
                    .header { background: #39A900; padding: 20px 24px; text-align: center; color: #ffffff; }
                    .header h1 { margin: 0; font-size: 19px; font-weight: 700; }
                    .header p { margin: 4px 0 0 0; font-size: 13px; opacity: 0.9; }
                    .content { padding: 24px; }
                    .status-badge { display: inline-block; background-color: {$bgBadge}; color: {$colorBadge}; padding: 6px 14px; border-radius: 50px; font-size: 13.5px; font-weight: 700; margin-bottom: 16px; border: 1px solid rgba(0,0,0,0.06); }
                    .greeting { font-size: 16px; font-weight: 600; margin-bottom: 12px; }
                    .details-box { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; margin: 18px 0; }
                    .observations-box { background-color: #ffffff; border-left: 3px solid {$colorBadge}; padding: 10px 14px; margin-top: 10px; font-style: italic; color: #334155; }
                    .note { font-size: 12.5px; color: #64748b; line-height: 1.5; margin-top: 16px; border-left: 3px solid #39A900; padding-left: 10px; }
                    .footer { background: #f8fafc; padding: 14px 20px; text-align: center; font-size: 11.5px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
                </style>
            </head>
            <body>
                <div class='card'>
                    <div class='header'>
                        <h1>SISTEMA DE ASISTENCIA SENA</h1>
                        <p>Notificación de Revisión de Excusa Médica</p>
                    </div>
                    <div class='content'>
                        <span class='status-badge'>{$icono} Excusa " . strtoupper($estadoRevision) . "</span>
                        <div class='greeting'>Hola, " . htmlspecialchars($nombreAprendiz) . ":</div>
                        <p style='margin: 0 0 14px 0; line-height: 1.5; font-size: 14px;'>
                            Te informamos que tu excusa médica radicada para la sesión formativa ha sido revisada por el instructor.
                        </p>

                        <div class='details-box'>
                            <table style='width: 100%; border-collapse: collapse; font-size: 13.5px;'>
                                <tr>
                                    <td style='padding: 6px 0; color: #64748b;'><strong>Fecha justificada:</strong></td>
                                    <td style='padding: 6px 0; text-align: right; color: #1e293b;'>{$fechaFormateada}</td>
                                </tr>
                                {$filaFicha}
                                <tr>
                                    <td style='padding: 6px 0; color: #64748b;'><strong>Instructor evaluador:</strong></td>
                                    <td style='padding: 6px 0; text-align: right; color: #1e293b;'>" . htmlspecialchars($nombreInstructor) . "</td>
                                </tr>
                                <tr>
                                    <td style='padding: 6px 0; color: #64748b;'><strong>Decisión:</strong></td>
                                    <td style='padding: 6px 0; text-align: right;'><span style='color: {$colorBadge}; font-weight: 700;'>" . strtoupper($estadoRevision) . "</span></td>
                                </tr>
                            </table>

                            <div style='margin-top: 12px; font-size: 13px;'>
                                <strong style='color: #475569;'>Observaciones del instructor:</strong>
                                <div class='observations-box'>" . (!empty($observaciones) ? htmlspecialchars($observaciones) : 'Sin observaciones adicionales.') . "</div>
                            </div>
                        </div>

                        <div class='note'>
                            {$mensajeOrientativo}
                        </div>
                    </div>
                    <div class='footer'>
                        Este es un mensaje automático generado por el Sistema de Asistencias SENA. Por favor no respondas a este correo.
                    </div>
                </div>
            </body>
            </html>";

            $mail->Body    = $cuerpoHtml;
            $mail->AltBody = "Hola {$nombreAprendiz}. Tu excusa médica para la sesión del {$fechaFormateada} fue revisada por {$nombreInstructor}. Decisión: " . strtoupper($estadoRevision) . ". Observaciones: {$observaciones}";

            $mail->send();
            return true;
        } catch (\Throwable $e) {
            error_log("EmailHelper: Error al enviar correo de excusa a [{$correoDestino}]: " . $e->getMessage());
            return false;
        }
    }
}

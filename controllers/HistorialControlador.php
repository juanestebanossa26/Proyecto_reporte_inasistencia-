<?php
// Controlador del historial de asistencia (vista del aprendiz).
// El Modelo nunca falla "en silencio": si la consulta truena, se registra
// el error real en el log del servidor y la vista recibe datos vacíos
// más un mensaje genérico, en vez de que la página se rompa.

class HistorialControlador extends ControladorBase
{
    public function mostrar(): void
    {
        $this->exigirSesion(['Aprendiz']);

        try {
            $asistenciaModelo   = new AsistenciaModelo();
            $asistenciaModelo->sincronizarInasistenciasGlobal();
            $historial          = $asistenciaModelo->listarPorAprendiz((int) $_SESSION['id']);
            $total_retardo      = $asistenciaModelo->contarPorEstado((int) $_SESSION['id'], 'retardo');
            $total_inasistencia = $asistenciaModelo->contarPorEstado((int) $_SESSION['id'], 'inasistencia');
            $total_pendientes   = $asistenciaModelo->contarExcusasPendientes((int) $_SESSION['id']);
        } catch (PDOException $e) {
            error_log('Error al listar historial: ' . $e->getMessage());
            $historial          = [];
            $total_retardo      = 0;
            $total_inasistencia = 0;
            $total_pendientes   = 0;
            $_SESSION['mensaje_error'] = 'No se pudo cargar tu historial. Intenta de nuevo.';
        }

        require BASE_PATH . '/views/historial.php';
    }

    public function descargarReportePdf(): void
    {
        $this->exigirSesion(['Aprendiz']);

        require_once BASE_PATH . '/libs/fpdf/fpdf.php';

        $idUsuario = (int) $_SESSION['id'];
        $asistenciaModelo = new AsistenciaModelo();

        $info = $asistenciaModelo->obtenerInfoAprendiz($idUsuario);
        $historial = $asistenciaModelo->listarHistorialCompleto($idUsuario);
        $totalRetardo = $asistenciaModelo->contarPorEstado($idUsuario, 'retardo');
        $totalInasistencia = $asistenciaModelo->contarPorEstado($idUsuario, 'inasistencia');
        $totalAsistencias = $asistenciaModelo->contarPorEstado($idUsuario, 'asistencia');
        $totalPendientes = $asistenciaModelo->contarExcusasPendientes($idUsuario);

        $nombreAprendiz = $info['nombre_completo'] ?? ($_SESSION['nombre'] ?? 'Aprendiz');
        $documento = $info['documento'] ?? ($_SESSION['usuario'] ?? 'N/A');
        $ficha = !empty($info['numero_ficha']) ? ($info['numero_ficha'] . ' - ' . $info['nombre_programa']) : 'Ficha no asignada';
        $correo = $info['correo_electronico'] ?? ($_SESSION['Correo'] ?? 'Sin correo registrado');

        // Clase FPDF personalizada para el reporte individual
        $pdf = new class('P', 'mm', 'Letter') extends FPDF {
            function Header(): void
            {
                // Barra superior institucional verde SENA
                $this->SetFillColor(57, 169, 0);
                $this->Rect(0, 0, 216, 12, 'F');

                $this->SetY(4);
                $this->SetFont('Arial', 'B', 8.5);
                $this->SetTextColor(255, 255, 255);
                $this->Cell(0, 5, utf8_decode('SERVICIO NACIONAL DE APRENDIZAJE · SENA — SISTEMA DE CONTROL DE ASISTENCIA RFID'), 0, 1, 'C');

                $this->SetY(16);
                $this->SetFont('Arial', 'B', 14);
                $this->SetTextColor(31, 122, 0);
                $this->Cell(0, 7, utf8_decode('REPORTE INDIVIDUAL DE ASISTENCIA'), 0, 1, 'C');

                $this->SetFont('Arial', '', 8);
                $this->SetTextColor(120, 120, 120);
                $this->Cell(0, 4, utf8_decode('Fecha y hora de generación: ') . date('d/m/Y h:i:s a'), 0, 1, 'C');
                $this->Ln(3);
            }

            function Footer(): void
            {
                $this->SetY(-14);
                $this->SetFont('Arial', 'I', 8);
                $this->SetTextColor(140, 140, 140);
                $this->Cell(0, 5, utf8_decode('Documento generado automáticamente por el Sistema de Control de Ingreso y Asistencia RFID · Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
            }
        };

        $pdf->AliasNbPages();
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        // 1. Tarjeta de información del Aprendiz
        $pdf->SetFillColor(246, 248, 250);
        $pdf->SetDrawColor(215, 222, 228);
        $pdf->Rect(12, 33, 192, 28, 'DF');

        $pdf->SetY(35);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor(30, 41, 59);
        $pdf->Cell(28, 5, utf8_decode('Aprendiz:'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(70, 5, substr(utf8_decode($nombreAprendiz), 0, 36), 0, 0, 'L');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(32, 5, utf8_decode('Identificación:'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(60, 5, utf8_decode($documento), 0, 1, 'L');

        $pdf->SetY(41);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(28, 5, utf8_decode('Ficha / Prog:'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(70, 5, substr(utf8_decode($ficha), 0, 40), 0, 0, 'L');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(32, 5, utf8_decode('Correo:'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(60, 5, substr(utf8_decode($correo), 0, 34), 0, 1, 'L');

        $pdf->SetY(47);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(28, 5, utf8_decode('Estado Cuenta:'), 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor(31, 122, 0);
        $pdf->Cell(70, 5, utf8_decode('ACTIVO'), 0, 0, 'L');

        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(32, 5, utf8_decode('Centro:'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(60, 5, utf8_decode('Centro de Formación SENA'), 0, 1, 'L');

        $pdf->Ln(14);

        // 2. Tarjetas KPI de Resumen
        $pdf->SetY(66);
        $anchoKpi = 46.5;
        $altoKpi = 15;

        // KPI Asistencias
        $pdf->SetFillColor(237, 247, 237);
        $pdf->SetDrawColor(180, 222, 180);
        $pdf->Rect(12, 66, $anchoKpi, $altoKpi, 'DF');
        $pdf->SetFont('Arial', '', 7.5);
        $pdf->SetTextColor(31, 122, 0);
        $pdf->SetXY(12, 68);
        $pdf->Cell($anchoKpi, 4, utf8_decode('Asistencias a tiempo'), 0, 1, 'C');
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell($anchoKpi, 6, (string) $totalAsistencias, 0, 0, 'C');

        // KPI Retardos
        $pdf->SetFillColor(254, 249, 231);
        $pdf->SetDrawColor(245, 219, 140);
        $pdf->Rect(60.5, 66, $anchoKpi, $altoKpi, 'DF');
        $pdf->SetFont('Arial', '', 7.5);
        $pdf->SetTextColor(138, 99, 0);
        $pdf->SetXY(60.5, 68);
        $pdf->Cell($anchoKpi, 4, utf8_decode('Retardos acumulados'), 0, 1, 'C');
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell($anchoKpi, 6, (string) $totalRetardo, 0, 0, 'C');

        // KPI Inasistencias
        $pdf->SetFillColor(254, 242, 242);
        $pdf->SetDrawColor(248, 180, 180);
        $pdf->Rect(109, 66, $anchoKpi, $altoKpi, 'DF');
        $pdf->SetFont('Arial', '', 7.5);
        $pdf->SetTextColor(180, 33, 43);
        $pdf->SetXY(109, 68);
        $pdf->Cell($anchoKpi, 4, utf8_decode('Inasistencias'), 0, 1, 'C');
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell($anchoKpi, 6, (string) $totalInasistencia, 0, 0, 'C');

        // KPI Excusas Pendientes
        $pdf->SetFillColor(239, 246, 255);
        $pdf->SetDrawColor(191, 219, 254);
        $pdf->Rect(157.5, 66, $anchoKpi, $altoKpi, 'DF');
        $pdf->SetFont('Arial', '', 7.5);
        $pdf->SetTextColor(47, 111, 237);
        $pdf->SetXY(157.5, 68);
        $pdf->Cell($anchoKpi, 4, utf8_decode('Excusas pendientes'), 0, 1, 'C');
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell($anchoKpi, 6, (string) $totalPendientes, 0, 1, 'C');

        $pdf->Ln(7);

        // 3. Encabezado de la tabla de registros
        $pdf->SetY(87);
        $pdf->SetFillColor(57, 169, 0);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(46, 138, 0);
        $pdf->SetFont('Arial', 'B', 8);

        $pdf->Cell(28, 7, utf8_decode('Fecha'), 1, 0, 'C', true);
        $pdf->Cell(44, 7, utf8_decode('Horario Programado'), 1, 0, 'C', true);
        $pdf->Cell(32, 7, utf8_decode('Hora Entrada Real'), 1, 0, 'C', true);
        $pdf->Cell(32, 7, utf8_decode('Hora Salida Real'), 1, 0, 'C', true);
        $pdf->Cell(26, 7, utf8_decode('Retardo (min)'), 1, 0, 'C', true);
        $pdf->Cell(30, 7, utf8_decode('Estado'), 1, 1, 'C', true);

        // 4. Filas de la tabla
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->SetDrawColor(220, 220, 220);

        if (empty($historial)) {
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Cell(192, 10, utf8_decode('No hay registros de asistencia en el historial.'), 1, 1, 'C', true);
        } else {
            $fill = false;
            foreach ($historial as $fila) {
                $pdf->SetFillColor($fill ? 248 : 255, $fill ? 250 : 255, $fill ? 252 : 255);

                $fecha = !empty($fila['fecha']) ? date('d/m/Y', strtotime($fila['fecha'])) : '—';
                
                $hEntProg = !empty($fila['hora_entrada_programada']) ? substr($fila['hora_entrada_programada'], 0, 5) : '';
                $hSalProg = !empty($fila['hora_salida_programada']) ? substr($fila['hora_salida_programada'], 0, 5) : '';
                $progTxt  = ($hEntProg && $hSalProg) ? "{$hEntProg} - {$hSalProg}" : 'Jornada asignada';

                $hEntReal = !empty($fila['hora_entrada_real']) ? date('h:i a', strtotime($fila['hora_entrada_real'])) : '—';
                $hSalReal = !empty($fila['hora_salida_real']) ? date('h:i a', strtotime($fila['hora_salida_real'])) : '—';

                $retrasoMin = (int) ($fila['tiempo_retraso'] ?? 0);
                $retrasoTxt = $retrasoMin > 0 ? "{$retrasoMin} min" : '0 min';

                $estado = ucfirst(strtolower($fila['estado'] ?? 'inasistencia'));

                $pdf->Cell(28, 6.5, $fecha, 1, 0, 'C', $fill);
                $pdf->Cell(44, 6.5, utf8_decode($progTxt), 1, 0, 'C', $fill);
                $pdf->Cell(32, 6.5, $hEntReal, 1, 0, 'C', $fill);
                $pdf->Cell(32, 6.5, $hSalReal, 1, 0, 'C', $fill);
                $pdf->Cell(26, 6.5, $retrasoTxt, 1, 0, 'C', $fill);
                $pdf->Cell(30, 6.5, utf8_decode($estado), 1, 1, 'C', $fill);

                $fill = !$fill;
            }
        }

        $nombreArchivo = 'reporte_asistencia_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $documento) . '_' . date('Ymd') . '.pdf';
        $pdf->Output('I', $nombreArchivo);
        exit;
    }
}


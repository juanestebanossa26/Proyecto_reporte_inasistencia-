<?php
// Controlador de reportes. Arma los filtros, pide el detalle a
// ReporteModelo (ya acotado por rol/área) y delega la generación del
// archivo a FPDF o SimpleXLSXGen.

class ReporteControlador extends ControladorBase
{
    // accion=reportes -> arma fichas/filtros/detalle y entrega el control
    // a la vista. El mismo formulario de filtros se reenvía aquí (GET
    // inicial sin filtros, o POST cuando el usuario los aplica).
    public function mostrar(): void
    {
        $this->exigirSesion(['Instructor', 'Administrador']);

        $rango    = $_POST['rango'] ?? 'personalizado';
        $desde    = $_POST['desde'] ?? '';
        $hasta    = $_POST['hasta'] ?? '';
        $ficha_id = $_POST['ficha_id'] ?? '';

        $filtros = ReporteModelo::normalizarFiltros($rango, $desde, $hasta, $ficha_id);
        $desde   = $filtros['desde'];
        $hasta   = $filtros['hasta'];

        $idUsuarioActual = (int) $_SESSION['id'];
        $rolActual       = $_SESSION['rol'];

        try {
            $reporteModelo     = new ReporteModelo();
            $fichasDisponibles = $reporteModelo->listarFichas($idUsuarioActual, $rolActual);
            $filasReporte      = $reporteModelo->obtenerDetalle($filtros, $idUsuarioActual, $rolActual);
            $filaContadores    = $reporteModelo->obtenerContadores($filtros, $idUsuarioActual, $rolActual);
        } catch (PDOException $e) {
            error_log('Error al generar el reporte: ' . $e->getMessage());
            $fichasDisponibles = [];
            $filasReporte      = [];
            $filaContadores    = [];
            $_SESSION['mensaje_error'] = 'No se pudo cargar el reporte. Intenta de nuevo.';
        }

        require BASE_PATH . '/views/reportes.php';
    }

    private function leerFiltros(): array
    {
        $this->exigirCsrfValido('reportes');

        $rango    = $_POST['rango'] ?? 'personalizado';
        $desde    = $_POST['desde'] ?? '';
        $hasta    = $_POST['hasta'] ?? '';
        $fichaId  = $_POST['ficha_id'] ?? '';

        return ReporteModelo::normalizarFiltros($rango, $desde, $hasta, $fichaId);
    }

    public function generarPdf(): void
    {
        $this->exigirSesion(['Instructor', 'Administrador']);

        try {
            $filtros = $this->leerFiltros();
            $filas   = (new ReporteModelo())->obtenerDetalle($filtros, (int) $_SESSION['id'], $_SESSION['rol']);

            if (empty($filas)) {
                Seguridad::alertaYRedirigir(
                    'info',
                    'Sin datos para exportar',
                    'No se encontraron registros de asistencia para el período y filtros seleccionados.',
                    $this->urlAccion('reportes')
                );
                return;
            }

            require_once BASE_PATH . '/libs/fpdf/fpdf.php';

            // Estructura personalizada del reporte
            $pdf = new class('L', 'mm', 'Letter') extends FPDF {
                function Header(): void
                {
                    $this->SetFont('Arial', 'B', 14);
                    $this->SetTextColor(57, 169, 0); // Verde SENA
                    $this->Cell(0, 7, 'REPORTE DETALLADO DE ASISTENCIAS', 0, 1, 'C');

                    $this->SetFont('Arial', '', 8.5);
                    $this->SetTextColor(110, 110, 110);
                    $this->Cell(0, 5, utf8_decode('Fecha de emisión: ') . date('d/m/Y H:i:s'), 0, 1, 'C');
                    $this->Ln(3);

                    $this->SetFillColor(57, 169, 0);
                    $this->SetTextColor(255, 255, 255);
                    $this->SetFont('Arial', 'B', 8);

                    $this->Cell(55, 8, 'Aprendiz', 1, 0, 'L', true);
                    $this->Cell(60, 8, 'Ficha / Programa', 1, 0, 'L', true);
                    $this->Cell(50, 8, 'Instructor', 1, 0, 'L', true);
                    $this->Cell(25, 8, 'Retardos', 1, 0, 'C', true);
                    $this->Cell(45, 8, 'Retardo Acumulado', 1, 0, 'C', true);
                    $this->Cell(25, 8, 'Inasistencias', 1, 1, 'C', true);
                }

                function Footer(): void
                {
                    $this->SetY(-15);
                    $this->SetFont('Arial', 'I', 8);
                    $this->SetTextColor(130, 130, 130);
                    $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
                }
            };

            $pdf->AliasNbPages();
            $pdf->SetMargins(10, 10, 10);
            $pdf->SetAutoPageBreak(true, 15);
            $pdf->AddPage();
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetTextColor(30, 30, 30);

            $fill = false;

            foreach ($filas as $fila) {
                $pdf->SetFillColor(245, 245, 245);

                $aprendiz   = utf8_decode($fila['nombre_completo'] ?? '');
                $ficha      = utf8_decode($fila['numero_ficha'] . ' - ' . $fila['nombre_programa']);
                $instructor = utf8_decode($fila['nombre_instructor'] ?: 'Sin instructor');
                
                $minutos    = (int) ($fila['min_retardo'] ?? 0);
                $tiempo     = $minutos >= 60
                    ? floor($minutos / 60) . 'h ' . ($minutos % 60) . 'm'
                    : $minutos . 'm';

                $pdf->Cell(55, 7, substr($aprendiz, 0, 32), 1, 0, 'L', $fill);
                $pdf->Cell(60, 7, substr($ficha, 0, 38), 1, 0, 'L', $fill);
                $pdf->Cell(50, 7, substr($instructor, 0, 28), 1, 0, 'L', $fill);
                $pdf->Cell(25, 7, $fila['retardos'] ?? 0, 1, 0, 'C', $fill);
                $pdf->Cell(45, 7, $tiempo, 1, 0, 'C', $fill);
                $pdf->Cell(25, 7, $fila['inasistencias'] ?? 0, 1, 1, 'C', $fill);

                $fill = !$fill;
            }

            $pdf->Output('I', 'reporte_asistencia_' . date('Ymd_His') . '.pdf');
            exit;
        } catch (\Throwable $e) {
            error_log('Error al generar PDF: ' . $e->getMessage());
            Seguridad::alertaYRedirigir(
                'error',
                'Error al exportar',
                'Ocurrió un error al generar el archivo PDF. Intenta de nuevo.',
                $this->urlAccion('reportes')
            );
        }
    }

    public function generarExcel(): void
    {
        $this->exigirSesion(['Instructor', 'Administrador']);

        try {
            $filtros = $this->leerFiltros();
            $filas   = (new ReporteModelo())->obtenerDetalle($filtros, (int) $_SESSION['id'], $_SESSION['rol']);

            if (empty($filas)) {
                Seguridad::alertaYRedirigir(
                    'info',
                    'Sin datos para exportar',
                    'No se encontraron registros de asistencia para el período y filtros seleccionados.',
                    $this->urlAccion('reportes')
                );
                return;
            }

            require_once BASE_PATH . '/libs/SimpleXLSXGen.php';

            $datos = [[
                '<b>Aprendiz</b>',
                '<b>Ficha</b>',
                '<b>Instructor</b>',
                '<center><b>Retardos</b></center>',
                '<center><b>Minutos Retardo</b></center>',
                '<center><b>Inasistencias</b></center>',
            ]];

            foreach ($filas as $fila) {
                $datos[] = [
                    $fila['nombre_completo'] ?? '',
                    $fila['numero_ficha'] . ' · ' . $fila['nombre_programa'],
                    $fila['nombre_instructor'] ?: 'Sin instructor',
                    $fila['retardos'] ?? 0,
                    $fila['min_retardo'] ?? 0,
                    $fila['inasistencias'] ?? 0,
                ];
            }

            $nombreArchivo = 'reporte_asistencia_' . date('Ymd_His') . '.xlsx';
            Shuchkin\SimpleXLSXGen::fromArray($datos)->downloadAs($nombreArchivo);
            exit;
        } catch (\Throwable $e) {
            error_log('Error al generar Excel: ' . $e->getMessage());
            Seguridad::alertaYRedirigir(
                'error',
                'Error al exportar',
                'Ocurrió un error al generar el archivo Excel. Intenta de nuevo.',
                $this->urlAccion('reportes')
            );
        }
    }
}
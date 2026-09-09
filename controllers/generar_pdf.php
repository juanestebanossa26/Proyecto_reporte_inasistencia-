<?php
session_start();
if (!isset($_SESSION["Documento"])) {
    header("Location: ../index.php");
    exit();
}

// Inclusión con la ruta a la carpeta fpdf detectada
require_once(__DIR__ . '/../libs/fpdf/fpdf.php');
require_once('../models/mysql.php');

$db = new mysql();
$db->conectar();

// 1. Recibir los filtros enviados por POST
$rango    = $_POST['rango'] ?? 'personalizado';
$desde    = $_POST['desde'] ?? '';
$hasta    = $_POST['hasta'] ?? '';
$ficha_id = $_POST['ficha_id'] ?? '';

if ($rango === 'dia') {
    $desde = !empty($desde) ? $desde : date('Y-m-d');
    $hasta = $desde;
} elseif ($rango === 'semana') {
    $desde = date('Y-m-d', strtotime('monday this week'));
    $hasta = date('Y-m-d', strtotime('sunday this week'));
}

// 2. Filtros SQL dinámicos con parámetros protegidos
$whereSql = " WHERE 1=1 ";
$params = [];

if (!empty($desde)) {
    $whereSql .= " AND (asistencias.fecha >= :desde OR asistencias.fecha IS NULL) ";
    $params[':desde'] = $desde;
}
if (!empty($hasta)) {
    $whereSql .= " AND (asistencias.fecha <= :hasta OR asistencias.fecha IS NULL) ";
    $params[':hasta'] = $hasta;
}
if (!empty($ficha_id)) {
    $whereSql .= " AND fichas.id_ficha = :ficha_id ";
    $params[':ficha_id'] = $ficha_id;
}

$sql = "SELECT 
    u_aprendiz.nombre_completo,
    u_aprendiz.apellido,
    fichas.numero_ficha,
    fichas.nombre_programa,
    u_instructor.nombre_completo AS nombre_instructor,
    u_instructor.apellido AS apellido_instructor,
    SUM(asistencias.estado = 'Retardo') AS retardos,
    IF(
        SUM(asistencias.tiempo_retraso) >= 60,
        CONCAT(FLOOR(SUM(asistencias.tiempo_retraso) / 60), 'h ', MOD(SUM(asistencias.tiempo_retraso), 60), 'm'),
        CONCAT(COALESCE(SUM(asistencias.tiempo_retraso), 0), 'm')
    ) AS tiempo_retraso_formato,
    SUM(asistencias.estado = 'Inasistencia' AND (excusas_medicas.estado_excusa IS NULL OR excusas_medicas.estado_excusa != 'Aprobada')) AS inasistencias
FROM aprendices
JOIN usuarios u_aprendiz ON aprendices.id_usuario = u_aprendiz.id_usuario
JOIN fichas ON aprendices.id_ficha = fichas.id_ficha
LEFT JOIN usuarios u_instructor ON fichas.id_instructor = u_instructor.id_usuario
LEFT JOIN asistencias ON asistencias.id_aprendiz = aprendices.id_aprendiz
LEFT JOIN excusas_medicas ON excusas_medicas.id_asistencia = asistencias.id_asistencia
$whereSql
GROUP BY aprendices.id_aprendiz, u_aprendiz.nombre_completo, u_aprendiz.apellido, fichas.numero_ficha, fichas.nombre_programa, u_instructor.nombre_completo, u_instructor.apellido";

$stmt = $db->prepare($sql);
$stmt->execute($params);

// 3. Estructura personalizada del reporte
class PDF extends FPDF {
    function Header() {
        // Título del reporte
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(57, 169, 0); // Verde SENA
        $this->Cell(0, 7, ('REPORTE DETALLADO DE ASISTENCIAS'), 0, 1, 'C');

        $this->SetFont('Arial', '', 8.5);
        $this->SetTextColor(110, 110, 110);
        $this->Cell(0, 5, ('Fecha de emisión: ' . date('d/m/Y H:i:s')), 0, 1, 'C');
        $this->Ln(3);

        // Cabeceras de la tabla (Ancho exacto: 55 + 60 + 50 + 25 + 45 + 25 = 260 mm)
        $this->SetFillColor(57, 169, 0);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 8);

        $this->Cell(55, 8,('Aprendiz'), 1, 0, 'L', true);
        $this->Cell(60, 8,('Ficha / Programa'), 1, 0, 'L', true);
        $this->Cell(50, 8,('Instructor'), 1, 0, 'L', true);
        $this->Cell(25, 8,('Retardos'), 1, 0, 'C', true);
        $this->Cell(45, 8,('Retardo Acumulado'), 1, 0, 'C', true);
        $this->Cell(25, 8,('Inasistencias'), 1, 1, 'C', true);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(130, 130, 130);
        $this->Cell(0, 10, ('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C'); //pie de pagina
    }
}

// 4. Instanciación: Formato horizontal ('L') tamaño Carta ('Letter')
$pdf = new PDF('L', 'mm', 'Letter');
$pdf->AliasNbPages();
$pdf->SetMargins(10, 10, 10);//margenes
$pdf->SetAutoPageBreak(true, 15);//activa el salto de pagina 
$pdf->AddPage();//esta me srive para agregar pagina con cabecera

$pdf->SetFont('Arial', '', 8);//fuente de eltra 
$pdf->SetTextColor(30, 30, 30);

$fill = false; // Alternar color de fila (efecto cebra)

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $pdf->SetFillColor(245, 245, 245);

    $aprendiz   = $row['nombre_completo'] . ' ' . $row['apellido'];
    $ficha      = $row['numero_ficha'] . ' - ' . $row['nombre_programa'];
    $instructor = trim(($row['nombre_instructor'] ?? '') . ' ' . ($row['apellido_instructor'] ?? '')) ?: 'Sin instructor';

    // Se usan EXACTAMENTE los mismos anchos que el Header (55, 60, 50, 25, 45, 25)
    $pdf->Cell(55, 7, (substr($aprendiz, 0, 32)), 1, 0, 'L', $fill);
    $pdf->Cell(60, 7, (substr($ficha, 0, 38)), 1, 0, 'L', $fill);
    $pdf->Cell(50, 7, (substr($instructor, 0, 28)), 1, 0, 'L', $fill);
    $pdf->Cell(25, 7, ($row['retardos'] ?? 0), 1, 0, 'C', $fill);
    $pdf->Cell(45, 7, ($row['tiempo_retraso_formato']), 1, 0, 'C', $fill);
    $pdf->Cell(25, 7, ($row['inasistencias'] ?? 0), 1, 1, 'C', $fill);

    $fill = !$fill;
}

// 5. Visualizar directo en el navegador
$pdf->Output('I', 'reporte_asistencia_' . date('Ymd_His') . '.pdf');
exit();
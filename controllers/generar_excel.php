<?php
session_start();
if (!isset($_SESSION["Documento"])) {
    header("Location: ../index.php");
    exit();
}


require_once(__DIR__ . '/../libs/SimpleXLSXGen.php');
require_once __DIR__ . "/../config/mysql.php";

$db = new mysql();
$db->conectar();

// Recibir filtros del formulario 
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

// Construir filtros dinámicos
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
    COALESCE(SUM(asistencias.tiempo_retraso), 0) AS min_retardo,
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

// Armar los datos con cabeceras estilizadas
$datos = [
    [
        '<b>Aprendiz</b>',
        '<b>Ficha</b>',
        '<b>Instructor</b>',
        '<center><b>Retardos</b></center>',
        '<center><b>Minutos Retardo</b></center>',
        '<center><b>Inasistencias</b></center>'
    ]
];
//recorremos la informacion traida de la consulta
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $datos[] = [
        $row['nombre_completo'] . ' ' . $row['apellido'],
        $row['numero_ficha'] . ' · ' . $row['nombre_programa'],
        trim(($row['nombre_instructor'] ?? '') . ' ' . ($row['apellido_instructor'] ?? '')) ?: 'Sin instructor',
        ($row['retardos'] ?? 0),
        ($row['min_retardo'] ?? 0),
        ($row['inasistencias'] ?? 0)
    ];
}

// Descargar directamente como .xlsx
$nombreArchivo = 'reporte_asistencia_' . date('Ymd_His') . '.xlsx';
//aca realizo la conversion de la informacion a excel
$xlsx = Shuchkin\SimpleXLSXGen::fromArray($datos);
$xlsx->downloadAs($nombreArchivo);
exit();


?>
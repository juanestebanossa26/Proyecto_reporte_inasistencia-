<?php
session_start();
if (!isset($_SESSION["Documento"])) {
    header("Location: ../index.php");
    exit();
}
require_once __DIR__ . "/../config/mysql.php";
$db = new mysql();
$db->conectar();

// 1. Obtener lista de fichas para el selector
$sqlFichas = "SELECT id_ficha, numero_ficha, nombre_programa FROM fichas ORDER BY numero_ficha ASC";
$resFichas = $db->prepare($sqlFichas);
$resFichas->execute();
$fichasDisponibles = $resFichas->fetchAll();

// 2. Captura y cálculo de filtros por POST
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

// 3. Condiciones WHERE dinámicas
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

// Consulta de la tabla de aprendices
$sqlTabla = "SELECT 
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
JOIN usuarios u_aprendiz 
    ON aprendices.id_usuario = u_aprendiz.id_usuario
JOIN fichas 
    ON aprendices.id_ficha = fichas.id_ficha
LEFT JOIN usuarios u_instructor 
    ON fichas.id_instructor = u_instructor.id_usuario
LEFT JOIN asistencias 
    ON asistencias.id_aprendiz = aprendices.id_aprendiz
LEFT JOIN excusas_medicas 
    ON excusas_medicas.id_asistencia = asistencias.id_asistencia
$whereSql
GROUP BY 
    aprendices.id_aprendiz,
    u_aprendiz.nombre_completo,
    u_aprendiz.apellido,
    fichas.numero_ficha,
    fichas.nombre_programa,
    u_instructor.nombre_completo,
    u_instructor.apellido";

$resultado = $db->prepare($sqlTabla);
$resultado->execute($params);

// Consulta de los contadores
$sqlContadores = "SELECT 
    COUNT(DISTINCT aprendices.id_aprendiz) AS aprendices_en_rango,
    COUNT(NULLIF(asistencias.estado = 'Retardo', 0)) AS total_retardos,
    COUNT(NULLIF(asistencias.estado = 'Inasistencia' AND (excusas_medicas.estado_excusa IS NULL OR excusas_medicas.estado_excusa != 'Aprobada'), 0)) AS total_inasistencias,
    COALESCE(SUM(asistencias.tiempo_retraso), 0) AS minutos_retardo
FROM aprendices
JOIN fichas 
    ON aprendices.id_ficha = fichas.id_ficha
LEFT JOIN asistencias 
    ON asistencias.id_aprendiz = aprendices.id_aprendiz
LEFT JOIN excusas_medicas 
    ON excusas_medicas.id_asistencia = asistencias.id_asistencia
$whereSql";

$contadores = $db->prepare($sqlContadores);
$contadores->execute($params);
$filaContadores = $contadores->fetch();

$rolActivo    = $_SESSION['rol'] ?? 'admin';
$paginaActiva = 'reportes';
$tituloPagina = 'Reportes de asistencia';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include 'includes/head.php'; ?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body>
<div class="app-shell">
    <?php include 'includes/sidebar.php'; ?>
    <div class="app-main">
        <?php include 'includes/topbar.php'; ?>
        <main class="app-content">
            <div class="page-header">
                <div class="eyebrow">Reportes</div>
                <h1 class="h4 fw-bold mb-0">Reportes de asistencia</h1>
                <p class="text-suave mb-0">Genera reportes por día, semana o rango personalizado y expórtalos.</p>
            </div>

            <div class="card p-3 mb-3">
                <form method="POST" action="" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Tipo de rango</label>
                        <select class="form-select" name="rango">
                            <option value="dia" <?= ($rango === 'dia') ? 'selected' : '' ?>>Un día</option>
                            <option value="semana" <?= ($rango === 'semana') ? 'selected' : '' ?>>Semana actual</option>
                            <option value="personalizado" <?= ($rango === 'personalizado') ? 'selected' : '' ?>>Rango personalizado</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Desde</label>
                        <input type="date" class="form-control" name="desde" value="<?= htmlspecialchars($desde) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Hasta</label>
                        <input type="date" class="form-control" name="hasta" value="<?= htmlspecialchars($hasta) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Ficha</label>
                        <select class="form-select" name="ficha_id">
                            <option value="">Todas las fichas</option>
                            <?php foreach ($fichasDisponibles as $f): ?>
                                <option value="<?= $f['id_ficha'] ?>" <?= ($ficha_id == $f['id_ficha']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($f['numero_ficha'] . ' · ' . $f['nombre_programa']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 d-flex justify-content-between flex-wrap gap-2">
    <!-- Botón normal: recarga la misma página aplicando filtros -->
    <button type="submit" class="btn btn-sena">
        <i class="bi bi-funnel me-1"></i> Generar reporte
    </button>
    
    <div class="d-flex gap-2">
        <!-- Envía los filtros por POST a generar_pdf.php -->
        <button type="submit" 
                class="btn btn-outline-secondary" 
                formaction="../controllers/generar_pdf.php" 
                formtarget="_blank">
            <i class="bi bi-file-earmark-pdf me-1"></i> Exportar PDF
        </button>

        <!-- Envía los filtros por POST a generar_excel.php -->
        <button type="submit" 
                class="btn btn-outline-secondary" 
                formaction="../controllers/generar_excel.php" 
                formtarget="_blank">
            <i class="bi bi-file-earmark-excel me-1"></i> Exportar Excel
        </button>
    </div>
</div>
                </form>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 text-center">
                        <div class="text-suave" style="font-size:.76rem;">Aprendices en el rango</div>
                        <div class="h4 fw-bold mb-0"><?= $filaContadores['aprendices_en_rango'] ?? 0 ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 text-center">
                        <div class="text-suave" style="font-size:.76rem;">Total retardos</div>
                        <div class="h4 fw-bold mb-0" style="color:#8a6300;"><?= $filaContadores['total_retardos'] ?? 0 ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 text-center">
                        <div class="text-suave" style="font-size:.76rem;">Total inasistencias</div>
                        <div class="h4 fw-bold mb-0" style="color:#B4212B;"><?= $filaContadores['total_inasistencias'] ?? 0 ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 text-center">
                        <div class="text-suave" style="font-size:.76rem;">Minutos de retardo</div>
                        <div class="h4 fw-bold mb-0" style="color:#2F6FED;"><?= $filaContadores['minutos_retardo'] ?? 0 ?></div>
                    </div>
                </div>
            </div>

            <div class="card p-3">
                <h2 class="h6 fw-bold mb-3">Detalle por aprendiz</h2>
                <div class="table-responsive">
                    <table id="tablaReportes" class="table table-app align-middle mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th>Aprendiz</th>
                                <th>Ficha</th>
                                <th>Instructor</th>
                                <th class="text-center">Retardos</th>
                                <th class="text-center">Tiempo Total de Retardo</th>
                                <th class="text-center">Inasistencias</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($fila = $resultado->fetch()): ?>
                            <tr>
                                <td class="d-flex align-items-center gap-2">
                                    <span class="avatar-circ"><?= strtoupper(substr($fila['nombre_completo'], 0, 1)) ?></span>
                                    <?= htmlspecialchars($fila['nombre_completo'] . ' ' . $fila['apellido']) ?>
                                </td>
                                <td><?= htmlspecialchars($fila['nombre_programa'] . ' · ' . $fila['numero_ficha']) ?></td>
                                <td><?= htmlspecialchars(trim(($fila['nombre_instructor'] ?? '') . ' ' . ($fila['apellido_instructor'] ?? ''))) ?: 'Sin instructor' ?></td>
                                <td class="text-center"><?= $fila['retardos'] ?? 0 ?></td>
                                <td class="text-center"><?= $fila['tiempo_retraso_formato'] ?></td>
                                <td class="text-center"><?= $fila['inasistencias'] ?? 0 ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-suave mt-3 mb-0" style="font-size:.8rem;">
                    * Las inasistencias con excusa médica aprobada ya han sido excluidas de este reporte.
                </p>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/scripts.php'; ?>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#tablaReportes').DataTable({
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
        },
        order: [],
        pageLength: 10,
        responsive: true
    });
});
</script>
</body>
</html>
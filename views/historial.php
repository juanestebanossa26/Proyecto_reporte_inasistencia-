<?php
/**
 * historial.php — Historial personal de asistencia (HU 6, 15), vista aprendiz
 *
 * TODO (backend):
 *   - Reemplazar $historial por el resultado de obtener_inasistencias_reales()
 *     (función expuesta por el módulo de reportes, ver contrato de datos)
 *   - El botón "Descargar mi reporte" debe generar un PDF/Excel individual
 */
$rolActivo     = 'aprendiz';
$nombreUsuario = 'Laura Martínez';
$paginaActiva  = 'historial';
$tituloPagina  = 'Mi historial de asistencia';

$historial = [
    ['fecha'=>'11/08/2026','entrada'=>'6:58 a.m.','salida'=>'12:30 p.m.','estado'=>'a-tiempo'],
    ['fecha'=>'10/08/2026','entrada'=>'7:15 a.m.','salida'=>'12:30 p.m.','estado'=>'retardo'],
    ['fecha'=>'07/08/2026','entrada'=>'—','salida'=>'—','estado'=>'inasistencia'],
    ['fecha'=>'06/08/2026','entrada'=>'6:55 a.m.','salida'=>'11:40 a.m.','estado'=>'salida-anticipada'],
    ['fecha'=>'05/08/2026','entrada'=>'—','salida'=>'—','estado'=>'a-tiempo', 'nota'=>'Excusa médica aprobada'],
];
$etiquetas = [
    'a-tiempo' => 'A tiempo', 'retardo' => 'Retardo',
    'inasistencia' => 'Inasistencia', 'salida-anticipada' => 'Salida anticipada',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include '../includes/head.php'; ?>
</head>
<body>
<div class="app-shell">
    <?php include '../includes/sidebar.php'; ?>
    <div class="app-main">
        <?php include '../includes/topbar.php'; ?>
        <main class="app-content">
            <div class="page-header d-flex justify-content-between align-items-end flex-wrap gap-2">
                <div>
                    <div class="eyebrow">Mi asistencia</div>
                    <h1 class="h4 fw-bold mb-0">Mi historial</h1>
                    <p class="text-suave mb-0">Consulta tus retardos, inasistencias y el estado de tus excusas.</p>
                </div>
                <button class="btn btn-outline-sena"><i class="bi bi-download me-1"></i> Descargar mi reporte</button>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 text-center">
                        <div class="text-suave" style="font-size:.76rem;">Asistencias</div>
                        <div class="h4 fw-bold mb-0 text-sena">18</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 text-center">
                        <div class="text-suave" style="font-size:.76rem;">Retardos</div>
                        <div class="h4 fw-bold mb-0" style="color:#8a6300;">2</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 text-center">
                        <div class="text-suave" style="font-size:.76rem;">Inasistencias</div>
                        <div class="h4 fw-bold mb-0" style="color:#B4212B;">1</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 text-center">
                        <div class="text-suave" style="font-size:.76rem;">Excusas aprobadas</div>
                        <div class="h4 fw-bold mb-0" style="color:#2F6FED;">1</div>
                    </div>
                </div>
            </div>

            <div class="card p-3">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <input type="date" class="form-control" style="max-width:180px;">
                    <span class="align-self-center text-suave">a</span>
                    <input type="date" class="form-control" style="max-width:180px;">
                    <button class="btn btn-outline-sena">Filtrar</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-app align-middle mb-0">
                        <thead>
                            <tr><th>Fecha</th><th>Entrada</th><th>Salida</th><th>Estado</th><th>Nota</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($historial as $h): ?>
                            <tr>
                                <td><?= htmlspecialchars($h['fecha']) ?></td>
                                <td><?= htmlspecialchars($h['entrada']) ?></td>
                                <td><?= htmlspecialchars($h['salida']) ?></td>
                                <td><span class="badge-estado badge-<?= $h['estado'] ?>"><?= $etiquetas[$h['estado']] ?></span></td>
                                <td class="text-suave" style="font-size:.82rem;"><?= htmlspecialchars($h['nota'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/scripts.php'; ?>
</body>
</html>

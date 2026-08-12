<?php
/**
 * reportes.php — Generación de reportes de inasistencia (HU 11, 12)
 *
 * TODO (backend):
 *   - El formulario de filtros debe enviarse a generar_reporte.php (GET/POST),
 *     que consulta `ingresos` + obtener_inasistencias_reales() para excluir
 *     inasistencias ya justificadas con excusa aprobada (HU 18, 19).
 *   - Los botones "Exportar PDF" / "Exportar Excel" deben apuntar a
 *     exportar_reporte.php?formato=pdf|excel con los mismos filtros aplicados.
 */
$rolActivo     = $_GET['rol'] ?? 'admin';
$nombreUsuario = 'Juan Camilo Vanegas';
$paginaActiva  = 'reportes';
$tituloPagina  = 'Reportes de asistencia';

$filas = [
    ['aprendiz'=>'Camila Suárez','ficha'=>'2678903 · Multimedia','instructor'=>'Marcela Ortiz','retardos'=>1,'inasistencias'=>2,'minutos_retardo'=>15],
    ['aprendiz'=>'Julián Rojas','ficha'=>'2589451 · ADSO','instructor'=>'Juan Camilo Vanegas','retardos'=>3,'inasistencias'=>0,'minutos_retardo'=>42],
    ['aprendiz'=>'Diego Peña','ficha'=>'2589451 · ADSO','instructor'=>'Juan Camilo Vanegas','retardos'=>0,'inasistencias'=>1,'minutos_retardo'=>0],
    ['aprendiz'=>'Andrés Gil','ficha'=>'2589451 · ADSO','instructor'=>'Juan Camilo Vanegas','retardos'=>2,'inasistencias'=>0,'minutos_retardo'=>18],
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
            <div class="page-header">
                <div class="eyebrow">Reportes</div>
                <h1 class="h4 fw-bold mb-0">Reportes de asistencia</h1>
                <p class="text-suave mb-0">Genera reportes por día, semana o rango personalizado y expórtalos.</p>
            </div>

            <div class="card p-3 mb-3">
                <!-- TODO (backend): action="generar_reporte.php" method="GET" -->
                <form class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Tipo de rango</label>
                        <select class="form-select" name="rango">
                            <option value="dia">Un día</option>
                            <option value="semana">Semana actual</option>
                            <option value="personalizado" selected>Rango personalizado</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Desde</label>
                        <input type="date" class="form-control" name="desde">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Hasta</label>
                        <input type="date" class="form-control" name="hasta">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Ficha</label>
                        <select class="form-select" name="ficha_id">
                            <option>Todas las fichas</option>
                            <option>2589451 · ADSO</option>
                            <option>2678903 · Multimedia</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex justify-content-between flex-wrap gap-2">
                        <button type="submit" class="btn btn-sena"><i class="bi bi-funnel me-1"></i> Generar reporte</button>
                        <div class="d-flex gap-2">
                            <a href="#" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1"></i> Exportar PDF</a>
                            <a href="#" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-excel me-1"></i> Exportar Excel</a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 text-center">
                        <div class="text-suave" style="font-size:.76rem;">Aprendices en el rango</div>
                        <div class="h4 fw-bold mb-0"><?= count($filas) ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 text-center">
                        <div class="text-suave" style="font-size:.76rem;">Total retardos</div>
                        <div class="h4 fw-bold mb-0" style="color:#8a6300;"><?= array_sum(array_column($filas,'retardos')) ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 text-center">
                        <div class="text-suave" style="font-size:.76rem;">Total inasistencias</div>
                        <div class="h4 fw-bold mb-0" style="color:#B4212B;"><?= array_sum(array_column($filas,'inasistencias')) ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 text-center">
                        <div class="text-suave" style="font-size:.76rem;">Minutos de retardo</div>
                        <div class="h4 fw-bold mb-0" style="color:#2F6FED;"><?= array_sum(array_column($filas,'minutos_retardo')) ?></div>
                    </div>
                </div>
            </div>

            <div class="card p-3">
                <h2 class="h6 fw-bold mb-3">Detalle por aprendiz</h2>
                <div class="table-responsive">
                    <table class="table table-app align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Aprendiz</th><th>Ficha</th><th>Instructor</th>
                                <th class="text-center">Retardos</th>
                                <th class="text-center">Min. retardo</th>
                                <th class="text-center">Inasistencias</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($filas as $f): ?>
                            <tr>
                                <td class="d-flex align-items-center gap-2">
                                    <span class="avatar-circ"><?= strtoupper(substr($f['aprendiz'],0,1)) ?></span>
                                    <?= htmlspecialchars($f['aprendiz']) ?>
                                </td>
                                <td><?= htmlspecialchars($f['ficha']) ?></td>
                                <td><?= htmlspecialchars($f['instructor']) ?></td>
                                <td class="text-center"><?= (int)$f['retardos'] ?></td>
                                <td class="text-center"><?= (int)$f['minutos_retardo'] ?></td>
                                <td class="text-center"><?= (int)$f['inasistencias'] ?></td>
                            </tr>
                        <?php endforeach; ?>
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

<?php include '../includes/scripts.php'; ?>
</body>
</html>

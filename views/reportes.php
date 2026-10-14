<?php
// Variables por defecto para evitar errores si el controlador no las define
$rolActivo         = $_SESSION['rol'] ?? 'Administrador';
$nombreUsuario     = $_SESSION['nombre'] ?? '';
$paginaActiva      = 'reportes';
$tituloPagina      = 'Reportes de asistencia';
$fichasDisponibles = $fichasDisponibles ?? [];
$filasReporte      = $filasReporte ?? [];
$filaContadores    = $filaContadores ?? [];
$rango             = $rango ?? 'dia';
$desde             = $desde ?? date('Y-m-d');
$hasta             = $hasta ?? date('Y-m-d');
$ficha_id          = $ficha_id ?? '';
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
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Seguridad::tokenCsrf()) ?>">
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
                                    formaction="<?= BASE_URL ?>/index.php?accion=generarPdf" 
                                    formtarget="_blank">
                                <i class="bi bi-file-earmark-pdf me-1"></i> Exportar PDF
                            </button>

                            <!-- Envía los filtros por POST a generar_excel.php -->
                            <button type="submit" 
                                    class="btn btn-outline-secondary" 
                                    formaction="<?= BASE_URL ?>/index.php?accion=generarExcel" 
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
                        <?php foreach ($filasReporte as $fila):
                            $minutos = (int) ($fila['min_retardo'] ?? 0);
                            $tiempoFormato = $minutos >= 60
                                ? floor($minutos / 60) . 'h ' . ($minutos % 60) . 'm'
                                : $minutos . 'm';
                        ?>
                            <tr>
                                <td class="d-flex align-items-center gap-2">
                                    <span class="avatar-circ"><?= strtoupper(substr($fila['nombre_completo'], 0, 1)) ?></span>
                                    <?= htmlspecialchars($fila['nombre_completo']) ?>
                                </td>
                                <td><?= htmlspecialchars($fila['nombre_programa'] . ' · ' . $fila['numero_ficha']) ?></td>
                                <!-- Corrección: nombre_instructor ya contiene el nombre y apellido combinados desde el SQL -->
                                <td><?= htmlspecialchars(!empty($fila['nombre_instructor']) ? $fila['nombre_instructor'] : 'Sin instructor') ?></td>
                                <td class="text-center"><?= $fila['retardos'] ?? 0 ?></td>
                                <td class="text-center"><?= $tiempoFormato ?></td>
                                <td class="text-center"><?= $fila['inasistencias'] ?? 0 ?></td>
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
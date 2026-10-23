<?php
// Vista del Dashboard para Instructor
// Muestra métricas de sus fichas, horarios de hoy, ingresos de sus aprendices y excusas asignadas.

if (!function_exists('getBadgeClassDashboard')) {
    function getBadgeClassDashboard(string $estado): string {
        return match(strtolower(trim($estado))) {
            'a tiempo', 'asistencia' => 'badge-a-tiempo',
            'retardo' => 'badge-retardo',
            'inasistencia' => 'badge-inasistencia',
            'salida anticipada' => 'badge-salida-anticipada',
            'pendiente' => 'badge-pendiente',
            'aprobada' => 'badge-aprobada',
            'rechazada' => 'badge-rechazada',
            default => 'badge-secondary text-dark bg-light'
        };
    }
}

$nombreCorto = htmlspecialchars(explode(' ', $nombreUsuario ?? 'Instructor')[0]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'includes/head.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css?v=<?= file_exists(BASE_PATH . '/assets/css/dashboard.css') ? filemtime(BASE_PATH . '/assets/css/dashboard.css') : '1.0' ?>">
</head>
<body>
<div class="app-shell">
    <?php include 'includes/sidebar.php'; ?>

    <div class="app-main">
        <?php include 'includes/topbar.php'; ?>

        <main class="app-content">

            <!-- Encabezado de bienvenida -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <div class="eyebrow"><i class="bi bi-person-video3 me-1"></i> Panel Académico · Instructor</div>
                    <h1 class="h3 fw-bold mb-1">Hola, Instructor <?= $nombreCorto ?> 👋</h1>
                    <p class="text-suave mb-0">Control de asistencia y seguimiento de tus fichas y aprendices a cargo.</p>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="badge bg-white text-dark border p-2 px-3 rounded-pill shadow-sm">
                        <i class="bi bi-calendar-event text-sena me-1"></i> <?= date('d/m/Y') ?>
                    </span>
                    <a href="<?= BASE_URL ?>/index.php?accion=validarExcusas" class="btn btn-outline-danger position-relative">
                        <i class="bi bi-file-earmark-medical me-1"></i> Validar Excusas
                        <?php if (!empty($kpis['excusas_pendientes'])): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?= $kpis['excusas_pendientes'] ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <a href="<?= BASE_URL ?>/index.php?accion=reportes" class="btn btn-sena">
                        <i class="bi bi-bar-chart-line-fill me-1"></i> Reportes de mi Ficha
                    </a>
                </div>
            </div>

            <!-- Accesos Rápidos del Instructor -->
            <div class="d-flex flex-wrap gap-2 mb-4">
                <a href="<?= BASE_URL ?>/index.php?accion=horarios" class="action-pill">
                    <i class="bi bi-calendar3 text-primary"></i> Mi Cronograma / Horarios
                </a>
                <a href="<?= BASE_URL ?>/index.php?accion=fichas" class="action-pill">
                    <i class="bi bi-journal-bookmark text-success"></i> Mis Fichas
                </a>
                <a href="<?= BASE_URL ?>/index.php?accion=validarExcusas" class="action-pill">
                    <i class="bi bi-file-earmark-medical text-danger"></i> Revisar Excusas
                    <?php if (!empty($kpis['excusas_pendientes'])): ?>
                        <span class="badge bg-danger ms-1"><?= $kpis['excusas_pendientes'] ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= BASE_URL ?>/index.php?accion=reportes" class="action-pill">
                    <i class="bi bi-bar-chart text-warning"></i> Reportes de Asistencia
                </a>
            </div>

            <!-- KPIs de Fichas y Aprendices a cargo -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 h-100">
                        <div class="d-flex align-items-center gap-3">
                            <div class="kpi-icon" style="background:rgba(57,169,0,.12); color:#1F7A00;">
                                <i class="bi bi-journal-check"></i>
                            </div>
                            <div>
                                <div class="text-suave" style="font-size:.76rem;">Mis Fichas Asignadas</div>
                                <div class="h4 fw-bold mb-0"><?= number_format($kpis['total_fichas'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 h-100">
                        <div class="d-flex align-items-center gap-3">
                            <div class="kpi-icon" style="background:rgba(47,111,237,.12); color:#2F6FED;">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <div>
                                <div class="text-suave" style="font-size:.76rem;">Aprendices a mi Cargo</div>
                                <div class="h4 fw-bold mb-0"><?= number_format($kpis['total_aprendices'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 h-100">
                        <div class="d-flex align-items-center gap-3">
                            <div class="kpi-icon" style="background:rgba(111,66,193,.12); color:#6f42c1;">
                                <i class="bi bi-calendar2-day-fill"></i>
                            </div>
                            <div>
                                <div class="text-suave" style="font-size:.76rem;">Clases Programadas Hoy</div>
                                <div class="h4 fw-bold mb-0"><?= number_format($kpis['clases_hoy'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 h-100 <?= !empty($kpis['excusas_pendientes']) ? 'border border-danger' : '' ?>">
                        <div class="d-flex align-items-center gap-3">
                            <div class="kpi-icon" style="background:rgba(214,69,80,.12); color:#B4212B;">
                                <i class="bi bi-file-earmark-medical-fill"></i>
                            </div>
                            <div>
                                <div class="text-suave" style="font-size:.76rem;">Excusas por Validar</div>
                                <div class="h4 fw-bold mb-0 text-danger"><?= number_format($kpis['excusas_pendientes'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contenido Principal: Dos Columnas -->
            <div class="row g-3">
                <!-- Columna Izquierda: Clases de Hoy e Ingresos Recientes -->
                <div class="col-lg-8">
                    <!-- Clases / Horarios Programados de Hoy -->
                    <div class="card p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h2 class="h6 fw-bold mb-0">
                                    <i class="bi bi-calendar-check text-sena me-1"></i> Clases Programadas para Hoy
                                </h2>
                                <small class="text-suave">Sesiones asignadas en tu cronograma para <?= date('d/m/Y') ?></small>
                            </div>
                            <a href="<?= BASE_URL ?>/index.php?accion=horarios" class="btn btn-sm btn-outline-sena">
                                Ver Calendario Completo
                            </a>
                        </div>

                        <?php if (empty($horariosHoy)): ?>
                            <div class="alert alert-light border text-center py-4 my-2">
                                <i class="bi bi-calendar2-check fs-2 text-sena d-block mb-2"></i>
                                <div class="fw-semibold">No tienes clases programadas para el día de hoy.</div>
                                <small class="text-suave">Puedes consultar las sesiones programadas para otros días en la pestaña Horarios.</small>
                            </div>
                        <?php else: ?>
                            <div class="row g-2">
                                <?php foreach ($horariosHoy as $clase): 
                                    $hIni = date('h:i a', strtotime($clase['hora_entrada_programada']));
                                    $hFin = date('h:i a', strtotime($clase['hora_salida_programada']));
                                ?>
                                <div class="col-md-6">
                                    <div class="p-3 border rounded-3 bg-light h-100 d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <span class="fw-bold text-sena fs-6"><?= htmlspecialchars($clase['numero_ficha']) ?></span>
                                                <span class="class-badge-time">
                                                    <i class="bi bi-clock me-1"></i><?= $hIni ?> - <?= $hFin ?>
                                                </span>
                                            </div>
                                            <div class="text-truncate fw-semibold mb-2" style="font-size:.86rem;" title="<?= htmlspecialchars($clase['nombre_programa']) ?>">
                                                <?= htmlspecialchars($clase['nombre_programa']) ?>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                            <small class="text-suave">
                                                <i class="bi bi-people me-1"></i><?= (int) $clase['total_aprendices'] ?> aprendices
                                            </small>
                                            <form method="POST" action="<?= BASE_URL ?>/index.php?accion=reportes" class="d-inline">
                                                <input type="hidden" name="ficha_id" value="<?= $clase['id_ficha'] ?>">
                                                <input type="hidden" name="rango" value="dia">
                                                <input type="hidden" name="desde" value="<?= date('Y-m-d') ?>">
                                                <input type="hidden" name="hasta" value="<?= date('Y-m-d') ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:.76rem;">
                                                    Ver asistencia
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Ingresos Recientes de mis Fichas -->
                    <?php 
                        $totalIngresos = count($ingresosRecientes);
                        $porPagina = 5;
                        $totalPaginas = max(1, (int) ceil($totalIngresos / $porPagina));
                    ?>
                    <div class="card p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h2 class="h6 fw-bold mb-0">
                                    <i class="bi bi-clock-history text-sena me-1"></i> Ingresos Recientes en tus Fichas
                                </h2>
                                <small class="text-suave">Últimos registros de asistencia de tus aprendices</small>
                            </div>
                            <a href="<?= BASE_URL ?>/index.php?accion=reportes" class="btn btn-sm btn-outline-secondary">
                                Generar reporte
                            </a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-app table-compact-app align-middle mb-0" id="tablaIngresosInstructor">
                                <thead>
                                    <tr>
                                        <th style="width: 28%;">Aprendiz</th>
                                        <th style="width: 24%;">Ficha</th>
                                        <th style="width: 14%;">Fecha</th>
                                        <th style="width: 11%;">Entrada</th>
                                        <th style="width: 11%;">Salida</th>
                                        <th style="width: 12%;">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($ingresosRecientes)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                <i class="bi bi-inbox fs-3 d-block mb-1 text-secondary"></i>
                                                No hay registros de asistencia de tus fichas recientemente.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($ingresosRecientes as $idx => $ingreso): 
                                            $partes = explode(' ', trim($ingreso['nombre_completo'] ?? 'Aprendiz'));
                                            $iniciales = strtoupper(substr($partes[0] ?? 'A', 0, 1) . substr($partes[1] ?? '', 0, 1));
                                            $nombre = htmlspecialchars($ingreso['nombre_completo'] ?? 'Sin nombre');
                                            $doc = htmlspecialchars($ingreso['documento'] ?? '');
                                            $fichaNum = htmlspecialchars($ingreso['numero_ficha'] ?: 'Sin ficha');
                                            $prog = htmlspecialchars($ingreso['nombre_programa'] ?: '');
                                            $fechaFmt = date('d/m/Y', strtotime($ingreso['fecha']));
                                            $horaEntrada = !empty($ingreso['hora_entrada_real']) ? date('h:i a', strtotime($ingreso['hora_entrada_real'])) : '—';
                                            $horaSalida = !empty($ingreso['hora_salida_real']) ? date('h:i a', strtotime($ingreso['hora_salida_real'])) : '—';
                                            $estado = $ingreso['estado'] ?? 'Asistencia';
                                        ?>
                                        <tr class="fila-dashboard" data-index="<?= $idx ?>" style="<?= $idx >= $porPagina ? 'display: none;' : '' ?>">
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="avatar-circ-sm"><?= $iniciales ?></span>
                                                    <div class="overflow-hidden">
                                                        <div class="fw-semibold text-truncate" style="font-size: .83rem; line-height: 1.25;" title="<?= $nombre ?>"><?= $nombre ?></div>
                                                        <small class="text-suave d-block" style="font-size: .72rem;">Doc: <?= $doc ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-truncate" style="max-width: 170px; font-size: .82rem; line-height: 1.25;" title="<?= $prog ?>">
                                                    <?= $fichaNum ?>
                                                </div>
                                                <small class="text-suave d-block text-truncate" style="max-width: 170px; font-size: .72rem;" title="<?= $prog ?>"><?= $prog ?></small>
                                            </td>
                                            <td><small class="fw-semibold" style="font-size: .78rem;"><?= $fechaFmt ?></small></td>
                                            <td><small class="text-success fw-semibold" style="font-size: .78rem;"><?= $horaEntrada ?></small></td>
                                            <td><small class="text-secondary" style="font-size: .78rem;"><?= $horaSalida ?></small></td>
                                            <td>
                                                <span class="badge-estado badge-estado-sm <?= getBadgeClassDashboard($estado) ?>">
                                                    <?= ucfirst(htmlspecialchars($estado)) ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($totalIngresos > 0): ?>
                        <!-- Paginación de Ingresos Recientes -->
                        <div class="d-flex flex-wrap justify-content-between align-items-center dashboard-pagination" id="paginacionIngresosInstructor">
                            <small class="text-suave info-paginacion">Mostrando 1 - <?= min($porPagina, $totalIngresos) ?> de <?= $totalIngresos ?> registros</small>
                            <div class="d-flex align-items-center gap-1">
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-prev" disabled style="font-size: .78rem;" title="Página anterior">
                                    <i class="bi bi-chevron-left me-1"></i> Anterior
                                </button>
                                <span class="px-2 small fw-semibold text-secondary indicador-pagina" style="font-size: .78rem;">Pág. 1 de <?= $totalPaginas ?></span>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-next" <?= $totalPaginas <= 1 ? 'disabled' : '' ?> style="font-size: .78rem;" title="Página siguiente">
                                    Siguiente <i class="bi bi-chevron-right ms-1"></i>
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Columna Derecha: Excusas Asignadas y Mis Fichas -->
                <div class="col-lg-4">
                    <!-- Excusas Pendientes por Validar -->
                    <div class="card p-3 mb-3 border-top border-4 border-danger">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="h6 fw-bold mb-0">
                                <i class="bi bi-file-earmark-medical text-danger me-1"></i> Excusas Asignadas
                            </h2>
                            <?php if (!empty($kpis['excusas_pendientes'])): ?>
                                <span class="badge bg-danger rounded-pill"><?= $kpis['excusas_pendientes'] ?> pendientes</span>
                            <?php endif; ?>
                        </div>

                        <?php if (empty($excusasPendientes)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-shield-check fs-3 text-sena d-block mb-1"></i>
                                ¡Todo al día! No tienes excusas médicas pendientes por validar.
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush mb-3">
                                <?php foreach ($excusasPendientes as $excusa): ?>
                                <div class="list-group-item px-0 py-2 border-bottom">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div>
                                            <div class="fw-semibold text-truncate" style="font-size:.85rem; max-width:180px;">
                                                <?= htmlspecialchars($excusa['aprendiz_nombre']) ?>
                                            </div>
                                            <small class="text-suave d-block">
                                                Ficha: <?= htmlspecialchars($excusa['numero_ficha'] ?: 'S/F') ?> · Fecha: <?= date('d/m/Y', strtotime($excusa['fecha_inasistencia'])) ?>
                                            </small>
                                            <small class="text-muted d-block text-truncate" style="max-width:200px;">
                                                <?= htmlspecialchars($excusa['motivo']) ?>
                                            </small>
                                        </div>
                                        <span class="badge-estado badge-pendiente">Pendiente</span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <a href="<?= BASE_URL ?>/index.php?accion=validarExcusas" class="btn btn-sm btn-danger w-100">
                            <i class="bi bi-check2-circle me-1"></i> Gestionar y Validar Excusas
                        </a>
                    </div>

                    <!-- Mis Fichas Asignadas -->
                    <?php 
                        $totalFichas = count($misFichas);
                        $porPaginaFichas = 3;
                        $totalPaginasFichas = max(1, (int) ceil($totalFichas / $porPaginaFichas));
                    ?>
                    <div class="card p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="h6 fw-bold mb-0">
                                <i class="bi bi-journal-bookmark text-sena me-1"></i> Mis Fichas Asignadas
                            </h2>
                            <a href="<?= BASE_URL ?>/index.php?accion=fichas" class="text-suave small">Ver todas</a>
                        </div>

                        <?php if (empty($misFichas)): ?>
                            <p class="text-muted text-center py-3 mb-0 small">No tienes fichas asignadas actualmente.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush" id="listaMisFichas">
                                <?php foreach ($misFichas as $idx => $f): ?>
                                <div class="list-group-item px-0 py-2 border-bottom fila-ficha-dashboard" data-index="<?= $idx ?>" style="<?= $idx >= $porPaginaFichas ? 'display: none;' : '' ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold text-sena" style="font-size:.88rem;">
                                                <?= htmlspecialchars($f['numero_ficha']) ?>
                                            </div>
                                            <small class="text-suave text-truncate d-block" style="max-width: 190px;" title="<?= htmlspecialchars($f['nombre_programa']) ?>">
                                                <?= htmlspecialchars($f['nombre_programa']) ?>
                                            </small>
                                            <small class="text-muted">
                                                <i class="bi bi-people me-1"></i><?= (int) $f['total_aprendices'] ?> aprendices
                                            </small>
                                        </div>
                                        <form method="POST" action="<?= BASE_URL ?>/index.php?accion=reportes" class="d-inline">
                                            <input type="hidden" name="ficha_id" value="<?= $f['id_ficha'] ?>">
                                            <input type="hidden" name="rango" value="personalizado">
                                            <input type="hidden" name="desde" value="<?= date('Y-m-01') ?>">
                                            <input type="hidden" name="hasta" value="<?= date('Y-m-d') ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-sena py-1 px-2" title="Reporte de esta ficha" style="font-size:.78rem;">
                                                <i class="bi bi-bar-chart"></i> Reporte
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($totalFichas > 0): ?>
                            <!-- Paginación de Fichas -->
                            <div class="d-flex flex-wrap justify-content-between align-items-center dashboard-pagination" id="paginacionFichasInstructor">
                                <small class="text-suave info-paginacion">Mostrando 1 - <?= min($porPaginaFichas, $totalFichas) ?> de <?= $totalFichas ?> fichas</small>
                                <div class="d-flex align-items-center gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-prev" disabled style="font-size: .78rem;" title="Página anterior">
                                        <i class="bi bi-chevron-left me-1"></i> Anterior
                                    </button>
                                    <span class="px-2 small fw-semibold text-secondary indicador-pagina" style="font-size: .78rem;">Pág. 1 de <?= $totalPaginasFichas ?></span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-next" <?= $totalPaginasFichas <= 1 ? 'disabled' : '' ?> style="font-size: .78rem;" title="Página siguiente">
                                        Siguiente <i class="bi bi-chevron-right ms-1"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<?php include 'includes/scripts.php'; ?>
<script src="<?= BASE_URL ?>/assets/js/dashboard.js?v=<?= file_exists(BASE_PATH . '/assets/js/dashboard.js') ? filemtime(BASE_PATH . '/assets/js/dashboard.js') : '1.0' ?>"></script>
</body>
</html>

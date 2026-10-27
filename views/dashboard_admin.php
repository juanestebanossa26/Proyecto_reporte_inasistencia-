<?php
// Vista del Dashboard para Administrador
// Muestra métricas globales, ingresos recientes, excusas del sistema y fichas.

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

$nombreCorto = htmlspecialchars(explode(' ', $nombreUsuario ?? 'Administrador')[0]);
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
                    <div class="eyebrow"><i class="bi bi-shield-check me-1"></i> Control General · Administrador</div>
                    <h1 class="h3 fw-bold mb-1">Bienvenido, <?= $nombreCorto ?> 👋</h1>
                    <p class="text-suave mb-0">Resumen operativo general de asistencia, fichas y aprendices en el centro.</p>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="badge bg-white text-dark border p-2 px-3 rounded-pill shadow-sm">
                        <i class="bi bi-calendar-event text-sena me-1"></i> <?= date('d/m/Y') ?>
                    </span>
                    <a href="<?= BASE_URL ?>/index.php?accion=reportes" class="btn btn-sena">
                        <i class="bi bi-file-earmark-bar-graph me-1"></i> Reportes Globales
                    </a>
                </div>
            </div>

            <!-- Accesos rápidos -->
            <div class="d-flex flex-wrap gap-2 mb-4">
                <a href="<?= BASE_URL ?>/index.php?accion=aprendices" class="action-pill">
                    <i class="bi bi-people text-primary"></i> Gestionar Aprendices
                </a>
                <a href="<?= BASE_URL ?>/index.php?accion=instructores" class="action-pill">
                    <i class="bi bi-person-badge text-success"></i> Gestionar Instructores
                </a>
                <a href="<?= BASE_URL ?>/index.php?accion=fichas" class="action-pill">
                    <i class="bi bi-journal-bookmark text-warning"></i> Fichas
                </a>
                <a href="<?= BASE_URL ?>/index.php?accion=horarios" class="action-pill">
                    <i class="bi bi-calendar3 text-info"></i> Horarios
                </a>
               
            </div>

            <!-- KPIs Globales del Sistema -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 h-100">
                        <div class="d-flex align-items-center gap-3">
                            <div class="kpi-icon" style="background:rgba(57,169,0,.12); color:#1F7A00;">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <div>
                                <div class="text-suave" style="font-size:.76rem;">Aprendices Registrados</div>
                                <div class="h4 fw-bold mb-0"><?= number_format($kpis['total_aprendices'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 h-100">
                        <div class="d-flex align-items-center gap-3">
                            <div class="kpi-icon" style="background:rgba(47,111,237,.12); color:#2F6FED;">
                                <i class="bi bi-person-workspace"></i>
                            </div>
                            <div>
                                <div class="text-suave" style="font-size:.76rem;">Instructores Activos</div>
                                <div class="h4 fw-bold mb-0"><?= number_format($kpis['total_instructores'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 h-100">
                        <div class="d-flex align-items-center gap-3">
                            <div class="kpi-icon" style="background:rgba(232,164,0,.14); color:#8a6300;">
                                <i class="bi bi-journal-text"></i>
                            </div>
                            <div>
                                <div class="text-suave" style="font-size:.76rem;">Fichas Totales</div>
                                <div class="h4 fw-bold mb-0"><?= number_format($kpis['total_fichas'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 h-100">
                        <div class="d-flex align-items-center gap-3">
                            <div class="kpi-icon" style="background:rgba(111,66,193,.12); color:#6f42c1;">
                                <i class="bi bi-calendar-check-fill"></i>
                            </div>
                            <div>
                                <div class="text-suave" style="font-size:.76rem;">Clases / Horarios Hoy</div>
                                <div class="h4 fw-bold mb-0"><?= number_format($kpis['horarios_hoy'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contenido Principal: Dos Columnas -->
            <div class="row g-3">
                <!-- Columna Izquierda: Ingresos Recientes y Resumen de Fichas -->
                <div class="col-lg-8">
                    <!-- Ingresos Recientes -->
                    <?php 
                        $totalIngresos = count($ingresosRecientes);
                        $porPagina = 5;
                        $totalPaginas = max(1, (int) ceil($totalIngresos / $porPagina));
                    ?>
                    <div class="card p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h2 class="h6 fw-bold mb-0"><i class="bi bi-clock-history me-1 text-sena"></i> Ingresos Recientes al Centro</h2>
                                <small class="text-suave">Últimos registros de marcajes en tiempo real de hoy</small>
                            </div>
                           
                        </div>
                        <div class="table-responsive">
                            <table class="table table-app table-compact-app align-middle mb-0" id="tablaIngresosAdmin">
                                <thead>
                                    <tr>
                                        <th style="width: 28%;">Aprendiz</th>
                                        <th style="width: 24%;">Ficha / Programa</th>
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
                                                No hay registros de ingreso registrados hoy.
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
                        <div class="d-flex flex-wrap justify-content-between align-items-center dashboard-pagination" id="paginacionIngresosAdmin">
                            <small class="text-suave info-paginacion">Mostrando 1 - <?= min($porPagina, $totalIngresos) ?> de <?= $totalIngresos ?> registros</small>
                            <div class="d-flex align-items-center gap-1">
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-prev" disabled style="font-size: .78rem;" title="Página anterior" aria-label="Página anterior">
                                    <i class="bi bi-chevron-left me-1"></i> Anterior
                                </button>
                                <span class="px-2 small fw-semibold text-secondary indicador-pagina" style="font-size: .78rem;">Pág. 1 de <?= $totalPaginas ?></span>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-next" <?= $totalPaginas <= 1 ? 'disabled' : '' ?> style="font-size: .78rem;" title="Página siguiente" aria-label="Página siguiente">
                                    Siguiente <i class="bi bi-chevron-right ms-1"></i>
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Fichas y Cobertura -->
                    <?php 
                        $totalResumenFichas = count($resumenFichas);
                        $porPaginaResumenFichas = 5;
                        $totalPaginasResumenFichas = max(1, (int) ceil($totalResumenFichas / $porPaginaResumenFichas));
                    ?>
                    <div class="card p-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <div>
                                <h2 class="h6 fw-bold mb-0"><i class="bi bi-journal-bookmark-fill me-1 text-sena"></i> Fichas del Centro de Formación</h2>
                                <small class="text-suave">Fichas con aprendices e instructores registrados</small>
                            </div>
                            <a href="<?= BASE_URL ?>/index.php?accion=fichas" class="btn btn-sm btn-outline-secondary">
                                Ver todas las fichas
                            </a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-app table-compact-app align-middle mb-0" id="tablaFichasAdmin">
                                <thead>
                                    <tr>
                                        <th style="width: 18%;">Ficha</th>
                                        <th style="width: 32%;">Programa</th>
                                        <th style="width: 15%;">Aprendices</th>
                                        <th style="width: 22%;">Instructores</th>
                                        <th style="width: 13%;" class="text-end">Reporte</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($resumenFichas)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-3">No hay fichas registradas aún.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($resumenFichas as $idx => $f): ?>
                                        <tr class="fila-ficha-admin" data-index="<?= $idx ?>" style="<?= $idx >= $porPaginaResumenFichas ? 'display: none;' : '' ?>">
                                            <td class="fw-bold text-sena" style="font-size: .88rem;"><?= htmlspecialchars($f['numero_ficha']) ?></td>
                                            <td>
                                                <div class="text-truncate" style="max-width: 230px; font-size: .83rem;" title="<?= htmlspecialchars($f['nombre_programa']) ?>">
                                                    <?= htmlspecialchars($f['nombre_programa']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border py-1 px-2" style="font-size: .76rem;">
                                                    <i class="bi bi-people me-1"></i><?= (int) $f['total_aprendices'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-suave text-truncate d-block" style="max-width: 180px; font-size: .78rem;" title="<?= htmlspecialchars($f['instructores'] ?: 'Sin asignar') ?>">
                                                    <?= htmlspecialchars($f['instructores'] ?: 'Sin asignar') ?>
                                                </small>
                                            </td>
                                            <td class="text-end">
                                                <form method="POST" action="<?= BASE_URL ?>/index.php?accion=reportes" class="d-inline">
                                                    <input type="hidden" name="ficha_id" value="<?= $f['id_ficha'] ?>">
                                                    <input type="hidden" name="rango" value="personalizado">
                                                    <input type="hidden" name="desde" value="<?= date('Y-m-01') ?>">
                                                    <input type="hidden" name="hasta" value="<?= date('Y-m-d') ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-sena py-0 px-2" title="Generar reporte de esta ficha" style="font-size: .78rem;">
                                                        <i class="bi bi-file-earmark-bar-graph"></i> Reporte
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($totalResumenFichas > 0): ?>
                        <!-- Paginación de Fichas -->
                        <div class="d-flex flex-wrap justify-content-between align-items-center dashboard-pagination" id="paginacionFichasAdmin">
                            <small class="text-suave info-paginacion">Mostrando 1 - <?= min($porPaginaResumenFichas, $totalResumenFichas) ?> de <?= $totalResumenFichas ?> fichas</small>
                            <div class="d-flex align-items-center gap-1">
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-prev" disabled style="font-size: .78rem;" title="Página anterior" aria-label="Página anterior">
                                    <i class="bi bi-chevron-left me-1"></i> Anterior
                                </button>
                                <span class="px-2 small fw-semibold text-secondary indicador-pagina" style="font-size: .78rem;">Pág. 1 de <?= $totalPaginasResumenFichas ?></span>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-next" <?= $totalPaginasResumenFichas <= 1 ? 'disabled' : '' ?> style="font-size: .78rem;" title="Página siguiente" aria-label="Página siguiente">
                                    Siguiente <i class="bi bi-chevron-right ms-1"></i>
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Columna Derecha: Excusas Pendientes, Banner de Reportes y Estado -->
                <div class="col-lg-4">
                    <!-- Banner de Reportes Globales sin restricción de área -->
                    <div class="card report-banner p-3 p-xl-4 mb-3 shadow-sm">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-patch-check-fill fs-4 text-warning"></i>
                            <h3 class="h6 fw-bold mb-0 text-white">Reportes Sin Restricción</h3>
                        </div>
                        <p class="small text-white-50 mb-3">
                            Como Administrador tienes habilitado el acceso para generar y exportar (PDF y Excel) la asistencia de <strong>cualquier ficha</strong> del centro, sin restricción de área.
                        </p>
                        <a href="<?= BASE_URL ?>/index.php?accion=reportes" class="btn btn-sm btn-light fw-bold text-success w-100">
                            <i class="bi bi-funnel me-1"></i> Filtrar y Exportar Reportes
                        </a>
                    </div>

                   

                    <!-- Estado y Enlaces de Interés -->
                    <div class="card p-3">
                        <h2 class="h6 fw-bold mb-2"><i class="bi bi-hdd-network me-1 text-primary"></i> Estado del Sistema</h2>
                        <ul class="list-unstyled mb-0 small">
                            <li class="d-flex justify-content-between py-1 border-bottom">
                                <span class="text-suave">Servicio RFID:</span>
                                <span class="text-success fw-semibold"><i class="bi bi-circle-fill me-1" style="font-size:.5rem;"></i> En línea</span>
                            </li>
                            <li class="d-flex justify-content-between py-1 border-bottom">
                                <span class="text-suave">Zona horaria:</span>
                                <span class="fw-semibold"><?= date_default_timezone_get() ?></span>
                            </li>
                            <li class="d-flex justify-content-between py-1">
                                <span class="text-suave">Hora del servidor:</span>
                                <span class="fw-semibold"><?= date('h:i:s a') ?></span>
                            </li>
                        </ul>
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

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
    <style>
        .kpi-card {
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0,0,0,.06);
        }
        .action-pill {
            font-size: .84rem;
            font-weight: 600;
            padding: .45rem .85rem;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            text-decoration: none;
            background: #fff;
            border: 1px solid var(--linea);
            color: var(--texto-fuerte);
            transition: all .15s ease;
        }
        .action-pill:hover {
            background: var(--sena-verde);
            border-color: var(--sena-verde);
            color: #fff;
        }
        .report-banner {
            background: linear-gradient(135deg, #10241C 0%, #1F7A00 100%);
            color: #fff;
            border-radius: var(--radius);
        }
    </style>
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
                <a href="<?= BASE_URL ?>/index.php?accion=marcaje" class="action-pill">
                    <i class="bi bi-wifi text-sena"></i> Terminal RFID
                </a>
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
                <a href="<?= BASE_URL ?>/index.php?accion=validarExcusas" class="action-pill">
                    <i class="bi bi-file-earmark-medical text-danger"></i> Validar Excusas
                </a>
            </div>

            <!-- KPIs Globales del Sistema -->
            <div class="row g-3 mb-3">
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

            <!-- KPIs de Asistencia de Hoy -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 h-100 border-start border-4 border-primary">
                        <div class="text-suave" style="font-size:.76rem;">Marcajes Registrados Hoy</div>
                        <div class="h3 fw-bold mb-0 text-primary"><?= number_format($kpis['asistencias_hoy'] ?? 0) ?></div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 h-100 border-start border-4 border-success">
                        <div class="text-suave" style="font-size:.76rem;">A Tiempo / Asistencias</div>
                        <div class="h3 fw-bold mb-0 text-sena"><?= number_format($kpis['a_tiempo_hoy'] ?? 0) ?></div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 h-100 border-start border-4 border-warning">
                        <div class="text-suave" style="font-size:.76rem;">Retardos Hoy</div>
                        <div class="h3 fw-bold mb-0" style="color:#8a6300;"><?= number_format($kpis['retardos_hoy'] ?? 0) ?></div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 h-100 border-start border-4 border-danger">
                        <div class="text-suave" style="font-size:.76rem;">Inasistencias Registradas</div>
                        <div class="h3 fw-bold mb-0" style="color:#B4212B;"><?= number_format($kpis['inasistencias_hoy'] ?? 0) ?></div>
                    </div>
                </div>
            </div>

            <!-- Contenido Principal: Dos Columnas -->
            <div class="row g-3">
                <!-- Columna Izquierda: Ingresos Recientes y Resumen de Fichas -->
                <div class="col-lg-8">
                    <!-- Ingresos Recientes -->
                    <div class="card p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h2 class="h6 fw-bold mb-0"><i class="bi bi-clock-history me-1 text-sena"></i> Ingresos Recientes al Centro</h2>
                                <small class="text-suave">Últimos registros de marcajes en tiempo real</small>
                            </div>
                           
                        </div>
                        <div class="table-responsive">
                            <table class="table table-app align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Aprendiz</th>
                                        <th>Ficha / Programa</th>
                                        <th>Fecha</th>
                                        <th>Entrada</th>
                                        <th>Salida</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($ingresosRecientes)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                <i class="bi bi-inbox fs-3 d-block mb-1 text-secondary"></i>
                                                No hay registros de asistencia recientes.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($ingresosRecientes as $ingreso): 
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
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="avatar-circ"><?= $iniciales ?></span>
                                                    <div>
                                                        <div class="fw-semibold" style="font-size:.88rem;"><?= $nombre ?></div>
                                                        <small class="text-suave">Doc: <?= $doc ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-truncate" style="max-width: 170px; font-size:.84rem;" title="<?= $prog ?>">
                                                    <?= $fichaNum ?>
                                                </div>
                                                <small class="text-suave d-block text-truncate" style="max-width: 170px;" title="<?= $prog ?>"><?= $prog ?></small>
                                            </td>
                                            <td><small class="fw-semibold"><?= $fechaFmt ?></small></td>
                                            <td><small class="text-success fw-semibold"><?= $horaEntrada ?></small></td>
                                            <td><small class="text-secondary"><?= $horaSalida ?></small></td>
                                            <td>
                                                <span class="badge-estado <?= getBadgeClassDashboard($estado) ?>">
                                                    <?= ucfirst(htmlspecialchars($estado)) ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Fichas y Cobertura -->
                    <div class="card p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h2 class="h6 fw-bold mb-0"><i class="bi bi-journal-bookmark-fill me-1 text-sena"></i> Fichas del Centro de Formación</h2>
                                <small class="text-suave">Fichas con aprendices e instructores registrados</small>
                            </div>
                            <a href="<?= BASE_URL ?>/index.php?accion=fichas" class="btn btn-sm btn-outline-secondary">
                                Ver todas las fichas
                            </a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-app align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Ficha</th>
                                        <th>Programa</th>
                                        <th>Aprendices</th>
                                        <th>Instructores</th>
                                        <th class="text-end">Reporte</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($resumenFichas)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-3">No hay fichas registradas aún.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($resumenFichas as $f): ?>
                                        <tr>
                                            <td class="fw-bold text-sena"><?= htmlspecialchars($f['numero_ficha']) ?></td>
                                            <td>
                                                <div class="text-truncate" style="max-width:230px;" title="<?= htmlspecialchars($f['nombre_programa']) ?>">
                                                    <?= htmlspecialchars($f['nombre_programa']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border">
                                                    <i class="bi bi-people me-1"></i><?= (int) $f['total_aprendices'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-suave text-truncate d-block" style="max-width:180px;" title="<?= htmlspecialchars($f['instructores'] ?: 'Sin asignar') ?>">
                                                    <?= htmlspecialchars($f['instructores'] ?: 'Sin asignar') ?>
                                                </small>
                                            </td>
                                            <td class="text-end">
                                                <form method="POST" action="<?= BASE_URL ?>/index.php?accion=reportes" class="d-inline">
                                                    <input type="hidden" name="ficha_id" value="<?= $f['id_ficha'] ?>">
                                                    <input type="hidden" name="rango" value="personalizado">
                                                    <input type="hidden" name="desde" value="<?= date('Y-m-01') ?>">
                                                    <input type="hidden" name="hasta" value="<?= date('Y-m-d') ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-sena py-0 px-2" title="Generar reporte de esta ficha">
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
</body>
</html>

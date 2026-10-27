<?php
// La sesión ya fue verificada por PaginaControlador::mostrar() antes de
// llegar aquí (ver index.php). No hace falta repetir el chequeo.

$rolActivo     = $_SESSION['rol']    ?? 'Aprendiz';
$nombreUsuario = $_SESSION['nombre'] ?? '';
$paginaActiva  = 'historial';
$tituloPagina  = 'Mi historial de asistencia';

// Variables por defecto si el controlador no las ha inyectado (evita warnings y diagnósticos)
$historial          = $historial          ?? [];
$total_retardo      = $total_retardo      ?? 0;
$total_inasistencia = $total_inasistencia ?? 0;
$total_pendientes   = $total_pendientes   ?? 0;

// Total de asistencias (asistencia, presente, a tiempo)
$total_asistencia   = $total_asistencia   ?? count(array_filter($historial, function($f) {
    $e = strtolower(trim($f['estado'] ?? ''));
    return in_array($e, ['asistencia', 'presente', 'a tiempo'], true);
}));

$diasSemana = [
    0 => 'Domingo',
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miércoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sábado'
];

if (!function_exists('renderBadgeHistorial')) {
    function renderBadgeHistorial(string $estadoRaw): string {
        $estado = strtolower(trim($estadoRaw));
        switch ($estado) {
            case 'asistencia':
            case 'presente':
            case 'a tiempo':
                return '<span class="badge-estado badge-asistencia"><i class="bi bi-check-circle-fill"></i> Asistencia</span>';
            case 'retardo':
                return '<span class="badge-estado badge-retardo"><i class="bi bi-clock-history"></i> Retardo</span>';
            case 'inasistencia':
                return '<span class="badge-estado badge-inasistencia"><i class="bi bi-x-circle-fill"></i> Inasistencia</span>';
            case 'salida anticipada':
            case 'salida-anticipada':
                return '<span class="badge-estado badge-salida-anticipada"><i class="bi bi-box-arrow-right"></i> Salida anticipada</span>';
            default:
                return '<span class="badge-estado bg-light text-secondary border">' . htmlspecialchars(ucfirst($estadoRaw)) . '</span>';
        }
    }
}
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
            <div class="page-header d-flex justify-content-between align-items-end flex-wrap gap-2">
                <div>
                    <div class="eyebrow">Mi asistencia</div>
                    <h1 class="h4 fw-bold mb-0">Mi historial</h1>
                    <p class="text-suave mb-0">Consulta tus asistencias, retardos, inasistencias y el estado de tus excusas.</p>
                </div>
                <a href="<?= BASE_URL ?>/index.php?accion=descargarMiReporte" target="_blank" rel="noopener noreferrer" class="btn btn-outline-sena" id="btnDescargarMiReporte" aria-label="Descargar reporte individual de asistencia en PDF">
                    <i class="bi bi-file-earmark-pdf-fill me-1"></i> Descargar mi reporte
                </a>
            </div>

            <!-- Resumen de Indicadores (KPIs) -->
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 text-center border-0 shadow-sm" style="border-top: 3px solid #1F7A00 !important;">
                        <div class="text-suave" style="font-size:.76rem;"><i class="bi bi-check-circle text-success me-1"></i>Asistencia de hoy</div>
                        <div class="h4 fw-bold mb-0 text-success"><?= $total_asistencia ?></div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 text-center border-0 shadow-sm" style="border-top: 3px solid #e8a400 !important;">
                        <div class="text-suave" style="font-size:.76rem;"><i class="bi bi-clock-history text-warning me-1"></i>Retardos</div>
                        <div class="h4 fw-bold mb-0" style="color:#8a6300;"><?= $total_retardo ?></div>
                    </div>
                </div>
                
                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 text-center border-0 shadow-sm" style="border-top: 3px solid #d64550 !important;">
                        <div class="text-suave" style="font-size:.76rem;"><i class="bi bi-x-circle text-danger me-1"></i>Inasistencias</div>
                        <div class="h4 fw-bold mb-0" style="color:#B4212B;"><?= $total_inasistencia ?></div>
                    </div>
                </div>
                
                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 text-center border-0 shadow-sm" style="border-top: 3px solid #2F6FED !important;">
                        <div class="text-suave" style="font-size:.76rem;"><i class="bi bi-file-earmark-medical text-primary me-1"></i>Excusas Pendientes</div>
                        <div class="h4 fw-bold mb-0" style="color:#2F6FED;"><?= $total_pendientes ?></div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Historial -->
            <div class="card p-3 border-0 shadow-sm">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom flex-wrap gap-2">
                    <div>
                        <h2 class="h6 fw-bold mb-0 text-dark">Registros de asistencia</h2>
                        <small class="text-muted" style="font-size:.78rem;">Historial de inasistencias, retardos y marcaje del día actual</small>
                    </div>
                    <span class="badge bg-light text-muted border px-2 py-1" style="font-size:.75rem;">
                        <i class="bi bi-list-check me-1"></i> <?= count($historial) ?> registro<?= count($historial) !== 1 ? 's' : '' ?>
                    </span>
                </div>

                <table id="tablaHistorial" class="table table-app table-hover align-middle mb-0" style="width:100%">
                    <thead>
                        <tr>
                            <th style="min-width: 140px;">Fecha</th>
                            <th class="text-center" style="min-width: 110px;">Entrada</th>
                            <th class="text-center" style="min-width: 110px;">Salida</th>
                            <th class="text-center" style="min-width: 110px;">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($historial as $fila): 
                        $fechaRaw = $fila['fecha'] ?? '';
                        $ts = !empty($fechaRaw) ? strtotime($fechaRaw) : false;
                        $fechaFmt = $ts ? date('d/m/Y', $ts) : '-';
                        $diaFmt = $ts ? ($diasSemana[(int)date('w', $ts)] ?? '') : '';

                        // Formato Entrada
                        $hEnt = $fila['hora_entrada_real'] ?? null;
                        if (!empty($hEnt)) {
                            $hEntFmt = date('h:i:s a', strtotime($hEnt));
                            $hEntHtml = '<span class="badge bg-light text-dark border px-2 py-1 fw-semibold text-nowrap" style="font-size: .8rem;" title="' . htmlspecialchars($hEnt) . '">
                                            <i class="bi bi-box-arrow-in-right text-success me-1"></i>' . $hEntFmt . '
                                         </span>';
                            $hEntOrder = $hEnt;
                        } else {
                            $hEntHtml = '<span class="text-muted fw-semibold opacity-50" style="font-size: 1rem;">&mdash;</span>';
                            $hEntOrder = '';
                        }

                        // Formato Salida
                        $hSal = $fila['hora_salida_real'] ?? null;
                        if (!empty($hSal)) {
                            $hSalFmt = date('h:i:s a', strtotime($hSal));
                            $hSalHtml = '<span class="badge bg-light text-dark border px-2 py-1 fw-semibold text-nowrap" style="font-size: .8rem;" title="' . htmlspecialchars($hSal) . '">
                                            <i class="bi bi-box-arrow-right text-secondary me-1"></i>' . $hSalFmt . '
                                         </span>';
                            $hSalOrder = $hSal;
                        } else {
                            $hSalHtml = '<span class="text-muted fw-semibold opacity-50" style="font-size: 1rem;">&mdash;</span>';
                            $hSalOrder = '';
                        }

                        $estadoRaw = $fila['estado'] ?? '';
                    ?>
                        <tr>
                            <td data-order="<?= htmlspecialchars($fechaRaw) ?>" class="text-nowrap">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="d-inline-flex align-items-center justify-content-center bg-light border rounded-3 text-sena" style="width:34px; height:34px; font-size:.95rem; flex-shrink:0;">
                                        <i class="bi bi-calendar-check"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: .86rem;"><?= $fechaFmt ?></div>
                                        <small class="text-muted text-capitalize" style="font-size: .72rem;"><?= $diaFmt ?></small>
                                    </div>
                                </div>
                            </td>
                        
                            <td class="text-center text-nowrap" data-order="<?= htmlspecialchars($hEntOrder) ?>"><?= $hEntHtml ?></td>
                            <td class="text-center text-nowrap" data-order="<?= htmlspecialchars($hSalOrder) ?>"><?= $hSalHtml ?></td>
                            <td class="text-center text-nowrap" data-order="<?= htmlspecialchars(strtolower($estadoRaw)) ?>">
                                <?= renderBadgeHistorial($estadoRaw) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/scripts.php'; ?>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script> 
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script> 
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/datatables-es.js"></script> 

<!-- Script para Mi Historial -->
<script src="<?= BASE_URL ?>/assets/js/historial.js?v=<?= file_exists(BASE_PATH . '/assets/js/historial.js') ? filemtime(BASE_PATH . '/assets/js/historial.js') : '1.0' ?>"></script>

</body>
</html>

<?php
/**
 * validar_excusas.php — Bandeja de excusas médicas (HU 13), vista instructor/admin
 *
 * TODO (backend):
 *   - Reemplazar $excusas por SELECT ... FROM excusas_medicas JOIN aprendices ...
 *     filtrando por ficha del instructor si $rolActivo === 'instructor'
 *   - Los botones Aprobar/Rechazar deben llamar a actualizar_excusa.php?id=X&estado=aprobada|rechazada
 *     (HU 18: una excusa aprobada hace que esa fecha no cuente como inasistencia en los reportes)
 */
$rolActivo     = $_GET['rol'] ?? 'instructor';
$nombreUsuario = 'Marcela Ortiz';
$paginaActiva  = 'validar_excusas';
$tituloPagina  = 'Validar excusas médicas';

$excusas = [
    ['aprendiz'=>'Camila Suárez','ficha'=>'2678903 · Multimedia','fecha'=>'11/08/2026','motivo'=>'Control médico especializado','estado'=>'pendiente'],
    ['aprendiz'=>'Andrés Gil','ficha'=>'2589451 · ADSO','fecha'=>'10/08/2026','motivo'=>'Cita odontológica','estado'=>'pendiente'],
    ['aprendiz'=>'Laura Martínez','ficha'=>'2589451 · ADSO','fecha'=>'05/08/2026','motivo'=>'Cita médica EPS','estado'=>'aprobada'],
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
                <div class="eyebrow">Control</div>
                <h1 class="h4 fw-bold mb-0">Excusas médicas</h1>
                <p class="text-suave mb-0">Aprueba o rechaza los soportes enviados por los aprendices.</p>
            </div>

            <ul class="nav nav-pills mb-3">
                <li class="nav-item"><a class="nav-link active" href="#" style="background:var(--sena-verde);">Pendientes <span class="badge bg-white text-sena ms-1">2</span></a></li>
                <li class="nav-item"><a class="nav-link text-suave" href="#">Aprobadas</a></li>
                <li class="nav-item"><a class="nav-link text-suave" href="#">Rechazadas</a></li>
            </ul>

            <div class="card p-3">
                <div class="table-responsive">
                    <table class="table table-app align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Aprendiz</th>
                                <th>Ficha</th>
                                <th>Fecha</th>
                                <th>Motivo</th>
                                <th>Soporte</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($excusas as $e): ?>
                            <tr>
                                <td class="d-flex align-items-center gap-2">
                                    <span class="avatar-circ"><?= strtoupper(substr($e['aprendiz'],0,1)) ?></span>
                                    <?= htmlspecialchars($e['aprendiz']) ?>
                                </td>
                                <td><?= htmlspecialchars($e['ficha']) ?></td>
                                <td><?= htmlspecialchars($e['fecha']) ?></td>
                                <td><?= htmlspecialchars($e['motivo']) ?></td>
                                <td><a href="#" class="text-sena"><i class="bi bi-file-earmark-pdf me-1"></i>Ver PDF</a></td>
                                <td><span class="badge-estado badge-<?= $e['estado'] ?>"><?= ucfirst($e['estado']) ?></span></td>
                                <td class="text-end">
                                    <?php if ($e['estado'] === 'pendiente'): ?>
                                        <button class="btn btn-sm btn-sena">Aprobar</button>
                                        <button class="btn btn-sm btn-outline-danger">Rechazar</button>
                                    <?php else: ?>
                                        <span class="text-suave" style="font-size:.8rem;">Sin acciones</span>
                                    <?php endif; ?>
                                </td>
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

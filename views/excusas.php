<?php
/**
 * excusas.php — Anexar excusa médica (HU 7), vista del aprendiz
 *
 * TODO (backend):
 *   - action="guardar_excusa.php" method="POST" enctype="multipart/form-data"
 *     INSERT en `excusas_medicas` (aprendiz, ficha, fecha, archivo, estado=pendiente)
 *   - Reemplazar $misExcusas por SELECT * FROM excusas_medicas WHERE aprendiz_id = ?
 */
$rolActivo     = 'aprendiz';
$nombreUsuario = 'Laura Martínez';
$paginaActiva  = 'excusas';
$tituloPagina  = 'Excusas médicas';

$misExcusas = [
    ['fecha'=>'05/08/2026','motivo'=>'Cita médica EPS','estado'=>'aprobada'],
    ['fecha'=>'22/07/2026','motivo'=>'Incapacidad por gripe','estado'=>'rechazada'],
    ['fecha'=>'11/08/2026','motivo'=>'Control médico especializado','estado'=>'pendiente'],
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
                <div class="eyebrow">Mi asistencia</div>
                <h1 class="h4 fw-bold mb-0">Excusas médicas</h1>
                <p class="text-suave mb-0">Anexa el soporte de tu inasistencia para que tu instructor la valide.</p>
            </div>

            <div class="row g-3">
                <div class="col-lg-5">
                    <div class="card p-3">
                        <h2 class="h6 fw-bold mb-3">Anexar nueva excusa</h2>
                        <!-- TODO (backend): action="guardar_excusa.php" method="POST" enctype="multipart/form-data" -->
                        <form>
                            <div class="mb-3">
                                <label class="form-label">Fecha de la inasistencia</label>
                                <input type="date" class="form-control" name="fecha" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Motivo</label>
                                <textarea class="form-control" name="motivo" rows="3" placeholder="Ej: Cita médica EPS" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Soporte (PDF o imagen)</label>
                                <div class="dropzone-excusa">
                                    <i class="bi bi-cloud-arrow-up text-sena" style="font-size:1.6rem;"></i>
                                    <p class="mb-1 mt-2 fw-semibold" style="font-size:.9rem;">Arrastra tu archivo aquí</p>
                                    <p class="text-suave mb-2" style="font-size:.8rem;">o haz clic para seleccionarlo</p>
                                    <input type="file" name="archivo" class="form-control">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-sena w-100">Enviar excusa</button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card p-3">
                        <h2 class="h6 fw-bold mb-3">Mis excusas enviadas</h2>
                        <div class="table-responsive">
                            <table class="table table-app align-middle mb-0">
                                <thead>
                                    <tr><th>Fecha</th><th>Motivo</th><th>Estado</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($misExcusas as $e): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($e['fecha']) ?></td>
                                        <td><?= htmlspecialchars($e['motivo']) ?></td>
                                        <td><span class="badge-estado badge-<?= $e['estado'] ?>"><?= ucfirst($e['estado']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/scripts.php'; ?>
</body>
</html>

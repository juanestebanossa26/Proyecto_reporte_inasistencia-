<?php
// Esta vista ya no consulta la base de datos por su cuenta.
// ExcusaControlador::listarPendientes() ya dejó listas las variables
// $excusasInstructor y $total_pendientes antes de hacer require de este archivo.

$rolActivo     = $_SESSION['rol'] ?? 'instructor';
$nombreUsuario = $_SESSION['nombre'] ?? '';
$paginaActiva  = 'validar_excusas';
$tituloPagina  = 'Validar excusas médicas';
$excusasInstructor = $excusasInstructor ?? [];
$total_pendientes  = $total_pendientes ?? 0;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'includes/head.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <!-- SweetAlert2 (Debe estar cargado para que el JS externo funcione) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="app-shell">
    <?php include 'includes/sidebar.php'; ?>
    <div class="app-main">
        <?php include 'includes/topbar.php'; ?>
        <main class="app-content">
            <div class="page-header">
                <div class="eyebrow">Control</div>
                <h1 class="h4 fw-bold mb-0">Excusas médicas</h1>
                <p class="text-suave mb-0">Aprueba, devuelve o rechaza los soportes enviados por los aprendices.</p>
            </div>

            <ul class="nav nav-pills mb-3">
                <li class="nav-item">
                    <a class="nav-link active" href="#" style="background:var(--sena-verde);">
                        Pendientes <span class="badge bg-white text-sena ms-1"><?php echo $total_pendientes ?></span>
                    </a>
                </li>
            </ul>

            <div class="card p-3">
                <div class="table-responsive">
                    <table id="tablaExcusas" class="table table-app align-middle mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th>Aprendiz</th>
                                <th>Ficha</th>
                                <th>Fecha Falta</th>
                                <th>Motivo</th>
                                <th>Soporte</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($excusasInstructor as $fila) { 
                            $nombreCompleto = htmlspecialchars($fila['nombre_completo'], ENT_QUOTES);
                            $estado = strtolower(trim($fila['estado_excusa']));
                        ?>
                            <tr>
                                <td class="d-flex align-items-center gap-2">
                                    <span class="avatar-circ"><?= strtoupper(substr($fila['nombre_completo'], 0, 1)) ?></span>
                                    <?= $nombreCompleto ?>
                                </td>
                                <td><?= htmlspecialchars($fila['nombre_programa'] . ' - ' . $fila['numero_ficha']) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($fila['fecha']) ?></div>
                                    <?php if (!empty($fila['hora_entrada_programada']) && !empty($fila['hora_salida_programada'])): ?>
                                        <span class="badge bg-light text-secondary border" style="font-size: .72rem;">
                                            <i class="bi bi-clock me-1"></i><?= substr($fila['hora_entrada_programada'], 0, 5) ?> - <?= substr($fila['hora_salida_programada'], 0, 5) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($fila['motivo']) ?></td>
                                <td>
                                    <?php if (!empty($fila['archivo_adjunto'])): ?>
                                        <a href="<?= BASE_URL ?>/<?= htmlspecialchars($fila['archivo_adjunto']) ?>" target="_blank" class="text-sena text-decoration-none">
                                            <i class="bi bi-file-earmark-pdf me-1"></i>Ver PDF
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">Sin soporte</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $badgeClass = match($estado) {
                                        'aprobada'  => 'bg-success-subtle text-success border border-success-subtle',
                                        'rechazada' => 'bg-danger-subtle text-danger border border-danger-subtle',
                                        'devuelta'  => 'bg-warning-subtle text-warning border border-warning-subtle',
                                        default     => 'bg-secondary-subtle text-secondary border' // Para 'pendiente'
                                    };
                                    ?>
                                    <span class="badge rounded-pill <?= $badgeClass ?>"><?= ucfirst($estado) ?></span>
                                </td>
                                <td class="text-end">
                                    <?php if ($estado === 'pendiente'): ?>
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <!-- Botón Aprobar -->
                                            <button type="button" class="btn btn-sm btn-outline-success border-0 btn-aprobar" 
                                                title="Aprobar excusa"
                                                data-id="<?= $fila['id_revision'] ?>"
                                                data-nombre="<?= $nombreCompleto ?>">
                                                <i class="bi bi-check-circle"></i>
                                            </button>

                                            <!-- Botón Devolver -->
                                            <button type="button" class="btn btn-sm btn-outline-warning border-0 btn-devolver" 
                                                title="Devolver para corrección"
                                                data-id="<?= $fila['id_revision'] ?>"
                                                data-nombre="<?= $nombreCompleto ?>">
                                                <i class="bi bi-arrow-return-left"></i>
                                            </button>

                                            <!-- Botón Rechazar -->
                                            <button type="button" class="btn btn-sm btn-outline-danger border-0 btn-rechazar" 
                                                title="Rechazar excusa"
                                                data-id="<?= $fila['id_revision'] ?>"
                                                data-nombre="<?= $nombreCompleto ?>">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-suave" style="font-size:.8rem;">Sin acciones</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Formulario oculto global para procesar la excusa -->
<form id="formProcesarExcusa" action="<?= BASE_URL ?>/index.php?accion=procesarExcusa" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Seguridad::tokenCsrf()) ?>">
    <!-- Usamos id_asistencia como identificador basado en tu modelo -->
    <input type="hidden" name="id_excusa" id="accion_id_excusa" value="">
    <input type="hidden" name="accion" id="accion_tipo" value="">
    <input type="hidden" name="observacion" id="accion_observacion" value="">
</form>

<?php include 'includes/scripts.php'; ?>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<!-- Scripts externos -->
<script>
    const BASE_URL = '<?= BASE_URL ?>';
</script>
<script src="<?= BASE_URL ?>/assets/js/excusas.js"></script>

</body>
</html>
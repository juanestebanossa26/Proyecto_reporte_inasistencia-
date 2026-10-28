<?php

/**
 * aprendices.php — Gestión de aprendices
 *
 * TODO (backend):
 *   - Reemplazar $aprendices (arreglo de ejemplo) por SELECT * FROM aprendices
 *   - El formulario del modal #modalAprendiz debe apuntar a guardar_aprendiz.php (POST)
 *   - El botón "Desactivar" debe llamar a desactivar_aprendiz.php?  no borra
 *     solo cambia estado a inactivo
 */

$rolActivo = $_SESSION['rol'] ?? 'Administrador';
$nombreUsuario = $_SESSION['nombre'] ?? '';
$paginaActiva  = 'aprendices';
$tituloPagina  = 'Aprendices';

// Evita la advertencia de Intelephense y asegura que siempre sean arreglos iterables
$aprendices    = $aprendices ?? [];
$fichas        = $fichas ?? [];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'includes/head.php'; ?>
    <!-- CSS de DataTables -->
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
                        <div class="eyebrow">Gestión</div>
                        <h1 class="h4 fw-bold mb-0">Aprendices</h1>
                        <p class="text-suave mb-0">Crea, edita y administra el estado de los aprendices.</p>
                    </div>
                    <button class="btn btn-sena" data-bs-toggle="modal" data-bs-target="#modalAprendiz">
                        <i class="bi bi-plus-lg me-1"></i> Nuevo aprendiz
                    </button>
                </div>

                <div class="card p-3">
                    <table id="tablaAprendices" class="table table-app align-middle mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th style="min-width: 180px;">Aprendiz</th>
                                <th style="min-width: 120px;">Usuario</th>
                                <th style="min-width: 220px;">Ficha</th>
                                <th style="min-width: 160px;">Código RFID</th>
                                <th style="min-width: 100px;">Estado</th>
                                <th class="text-end" style="min-width: 100px;" data-orderable="false">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($aprendices as $a):
                                $nombreCompleto = trim($a['nombre_completo'] ?? $a['nombre'] ?? '');
                                $usuario        = $a['usuario'] ?? $a['num_documento'] ?? $a['doc'] ?? '';
                                $fichaLabel     = $a['ficha_label'] ?? $a['ficha'] ?? '';
                                $rfid           = $a['tag_rfid'] ?? $a['rfid'] ?? '';
                                $estado         = strtolower($a['estado'] ?? 'activo');
                                $idUsuario = (int) ($a['id_usuario'] ?? 0);
                            ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="avatar-circ flex-shrink-0"><?= strtoupper(substr($nombreCompleto, 0, 1)) ?></span>
                                            <span class="fw-semibold text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($nombreCompleto) ?>"><?= htmlspecialchars($nombreCompleto) ?></span>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($usuario) ?></td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 240px;" title="<?= htmlspecialchars($fichaLabel) ?>">
                                            <?= htmlspecialchars($fichaLabel) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $rfidVal = trim((string)($rfid ?? ''));
                                        $tieneRfid = ($rfidVal !== '' && $rfidVal !== '0' && strtolower($rfidVal) !== 'sin tag' && strtolower($rfidVal) !== 'null');
                                        ?>
                                        <?php if ($tieneRfid): ?>
                                            <div class="rfid-tag-container" title="TAG RFID: <?= htmlspecialchars($rfidVal) ?>">
                                                <span class="rfid-tag-badge">
                                                    <i class="bi bi-broadcast rfid-icon"></i>
                                                    <span class="rfid-code"><?= htmlspecialchars($rfidVal) ?></span>
                                                </span>
                                                <button type="button" class="btn-copy-tag" title="Copiar código RFID" onclick="copiarTag(event, '<?= htmlspecialchars($rfidVal, ENT_QUOTES) ?>')">
                                                    <i class="bi bi-clipboard"></i>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span class="rfid-tag-empty" title="Aprendiz sin tarjeta o TAG asignado">
                                                <i class="bi bi-slash-circle me-1"></i>Sin TAG asignado
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-nowrap">
                                        <?php if ($estado === 'activo'): ?>
                                            <span class="badge-estado badge-a-tiempo">Activo</span>
                                        <?php else: ?>
                                            <span class="badge-estado badge-inasistencia">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <button class="btn btn-sm btn-outline-secondary border-0 btn-editar"
                                            title="Editar"
                                            data-id="<?= $idUsuario ?>"
                                            data-nombre="<?= htmlspecialchars($nombreCompleto, ENT_QUOTES) ?>"
                                            data-usuario="<?= htmlspecialchars($usuario, ENT_QUOTES) ?>"
                                            data-documento="<?= htmlspecialchars($usuario, ENT_QUOTES) ?>"
                                            data-correo="<?= htmlspecialchars($a['correo_electronico'] ?? '', ENT_QUOTES) ?>"
                                            data-rfid="<?= htmlspecialchars($rfidVal, ENT_QUOTES) ?>"
                                            data-ficha="<?= (int) ($a['id_ficha'] ?? 0) ?>">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>

                                        <?php 
                                          // Si el aprendiz esta activo se muestra desactivar
                                           if ($estado === 'activo'): ?>
                                            <button class="btn btn-sm btn-outline-danger border-0 btn-estado" 
                                                title="Desactivar aprendiz"
                                                data-id="<?= $idUsuario ?>"
                                                data-nombre="<?= htmlspecialchars($nombreCompleto, ENT_QUOTES) ?>"
                                                data-accion-tipo="desactivar">
                                                <i class="bi bi-slash-circle"></i>
                                            </button>
                                        <?php else: ?>
                                            
                                            <button class="btn btn-sm btn-outline-success border-0 btn-estado" 
                                                title="Activar aprendiz"
                                                data-id="<?= $idUsuario ?>"
                                                data-nombre="<?= htmlspecialchars($nombreCompleto, ENT_QUOTES) ?>"
                                                data-accion-tipo="activar">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>


    <div class="modal fade" id="modalAprendiz" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">

                <form action="<?= BASE_URL ?>/index.php?accion=guardarAprendiz" method="POST" id="formAprendiz">
                    <input type="hidden" name="csrf_token" value="<?= Seguridad::tokenCsrf() ?>">
                    <input type="hidden" name="id_usuario" id="modal_id_usuario" value="0">

                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold" id="modalAprendizTitulo">Nuevo aprendiz</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nombre completo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombre" id="modal_nombre" placeholder="Ej: Laura Martínez" required minlength="3" maxlength="120">
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Usuario / Documento <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="usuario" id="modal_usuario" placeholder="Ej: 10245678" required pattern="[a-zA-Z0-9\-_]{4,25}" maxlength="25">
                                <div class="form-text">Documento único de identidad.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Código TAG RFID</label>
                                <input type="text" class="form-control" name="codigo_rfid" id="modal_codigo_rfid" placeholder="Ej: RF-1001 o 0009433667" maxlength="50" pattern="[a-zA-Z0-9\-_]{3,50}">
                                <div class="form-text">Opcional. Código único de la tarjeta.</div>
                            </div>
                        </div>
                        <div class="row g-3 mt-0">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Ficha <span class="text-danger">*</span></label>
                                <select class="form-select" name="ficha_id" id="modal_ficha_id" required>
                                    <option value="">Seleccione una ficha...</option>
                                    <?php foreach ($fichas as $f): ?>
                                        <option value="<?= $f['id_ficha'] ?>">
                                            <?= htmlspecialchars($f['numero_ficha'] . ' · ' . $f['nombre_programa']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Correo electrónico</label>
                                <input type="email" class="form-control" name="correo" id="modal_correo" placeholder="correo@ejemplo.com">
                            </div>
                        </div>
                        <div class="mb-0 mt-3">
                            <label class="form-label fw-semibold" id="modal_password_label">Contraseña de acceso <span class="text-danger" id="modal_password_req">*</span></label>
                            <input type="password" class="form-control" name="password" id="modal_password" placeholder="Se usará para iniciar sesión">
                            <small class="text-muted d-none" id="modal_password_help">Deja en blanco si deseas mantener la contraseña actual.</small>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-sena" id="modalAprendizBoton">Guardar aprendiz</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Formulario oculto para cambiar el estado -->
    <form id="formCambiarEstado" action="<?= BASE_URL ?>/index.php?accion=cambiarEstadoAprendiz" method="POST" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Seguridad::tokenCsrf()) ?>">
        <input type="hidden" name="id_usuario" id="estado_id_usuario" value="0">
        <input type="hidden" name="estado" id="estado_nuevo" value="">
    </form>

    <?php include 'includes/scripts.php'; ?>

    <!-- Scripts de DataTables -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/datatables-es.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Scripts del modulo de aprendices -->
    <script src="<?= BASE_URL ?>/assets/js/aprendices.js"></script>


</body>

</html>

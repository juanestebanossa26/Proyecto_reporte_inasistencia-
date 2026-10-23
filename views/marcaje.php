<?php
/**
 * marcaje.php — Lector RFID Interactivo (HU 8, 16)
 *
 * MarcajeControlador::mostrar() deja lista la variable $ultimosMarcajes.
 * El envío del código (por lector físico o virtual) va por AJAX a
 * accion=marcarAsistencia, sin recargar la página.
 */
$rolActivo     = $_SESSION['rol'] ?? 'Administrador';
$nombreUsuario = $_SESSION['nombre'] ?? '';
$paginaActiva  = 'marcaje';
$tituloPagina  = 'Terminal de Marcaje RFID';
$ultimosMarcajes = $ultimosMarcajes ?? [];

// Calcular estadísticas rápidas de los marcajes de hoy para el panel
$totalHoy = count($ultimosMarcajes);
$totalEntradas = 0;
$totalSalidas = 0;
$totalRetardos = 0;
foreach ($ultimosMarcajes as $m) {
    if (!empty($m['hora_salida_real']) && (empty($m['hora_entrada_real']) || $m['hora_salida_real'] >= $m['hora_entrada_real'])) {
        $totalSalidas++;
    } else {
        $totalEntradas++;
    }
    if (strtolower($m['estado'] ?? '') === 'retardo') {
        $totalRetardos++;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include 'includes/head.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/marcaje.css">
</head>
<body>
<div class="app-shell">
    <?php include 'includes/sidebar.php'; ?>
    <div class="app-main">
        <?php include 'includes/topbar.php'; ?>
        <main class="app-content">
            <!-- Encabezado de página -->
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <div class="eyebrow">Biometría & RFID</div>
                    <h1 class="h4 fw-bold mb-0">Terminal de Marcaje RFID</h1>
                    <?php if ($rolActivo === 'Instructor'): ?>
                        <p class="text-suave mb-0">Sesión activa: <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-person-check-fill me-1"></i><?= htmlspecialchars($nombreUsuario) ?></span> &middot; Solo registra asistencia para sus propios bloques de clase.</p>
                    <?php else: ?>
                        <p class="text-suave mb-0">Control de asistencia en tiempo real por proximidad o ingreso manual.</p>
                    <?php endif; ?>
                </div>
                <!-- Control de Sonido -->
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" id="btnAudioToggle">
                        <i class="bi bi-volume-up-fill me-1" id="audioIcon"></i> <span id="audioText">Sonido: Activado</span>
                    </button>
                </div>
            </div>

            <!-- Mini KPIs de Marcaje de Hoy -->
            <div class="row g-2 mb-3">
                <div class="col-6 col-md-3">
                    <div class="mini-stat-card">
                        <div class="mini-stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-people"></i>
                        </div>
                        <div>
                            <div class="text-suave" style="font-size: .74rem;">Total Registros Hoy</div>
                            <div class="fw-bold fs-5 text-dark" id="statTotal"><?= $totalHoy ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="mini-stat-card">
                        <div class="mini-stat-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-box-arrow-in-down"></i>
                        </div>
                        <div>
                            <div class="text-suave" style="font-size: .74rem;">Entradas</div>
                            <div class="fw-bold fs-5 text-success" id="statEntradas"><?= $totalEntradas ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="mini-stat-card">
                        <div class="mini-stat-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-box-arrow-up"></i>
                        </div>
                        <div>
                            <div class="text-suave" style="font-size: .74rem;">Salidas</div>
                            <div class="fw-bold fs-5 text-info" id="statSalidas"><?= $totalSalidas ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="mini-stat-card">
                        <div class="mini-stat-icon bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <div>
                            <div class="text-suave" style="font-size: .74rem;">Retardos</div>
                            <div class="fw-bold fs-5 text-warning" id="statRetardos"><?= $totalRetardos ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <!-- Panel Izquierdo: Terminal RFID Inteligente -->
                <div class="col-lg-6">
                    <div class="rfid-terminal-card text-center">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="badge bg-white bg-opacity-10 text-white border border-white border-opacity-10 px-2 py-1 rounded-pill" style="font-size: .75rem;">
                                <i class="bi bi-broadcast me-1 text-success"></i> Antena RFID Activa
                            </span>
                            <!-- Reloj digital dinámico -->
                            <div class="digital-clock-badge" id="relojDigital">
                                <i class="bi bi-clock"></i> <span id="relojHora">--:--:--</span>
                            </div>
                        </div>

                        <!-- Estación central de escaneo con ondas -->
                        <div class="scanner-station">
                            <div class="scanner-wave"></div>
                            <div class="scanner-wave"></div>
                            <div class="scanner-wave"></div>
                            <div class="rfid-scan-circle" id="circuloLector" title="Acerca tu carné RFID o haz clic para simular">
                                <i class="bi bi-wifi" id="iconoScanner"></i>
                            </div>
                        </div>

                        <h2 class="h5 fw-bold text-white mb-1" id="textoEstadoLector">Esperando lectura...</h2>
                        <p style="color:rgba(255,255,255,.75); font-size: .9rem;" id="textoAyudaLector">Acerca tu carné RFID al sensor o digita el código</p>

                        <!-- Formulario de Marcaje (físico o manual) -->
                        <form id="formMarcaje" class="mt-4">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Seguridad::tokenCsrf()) ?>">
                            <div class="input-rfid-group">
                                <i class="bi bi-upc-scan text-white text-opacity-50 me-2 fs-5"></i>
                                <input type="text"
                                       id="codigoRfid"
                                       name="tag_rfid"
                                       placeholder="Escanear o digitar código RFID..."
                                       autocomplete="off"
                                       autofocus>
                                <button class="btn btn-sena btn-sm px-3 rounded-3" type="submit" id="btnMarcar">
                                    <i class="bi bi-arrow-return-left"></i>
                                </button>
                            </div>
                        </form>

                  
                    </div>
                </div>

                <!-- Panel Derecho: Último Marcaje y Feed de Hoy -->
                <div class="col-lg-6">
                    <div class="card p-3 h-100 shadow-sm border-0">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="h6 fw-bold mb-0">
                                <i class="bi bi-person-check-fill text-sena me-2"></i>Último marcaje registrado
                            </h2>
                            <span class="badge bg-light text-secondary border" id="badgeUltimaHora">En espera</span>
                        </div>

                        <!-- Hero del último marcaje -->
                        <div class="last-scan-hero mb-3" id="panelUltimoMarcaje">
                            <div class="d-flex align-items-center gap-3">
                                <span class="avatar-circ" style="width:54px;height:54px; font-size:1.2rem; background:#fff; border: 1.5px solid #86efac; color:#1f7a00;" id="avatarHero">--</span>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-bold fs-6 text-dark text-truncate" id="nombreHero">Sin marcajes todavía</div>
                                    <div class="text-suave small text-truncate" id="fichaHero">Esperando el primer carné del día</div>
                                </div>
                                <div id="badgeHeroCont">
                                    <span class="badge bg-white text-secondary border px-2 py-1 rounded-pill" style="font-size: .75rem;">Standby</span>
                                </div>
                            </div>
                        </div>

                        <!-- Lista de últimos marcajes con filtro rápido -->
                        <div class="d-flex justify-content-between align-items-center mt-2 mb-2">
                            <h2 class="h6 fw-bold mb-0">Marcajes recientes de hoy</h2>
                            <span class="badge bg-secondary-subtle text-secondary" style="font-size:.72rem;">Actualización en vivo</span>
                        </div>

                        <div class="table-responsive" style="max-height: 290px; overflow-y: auto;">
                            <ul class="list-unstyled mb-0" id="listaUltimosMarcajes">
                                <?php if (empty($ultimosMarcajes)): ?>
                                    <li class="text-suave text-center py-4" id="itemSinMarcajes">
                                        <i class="bi bi-inbox fs-2 text-muted d-block mb-1"></i>
                                        Todavía no hay marcajes registrados hoy.
                                    </li>
                                <?php else: ?>
                                    <?php foreach ($ultimosMarcajes as $m):
                                        $esSalida = !empty($m['hora_salida_real']) && (empty($m['hora_entrada_real']) || $m['hora_salida_real'] >= $m['hora_entrada_real']);
                                        $hora = $esSalida ? $m['hora_salida_real'] : $m['hora_entrada_real'];
                                        $horaFormateada = $hora ? date('g:i:s a', strtotime($hora)) : '';
                                        $esRetardo = strtolower($m['estado'] ?? '') === 'retardo';
                                    ?>
                                        <li class="d-flex justify-content-between align-items-center p-2 mb-1 rounded-3 bg-light border">
                                            <div class="d-flex align-items-center gap-2 text-truncate">
                                                <i class="bi <?= $esSalida ? 'bi-arrow-up-circle-fill text-info' : ($esRetardo ? 'bi-exclamation-circle-fill text-warning' : 'bi-arrow-down-circle-fill text-success') ?> fs-5"></i>
                                                <div class="text-truncate">
                                                    <div class="fw-semibold text-dark text-truncate" style="font-size: .88rem;">
                                                        <?= htmlspecialchars($m['nombre_completo']) ?>
                                                    </div>
                                                    <div class="text-muted" style="font-size: .72rem;">
                                                        <?= htmlspecialchars($m['numero_ficha'] ?? '') ?> · <?= $esSalida ? 'Salida' : ($esRetardo ? 'Retardo' : 'Entrada a tiempo') ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <span class="badge bg-white text-secondary border px-2 py-1 ms-2" style="font-size:.75rem;">
                                                <?= htmlspecialchars($horaFormateada) ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/scripts.php'; ?>
<script src="<?= BASE_URL ?>/assets/js/marcaje.js?v=<?= file_exists(BASE_PATH . "/assets/js/marcaje.js") ? filemtime(BASE_PATH . "/assets/js/marcaje.js") : "1.0" ?>"></script>
</body>
</html>

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
                    <p class="text-suave mb-0">Control de asistencia en tiempo real por proximidad o ingreso manual.</p>
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

                        <!-- Simulador interactivo: etiquetas de prueba rápida con un clic -->
                        <div class="mt-4 pt-3 border-top border-white border-opacity-10">
                            <div class="text-white text-opacity-75 small mb-2 fw-semibold">
                                <i class="bi bi-cursor-fill me-1 text-sena"></i> Simulación interactiva con un clic:
                            </div>
                            <div class="d-flex flex-wrap gap-2 justify-content-center">
                                <button type="button" class="virtual-card-chip" onclick="simularMarcaje('RFID-1001')">
                                    <i class="bi bi-credit-card-2-front"></i> Carné Aprendiz 1
                                </button>
                                <button type="button" class="virtual-card-chip" onclick="simularMarcaje('RFID-1002')">
                                    <i class="bi bi-credit-card-2-front"></i> Carné Aprendiz 2
                                </button>
                                <button type="button" class="virtual-card-chip" onclick="simularMarcaje('123456')">
                                    <i class="bi bi-person-badge"></i> Tag Genérico
                                </button>
                            </div>
                        </div>
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
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form        = document.getElementById('formMarcaje');
    const input       = document.getElementById('codigoRfid');
    const circulo     = document.getElementById('circuloLector');
    const icono       = document.getElementById('iconoScanner');
    const textoEstado = document.getElementById('textoEstadoLector');
    const textoAyuda  = document.getElementById('textoAyudaLector');
    const panelHero   = document.getElementById('panelUltimoMarcaje');
    const avatarHero  = document.getElementById('avatarHero');
    const nombreHero  = document.getElementById('nombreHero');
    const fichaHero   = document.getElementById('fichaHero');
    const badgeHero   = document.getElementById('badgeHeroCont');
    const badgeHora   = document.getElementById('badgeUltimaHora');
    const listaUltimos = document.getElementById('listaUltimosMarcajes');
    const itemSinMarcajes = document.getElementById('itemSinMarcajes');
    const statTotal   = document.getElementById('statTotal');
    const statEntradas = document.getElementById('statEntradas');
    const statSalidas = document.getElementById('statSalidas');
    const statRetardos = document.getElementById('statRetardos');
    const btnAudio    = document.getElementById('btnAudioToggle');
    const audioIcon   = document.getElementById('audioIcon');
    const audioText   = document.getElementById('audioText');

    let audioHabilitado = true;

    // 1. Reloj en tiempo real
    function actualizarReloj() {
        const ahora = new Date();
        const horaStr = ahora.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        const el = document.getElementById('relojHora');
        if (el) el.textContent = horaStr;
    }
    actualizarReloj();
    setInterval(actualizarReloj, 1000);

    // 2. Síntesis de sonido Web Audio API (Sin archivos externos)
    const audioCtx = (window.AudioContext || window.webkitAudioContext) ? new (window.AudioContext || window.webkitAudioContext)() : null;

    function reproducirSonido(tipo) {
        if (!audioHabilitado || !audioCtx) return;
        try {
            if (audioCtx.state === 'suspended') audioCtx.resume();
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.connect(gain);
            gain.connect(audioCtx.destination);

            if (tipo === 'exito') {
                // Doble beep melódico agradable
                osc.type = 'sine';
                osc.frequency.setValueAtTime(587.33, audioCtx.currentTime); // D5
                osc.frequency.setValueAtTime(880, audioCtx.currentTime + 0.1); // A5
                gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.35);
                osc.start();
                osc.stop(audioCtx.currentTime + 0.35);
            } else {
                // Tono de error
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(220, audioCtx.currentTime);
                osc.frequency.setValueAtTime(160, audioCtx.currentTime + 0.15);
                gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.4);
                osc.start();
                osc.stop(audioCtx.currentTime + 0.4);
            }
        } catch (e) {
            // Audio no soportado o bloqueado
        }
    }

    // Toggle de Audio
    btnAudio.addEventListener('click', function () {
        audioHabilitado = !audioHabilitado;
        audioIcon.className = audioHabilitado ? 'bi bi-volume-up-fill me-1' : 'bi bi-volume-mute-fill me-1 text-danger';
        audioText.textContent = audioHabilitado ? 'Sonido: Activado' : 'Sonido: Desactivado';
    });

    function iniciales(nombre) {
        return (nombre || 'AP').split(' ').filter(Boolean).slice(0, 2).map(p => p[0].toUpperCase()).join('');
    }

    // Mostrar resultado del escaneo en la terminal
    function mostrarResultado(ok, data) {
        circulo.classList.remove('rfid-scanning', 'rfid-ok', 'rfid-error');

        if (ok) {
            circulo.classList.add('rfid-ok');
            icono.className = 'bi bi-check2';
            textoEstado.textContent = data.tipo === 'salida' ? 'Salida Registrada' : 'Entrada Registrada';
            textoAyuda.textContent = data.message || 'Marcaje verificado con éxito.';
            reproducirSonido('exito');

            // Actualizar Hero
            const inic = iniciales(data.nombre);
            avatarHero.textContent = inic;
            nombreHero.textContent = data.nombre;
            fichaHero.textContent = data.ficha;
            badgeHora.textContent = data.hora;

            const esSalida = data.tipo === 'salida';
            const esRetardo = (data.estado === 'retardo');

            let badgeHtml = '';
            if (esSalida) {
                badgeHtml = '<span class="badge bg-info text-white px-2 py-1 rounded-pill"><i class="bi bi-box-arrow-up me-1"></i>Salida</span>';
            } else if (esRetardo) {
                badgeHtml = '<span class="badge bg-warning text-dark px-2 py-1 rounded-pill"><i class="bi bi-clock-history me-1"></i>Retardo</span>';
            } else {
                badgeHtml = '<span class="badge bg-success text-white px-2 py-1 rounded-pill"><i class="bi bi-check-circle me-1"></i>A tiempo</span>';
            }
            badgeHero.innerHTML = badgeHtml;

            panelHero.classList.remove('active-scan');
            void panelHero.offsetWidth; // trigger reflow
            panelHero.classList.add('active-scan');

            // Actualizar contadores
            statTotal.textContent = parseInt(statTotal.textContent || 0) + 1;
            if (esSalida) {
                statSalidas.textContent = parseInt(statSalidas.textContent || 0) + 1;
            } else {
                statEntradas.textContent = parseInt(statEntradas.textContent || 0) + 1;
                if (esRetardo) {
                    statRetardos.textContent = parseInt(statRetardos.textContent || 0) + 1;
                }
            }

            // Agregar a la lista de marcajes
            if (itemSinMarcajes) itemSinMarcajes.remove();
            const li = document.createElement('li');
            li.className = 'd-flex justify-content-between align-items-center p-2 mb-1 rounded-3 bg-light border animate__animated animate__fadeInDown';
            li.innerHTML = `
                <div class="d-flex align-items-center gap-2 text-truncate">
                    <i class="bi ${esSalida ? 'bi-arrow-up-circle-fill text-info' : (esRetardo ? 'bi-exclamation-circle-fill text-warning' : 'bi-arrow-down-circle-fill text-success')} fs-5"></i>
                    <div class="text-truncate">
                        <div class="fw-semibold text-dark text-truncate" style="font-size: .88rem;">${data.nombre}</div>
                        <div class="text-muted" style="font-size: .72rem;">${data.ficha} · ${esSalida ? 'Salida' : (esRetardo ? 'Retardo' : 'A tiempo')}</div>
                    </div>
                </div>
                <span class="badge bg-white text-secondary border px-2 py-1 ms-2" style="font-size:.75rem;">${data.hora}</span>
            `;
            listaUltimos.prepend(li);

        } else {
            circulo.classList.add('rfid-error');
            icono.className = 'bi bi-x-lg';
            textoEstado.textContent = 'Lectura no válida';
            textoAyuda.textContent = data.message || 'No se pudo procesar el carné.';
            reproducirSonido('error');
        }

        // Restaurar estado a la espera
        setTimeout(() => {
            circulo.classList.remove('rfid-ok', 'rfid-error');
            icono.className = 'bi bi-wifi';
            textoEstado.textContent = 'Esperando lectura...';
            textoAyuda.textContent = 'Acerca tu carné RFID al sensor o digita el código';
        }, 3200);
    }

    // Procesar envío del formulario
    window.enviarMarcaje = function (tag) {
        if (!tag || tag.trim() === '') return;

        circulo.classList.add('rfid-scanning');
        icono.className = 'bi bi-arrow-repeat';
        textoEstado.textContent = 'Procesando carné...';
        textoAyuda.textContent = 'Validando credencial en base de datos...';

        const formData = new FormData(form);
        formData.set('tag_rfid', tag.trim());

        fetch('<?= BASE_URL ?>/index.php?accion=marcarAsistencia', {
            method: 'POST',
            body: formData,
        })
        .then(res => res.json())
        .then(data => {
            mostrarResultado(data.success, data);
        })
        .catch(() => {
            mostrarResultado(false, { message: 'Error de conexión con el servidor. Intenta de nuevo.' });
        })
        .finally(() => {
            input.value = '';
            input.focus();
        });
    };

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        window.enviarMarcaje(input.value);
    });

    // Clic directo en el círculo del lector para enfocar
    circulo.addEventListener('click', function () {
        input.focus();
    });

    // Simular marcaje rápido desde los botones de prueba
    window.simularMarcaje = function (tag) {
        input.value = tag;
        window.enviarMarcaje(tag);
    };
});
</script>
</body>
</html>

<?php
/**
 * marcaje.php — Lector RFID (HU 8, 16)
 *
 * MarcajeControlador::mostrar() ya dejó lista la variable $ultimosMarcajes
 * antes de hacer require de este archivo (ver index.php). El envío del
 * código (por lector físico o escrito a mano) va por AJAX a
 * accion=marcarAsistencia, sin recargar la página.
 */
$rolActivo     = $_SESSION['rol'] ?? 'Administrador';
$nombreUsuario = $_SESSION['nombre'] ?? '';
$paginaActiva  = 'marcaje';
$tituloPagina  = 'Marcaje RFID';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include 'includes/head.php'; ?>
</head>
<body>
<div class="app-shell">
    <?php include 'includes/sidebar.php'; ?>
    <div class="app-main">
        <?php include 'includes/topbar.php'; ?>
        <main class="app-content">
            <div class="page-header">
                <div class="eyebrow">Control</div>
                <h1 class="h4 fw-bold mb-0">Marcaje de ingreso / salida</h1>
                <p class="text-suave mb-0">Acerca el carné al lector o digita el código RFID manualmente. </p>
            </div>

            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="rfid-reader text-center">
                        <div class="rfid-scan-circle mb-3" id="circuloLector">
                            <i class="bi bi-wifi"></i>
                        </div>
                        <h2 class="h5 fw-bold text-white mb-1" id="textoEstadoLector">Esperando lectura...</h2>
                        <p style="color:rgba(255,255,255,.7);" id="textoAyudaLector">Acerca tu carné RFID al lector</p>

                        <form id="formMarcaje" class="d-flex gap-2 justify-content-center mt-4" style="max-width:320px; margin:0 auto;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Seguridad::tokenCsrf()) ?>">
                            <input type="text" class="form-control" placeholder="Ingresar código manualmente" id="codigoRfid" autocomplete="off" autofocus>
                            <button class="btn btn-sena" type="submit"><i class="bi bi-check-lg"></i></button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card p-3 h-100">
                        <h2 class="h6 fw-bold mb-3">Último marcaje registrado</h2>
                        <div class="d-flex align-items-center gap-3 p-3 rounded-3" style="background:var(--bg-app);" id="panelUltimoMarcaje">
                            <span class="avatar-circ" style="width:52px;height:52px; font-size:1.1rem;">--</span>
                            <div class="flex-grow-1">
                                <div class="fw-bold">Sin marcajes todavía</div>
                                <div class="text-suave" style="font-size:.82rem;">Esperando el primer carné del día</div>
                            </div>
                        </div>

                        <h2 class="h6 fw-bold mt-4 mb-3">Últimos marcajes de hoy</h2>
                        <ul class="list-unstyled mb-0" id="listaUltimosMarcajes">
                            <?php if (empty($ultimosMarcajes)): ?>
                                <li class="text-suave py-2" id="itemSinMarcajes">Todavía no hay marcajes hoy.</li>
                            <?php else: ?>
                                <?php foreach ($ultimosMarcajes as $m):
                                    $esSalida = !empty($m['hora_salida_real']) && (empty($m['hora_entrada_real']) || $m['hora_salida_real'] >= $m['hora_entrada_real']);
                                    $hora = $esSalida ? $m['hora_salida_real'] : $m['hora_entrada_real'];
                                    $horaFormateada = $hora ? date('g:i a', strtotime($hora)) : '';
                                ?>
                                    <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                        <span>
                                            <i class="bi <?= $esSalida ? 'bi-arrow-up-circle text-primary' : 'bi-arrow-down-circle text-success' ?> me-2"></i>
                                            <?= htmlspecialchars($m['nombre_completo'] . ' ' . $m['apellido']) ?>
                                            <?= $esSalida ? ' (salida)' : '' ?>
                                        </span>
                                        <span class="text-suave" style="font-size:.82rem;"><?= htmlspecialchars($horaFormateada) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
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
    const circulo      = document.getElementById('circuloLector');
    const textoEstado  = document.getElementById('textoEstadoLector');
    const textoAyuda   = document.getElementById('textoAyudaLector');
    const panelUltimo  = document.getElementById('panelUltimoMarcaje');
    const listaUltimos = document.getElementById('listaUltimosMarcajes');
    const itemSinMarcajes = document.getElementById('itemSinMarcajes');

    function iniciales(nombre) {
        return nombre.split(' ').filter(Boolean).slice(0, 2).map(p => p[0].toUpperCase()).join('');
    }

    function mostrarResultado(ok, data) {
        if (ok) {
            circulo.classList.remove('rfid-error');
            circulo.classList.add('rfid-ok');
            textoEstado.textContent = data.tipo === 'salida' ? 'Salida registrada' : 'Entrada registrada';
            textoAyuda.textContent = data.message;

            panelUltimo.innerHTML = `
                <span class="avatar-circ" style="width:52px;height:52px; font-size:1.1rem;">${iniciales(data.nombre)}</span>
                <div class="flex-grow-1">
                    <div class="fw-bold">${data.nombre}</div>
                    <div class="text-suave" style="font-size:.82rem;">${data.ficha}</div>
                </div>
                <span class="badge-estado badge-${data.estado || (data.tipo === 'salida' ? 'asistencia' : 'retardo')}">
                    ${data.tipo === 'salida' ? 'Salida' : 'Entrada'} · ${data.hora}
                </span>
            `;

            if (itemSinMarcajes) itemSinMarcajes.remove();
            const li = document.createElement('li');
            li.className = 'd-flex justify-content-between align-items-center py-2 border-bottom';
            li.innerHTML = `
                <span>
                    <i class="bi ${data.tipo === 'salida' ? 'bi-arrow-up-circle text-primary' : 'bi-arrow-down-circle text-success'} me-2"></i>
                    ${data.nombre}${data.tipo === 'salida' ? ' (salida)' : ''}
                </span>
                <span class="text-suave" style="font-size:.82rem;">${data.hora}</span>
            `;
            listaUltimos.prepend(li);
        } else {
            circulo.classList.remove('rfid-ok');
            circulo.classList.add('rfid-error');
            textoEstado.textContent = 'No se pudo registrar';
            textoAyuda.textContent = data.message;
        }

        setTimeout(() => {
            circulo.classList.remove('rfid-ok', 'rfid-error');
            textoEstado.textContent = 'Esperando lectura...';
            textoAyuda.textContent = 'Acerca tu carné RFID al lector';
        }, 3500);
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const tag = input.value.trim();
        if (tag === '') return;

        const formData = new FormData(form);
        formData.set('tag_rfid', tag);

        fetch('<?= BASE_URL ?>/index.php?accion=marcarAsistencia', {
            method: 'POST',
            body: formData,
        })
            .then(res => res.json())
            .then(data => mostrarResultado(data.success, data))
            .catch(() => mostrarResultado(false, { message: 'Error de conexión. Intenta de nuevo.' }));

        input.value = '';
        input.focus();
    });
});
</script>
</body>
</html>

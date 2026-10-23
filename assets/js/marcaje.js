/* ============================================================
   Lógica interactiva de Marcaje y Simulación RFID
   ============================================================ */

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
        }, 4500);
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

        const baseUrl = (typeof window.BASE_URL !== 'undefined') ? window.BASE_URL : (typeof BASE_URL !== 'undefined' ? BASE_URL : '');
        fetch(`${baseUrl}/index.php?accion=marcarAsistencia`, {
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

<?php
// vistas/login.php
// Servido por AuthControlador::mostrarLogin() desde index.php?accion=login

$tituloPagina = 'Iniciar sesión';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include __DIR__ . '/includes/head.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/login.css">
</head>
<body>

<div class="login-shell">

    <!-- Panel izquierdo: identidad del sistema + carné RFID 3D interactivo -->
    <div class="login-aside">
        <div class="brand-mark">
            <div class="dot"><i class="bi bi-shield-check"></i></div>
            <div>
                <div class="fw-bold" style="font-size:1.15rem; letter-spacing: -0.01em;">SENA</div>
                <small style="opacity:.85; font-size:.82rem;">Control de Asistencia Biométrico & RFID</small>
            </div>
        </div>

        <div style="position: relative; z-index: 2;">
            <div class="pulse-badge mb-3">
                <span class="pulse-dot"></span> Terminal Activo v2.4
            </div>

            <h1 class="font-display fw-bold text-white mb-3" style="font-size:2.4rem; line-height: 1.15; max-width:480px;">
                Cada entrada, cada salida, un registro exacto.
            </h1>
            <p style="color:rgba(255,255,255,.8); font-size: 0.98rem; max-width:430px; line-height: 1.6;">
                Marca tu ingreso al instante con tu carné RFID, gestiona excusas médicas con soporte oficial y consulta tus estadísticas en tiempo real.
            </p>

            <!-- Carné 3D interactivo con efecto de inclinación al mover el cursor -->
            <div class="card-rfid-wrap">
                <div class="card-rfid-3d" id="card3d" title="Carné Institucional SENA (Haz clic para simular)">
                    <div class="chip-box">
                        <div class="chip"></div>
                        <i class="bi bi-broadcast nfc-icon"></i>
                    </div>
                    <div>
                        <div style="opacity:.7; font-size:.72rem; letter-spacing:.12em; text-transform: uppercase;">Servicio Nacional de Aprendizaje</div>
                        <div class="fw-bold mt-1" style="font-size:1.15rem; letter-spacing:.05em;" id="cardDisplayNumber">•••• •••• 4471</div>
                    </div>
                    <div class="d-flex justify-content-between align-items-end mt-3 pt-2" style="border-top: 1px solid rgba(255,255,255,0.15);">
                        <div>
                            <div style="opacity:.6; font-size:.68rem;">ESTADO</div>
                            <div class="fw-semibold" style="font-size:.85rem; color:#7cff6b;">Vigente · Activo</div>
                        </div>
                        <span class="badge bg-white bg-opacity-25 px-2 py-1 rounded-2" style="font-size: 0.72rem;">SISTEMA RFID</span>
                    </div>
                </div>
            </div>

            <div class="features-pills">
                <div class="feature-pill">
                    <i class="bi bi-clock-history"></i>
                    <span>Control de bloques</span>
                </div>
                <div class="feature-pill">
                    <i class="bi bi-file-earmark-medical"></i>
                    <span>Excusas médicas</span>
                </div>
                <div class="feature-pill">
                    <i class="bi bi-shield-lock"></i>
                    <span>Seguridad y Cifrado</span>
                </div>
            </div>
        </div>

        <div style="position:relative; z-index:2; color:rgba(255,255,255,.6); font-size:.8rem;">
            © <?= date('Y') ?> SENA · Centro de Biotecnología Agropecuaria · ADSO
        </div>
    </div>

    <!-- Panel derecho: formulario interactivo -->
    <div class="login-form-side">
        <div class="login-box">

            <div class="mb-4">
                <div class="d-inline-flex p-2 rounded-3 bg-success bg-opacity-10 text-sena mb-2">
                    <i class="bi bi-box-arrow-in-right fs-4"></i>
                </div>
                <h2 class="fw-bold mb-1" style="letter-spacing:-0.02em;">Bienvenido al Sistema</h2>
                <p class="text-suave mb-0" style="font-size: 0.92rem;">Ingresa tus credenciales institucionales para continuar</p>
            </div>

            <!-- Accesos rápidos de prueba interactivos (Carga con un clic) -->
            <div class="demo-accounts">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold text-dark" style="font-size:.78rem;">
                        <i class="bi bi-lightning-charge-fill text-warning me-1"></i>Acceso rápido de prueba:
                    </span>
                    <span class="text-muted" style="font-size:.72rem;">Clic para auto-llenar</span>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="demo-chip" onclick="cargarDemo('1111', '123')">
                        <i class="bi bi-mortarboard-fill text-primary"></i> Aprendiz
                    </button>
                    <button type="button" class="demo-chip" onclick="cargarDemo('54321', '123')">
                        <i class="bi bi-person-video3 text-success"></i> Instructor
                    </button>
                    <button type="button" class="demo-chip" onclick="cargarDemo('123456', '123')">
                        <i class="bi bi-shield-shaded text-danger"></i> Admin
                    </button>
                </div>
            </div>

            <form action="<?= BASE_URL ?>/index.php?accion=autenticar" method="POST" id="formLogin" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Seguridad::tokenCsrf()) ?>">

                <!-- Usuario -->
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-dark mb-1" for="usuario">Usuario</label>
                    <div class="form-floating-icon">
                        <i class="bi bi-person-badge input-icon"></i>
                        <input type="text"
                               class="form-control"
                               id="usuario"
                               name="usuario"
                               placeholder="Ingresa tu usuario"
                               autocomplete="username"
                               required
                               autofocus>
                    </div>
                </div>

                <!-- Contraseña -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold text-dark mb-0" for="password">Contraseña</label>
                    </div>
                    <div class="form-floating-icon">
                        <i class="bi bi-shield-lock input-icon"></i>
                        <input type="password"
                               class="form-control"
                               id="password"
                               name="password"
                               placeholder="Ingresa tu contraseña"
                               autocomplete="current-password"
                               required>
                        <button type="button" class="toggle-password" id="btnTogglePass" title="Mostrar/Ocultar contraseña">
                            <i class="bi bi-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                    <!-- Aviso Bloq Mayús -->
                    <div class="caps-warning" id="capsWarning">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Bloq Mayús está activado.
                    </div>
                </div>

                <!-- Alerta de error si aplica -->
                <?php if (!empty($_GET['error'])): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 mb-3 rounded-3" role="alert" style="font-size: .88rem;">
                    <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
                    <div>
                        <?php if ($_GET['error'] === 'token'): ?>
                            La sesión ha expirado o el token de seguridad no es válido. Intenta de nuevo.
                        <?php elseif ($_GET['error'] === 'inactivo'): ?>
                            Tu cuenta de usuario se encuentra inactiva. Comunícate con tu administrador.
                        <?php elseif ($_GET['error'] === '11'): ?>
                            Por favor ingresa tanto el usuario como la contraseña.
                        <?php elseif ($_GET['error'] === '1'): ?>
                            El usuario ingresado no existe en el sistema.
                        <?php elseif ($_GET['error'] === '2'): ?>
                            La contraseña ingresada es incorrecta. Verifica e inténtalo de nuevo.
                        <?php elseif ($_GET['error'] === '500'): ?>
                            Error de conexión con la base de datos MySQL. Verifica que el servicio esté iniciado en XAMPP.
                        <?php else: ?>
                            Credenciales no válidas. Verifica tus datos de acceso.
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-sena btn-login w-100 mb-3" id="btnLogin">
                    <span>Iniciar sesión</span>
                    <i class="bi bi-arrow-right"></i>
                </button>

                <div class="p-3 bg-light rounded-3 text-center border">
                    <p class="text-suave mb-0" style="font-size:.82rem;">
                        <i class="bi bi-info-circle me-1 text-sena"></i>
                        ¿Olvidaste tu contraseña o tienes problemas para ingresar? Solicita el restablecimiento a tu instructor o administrador.
                    </p>
                </div>
            </form>

        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/scripts.php'; ?>

<script>
// Lógica interactiva para la vista de login
document.addEventListener('DOMContentLoaded', function () {
    const card = document.getElementById('card3d');
    const inputUsuario = document.getElementById('usuario');
    const inputPass = document.getElementById('password');
    const btnToggle = document.getElementById('btnTogglePass');
    const eyeIcon = document.getElementById('eyeIcon');
    const capsWarning = document.getElementById('capsWarning');
    const form = document.getElementById('formLogin');
    const btnLogin = document.getElementById('btnLogin');

    // 1. Efecto 3D de inclinación del carné con el ratón
    if (card) {
        const wrap = card.parentElement;
        wrap.addEventListener('mousemove', function (e) {
            const rect = wrap.getBoundingClientRect();
            const x = e.clientX - rect.left - rect.width / 2;
            const y = e.clientY - rect.top - rect.height / 2;
            const mult = 20;
            const rotateX = -(y / (rect.height / 2)) * mult;
            const rotateY = (x / (rect.width / 2)) * mult;
            card.style.transform = `rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
        });

        wrap.addEventListener('mouseleave', function () {
            card.style.transform = 'rotateX(0deg) rotateY(0deg)';
        });

        card.addEventListener('click', function () {
            card.style.transform = 'scale(0.96)';
            setTimeout(() => { card.style.transform = 'scale(1)'; }, 150);
        });
    }

    // 2. Mostrar / ocultar contraseña
    btnToggle.addEventListener('click', function () {
        const isPass = inputPass.type === 'password';
        inputPass.type = isPass ? 'text' : 'password';
        eyeIcon.className = isPass ? 'bi bi-eye-slash' : 'bi bi-eye';
    });

    // 3. Detector de Bloq Mayús activo
    inputPass.addEventListener('keyup', function (e) {
        if (e.getModifierState && e.getModifierState('CapsLock')) {
            capsWarning.style.display = 'block';
        } else {
            capsWarning.style.display = 'none';
        }
    });

    // 4. Feedback de carga en el botón
    form.addEventListener('submit', function () {
        btnLogin.disabled = true;
        btnLogin.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Ingresando...';
    });
});

// Función para cargar credenciales de demo con animación
function cargarDemo(usuario, pass) {
    const inputUsuario = document.getElementById('usuario');
    const inputPass = document.getElementById('password');

    inputUsuario.value = usuario;
    inputPass.value = pass;

    // Resaltar visualmente
    inputUsuario.style.background = '#eaf7e9';
    inputPass.style.background = '#eaf7e9';
    setTimeout(() => {
        inputUsuario.style.background = '#ffffff';
        inputPass.style.background = '#ffffff';
    }, 400);

    inputPass.focus();
}
</script>
</body>
</html>

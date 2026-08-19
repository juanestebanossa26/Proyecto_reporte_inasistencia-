<?php
/**
 * index.php — Punto de entrada del sistema (Login)
 *
 * Único formulario de acceso reutilizado por los 3 roles (admin / instructor / aprendiz),
 * tal como lo define el contrato de datos del proyecto.
 *
 * TODO (backend):
 *   - action="procesar_login.php" -> validar usuario/contraseña contra tabla `usuarios`
 *   - redirigir según rol a dashboard.php
 *   - manejar mensajes de error vía $_SESSION o querystring (?error=1)
 */
$tituloPagina = 'Iniciar sesión';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include 'includes/head.php'; ?>
</head>
<body>

<div class="login-shell">

    <!-- Panel izquierdo: identidad del sistema -->
    <div class="login-aside">
        <div class="brand-mark">
            <div class="dot"><i class="bi bi-shield-check"></i></div>
            <div>
                <div class="fw-bold" style="font-size:1.05rem;">SENA</div>
                <small style="opacity:.75;">Control de Ingreso de Aprendices</small>
            </div>
        </div>

        <div>
            <h1 class="font-display fw-bold text-white mb-3" style="font-size:2.3rem; max-width:460px;">
                Cada entrada, cada salida, un registro exacto.
            </h1>
            <p style="color:rgba(255,255,255,.75); max-width:420px;">
                Marca tu ingreso con tu carné RFID, consulta tu historial de asistencia
                y gestiona excusas médicas desde un solo lugar.
            </p>

            <div class="card-rfid mt-4">
                <div class="chip"></div>
                <div class="d-flex justify-content-between align-items-end">
                    <div>
                        <div style="opacity:.65; font-size:.72rem; letter-spacing:.08em;">CARNÉ APRENDIZ</div>
                        <div class="fw-semibold" style="font-size:1.05rem;">•••• •••• 4471</div>
                    </div>
                    <span class="pulse-ring"></span>
                </div>
            </div>
        </div>

        <div style="position:relative; z-index:1; color:rgba(255,255,255,.55); font-size:.78rem;">
            © <?= date('Y') ?> SENA · Análisis y Desarrollo de Software
        </div>
    </div>

    <!-- Panel derecho: formulario -->
    <div class="login-form-side">
        <div class="login-box">

            <div class="mb-4">
                <h2 class="fw-bold mb-1">Iniciar sesión</h2>
                <p class="text-suave mb-0">Ingresa tus credenciales para continuar</p>
            </div>

           

            <!-- TODO (backend): action="procesar_login.php" method="POST" -->
            <form action="./controllers/login.php" method="POST" novalidate>

                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="usuario" name="documento" placeholder="documento" required>
                    <label for="usuario">número de documento</label>
                </div>

                <div class="form-floating mb-2 position-relative">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Contraseña" required>
                    <label for="password">Contraseña</label>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="recordar">
                        <label class="form-check-label text-suave" for="recordar" style="font-size:.85rem;">Recordarme</label>
                 

                <!-- Alerta de error de ejemplo (el backend la muestra condicionalmente) -->
                <div class="alert alert-danger d-none" id="alertaError" role="alert">
                    Usuario o contraseña incorrectos.
                </div>

                <button type="submit" class="btn btn-sena w-100 py-2 mb-3" >
                    Ingresar <i class="bi bi-arrow-right ms-1"></i>
                </button>

                <p class="text-center text-suave mb-0" style="font-size:.85rem;">
                    ¿Problemas para ingresar? Contacta a tu instructor o al administrador del sistema.
                </p>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/scripts.php'; ?>
</body>
</html>

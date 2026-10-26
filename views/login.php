<?php
// vistas/login.php
// Sistema SERVIPLUS - Control de Asistencia y Acceso RFID
$tituloPagina = 'Iniciar sesión · SERVIPLUS SENA';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include __DIR__ . '/includes/head.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/login.css?v=<?= file_exists(BASE_PATH . '/assets/css/login.css') ? filemtime(BASE_PATH . '/assets/css/login.css') : '1.0' ?>">
</head>
<body class="login-body d-flex align-items-center justify-content-center min-vh-100 p-3">

    <!-- Capas decorativas de fondo estilo institucional SENA -->
    <div class="login-decorations" aria-hidden="true">
        <div class="sena-ambient-glow glow-1"></div>
        <div class="sena-ambient-glow glow-2"></div>
        <div class="sena-ambient-glow glow-3"></div>
        <div class="sena-bg-watermark">
            <img src="<?= BASE_URL ?>/assets/img/logo-sena-blanco.svg" alt="" class="sena-watermark-svg">
        </div>
    </div>

    <!-- Contenedor principal centrado -->
    <div class="login-wrapper position-relative">

        <!-- Barra superior institucional tipo píldora -->
        <div class="text-center mb-3">
            <div class="badge-sena-portal d-inline-flex align-items-center gap-2">
                <img src="<?= BASE_URL ?>/assets/img/logo-sena-blanco.svg" alt="SENA" class="sena-pill-logo">
                <span class="fw-semibold">SENA</span>
                <span class="opacity-50">·</span>
                <span>Regional Valle</span>
                <span class="opacity-50 d-none d-sm-inline">·</span>
                <span class="d-none d-sm-inline opacity-75">Centro de Tecnologías Agroindustriales</span>
            </div>
        </div>

        <main class="login-layout">

            <!-- Panel Izquierdo: Token Llavero RFID Inteligente -->
            <section class="aside-panel">
                <div>
                    <!-- Marca Institucional con Logo SENA Oficial -->
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="sena-logo-card-badge">
                                <img src="<?= BASE_URL ?>/assets/img/logo-sena-verde.svg" alt="Logo SENA" class="sena-card-logo">
                            </div>
                            <div>
                                <div class="fw-bold text-dark lh-1" style="font-size: 1.1rem;">ASISTENCIAS</div>
                                <small class="text-success fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.5px;">Control RFID</small>
                            </div>
                        </div>
                        <span class="badge bg-white border text-secondary px-3 py-2 rounded-pill small d-flex align-items-center">
                            <span class="status-dot d-inline-block me-2"></span> Lector Activo
                        </span>
                    </div>

                    <h1 class="fw-bold text-dark fs-4 mb-2">Control de Asistencia</h1>
                    <p class="text-muted small mb-4">Acceso mediante llavero token RFID y credenciales del sistema institucional.</p>

                    <!-- Llavero inteligente RFID -->
                    <div class="fob-showcase" id="fobCard" title="Llavero Token RFID">
                        <div class="fob-container">
                            <div class="key-fob-visual">
                                <div class="fob-metal-ring"></div>
                                <div class="fob-connector"></div>
                                <div class="fob-body">
                                    <div class="fob-hole"></div>
                                    <div class="fob-chip-core">
                                        <i class="bi bi-broadcast fob-rfid-icon text-white"></i>
                                    </div>
                                    <div class="fob-led"></div>
                                </div>
                            </div>
                            <div class="fob-data">
                                <span class="badge bg-success bg-opacity-10 text-success mb-2">Tag RFID 13.56 MHz</span>
                                <div class="fw-bold text-dark fs-6 font-monospace">UID: 04:A2:8F:7C</div>
                                <div class="text-muted small mt-1">Llavero vinculado y activo</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-muted small pt-3 border-top d-flex align-items-center justify-content-between">
                    <span>© <?= date('Y') ?> SENA · Centro de Tecnologías Agroindustriales - CTA</span>
                    <span class="badge bg-light text-muted border px-2 py-1">ADSO</span>
                </div>
            </section>

            <!-- Panel Derecho: Formulario Minimalista -->
            <section class="form-panel">
                <!-- Encabezado con Logo SENA para Móvil y Desktop -->
                <div class="form-sena-header mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <img src="<?= BASE_URL ?>/assets/img/logo-sena-verde.svg" alt="Logo SENA" class="form-sena-logo">
                        <div class="border-start border-2 ps-3" style="border-color: #39A900 !important;">
                            <span class="d-block fw-bold text-dark lh-1" style="font-size: 0.95rem;">SENA</span>
                            <small class="text-muted d-block" style="font-size: 0.75rem; margin-top: 2px;">Centro de Tecnologías Agroindustriales</small>
                        </div>
                    </div>
                </div>

                <h2 class="fw-bold text-dark fs-4 mb-1">Iniciar sesión</h2>
                <p class="text-muted small mb-4">Ingresa tus credenciales para acceder a la plataforma.</p>

                <form action="<?= BASE_URL ?>/index.php?accion=autenticar" method="POST" id="formLogin">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Seguridad::tokenCsrf()) ?>">

                    <!-- Usuario -->
                    <div class="mb-3">
                        <label for="usuario" class="form-label small fw-semibold text-dark">Usuario o Documento</label>
                        <div class="position-relative">
                            <input type="text" 
                                   class="form-control form-control-minimal" 
                                   id="usuario" 
                                   name="usuario" 
                                   placeholder="Ingresa tu documento" 
                                   autocomplete="username" 
                                   required 
                                   autofocus>
                            <i class="bi bi-person position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                        </div>
                    </div>

                    <!-- Contraseña -->
                    <div class="mb-4">
                        <label for="password" class="form-label small fw-semibold text-dark">Contraseña</label>
                        <div class="position-relative">
                            <input type="password" 
                                   class="form-control form-control-minimal" 
                                   id="password" 
                                   name="password" 
                                   placeholder="Ingresa tu contraseña" 
                                   autocomplete="current-password" 
                                   required>
                            <i class="bi bi-lock position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                        </div>
                    </div>

                    <?php if (!empty($_GET['error'])): ?>
                        <div class="alert alert-danger py-2 small mb-3">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> Credenciales incorrectas o usuario inactivo.
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn-submit-clean">
                        <span>Acceder al sistema</span>
                        <i class="bi bi-arrow-right"></i>
                    </button>
                </form>

                <div class="text-center text-muted small mt-4 pt-3 border-top">
                    <i class="bi bi-info-circle me-1"></i>
                    ¿Dificultades para ingresar? Solicita asistencia a tu instructor o administrador.
                </div>
            </section>

        </main>

        <!-- Pie institucional inferior -->
        <div class="text-center mt-3">
            <small class="text-white text-opacity-75" style="font-size: 0.78rem;">
                © <?= date('Y') ?> Servicio Nacional de Aprendizaje SENA · Sistema de Control de Acceso y Asistencia RFID
            </small>
        </div>

    </div>

    <?php include __DIR__ . '/includes/scripts.php'; ?>
    <script src="<?= BASE_URL ?>/assets/js/login.js?v=<?= file_exists(BASE_PATH . "/assets/js/login.js") ? filemtime(BASE_PATH . "/assets/js/login.js") : "1.0" ?>"></script>
</body>
</html>

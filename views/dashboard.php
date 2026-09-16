<?php
/**
 * dashboard.php — Panel principal
 *
 * TODO (backend):
 *   - $rolActivo debe venir de $_SESSION['rol'] (no de $_GET, que aquí es solo para
 *     poder previsualizar el diseño con cada rol: dashboard.php?rol=aprendiz)
 *   - Las tarjetas KPI y la tabla de "ingresos recientes" deben poblarse desde la BD
 *     (tabla `ingresos`, filtrando por fecha de hoy).
 */
$rolActivo    = $_GET['rol'] ?? 'admin';           // TODO: reemplazar por $_SESSION['rol']
$nombreUsuario = 'Juan Camilo Vanegas';             // TODO: $_SESSION['nombre']
$paginaActiva = 'dashboard';
$tituloPagina = 'Panel principal';
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
                <div class="eyebrow">Hoy, <?= date('d/m/Y') ?></div>
                <h1 class="h3 fw-bold mb-0">Hola, <?= explode(' ', $nombreUsuario)[0] ?> 👋</h1>
                <p class="text-suave mb-0">Este es el resumen de asistencia del día.</p>
            </div>

            <?php if ($rolActivo === 'admin' || $rolActivo === 'instructor'): ?>
            <!-- KPI cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-xl-3">
                    <div class="card kpi-card p-3 h-100">
                        <div class="d-flex align-items-center gap-3">
                            <div class="kpi-icon" style="background:rgba(57,169,0,.12); color:#1F7A00;"><i class="bi bi-people-fill"></i></div>
                            <div>
                                <div class="text-suave" style="font-size:.78rem;">Aprendices activos</div>
                                <div class="h4 fw-bold mb-0">248</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card kpi-card p-3 h-100">
                        <div class="d-flex align-items-center gap-3">
                            <div class="kpi-icon" style="background:rgba(47,111,237,.12); color:#2F6FED;"><i class="bi bi-check-circle-fill"></i></div>
                            <div>
                                <div class="text-suave" style="font-size:.78rem;">A tiempo hoy</div>
                                <div class="h4 fw-bold mb-0">211</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card kpi-card p-3 h-100">
                        <div class="d-flex align-items-center gap-3">
                            <div class="kpi-icon" style="background:rgba(232,164,0,.14); color:#8a6300;"><i class="bi bi-clock-fill"></i></div>
                            <div>
                                <div class="text-suave" style="font-size:.78rem;">Retardos hoy</div>
                                <div class="h4 fw-bold mb-0">24</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card kpi-card p-3 h-100">
                        <div class="d-flex align-items-center gap-3">
                            <div class="kpi-icon" style="background:rgba(214,69,80,.12); color:#B4212B;"><i class="bi bi-x-circle-fill"></i></div>
                            <div>
                                <div class="text-suave" style="font-size:.78rem;">Inasistencias hoy</div>
                                <div class="h4 fw-bold mb-0">13</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card p-3 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="h6 fw-bold mb-0">Ingresos recientes</h2>
                            <a href="marcaje.php" class="btn btn-sm btn-outline-sena">Ver marcaje RFID</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-app align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Aprendiz</th>
                                        <th>Ficha</th>
                                        <th>Entrada</th>
                                        <th>Salida</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="d-flex align-items-center gap-2"><span class="avatar-circ">LM</span> Laura Martínez</td>
                                        <td>2589451 · ADSO</td>
                                        <td>6:58 a.m.</td>
                                        <td>—</td>
                                        <td><span class="badge-estado badge-a-tiempo">A tiempo</span></td>
                                    </tr>
                                    <tr>
                                        <td class="d-flex align-items-center gap-2"><span class="avatar-circ">JR</span> Julián Rojas</td>
                                        <td>2589451 · ADSO</td>
                                        <td>7:12 a.m.</td>
                                        <td>—</td>
                                        <td><span class="badge-estado badge-retardo">Retardo</span></td>
                                    </tr>
                                    <tr>
                                        <td class="d-flex align-items-center gap-2"><span class="avatar-circ">CS</span> Camila Suárez</td>
                                        <td>2678903 · Multimedia</td>
                                        <td>—</td>
                                        <td>—</td>
                                        <td><span class="badge-estado badge-inasistencia">Inasistencia</span></td>
                                    </tr>
                                    <tr>
                                        <td class="d-flex align-items-center gap-2"><span class="avatar-circ">DP</span> Diego Peña</td>
                                        <td>2589451 · ADSO</td>
                                        <td>6:50 a.m.</td>
                                        <td>12:40 p.m.</td>
                                        <td><span class="badge-estado badge-salida-anticipada">Salida anticipada</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card p-3 h-100">
                        <h2 class="h6 fw-bold mb-3">Excusas pendientes</h2>
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-2">
                            <div>
                                <div class="fw-semibold" style="font-size:.88rem;">Camila Suárez</div>
                                <div class="text-suave" style="font-size:.78rem;">Incapacidad médica · 2 días</div>
                            </div>
                            <span class="badge-estado badge-pendiente">Pendiente</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-2">
                            <div>
                                <div class="fw-semibold" style="font-size:.88rem;">Andrés Gil</div>
                                <div class="text-suave" style="font-size:.78rem;">Cita médica · 1 día</div>
                            </div>
                            <span class="badge-estado badge-pendiente">Pendiente</span>
                        </div>
                        <a href="validar_excusas.php" class="btn btn-sm btn-sena w-100 mt-2">Revisar excusas</a>
                    </div>
                </div>
            </div>

            <?php else: /* ---------- Vista aprendiz ---------- */ ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card kpi-card p-3 h-100">
                        <div class="text-suave" style="font-size:.78rem;">Asistencias del mes</div>
                        <div class="h3 fw-bold mb-0 text-sena">18</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card kpi-card p-3 h-100">
                        <div class="text-suave" style="font-size:.78rem;">Retardos del mes</div>
                        <div class="h3 fw-bold mb-0" style="color:#8a6300;">2</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card kpi-card p-3 h-100">
                        <div class="text-suave" style="font-size:.78rem;">Inasistencias sin justificar</div>
                        <div class="h3 fw-bold mb-0" style="color:#B4212B;">1</div>
                    </div>
                </div>
            </div>
            <div class="card p-4 mt-3 text-center">
                <i class="bi bi-wifi text-sena" style="font-size:2rem;"></i>
                <h2 class="h6 fw-bold mt-2">¿Ya marcaste tu ingreso hoy?</h2>
                <p class="text-suave">Acerca tu carné al lector para registrar tu entrada.</p>
                <a href="marcaje.php" class="btn btn-sena mx-auto" style="width:fit-content;">Ir a marcaje RFID</a>
            </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<?php include 'includes/scripts.php'; ?>
</body>
</html>

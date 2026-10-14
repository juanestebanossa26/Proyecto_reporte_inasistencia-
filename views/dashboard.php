<?php


// 1. Variables de sesión simuladas (cambia 'admin' por 'aprendiz' para ver la otra vista)
$rolActivo = $_GET['rol'] ?? 'admin'; // Usando $_GET para que pruebes fácil: dashboard.php?rol=aprendiz
$nombreUsuario = 'Juan Esteban'; // Nombre simulado para el saludo
$paginaActiva = 'dashboard';
$tituloPagina = 'Panel principal';
$fechaHoy = date('Y-m-d');

// 2. Datos estáticos simulados para Admin / Instructor
$kpiActivos = 248;
$kpiATiempo = 211;
$kpiRetardos = 24;
$kpiInasistencias = 13;

// Simulamos lo que devolvería $stmt->fetchAll(PDO::FETCH_ASSOC)
$ingresosRecientes = [
    [
        'nombre' => 'Laura', 'apellido' => 'Martínez', 
        'ficha_codigo' => '2589451', 'programa_formacion' => 'ADSO', 
        'hora_entrada' => '2026-09-28 06:58:00', 'hora_salida' => null, 
        'estado' => 'A tiempo'
    ],
    [
        'nombre' => 'Julián', 'apellido' => 'Rojas', 
        'ficha_codigo' => '2589451', 'programa_formacion' => 'ADSO', 
        'hora_entrada' => '2026-09-28 07:12:00', 'hora_salida' => null, 
        'estado' => 'Retardo'
    ],
    [
        'nombre' => 'Camila', 'apellido' => 'Suárez', 
        'ficha_codigo' => '2678903', 'programa_formacion' => 'Multimedia', 
        'hora_entrada' => null, 'hora_salida' => null, 
        'estado' => 'Inasistencia'
    ],
    [
        'nombre' => 'Diego', 'apellido' => 'Peña', 
        'ficha_codigo' => '2589451', 'programa_formacion' => 'ADSO', 
        'hora_entrada' => '2026-09-28 06:50:00', 'hora_salida' => '2026-09-28 12:40:00', 
        'estado' => 'Salida anticipada'
    ]
];

$excusasPendientes = [
    ['nombre' => 'Camila', 'apellido' => 'Suárez', 'motivo' => 'Incapacidad médica', 'dias' => 2],
    ['nombre' => 'Andrés', 'apellido' => 'Gil', 'motivo' => 'Cita médica', 'dias' => 1]
];

// 3. Datos estáticos simulados para el Aprendiz
$misAsistencias = 18;
$misRetardos = 2;
$misInasistencias = 1;

// Helper para clases CSS de estados (se mantiene igual)
function getBadgeClass($estado) {
    return match($estado) {
        'A tiempo' => 'badge-a-tiempo',
        'Retardo' => 'badge-retardo',
        'Inasistencia' => 'badge-inasistencia',
        'Salida anticipada' => 'badge-salida-anticipada',
        default => 'badge-secundario'
    };
}
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
                <h1 class="h3 fw-bold mb-0">Hola, <?= htmlspecialchars(explode(' ', $nombreUsuario)[0]) ?> 👋</h1>
                <p class="text-suave mb-0">Este es el resumen de asistencia del día.</p>
            </div>

            <?php if ($rolActivo === 'Administrador' || $rolActivo === 'instructor'): ?>
            
            <!-- KPI cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-xl-3">
                    <div class="card kpi-card p-3 h-100">
                        <div class="d-flex align-items-center gap-3">
                            <div class="kpi-icon" style="background:rgba(57,169,0,.12); color:#1F7A00;"><i class="bi bi-people-fill"></i></div>
                            <div>
                                <div class="text-suave" style="font-size:.78rem;">Aprendices activos</div>
                                <div class="h4 fw-bold mb-0"><?= $kpiActivos ?></div>
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
                                <div class="h4 fw-bold mb-0"><?= $kpiATiempo ?></div>
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
                                <div class="h4 fw-bold mb-0"><?= $kpiRetardos ?></div>
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
                                <div class="h4 fw-bold mb-0"><?= $kpiInasistencias ?></div>
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
                            <a href="<?= BASE_URL ?>/index.php?accion=marcaje" class="btn btn-sm btn-outline-sena">Ver marcaje RFID</a>
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
                                    <?php if(empty($ingresosRecientes)): ?>
                                        <tr><td colspan="5" class="text-center text-muted">No hay ingresos registrados hoy.</td></tr>
                                    <?php else: ?>
                                        <?php foreach($ingresosRecientes as $ingreso): 
                                            // Lógica dinámica leyendo los arrays estáticos
                                            $iniciales = strtoupper(substr($ingreso['nombre'], 0, 1) . substr($ingreso['apellido'], 0, 1));
                                            $nombreCompleto = htmlspecialchars($ingreso['nombre'] . ' ' . $ingreso['apellido']);
                                            $fichaTxt = htmlspecialchars($ingreso['ficha_codigo'] . ' · ' . $ingreso['programa_formacion']);
                                            $horaEntrada = $ingreso['hora_entrada'] ? date('h:i a', strtotime($ingreso['hora_entrada'])) : '—';
                                            $horaSalida = $ingreso['hora_salida'] ? date('h:i a', strtotime($ingreso['hora_salida'])) : '—';
                                        ?>
                                        <tr>
                                            <td class="d-flex align-items-center gap-2">
                                                <span class="avatar-circ"><?= $iniciales ?></span> 
                                                <?= $nombreCompleto ?>
                                            </td>
                                            <td><?= $fichaTxt ?></td>
                                            <td><?= $horaEntrada ?></td>
                                            <td><?= $horaSalida ?></td>
                                            <td><span class="badge-estado <?= getBadgeClass($ingreso['estado']) ?>"><?= htmlspecialchars($ingreso['estado']) ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card p-3 h-100">
                        <h2 class="h6 fw-bold mb-3">Excusas pendientes</h2>
                        
                        <?php if(empty($excusasPendientes)): ?>
                            <p class="text-muted text-center my-4">No hay excusas pendientes por revisar.</p>
                        <?php else: ?>
                            <?php foreach($excusasPendientes as $excusa): ?>
                                <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-2">
                                    <div>
                                        <div class="fw-semibold" style="font-size:.88rem;">
                                            <?= htmlspecialchars($excusa['nombre'] . ' ' . $excusa['apellido']) ?>
                                        </div>
                                        <div class="text-suave" style="font-size:.78rem;">
                                            <?= htmlspecialchars($excusa['motivo']) ?> · <?= htmlspecialchars($excusa['dias']) ?> día(s)
                                        </div>
                                    </div>
                                    <span class="badge-estado badge-pendiente">Pendiente</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <a href="<?= BASE_URL ?>/index.php?accion=validarexcusas" class="btn btn-sm btn-sena w-100 mt-auto">Revisar excusas</a>
                    </div>
                </div>
            </div>

            <?php else: /* ---------- Vista aprendiz ---------- */ ?>
            
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card kpi-card p-3 h-100">
                        <div class="text-suave" style="font-size:.78rem;">Asistencias del mes</div>
                        <div class="h3 fw-bold mb-0 text-sena"><?= $misAsistencias ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card kpi-card p-3 h-100">
                        <div class="text-suave" style="font-size:.78rem;">Retardos del mes</div>
                        <div class="h3 fw-bold mb-0" style="color:#8a6300;"><?= $misRetardos ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card kpi-card p-3 h-100">
                        <div class="text-suave" style="font-size:.78rem;">Inasistencias sin justificar</div>
                        <div class="h3 fw-bold mb-0" style="color:#B4212B;"><?= $misInasistencias ?></div>
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
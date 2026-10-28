<?php
/**
 * views/fichas_instructor.php — Vista «Mis fichas» para el rol Instructor
 * Sistema de Control de Ingreso y Asistencia RFID · SENA
 *
 * Muestra exclusivamente las fichas activas asignadas al instructor autenticado,
 * sus métricas académicas pertinentes y el estado de sus próximas clases.
 */

declare(strict_types=1);

$rolActivo      = $_SESSION['rol'] ?? 'Instructor';
$nombreUsuario  = $_SESSION['nombre'] ?? 'Instructor';
$paginaActiva   = 'fichas';
$tituloPagina   = 'Mis Fichas de Formación';

$fichas         = $fichas ?? [];
$kpisInstructor = $kpisInstructor ?? [
    'total_fichas'    => count($fichas),
    'clases_hoy'      => 0,
    'proximas_clases' => 0
];

/**
 * Formatea de forma amigable la fecha y horas de la próxima clase del instructor
 */
if (!function_exists('formatearHorarioInstructor')) {
    function formatearHorarioInstructor(?array $horario): ?string {
        if (!$horario || empty($horario['dia_semana'])) {
            return null;
        }

        $fecha = (string)$horario['dia_semana'];
        $dt = DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$dt) {
            return null;
        }

        $diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $meses = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

        $w = (int)$dt->format('w');
        $diaNombre = $diasSemana[$w] ?? '';
        $diaNumero = (int)$dt->format('d');
        $mesNombre = $meses[(int)$dt->format('n')] ?? '';

        $horaInicio = substr((string)($horario['hora_entrada_programada'] ?? ''), 0, 5);
        $horaFin    = substr((string)($horario['hora_salida_programada'] ?? ''), 0, 5);
        $rangoHoras = ($horaInicio !== '' && $horaFin !== '') ? " · {$horaInicio} - {$horaFin}" : '';

        $hoy = (new DateTime())->format('Y-m-d');
        $manana = (new DateTime('+1 day'))->format('Y-m-d');

        if ($fecha === $hoy) {
            return "Hoy{$rangoHoras}";
        }
        if ($fecha === $manana) {
            return "Mañana{$rangoHoras}";
        }

        return "{$diaNombre} {$diaNumero} {$mesNombre}{$rangoHoras}";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'includes/head.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/fichas.css?v=<?= file_exists(BASE_PATH . "/assets/css/fichas.css") ? filemtime(BASE_PATH . "/assets/css/fichas.css") : "1.0" ?>">
</head>
<body>
<div class="app-shell">
    <?php include 'includes/sidebar.php'; ?>
    <div class="app-main">
        <?php include 'includes/topbar.php'; ?>
        <main class="app-content p-4">

            <!-- Encabezado de página -->
            <div class="page-header d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
                <div>
                    <div class="eyebrow text-uppercase fw-semibold" style="letter-spacing: 0.05em; font-size: 0.8rem; color: #1b5e20;">
                        <i class="bi bi-person-video3 me-1"></i> Gestión Académica · Instructor
                    </div>
                    <h1 class="h4 fw-bold mb-1">Mis Fichas de Formación</h1>
                    <p class="text-suave mb-0">Consulta tus grupos lectivos asignados, cantidad de aprendices y horarios programados.</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="<?= BASE_URL ?>/index.php?accion=horarios" class="btn btn-sena">
                        <i class="bi bi-calendar3 me-1"></i> Mi Calendario de Clases
                    </a>
                </div>
            </div>

            <!-- Resumen de Indicadores Clave del Instructor -->
            <div class="row g-3 mb-4">
                <!-- Indicador: Fichas Asignadas -->
                <div class="col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm p-3 h-100" style="border-radius: 12px;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Fichas Asignadas</small>
                                <h3 class="fw-bold mb-0 text-success"><?= (int)$kpisInstructor['total_fichas'] ?></h3>
                                <small class="text-muted">Grupos activos a tu cargo</small>
                            </div>
                            <div class="rounded-circle bg-success-subtle p-3 text-success">
                                <i class="bi bi-journal-bookmark-fill fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Indicador: Clases Hoy -->
                <div class="col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm p-3 h-100" style="border-radius: 12px;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Clases Hoy</small>
                                <h3 class="fw-bold mb-0 text-primary"><?= (int)$kpisInstructor['clases_hoy'] ?></h3>
                                <small class="text-muted">Programadas para el día de hoy</small>
                            </div>
                            <div class="rounded-circle bg-primary-subtle p-3 text-primary">
                                <i class="bi bi-calendar-check-fill fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Indicador: Próximas Clases -->
                <div class="col-md-4 col-sm-12">
                    <div class="card border-0 shadow-sm p-3 h-100" style="border-radius: 12px;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Próximas Clases</small>
                                <h3 class="fw-bold mb-0 text-dark"><?= (int)$kpisInstructor['proximas_clases'] ?></h3>
                                <small class="text-muted">Sesiones en fechas futuras</small>
                            </div>
                            <div class="rounded-circle bg-light p-3 text-secondary border">
                                <i class="bi bi-clock-history fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Barra de búsqueda y control de vista -->
            <div class="ficha-toolbar-card d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
                <div class="ficha-search-box flex-grow-1">
                    <i class="bi bi-search ficha-search-icon"></i>
                    <input type="text" 
                           id="inputBuscarFichaInstructor" 
                           class="ficha-search-input" 
                           placeholder="Buscar por número de ficha o programa de formación..." 
                           autocomplete="off">
                    <button type="button" id="btnLimpiarBusqueda" class="ficha-search-clear" title="Limpiar búsqueda">
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-secondary border px-3 py-2 fw-semibold" id="contadorFichasVisibles">
                        <?= count($fichas) ?> <?= count($fichas) === 1 ? 'ficha asignada' : 'fichas asignadas' ?>
                    </span>
                </div>
            </div>

            <!-- Listado de Fichas (Cuadrícula Adaptable) -->
            <div class="fichas-grid" id="contenedorFichasInstructor">
                <?php if (empty($fichas)): ?>
                    <!-- Estado vacío cuando el instructor no tiene fichas asignadas -->
                    <div class="ficha-empty-state text-center py-5">
                        <div class="empty-icon-circle mx-auto mb-3">
                            <i class="bi bi-folder-x fs-2 text-muted"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-1">Actualmente no tienes fichas asignadas</h5>
                        <p class="text-muted mb-3" style="max-width: 480px; margin-inline: auto; font-size: 0.9rem;">
                            No tienes grupos de formación activos asociados a tu perfil. Comunícate con la coordinación académica si necesitas que se te asigne una ficha lectiva.
                        </p>
                        <a href="<?= BASE_URL ?>/index.php?accion=horarios" class="btn btn-sm btn-outline-sena">
                            <i class="bi bi-calendar3 me-1"></i> Ir al Calendario
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($fichas as $f): 
                        $proximoHorarioFormateado = formatearHorarioInstructor($f['proximo_horario'] ?? null);
                        $textoBusqueda = strtolower(trim(($f['numero_ficha'] ?? '') . ' ' . ($f['nombre_programa'] ?? '')));
                    ?>
                        <div class="ficha-card-instructor" 
                             data-search="<?= htmlspecialchars($textoBusqueda, ENT_QUOTES, 'UTF-8') ?>"
                             data-numero="<?= htmlspecialchars((string)$f['numero_ficha'], ENT_QUOTES, 'UTF-8') ?>">
                            
                            <!-- Cabecera de la Tarjeta -->
                            <div>
                                <div class="ficha-card-header">
                                    <div class="ficha-numero-badge">
                                        <i class="bi bi-hash text-muted"></i>
                                        <span><?= htmlspecialchars((string)$f['numero_ficha'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <span class="badge badge-estado-activa rounded-pill px-2 py-1">
                                        <i class="bi bi-circle-fill me-1" style="font-size: 0.45rem;"></i> Activa
                                    </span>
                                </div>

                                <!-- Nombre del Programa de Formación -->
                                <h3 class="ficha-programa-titulo" title="<?= htmlspecialchars((string)$f['nombre_programa'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars((string)$f['nombre_programa'], ENT_QUOTES, 'UTF-8') ?>
                                </h3>

                                <!-- Métricas Académicas (Aprendices y Horarios) -->
                                <div class="ficha-metricas-row">
                                    <span class="ficha-metrica-chip" title="Aprendices matriculados en la ficha">
                                        <i class="bi bi-people-fill text-primary"></i>
                                        <span><?= (int)($f['total_aprendices'] ?? 0) ?> aprendices</span>
                                    </span>
                                    <span class="ficha-metrica-chip" title="Horarios registrados para tu asignación">
                                        <i class="bi bi-calendar-event-fill text-success"></i>
                                        <span><?= (int)($f['total_horarios'] ?? 0) ?> clases</span>
                                    </span>
                                </div>

                                <!-- Información de la Próxima Clase -->
                                <?php if ($proximoHorarioFormateado !== null): ?>
                                    <div class="ficha-horario-proximo" title="Próxima sesión programada en calendario">
                                        <i class="bi bi-calendar2-check-fill ficha-horario-icon"></i>
                                        <div>
                                            <div class="ficha-horario-label">Próxima Clase</div>
                                            <div class="ficha-horario-texto"><?= htmlspecialchars($proximoHorarioFormateado, ENT_QUOTES, 'UTF-8') ?></div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="ficha-horario-proximo sin-horario" title="No hay sesiones próximas en el calendario">
                                        <i class="bi bi-calendar-x ficha-horario-icon"></i>
                                        <div>
                                            <div class="ficha-horario-label">Próxima Clase</div>
                                            <div class="ficha-horario-texto">No hay clases programadas próximamente</div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Botón de Acción Principal -->
                            <div class="pt-2 border-top mt-2">
                                <a href="<?= BASE_URL ?>/index.php?accion=horarios" 
                                   class="btn-ver-ficha-instructor" 
                                   title="Consultar calendario y horarios de esta ficha">
                                    <i class="bi bi-calendar3"></i> Ver horarios
                                </a>
                            </div>

                        </div>
                    <?php endforeach; ?>

                    <!-- Estado para búsqueda sin resultados -->
                    <div id="fichaSinResultadosBusqueda" class="ficha-empty-state text-center py-5 d-none">
                        <div class="empty-icon-circle mx-auto mb-3">
                            <i class="bi bi-search fs-2 text-muted"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-1">No se encontraron fichas</h5>
                        <p class="text-muted mb-0" id="mensajeFiltroVacio" style="font-size: 0.9rem;">
                            No hay fichas asignadas que coincidan con el término ingresado.
                        </p>
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div>
</div>

<?php include 'includes/scripts.php'; ?>

<!-- Script para filtrado instantáneo en el cliente -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const inputBuscar = document.getElementById('inputBuscarFichaInstructor');
    const btnLimpiar = document.getElementById('btnLimpiarBusqueda');
    const cards = Array.from(document.querySelectorAll('.ficha-card-instructor'));
    const sinResultados = document.getElementById('fichaSinResultadosBusqueda');
    const contadorVisibles = document.getElementById('contadorFichasVisibles');
    const mensajeFiltro = document.getElementById('mensajeFiltroVacio');
    const totalInicial = cards.length;

    if (!inputBuscar) return;

    function filtrarFichas() {
        const query = (inputBuscar.value || '').trim().toLowerCase();
        let visibles = 0;

        if (btnLimpiar) {
            btnLimpiar.style.display = query.length > 0 ? 'block' : 'none';
        }

        cards.forEach(card => {
            const dataSearch = card.getAttribute('data-search') || '';
            const coincide = query === '' || dataSearch.includes(query);
            if (coincide) {
                card.style.display = '';
                visibles++;
            } else {
                card.style.display = 'none';
            }
        });

        if (sinResultados) {
            if (visibles === 0 && totalInicial > 0) {
                sinResultados.classList.remove('d-none');
                if (mensajeFiltro) {
                    mensajeFiltro.textContent = `No hay fichas asignadas que coincidan con "${inputBuscar.value.trim()}".`;
                }
            } else {
                sinResultados.classList.add('d-none');
            }
        }

        if (contadorVisibles) {
            contadorVisibles.textContent = `${visibles} ${visibles === 1 ? 'ficha visible' : 'fichas visibles'}`;
        }
    }

    inputBuscar.addEventListener('input', filtrarFichas);

    if (btnLimpiar) {
        btnLimpiar.addEventListener('click', function () {
            inputBuscar.value = '';
            filtrarFichas();
            inputBuscar.focus();
        });
    }
});
</script>
</body>
</html>

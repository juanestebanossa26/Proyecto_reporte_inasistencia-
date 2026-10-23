<?php
/**
 * views/fichas.php — Gestión Integral del Ciclo de Vida de Fichas de Formación
 * Sistema de Control de Ingreso y Asistencia RFID · SENA
 */
$rolActivo     = $_SESSION['rol'] ?? 'Administrador';
$nombreUsuario = $_SESSION['nombre'] ?? '';
$paginaActiva  = 'fichas';
$tituloPagina  = 'Fichas de Formación';

$fichas       = $fichas ?? [];
$instructores = $instructores ?? [];
$conteos      = $conteos ?? ['total' => 0, 'activas' => 0, 'finalizadas' => 0, 'archivadas' => 0];
$estadoFiltro = $estadoFiltro ?? 'Activa';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'includes/head.php'; ?>

    <style>
        .badge-estado-activa {
            background-color: #e8f5e9;
            color: #1b5e20;
            border: 1px solid #c8e6c9;
            font-weight: 600;
        }
        .badge-estado-finalizada {
            background-color: #fff3e0;
            color: #e65100;
            border: 1px solid #ffe0b2;
            font-weight: 600;
        }
        .badge-estado-archivada {
            background-color: #eceff1;
            color: #455a64;
            border: 1px solid #cfd8dc;
            font-weight: 600;
        }
        .nav-tabs-sena .nav-link {
            color: #4b6b58;
            font-weight: 500;
            border: none;
            border-bottom: 3px solid transparent;
            padding: 0.75rem 1.25rem;
        }
        .nav-tabs-sena .nav-link.active {
            color: #1b5e20;
            font-weight: 700;
            background: transparent;
            border-bottom: 3px solid #39A900;
        }
        .timeline-audit-item {
            border-left: 2px solid #39A900;
            padding-left: 1rem;
            position: relative;
            margin-bottom: 1.25rem;
        }
        .timeline-audit-item::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 4px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #39A900;
        }

        /* Cuadrícula CSS Grid y Tarjetas de Ficha */
        .fichas-grid-container {
            width: 100%;
        }
        .fichas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.25rem;
        }
        @media (max-width: 575.98px) {
            .fichas-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }
        @media (min-width: 576px) and (max-width: 991.98px) {
            .fichas-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }
        }
        @media (min-width: 1400px) {
            .fichas-grid {
                grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            }
        }

        .ficha-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.2s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            min-height: 250px;
        }
        .ficha-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 24px rgba(16, 36, 28, 0.08);
            border-color: #cbd5e1;
        }
        .ficha-card.card-estado-activa {
            border-top: 3.5px solid #39A900;
        }
        .ficha-card.card-estado-finalizada {
            border-top: 3.5px solid #f59e0b;
        }
        .ficha-card.card-estado-archivada {
            border-top: 3.5px solid #64748b;
        }

        .ficha-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.85rem;
        }
        .ficha-numero-badge {
            background: #f8fafc;
            color: #0f172a;
            font-family: var(--font-display, 'Sora', sans-serif);
            font-weight: 700;
            font-size: 0.95rem;
            letter-spacing: 0.02em;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.3rem 0.65rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .ficha-card-body {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .ficha-programa-titulo {
            font-family: var(--font-display, 'Sora', sans-serif);
            font-size: 0.985rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.35;
            margin-bottom: 0.65rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            min-height: 2.65rem;
        }
        .ficha-instructor-info {
            font-size: 0.825rem;
            color: #475569;
            margin-bottom: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.45rem;
        }
        .ficha-metricas-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: auto;
            margin-bottom: 0.85rem;
        }
        .ficha-metrica-chip {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 0.3rem 0.6rem;
            font-size: 0.775rem;
            font-weight: 600;
            color: #334155;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .ficha-meta-cierre {
            background: #f8fafc;
            border-left: 3px solid #cbd5e1;
            border-radius: 0 6px 6px 0;
            padding: 0.4rem 0.65rem;
            font-size: 0.775rem;
            margin-bottom: 0.85rem;
        }

        .ficha-card-actions {
            border-top: 1px solid #f1f5f9;
            padding-top: 0.75rem;
            margin-top: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.35rem;
            flex-wrap: wrap;
        }
        .ficha-action-btn {
            border-radius: 8px;
            padding: 0.35rem 0.6rem;
            font-size: 0.825rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s ease;
        }
        .ficha-action-btn:hover {
            transform: translateY(-1px);
        }

        /* Barra de búsqueda y herramientas */
        .ficha-toolbar-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            margin-bottom: 1.25rem;
        }
        .ficha-search-box {
            position: relative;
            max-width: 460px;
            width: 100%;
        }
        .ficha-search-input {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0.45rem 2rem 0.45rem 2.25rem;
            font-size: 0.875rem;
            width: 100%;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .ficha-search-input:focus {
            border-color: #39A900;
            box-shadow: 0 0 0 3px rgba(57, 169, 0, 0.15);
            outline: none;
        }
        .ficha-search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
        }
        .ficha-search-clear {
            position: absolute;
            right: 0.6rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            cursor: pointer;
            border: none;
            background: transparent;
            padding: 0;
            display: none;
        }
        .ficha-search-clear:hover {
            color: #334155;
        }

        .ficha-empty-state {
            background: #ffffff;
            border: 1.5px dashed #cbd5e1;
            border-radius: 14px;
            padding: 3.5rem 1.5rem;
            width: 100%;
            grid-column: 1 / -1;
        }
        .empty-icon-circle {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php include 'includes/sidebar.php'; ?>
    <div class="app-main">
        <?php include 'includes/topbar.php'; ?>
        <main class="app-content p-4">
            <!-- Encabezado -->
            <div class="page-header d-flex justify-content-between align-items-end flex-wrap gap-2 mb-4">
                <div>
                    <div class="eyebrow text-uppercase fw-semibold" style="letter-spacing: 0.05em; font-size: 0.8rem; color: #1b5e20;">Ciclo de Vida Académico</div>
                    <h1 class="h4 fw-bold mb-1">Fichas de Formación</h1>
                    <p class="text-suave mb-0">Gestiona los estados operativos, finalización lectiva, archivo histórico y asignaciones académicas del SENA.</p>
                </div>
                <?php if ($rolActivo === 'Administrador'): ?>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sena" onclick="abrirModalCrearFicha()">
                        <i class="bi bi-plus-lg me-1"></i> Nueva Ficha
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Resumen de Métricas de Ciclo de Vida -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Fichas Activas</small>
                                <h3 class="fw-bold mb-0 text-success"><?= $conteos['activas'] ?></h3>
                                <small class="text-muted">En formación lectiva</small>
                            </div>
                            <div class="rounded-circle bg-success-subtle p-3 text-success">
                                <i class="bi bi-play-circle fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Fichas Finalizadas</small>
                                <h3 class="fw-bold mb-0 text-warning"><?= $conteos['finalizadas'] ?></h3>
                                <small class="text-muted">Ciclo concluido</small>
                            </div>
                            <div class="rounded-circle bg-warning-subtle p-3 text-warning">
                                <i class="bi bi-check2-circle fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Fichas Archivadas</small>
                                <h3 class="fw-bold mb-0 text-secondary"><?= $conteos['archivadas'] ?></h3>
                                <small class="text-muted">Memoria histórica</small>
                            </div>
                            <div class="rounded-circle bg-secondary-subtle p-3 text-secondary">
                                <i class="bi bi-archive fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Total Fichas</small>
                                <h3 class="fw-bold mb-0 text-primary"><?= $conteos['total'] ?></h3>
                                <small class="text-muted">Registradas en sistema</small>
                            </div>
                            <div class="rounded-circle bg-primary-subtle p-3 text-primary">
                                <i class="bi bi-folder2 fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navegación de Estados (Tabs) -->
            <ul class="nav nav-tabs nav-tabs-sena mb-3 border-bottom">
                <li class="nav-item">
                    <a class="nav-link <?= $estadoFiltro === 'Activa' ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php?accion=fichas&estado=Activa">
                        <i class="bi bi-play-circle-fill text-success me-1"></i> Activas 
                        <span class="badge bg-success-subtle text-success rounded-pill ms-1"><?= $conteos['activas'] ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $estadoFiltro === 'Finalizada' ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php?accion=fichas&estado=Finalizada">
                        <i class="bi bi-check-circle-fill text-warning me-1"></i> Finalizadas 
                        <span class="badge bg-warning-subtle text-warning rounded-pill ms-1"><?= $conteos['finalizadas'] ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $estadoFiltro === 'Archivada' ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php?accion=fichas&estado=Archivada">
                        <i class="bi bi-archive-fill text-secondary me-1"></i> Archivadas 
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill ms-1"><?= $conteos['archivadas'] ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $estadoFiltro === 'todos' ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php?accion=fichas&estado=todos">
                        <i class="bi bi-list-ul me-1"></i> Todas las Fichas
                    </a>
                </li>
              <!-- Barra de Búsqueda y Herramientas -->
            <div class="ficha-toolbar-card d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="ficha-search-box">
                    <i class="bi bi-search ficha-search-icon"></i>
                    <input type="text" id="inputBuscarFicha" class="form-control ficha-search-input" placeholder="Buscar por número de ficha, programa o instructor..." autocomplete="off">
                    <button type="button" id="btnLimpiarBusqueda" class="ficha-search-clear" title="Limpiar búsqueda">
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                </div>
                <div class="d-flex align-items-center gap-2 text-muted small">
                    <span>Mostrando <strong id="contadorFichasMostradas" class="text-dark"><?= count($fichas) ?></strong> de <strong class="text-dark"><?= count($fichas) ?></strong> fichas</span>
                </div>
            </div>

            <!-- Cuadrícula de Tarjetas de Fichas (Grid) -->
            <div class="fichas-grid-container mb-4">
                <div class="fichas-grid" id="contenedorFichasGrid">
                    <?php if (empty($fichas)): ?>
                        <div class="ficha-empty-state text-center">
                            <div class="empty-icon-circle mx-auto mb-3">
                                <i class="bi bi-folder-x fs-1 text-muted"></i>
                            </div>
                            <h5 class="fw-bold text-dark mb-1">No hay fichas registradas</h5>
                            <p class="text-muted small mb-3">No se encontraron fichas en el estado <strong><?= htmlspecialchars($estadoFiltro) ?></strong>.</p>
                            <?php if ($rolActivo === 'Administrador' && $estadoFiltro === 'Activa'): ?>
                            <button type="button" class="btn btn-sm btn-sena" onclick="abrirModalCrearFicha()">
                                <i class="bi bi-plus-lg me-1"></i> Registrar Primera Ficha
                            </button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($fichas as $f): 
                            $badgeClase = match($f['estado']) {
                                'Activa'     => 'badge-estado-activa',
                                'Finalizada' => 'badge-estado-finalizada',
                                'Archivada'  => 'badge-estado-archivada',
                                default      => 'badge-estado-activa'
                            };
                            $badgeIcono = match($f['estado']) {
                                'Activa'     => 'bi-play-circle',
                                'Finalizada' => 'bi-check2-circle',
                                'Archivada'  => 'bi-archive',
                                default      => 'bi-play-circle'
                            };
                            $instTexto = trim($f['instructores'] ?? '');
                        ?>
                            <div class="ficha-card card-estado-<?= strtolower($f['estado']) ?>"
                                 data-numero="<?= strtolower(htmlspecialchars($f['numero_ficha'], ENT_QUOTES, 'UTF-8')) ?>"
                                 data-programa="<?= strtolower(htmlspecialchars($f['nombre_programa'], ENT_QUOTES, 'UTF-8')) ?>"
                                 data-instructor="<?= strtolower(htmlspecialchars($instTexto, ENT_QUOTES, 'UTF-8')) ?>"
                                 data-estado="<?= strtolower(htmlspecialchars($f['estado'], ENT_QUOTES, 'UTF-8')) ?>">
                                
                                <!-- Encabezado de la Tarjeta -->
                                <div class="ficha-card-header">
                                    <div class="ficha-numero-badge" title="Número de ficha SENA">
                                        <i class="bi bi-folder2-open text-success"></i>
                                        <span><?= htmlspecialchars($f['numero_ficha']) ?></span>
                                    </div>
                                    <span class="badge <?= $badgeClase ?> px-2 py-1">
                                        <i class="bi <?= $badgeIcono ?> me-1"></i><?= htmlspecialchars($f['estado']) ?>
                                    </span>
                                </div>

                                <!-- Cuerpo / Información Principal -->
                                <div class="ficha-card-body">
                                    <h3 class="ficha-programa-titulo" title="<?= htmlspecialchars($f['nombre_programa']) ?>">
                                        <?= htmlspecialchars($f['nombre_programa']) ?>
                                    </h3>

                                    <!-- Instructor Asignado -->
                                    <div class="ficha-instructor-info">
                                        <?php if ($instTexto !== ''): ?>
                                            <i class="bi bi-person-badge text-primary flex-shrink-0"></i>
                                            <span class="text-truncate" title="Instructor(es): <?= htmlspecialchars($instTexto) ?>">
                                                <?= htmlspecialchars($instTexto) ?>
                                            </span>
                                        <?php else: ?>
                                            <i class="bi bi-person-x text-muted flex-shrink-0"></i>
                                            <span class="text-muted fst-italic">Sin instructor asignado</span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Metadatos de Finalización / Archivado -->
                                    <?php if (!empty($f['fecha_finalizacion']) && ($f['estado'] === 'Finalizada' || $f['estado'] === 'Archivada' || $estadoFiltro === 'todos')): ?>
                                    <div class="ficha-meta-cierre">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <span class="text-muted"><i class="bi bi-calendar-check text-warning me-1"></i>Finalizada:</span>
                                            <strong class="text-dark"><?= htmlspecialchars(substr((string)$f['fecha_finalizacion'], 0, 10)) ?></strong>
                                        </div>
                                        <?php if (!empty($f['responsable_finalizacion'])): ?>
                                        <div class="small text-muted text-truncate" title="Por: <?= htmlspecialchars($f['responsable_finalizacion']) ?>">
                                            Por: <?= htmlspecialchars($f['responsable_finalizacion']) ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($f['fecha_archivado']) && ($f['estado'] === 'Archivada' || $estadoFiltro === 'todos')): ?>
                                    <div class="ficha-meta-cierre border-secondary">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <span class="text-muted"><i class="bi bi-archive text-secondary me-1"></i>Archivada:</span>
                                            <strong class="text-secondary"><?= htmlspecialchars(substr((string)$f['fecha_archivado'], 0, 10)) ?></strong>
                                        </div>
                                        <?php if (!empty($f['responsable_archivado'])): ?>
                                        <div class="small text-muted text-truncate" title="Por: <?= htmlspecialchars($f['responsable_archivado']) ?>">
                                            Por: <?= htmlspecialchars($f['responsable_archivado']) ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Métricas e Indicadores -->
                                    <div class="ficha-metricas-row">
                                        <div class="ficha-metrica-chip" title="Total de aprendices vinculados">
                                            <i class="bi bi-people-fill text-success"></i>
                                            <span><?= (int)($f['total_aprendices'] ?? 0) ?> aprendices</span>
                                        </div>
                                        <div class="ficha-metrica-chip" title="Horarios: <?= (int)($f['horarios_programados'] ?? 0) ?> programados | <?= (int)($f['horarios_cancelados'] ?? 0) ?> cancelados">
                                            <i class="bi bi-calendar-week text-primary"></i>
                                            <span><?= (int)($f['total_horarios'] ?? 0) ?> horarios</span>
                                            <?php if ((int)($f['horarios_cancelados'] ?? 0) > 0): ?>
                                                <span class="badge bg-danger rounded-pill" style="font-size:0.65rem;" title="Cancelados">
                                                    <?= (int)$f['horarios_cancelados'] ?> canc.
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Acciones y Botones Operativos -->
                                <div class="ficha-card-actions">
                                    <div class="d-flex align-items-center gap-1">
                                        <!-- Botón Ver Historial / Bitácora de Auditoría -->
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-info ficha-action-btn btn-ver-auditoria" 
                                                title="Consultar bitácora de auditoría"
                                                data-id="<?= $f['id_ficha'] ?>"
                                                data-numero="<?= htmlspecialchars($f['numero_ficha'], ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="bi bi-journal-text me-1"></i><span class="d-none d-sm-inline">Historial</span>
                                        </button>

                                        <!-- Ver horarios en calendario (solo si activa) -->
                                        <?php if ($f['estado'] === 'Activa'): ?>
                                        <a href="<?= BASE_URL ?>/index.php?accion=horarios" 
                                           class="btn btn-sm btn-outline-primary ficha-action-btn" 
                                           title="Ver horarios en calendario">
                                            <i class="bi bi-calendar3 me-1"></i><span class="d-none d-sm-inline">Horarios</span>
                                        </a>
                                        <?php endif; ?>
                                    </div>

                                    <div class="d-flex align-items-center gap-1 ms-auto">
                                        <!-- Acciones para ficha ACTIVA -->
                                        <?php if ($f['estado'] === 'Activa' && $rolActivo === 'Administrador'): ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-secondary ficha-action-btn btn-editar-ficha" 
                                                    title="Editar Ficha"
                                                    data-id="<?= $f['id_ficha'] ?>"
                                                    data-numero="<?= htmlspecialchars($f['numero_ficha'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-programa="<?= htmlspecialchars($f['nombre_programa'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-instructor="<?= (int)($f['id_instructor'] ?? 0) ?>">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>

                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-warning ficha-action-btn btn-finalizar-ficha" 
                                                    title="Finalizar etapa lectiva de formación"
                                                    data-id="<?= $f['id_ficha'] ?>"
                                                    data-numero="<?= htmlspecialchars($f['numero_ficha'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-programa="<?= htmlspecialchars($f['nombre_programa'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-aprendices="<?= (int)($f['total_aprendices'] ?? 0) ?>"
                                                    data-horarios="<?= (int)($f['total_horarios'] ?? 0) ?>">
                                                <i class="bi bi-check2-circle text-warning"></i>
                                            </button>

                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-danger ficha-action-btn btn-eliminar-ficha" 
                                                    title="Eliminar Ficha (Solo si fue creada por error y sin registros)"
                                                    data-id="<?= $f['id_ficha'] ?>"
                                                    data-numero="<?= htmlspecialchars($f['numero_ficha'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-aprendices="<?= (int)($f['total_aprendices'] ?? 0) ?>"
                                                    data-horarios="<?= (int)($f['total_horarios'] ?? 0) ?>">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        <?php endif; ?>

                                        <!-- Acciones para ficha FINALIZADA -->
                                        <?php if ($f['estado'] === 'Finalizada' && $rolActivo === 'Administrador'): ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-secondary ficha-action-btn btn-archivar-ficha" 
                                                    title="Archivar ficha para custodia histórica"
                                                    data-id="<?= $f['id_ficha'] ?>"
                                                    data-numero="<?= htmlspecialchars($f['numero_ficha'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-programa="<?= htmlspecialchars($f['nombre_programa'], ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="bi bi-archive text-secondary me-1"></i>Archivar
                                            </button>

                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-success ficha-action-btn btn-reabrir-ficha" 
                                                    title="Reapertura administrativa excepcional"
                                                    data-id="<?= $f['id_ficha'] ?>"
                                                    data-numero="<?= htmlspecialchars($f['numero_ficha'], ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="bi bi-arrow-counterclockwise text-success"></i>
                                            </button>
                                        <?php endif; ?>

                                        <!-- Ficha ARCHIVADA -->
                                        <?php if ($f['estado'] === 'Archivada'): ?>
                                            <span class="badge bg-secondary-subtle text-secondary small py-1 px-2 border" title="Ficha en custodia histórica">
                                                <i class="bi bi-lock me-1"></i>Custodiada
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Estado vacío para búsqueda sin coincidencias -->
                    <div id="fichaSearchEmptyState" class="ficha-empty-state text-center d-none">
                        <div class="empty-icon-circle mx-auto mb-3">
                            <i class="bi bi-search fs-2 text-muted"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-1">Sin resultados de búsqueda</h5>
                        <p class="text-muted small mb-0">No se encontraron fichas que coincidan con el término ingresado. Intenta con otro número o nombre.</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODALES DEL CICLO DE VIDA DE FICHAS -->
<!-- ========================================================================= -->

<!-- 1. Modal: Crear / Editar Ficha -->
<div class="modal fade" id="modalFicha" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form id="formFicha" action="<?= BASE_URL ?>/index.php?accion=guardarFicha" method="POST">
        <input type="hidden" name="csrf_token" value="<?= Seguridad::tokenCsrf() ?>">
        <input type="hidden" name="id_ficha" id="form_id_ficha" value="0">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold" id="modalTituloFicha">
            <i class="bi bi-journal-bookmark text-success me-2"></i>Nueva Ficha
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label fw-semibold">Número de Ficha <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="numero_ficha" id="form_numero_ficha" required maxlength="20" minlength="3" pattern="[a-zA-Z0-9\-_]{3,20}" placeholder="Ej. 3064749" autocomplete="off">
                <div class="form-text">Código único de la ficha académica (3 a 20 caracteres, sin espacios ni caracteres especiales).</div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Programa de Formación <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="nombre_programa" id="form_nombre_programa" required minlength="3" maxlength="150" placeholder="Ej. ANÁLISIS Y DESARROLLO DE SOFTWARE">
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Instructor encargado (Opcional)</label>
                <select class="form-select" name="id_instructor" id="form_id_instructor">
                    <option value="0">Seleccionar instructor...</option>
                    <?php foreach ($instructores as $inst): ?>
                        <option value="<?= $inst['id_usuario'] ?>"><?= htmlspecialchars($inst['nombre_completo']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Asocia un instructor líder de referencia a esta ficha.</div>
            </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-sena" id="btnGuardarFicha">Guardar Ficha</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- 2. Modal: Finalizar Ficha -->
<div class="modal fade" id="modalFinalizarFicha" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form id="formFinalizarFicha" action="<?= BASE_URL ?>/index.php?accion=finalizarFicha" method="POST">
        <input type="hidden" name="csrf_token" value="<?= Seguridad::tokenCsrf() ?>">
        <input type="hidden" name="id_ficha" id="finalizar_id_ficha" value="0">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold text-warning">
            <i class="bi bi-check2-circle text-warning me-2"></i>Finalizar Formación Lectiva
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="alert alert-warning border-0 small mb-3">
                <i class="bi bi-info-circle-fill me-1"></i>
                Esta operación finalizará el ciclo formativo de la ficha <strong id="finalizar_numero_ficha"></strong>.
                <ul class="mb-0 mt-2 ps-3">
                    <li>Cancela automáticamente los horarios futuros a partir de la fecha de corte.</li>
                    <li>Excluye la ficha de la generación de futuras inasistencias.</li>
                    <li>Cierra las vinculaciones de los aprendices y recalcula su acceso sin destruir historiales.</li>
                    <li><strong>Conserva intactas</strong> todas las asistencias, excusas médicas y horarios pasados.</li>
                </ul>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Fecha efectiva de finalización</label>
                <input type="date" class="form-control" name="fecha_efectiva" id="finalizar_fecha_efectiva" value="<?= date('Y-m-d') ?>">
                <div class="form-text">Las sesiones posteriores a esta fecha quedarán canceladas.</div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Motivo de finalización <span class="text-danger">*</span></label>
                <textarea class="form-control" name="motivo_finalizacion" id="finalizar_motivo" rows="2" required placeholder="Ej. Culminación exitosa de la etapa lectiva institucional"></textarea>
            </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning text-dark fw-semibold" id="btnConfirmarFinalizar">Confirmar Finalización</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- 3. Modal: Archivar Ficha -->
<div class="modal fade" id="modalArchivarFicha" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form id="formArchivarFicha" action="<?= BASE_URL ?>/index.php?accion=archivarFicha" method="POST">
        <input type="hidden" name="csrf_token" value="<?= Seguridad::tokenCsrf() ?>">
        <input type="hidden" name="id_ficha" id="archivar_id_ficha" value="0">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold text-secondary">
            <i class="bi bi-archive-fill text-secondary me-2"></i>Archivar Ficha Histórica
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <p class="text-muted small">
                La ficha <strong id="archivar_numero_ficha"></strong> pasará a custodia en el archivo histórico general.
                Se excluirá de los listados y selectores operativos cotidianos, pero sus registros de asistencia y reportes
                permanecerán consultables permanentemente para auditorías.
            </p>
            <div class="mb-3">
                <label class="form-label fw-semibold">Motivo del archivado (Opcional)</label>
                <input type="text" class="form-control" name="motivo_archivado" id="archivar_motivo" placeholder="Ej. Cierre de expediente institucional anual">
            </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-secondary" id="btnConfirmarArchivar">Archivar Ficha</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- 4. Modal: Reapertura Excepcional -->
<div class="modal fade" id="modalReabrirFicha" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form id="formReabrirFicha" action="<?= BASE_URL ?>/index.php?accion=reabrirFicha" method="POST">
        <input type="hidden" name="csrf_token" value="<?= Seguridad::tokenCsrf() ?>">
        <input type="hidden" name="id_ficha" id="reabrir_id_ficha" value="0">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold text-success">
            <i class="bi bi-arrow-counterclockwise text-success me-2"></i>Reapertura Excepcional de Ficha
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="alert alert-info border-0 small mb-3">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                Esta operación reactivará la ficha <strong id="reabrir_numero_ficha"></strong> para permitir nuevas actividades.
                Requiere un motivo explícito que quedará registrado en la bitácora de auditoría.
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Motivo justificado de reapertura <span class="text-danger">*</span></label>
                <textarea class="form-control" name="motivo_reapertura" id="reabrir_motivo" rows="2" required minlength="8" placeholder="Ej. Corrección por ampliación del calendario lectivo autorizada"></textarea>
            </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success" id="btnConfirmarReabrir">Reabrir Ficha</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- 5. Modal: Bitácora de Auditoría -->
<div class="modal fade" id="modalAuditoriaFicha" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold">
          <i class="bi bi-journal-text text-info me-2"></i>Bitácora de Auditoría · Ficha <span id="audit_num_ficha"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-3" style="max-height: 480px; overflow-y: auto;">
        <div id="audit_loading" class="text-center py-4">
            <div class="spinner-border text-success" role="status"></div>
            <p class="text-muted small mt-2">Cargando bitácora de auditoría...</p>
        </div>
        <div id="audit_contenido" style="display: none;">
            <!-- Contenedor dinámico de eventos de auditoría -->
        </div>
        <div id="audit_vacio" class="text-center py-4 text-muted small" style="display: none;">
            <i class="bi bi-info-circle fs-3 d-block mb-1"></i>
            No se registran eventos de auditoría para esta ficha.
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Formulario Oculto para Eliminar Ficha con CSRF -->
<form id="formEliminarFichaOculto" action="<?= BASE_URL ?>/index.php?accion=eliminarFicha" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= Seguridad::tokenCsrf() ?>">
    <input type="hidden" name="id_ficha" id="inputEliminarFichaId" value="0">
    <input type="hidden" name="motivo_eliminacion" id="inputEliminarFichaMotivo" value="">
</form>

<?php include 'includes/scripts.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Instancias de Modales Bootstrap 5
const modalFichaBs     = new bootstrap.Modal(document.getElementById('modalFicha'));
const modalFinBs       = new bootstrap.Modal(document.getElementById('modalFinalizarFicha'));
const modalArcBs       = new bootstrap.Modal(document.getElementById('modalArchivarFicha'));
const modalReaBs       = new bootstrap.Modal(document.getElementById('modalReabrirFicha'));
const modalAuditBs     = new bootstrap.Modal(document.getElementById('modalAuditoriaFicha'));

function abrirModalCrearFicha() {
    document.getElementById('formFicha').reset();
    document.getElementById('form_id_ficha').value = '0';
    document.getElementById('modalTituloFicha').innerHTML = '<i class="bi bi-journal-bookmark text-success me-2"></i>Nueva Ficha';
    document.getElementById('btnGuardarFicha').textContent = 'Guardar Ficha';
    modalFichaBs.show();
}

$(document).ready(function() {
    // Filtrado interactivo en tiempo real de tarjetas de fichas
    const inputBuscar = document.getElementById('inputBuscarFicha');
    const btnLimpiar  = document.getElementById('btnLimpiarBusqueda');
    const contador    = document.getElementById('contadorFichasMostradas');
    const grid        = document.getElementById('contenedorFichasGrid');
    const emptySearch = document.getElementById('fichaSearchEmptyState');

    if (inputBuscar) {
        inputBuscar.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            const cards = grid ? grid.querySelectorAll('.ficha-card') : [];
            let visibles = 0;

            cards.forEach(card => {
                const num  = card.getAttribute('data-numero') || '';
                const prog = card.getAttribute('data-programa') || '';
                const inst = card.getAttribute('data-instructor') || '';

                if (query === '' || num.includes(query) || prog.includes(query) || inst.includes(query)) {
                    card.style.display = '';
                    visibles++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (contador) {
                contador.textContent = visibles;
            }

            if (emptySearch) {
                if (visibles === 0 && cards.length > 0) {
                    emptySearch.classList.remove('d-none');
                } else {
                    emptySearch.classList.add('d-none');
                }
            }

            if (btnLimpiar) {
                btnLimpiar.style.display = query.length > 0 ? 'inline-block' : 'none';
            }
        });

        if (btnLimpiar) {
            btnLimpiar.addEventListener('click', function() {
                inputBuscar.value = '';
                inputBuscar.dispatchEvent(new Event('input'));
                inputBuscar.focus();
            });
        }
    }

    // 1. Manejador para Editar Ficha
    $(document).on('click', '.btn-editar-ficha', function() {
        const id   = $(this).data('id');
        const num  = $(this).data('numero');
        const prog = $(this).data('programa');
        const inst = $(this).data('instructor');

        document.getElementById('form_id_ficha').value = id;
        document.getElementById('form_numero_ficha').value = num;
        document.getElementById('form_nombre_programa').value = prog;
        document.getElementById('form_id_instructor').value = inst || 0;

        document.getElementById('modalTituloFicha').innerHTML = '<i class="bi bi-pencil-square text-success me-2"></i>Editar Ficha';
        document.getElementById('btnGuardarFicha').textContent = 'Guardar Cambios';

        modalFichaBs.show();
    });

    // 2. Manejador para Finalizar Ficha
    $(document).on('click', '.btn-finalizar-ficha', function() {
        const id   = $(this).data('id');
        const num  = $(this).data('numero');
        const apr  = $(this).data('aprendices');
        const hor  = $(this).data('horarios');

        document.getElementById('finalizar_id_ficha').value = id;
        document.getElementById('finalizar_numero_ficha').textContent = num;
        document.getElementById('finalizar_motivo').value = 'Culminación de etapa lectiva de formación';

        modalFinBs.show();
    });

    // 3. Manejador para Archivar Ficha
    $(document).on('click', '.btn-archivar-ficha', function() {
        const id  = $(this).data('id');
        const num = $(this).data('numero');

        document.getElementById('archivar_id_ficha').value = id;
        document.getElementById('archivar_numero_ficha').textContent = num;
        document.getElementById('archivar_motivo').value = 'Archivado definitivo para custodia histórica';

        modalArcBs.show();
    });

    // 4. Manejador para Reabrir Ficha
    $(document).on('click', '.btn-reabrir-ficha', function() {
        const id  = $(this).data('id');
        const num = $(this).data('numero');

        document.getElementById('reabrir_id_ficha').value = id;
        document.getElementById('reabrir_numero_ficha').textContent = num;
        document.getElementById('reabrir_motivo').value = '';

        modalReaBs.show();
    });

    // 5. Manejador para Ver Auditoría / Bitácora
    $(document).on('click', '.btn-ver-auditoria', function() {
        const id  = $(this).data('id');
        const num = $(this).data('numero');

        document.getElementById('audit_num_ficha').textContent = num;
        $('#audit_loading').show();
        $('#audit_contenido').hide().empty();
        $('#audit_vacio').hide();

        modalAuditBs.show();

        fetch(`<?= BASE_URL ?>/index.php?accion=obtenerAuditoriaFicha&id_ficha=${id}`)
            .then(res => res.json())
            .then(data => {
                $('#audit_loading').hide();
                if (data.exito && data.eventos && data.eventos.length > 0) {
                    let html = '<div class="timeline-audit">';
                    data.eventos.forEach(ev => {
                        const badgeRes = ev.resultado === 'EXITOSO' 
                            ? '<span class="badge bg-success-subtle text-success border border-success-subtle">EXITOSO</span>'
                            : (ev.resultado === 'RECHAZADO' 
                                ? '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">RECHAZADO</span>'
                                : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">ERROR</span>');
                        
                        let detallesTexto = '';
                        if (ev.detalles) {
                            try {
                                const det = typeof ev.detalles === 'string' ? JSON.parse(ev.detalles) : ev.detalles;
                                const items = [];
                                if (det.horarios_cancelados !== undefined) items.push(`Horarios cancelados: ${det.horarios_cancelados}`);
                                if (det.cuentas_conservadas !== undefined) items.push(`Cuentas conservadas: ${det.cuentas_conservadas}`);
                                if (det.cuentas_inactivadas !== undefined) items.push(`Cuentas inactivadas: ${det.cuentas_inactivadas}`);
                                if (det.error) items.push(`Detalle: ${det.error}`);
                                if (items.length > 0) {
                                    detallesTexto = `<div class="bg-light p-2 rounded small mt-1 text-muted">${items.join(' | ')}</div>`;
                                }
                            } catch(e) {}
                        }

                        html += `
                            <div class="timeline-audit-item">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong class="text-dark">${ev.accion} ${badgeRes}</strong>
                                    <small class="text-muted">${ev.created_at}</small>
                                </div>
                                <div class="small text-muted mb-1">
                                    Responsable: <strong>${ev.usuario_nombre || 'Sistema'}</strong> 
                                    ${ev.estado_anterior ? `(${ev.estado_anterior} &rarr; ${ev.estado_nuevo})` : ''}
                                </div>
                                ${ev.motivo ? `<div class="small fst-italic text-secondary">"${ev.motivo}"</div>` : ''}
                                ${detallesTexto}
                            </div>
                        `;
                    });
                    html += '</div>';
                    $('#audit_contenido').html(html).show();
                } else {
                    $('#audit_vacio').show();
                }
            })
            .catch(() => {
                $('#audit_loading').hide();
                $('#audit_contenido').html('<div class="alert alert-danger small">No se pudo cargar la bitácora.</div>').show();
            });
    });

    // 6. Manejador para Eliminar Ficha
    $(document).on('click', '.btn-eliminar-ficha', function() {
        const id         = $(this).data('id');
        const num        = $(this).data('numero');
        const aprendices = parseInt($(this).data('aprendices'), 10) || 0;
        const horarios   = parseInt($(this).data('horarios'), 10) || 0;

        if (aprendices > 0 || horarios > 0) {
            Swal.fire({
                title: 'Eliminación no permitida',
                html: `La ficha <strong>${num}</strong> posee <strong>${aprendices} aprendices</strong> y <strong>${horarios} horarios</strong>.<br><br>Por políticas de trazabilidad y memoria académica institucional, no es posible eliminar fichas con registros asociados.<br><br>Utiliza la opción <strong>Finalizar</strong> o <strong>Archivar</strong> para gestionar su ciclo de vida.`,
                icon: 'warning',
                confirmButtonColor: '#39A900',
                confirmButtonText: 'Entendido'
            });
            return;
        }

        Swal.fire({
            title: `¿Eliminar ficha ${num}?`,
            text: 'Esta acción es irreversible y solo está autorizada para fichas vacías sin actividad académica creada por error.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#546e7a',
            confirmButtonText: 'Sí, eliminar ficha',
            cancelButtonText: 'Cancelar',
            input: 'text',
            inputPlaceholder: 'Ingresa el motivo de eliminación...',
            inputValidator: (value) => {
                if (!value || value.trim().length < 5) {
                    return 'Debes ingresar un motivo de al menos 5 caracteres.';
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('inputEliminarFichaId').value = id;
                document.getElementById('inputEliminarFichaMotivo').value = result.value.trim();
                document.getElementById('formEliminarFichaOculto').submit();
            }
        });
    });
});
</script>
</body>
</html>
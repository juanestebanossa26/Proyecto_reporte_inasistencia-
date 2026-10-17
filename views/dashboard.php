<?php
// Vista puente de compatibilidad para Dashboard
// Delega al controlador y carga la vista correspondiente según el rol del usuario (Administrador o Instructor).

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rolActual = strtolower(trim((string) ($_SESSION['rol'] ?? '')));

if (in_array($rolActual, ['instructor'], true)) {
    if (!isset($kpis)) {
        (new DashboardControlador())->mostrarInstructor();
        return;
    }
    include __DIR__ . '/dashboard_instructor.php';
} else {
    if (!isset($kpis)) {
        (new DashboardControlador())->mostrarAdmin();
        return;
    }
    include __DIR__ . '/dashboard_admin.php';
}
<?php
/**
 * includes/head.php
 * Se incluye dentro de <head> de cada vista interna.
 * Requiere que la vista defina $tituloPagina antes de incluirlo.
 */
if (!isset($tituloPagina)) {
    $tituloPagina = 'Sistema de Control de Ingreso de Aprendices';
}
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($tituloPagina) ?> · Control de Ingreso</title>

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Tipografías -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<!-- Estilos propios -->
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

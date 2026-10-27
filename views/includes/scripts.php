<!-- Bootstrap 5 JS diferido para rendimiento móvil -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
<!-- Script propio: manejo del sidebar colapsable -->
<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= file_exists(BASE_PATH . '/assets/js/main.js') ? filemtime(BASE_PATH . '/assets/js/main.js') : '1.0' ?>" defer></script>

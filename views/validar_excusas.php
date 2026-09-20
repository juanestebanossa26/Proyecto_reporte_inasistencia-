<?php


$rolActivo     = $_SESSION['rol']?? 'Instructor';
$nombreUsuario =$_SESSION['nombre'] ?? '';
$paginaActiva  = 'validar_excusas';
$tituloPagina  = 'Validar excusas médicas';



?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include 'includes/head.php'; ?>
<!-- CSS de DataTables -->

 <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="app-shell">
    <?php include 'includes/sidebar.php'; ?>
    <div class="app-main">
        <?php include 'includes/topbar.php'; ?>
        <main class="app-content">
            <div class="page-header">
                <div class="eyebrow">Control</div>
                <h1 class="h4 fw-bold mb-0">Excusas médicas</h1>
                <p class="text-suave mb-0">Aprueba o rechaza los soportes enviados por los aprendices.</p>
            </div>

            <ul class="nav nav-pills mb-3">
             
                <li class="nav-item"><a class="nav-link active" href="#" style="background:var(--sena-verde);">Pendientes <span class="badge bg-white text-sena ms-1"></span></a></li>
               
            </ul>

            <div class="card p-3">
                <div class="table-responsive">
                    <!-- Se agregó el ID "tablaExcusas" a la tabla -->
                    <table id="tablaExcusas" class="table table-app align-middle mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th>Aprendiz</th>
                                <th>Ficha</th>
                                <th>Fecha</th>
                                <th>Motivo</th>
                                <th>Soporte</th>
                                <th>Estado</th>
                                <!-- Se agrega una clase para alinear correctamente -->
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                      
                    
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/scripts.php'; ?>
<!-- JS de DataTables (Asegúrate de que no tengan comentarios HTML a los lados) -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#tablaExcusas').DataTable({
        // Configuración de idioma español
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
        },
        // Evitar que DataTables ordene automáticamente la primera columna al inicio
        order: [],
        pageLength: 10,
        responsive: true,
        // Configuración para desactivar el filtrado/ordenamiento en la última columna de "Acciones" (columna índice 6)
        columnDefs: [
            { targets: 6, orderable: false, searchable: false }
        ]
    });
});
</script>
</body>
</html>
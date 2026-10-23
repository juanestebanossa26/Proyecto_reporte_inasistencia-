<?php
// scripts/ejecutar_migracion_ciclo_vida.php
require_once __DIR__ . '/../config/conexion.php';

$pdo = Conexion::obtener();
echo "Iniciando ejecución de migración de ciclo de vida...\n";

function columnaExiste(PDO $pdo, string $tabla, string $columna): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tabla AND COLUMN_NAME = :col
    ");
    $stmt->execute([':db' => DB_NAME, ':tabla' => $tabla, ':col' => $columna]);
    return (int)$stmt->fetchColumn() > 0;
}

function tablaExiste(PDO $pdo, string $tabla): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tabla
    ");
    $stmt->execute([':db' => DB_NAME, ':tabla' => $tabla]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    // 1. Columnas en fichas
    if (!columnaExiste($pdo, 'fichas', 'estado')) {
        echo "Agregando columnas de estado a 'fichas'...\n";
        $pdo->exec("
            ALTER TABLE fichas
                ADD COLUMN estado ENUM('Activa', 'Finalizada', 'Archivada') NOT NULL DEFAULT 'Activa' AFTER nombre_programa,
                ADD COLUMN fecha_finalizacion DATETIME NULL DEFAULT NULL AFTER estado,
                ADD COLUMN id_usuario_finalizacion INT(11) NULL DEFAULT NULL AFTER fecha_finalizacion,
                ADD COLUMN motivo_finalizacion TEXT NULL DEFAULT NULL AFTER id_usuario_finalizacion,
                ADD COLUMN fecha_archivado DATETIME NULL DEFAULT NULL AFTER motivo_finalizacion,
                ADD COLUMN id_usuario_archivado INT(11) NULL DEFAULT NULL AFTER fecha_archivado,
                ADD COLUMN motivo_archivado TEXT NULL DEFAULT NULL AFTER id_usuario_archivado
        ");
        $pdo->exec("
            ALTER TABLE fichas
                ADD CONSTRAINT fk_fichas_usuario_fin FOREIGN KEY (id_usuario_finalizacion) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
                ADD CONSTRAINT fk_fichas_usuario_arc FOREIGN KEY (id_usuario_archivado) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
                ADD INDEX idx_fichas_estado (estado)
        ");
        echo "  -> Columnas en 'fichas' agregadas exitosamente.\n";
    } else {
        echo "  -> Las columnas en 'fichas' ya existen. Omitiendo.\n";
    }

    // 2. Columnas en horarios
    if (!columnaExiste($pdo, 'horarios', 'estado')) {
        echo "Agregando columnas de estado a 'horarios'...\n";
        $pdo->exec("
            ALTER TABLE horarios
                ADD COLUMN estado ENUM('Programado', 'Cancelado') NOT NULL DEFAULT 'Programado' AFTER dia_semana,
                ADD COLUMN fecha_cancelacion DATETIME NULL DEFAULT NULL AFTER estado,
                ADD COLUMN motivo_cancelacion VARCHAR(255) NULL DEFAULT NULL AFTER fecha_cancelacion
        ");
        $pdo->exec("
            ALTER TABLE horarios
                ADD INDEX idx_horarios_estado_fecha (estado, dia_semana),
                ADD INDEX idx_horarios_ficha_estado (id_ficha, estado)
        ");
        echo "  -> Columnas en 'horarios' agregadas exitosamente.\n";
    } else {
        echo "  -> Las columnas en 'horarios' ya existen. Omitiendo.\n";
    }

    // 3. Tabla aprendiz_ficha
    if (!tablaExiste($pdo, 'aprendiz_ficha')) {
        echo "Creando tabla 'aprendiz_ficha'...\n";
        $pdo->exec("
            CREATE TABLE aprendiz_ficha (
                id_aprendiz_ficha INT(11) NOT NULL AUTO_INCREMENT,
                id_aprendiz BIGINT(20) NOT NULL,
                id_ficha INT(11) NOT NULL,
                estado ENUM('Activo', 'Finalizado', 'Retirado') NOT NULL DEFAULT 'Activo',
                fecha_vinculacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                fecha_finalizacion DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (id_aprendiz_ficha),
                UNIQUE KEY uq_aprendiz_ficha_activa (id_aprendiz, id_ficha),
                CONSTRAINT fk_af_aprendiz FOREIGN KEY (id_aprendiz) REFERENCES aprendices(id_aprendiz) ON DELETE CASCADE,
                CONSTRAINT fk_af_ficha FOREIGN KEY (id_ficha) REFERENCES fichas(id_ficha) ON DELETE CASCADE,
                INDEX idx_af_estado (estado)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            INSERT IGNORE INTO aprendiz_ficha (id_aprendiz, id_ficha, estado, fecha_vinculacion)
            SELECT id_aprendiz, id_ficha, 'Activo', NOW()
            FROM aprendices
            WHERE id_ficha IS NOT NULL
        ");
        echo "  -> Tabla 'aprendiz_ficha' creada y poblada.\n";
    } else {
        echo "  -> La tabla 'aprendiz_ficha' ya existe. Omitiendo.\n";
    }

    // 4. Tabla auditoria_fichas
    if (!tablaExiste($pdo, 'auditoria_fichas')) {
        echo "Creando tabla 'auditoria_fichas'...\n";
        $pdo->exec("
            CREATE TABLE auditoria_fichas (
                id_auditoria INT(11) NOT NULL AUTO_INCREMENT,
                id_ficha INT(11) NULL DEFAULT NULL,
                numero_ficha VARCHAR(45) NULL DEFAULT NULL,
                id_usuario INT(11) NULL DEFAULT NULL,
                accion VARCHAR(60) NOT NULL,
                estado_anterior VARCHAR(30) NULL DEFAULT NULL,
                estado_nuevo VARCHAR(30) NULL DEFAULT NULL,
                motivo TEXT NULL DEFAULT NULL,
                resultado ENUM('EXITOSO', 'RECHAZADO', 'ERROR') NOT NULL DEFAULT 'EXITOSO',
                detalles JSON NULL DEFAULT NULL,
                correlacion_id VARCHAR(64) NULL DEFAULT NULL,
                ip_origen VARCHAR(45) NULL DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id_auditoria),
                INDEX idx_auditoria_ficha (id_ficha),
                INDEX idx_auditoria_fecha (created_at),
                INDEX idx_auditoria_accion (accion),
                CONSTRAINT fk_auditoria_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "  -> Tabla 'auditoria_fichas' creada exitosamente.\n";
    } else {
        echo "  -> La tabla 'auditoria_fichas' ya existe. Omitiendo.\n";
    }

    echo "\n=== MIGRACIÓN COMPLETADA EXITOSAMENTE SIN ERRORES ===\n";

} catch (\Throwable $e) {
    echo "\n[ERROR CRÍTICO EN MIGRACIÓN]: " . $e->getMessage() . "\n";
    exit(1);
}

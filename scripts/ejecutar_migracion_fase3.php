<?php
/**
 * scripts/ejecutar_migracion_fase3.php
 * Migración idempotente de Fase 3 (Integridad de festivos e índice compuesto de horarios).
 * Uso exclusivo por CLI: php scripts/ejecutar_migracion_fase3.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo '403 Forbidden - Este script solo puede ejecutarse mediante CLI.';
    exit(1);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexion.php';

try {
    $pdo = Conexion::obtener();
    $dbName = DB_NAME;

    echo "[Fase 3] Verificando estructura de festivos y horarios en '{$dbName}'...\n";

    // Verificar tablas festivos vs dias_festivos
    $tablas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $existeFestivos = in_array('festivos', $tablas, true);
    $existeDiasFestivos = in_array('dias_festivos', $tablas, true);

    echo " - Tabla 'festivos': " . ($existeFestivos ? 'EXISTE' : 'NO existe') . "\n";
    echo " - Tabla 'dias_festivos': " . ($existeDiasFestivos ? 'EXISTE' : 'NO existe') . "\n";

    if (!$existeDiasFestivos) {
        throw new RuntimeException("No existe la tabla 'dias_festivos' en la base de datos.");
    }

    // Verificar FKs entrantes hacia dias_festivos
    $stmtFk = $pdo->prepare("
        SELECT TABLE_NAME, CONSTRAINT_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE REFERENCED_TABLE_SCHEMA = :db
          AND REFERENCED_TABLE_NAME = 'dias_festivos'
    ");
    $stmtFk->execute([':db' => $dbName]);
    $fksEntrantes = $stmtFk->fetchAll(PDO::FETCH_ASSOC);
    echo " - Claves foráneas entrantes hacia dias_festivos: " . count($fksEntrantes) . "\n";

    // Limpiar duplicados exactos en dias_festivos conservando el id_festivo mínimo por grupo
    $dups = $pdo->query("
        SELECT fecha,
               COALESCE(id_ficha, 0) AS ficha_norm,
               COALESCE(hora_inicio, '00:00:00') AS hi_norm,
               COALESCE(hora_fin, '23:59:59') AS hf_norm,
               MIN(id_festivo) AS id_conservar,
               COUNT(*) AS total
        FROM dias_festivos
        GROUP BY fecha, ficha_norm, hi_norm, hf_norm
        HAVING total > 1
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo " - Grupos duplicados detectados en dias_festivos: " . count($dups) . "\n";
    if (!empty($dups)) {
        $pdo->beginTransaction();
        try {
            $delStmt = $pdo->prepare("
                DELETE FROM dias_festivos
                WHERE fecha = :fecha
                  AND COALESCE(id_ficha, 0) = :ficha_norm
                  AND COALESCE(hora_inicio, '00:00:00') = :hi_norm
                  AND COALESCE(hora_fin, '23:59:59') = :hf_norm
                  AND id_festivo <> :id_conservar
            ");
            $eliminados = 0;
            foreach ($dups as $g) {
                $delStmt->execute([
                    ':fecha'        => $g['fecha'],
                    ':ficha_norm'   => (int) $g['ficha_norm'],
                    ':hi_norm'      => $g['hi_norm'],
                    ':hf_norm'      => $g['hf_norm'],
                    ':id_conservar' => (int) $g['id_conservar'],
                ]);
                $eliminados += $delStmt->rowCount();
            }
            $pdo->commit();
            echo " - Registros duplicados eliminados de dias_festivos: {$eliminados}\n";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    // Asegurar columnas virtuales normalizadas y restricción UNIQUE uq_festivo_fecha_ficha_franja
    $colsStmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'dias_festivos'
    ");
    $colsStmt->execute([':db' => $dbName]);
    $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('id_ficha_normalizada', $cols, true)) {
        $pdo->exec("ALTER TABLE dias_festivos ADD COLUMN id_ficha_normalizada INT(11) GENERATED ALWAYS AS (COALESCE(id_ficha, 0)) VIRTUAL");
        echo " - Agregada columna virtual id_ficha_normalizada.\n";
    }
    if (!in_array('hora_inicio_normalizada', $cols, true)) {
        $pdo->exec("ALTER TABLE dias_festivos ADD COLUMN hora_inicio_normalizada TIME GENERATED ALWAYS AS (COALESCE(hora_inicio, '00:00:00')) VIRTUAL");
        echo " - Agregada columna virtual hora_inicio_normalizada.\n";
    }
    if (!in_array('hora_fin_normalizada', $cols, true)) {
        $pdo->exec("ALTER TABLE dias_festivos ADD COLUMN hora_fin_normalizada TIME GENERATED ALWAYS AS (COALESCE(hora_fin, '23:59:59')) VIRTUAL");
        echo " - Agregada columna virtual hora_fin_normalizada.\n";
    }

    $idxFestivos = $pdo->query("SHOW INDEX FROM dias_festivos")->fetchAll(PDO::FETCH_ASSOC);
    $nombresIdxFest = array_unique(array_column($idxFestivos, 'Key_name'));

    foreach (['uq_festivo_fecha_ficha_horario', 'uq_festivo_fecha_ficha_central'] as $idxObsoleto) {
        if (in_array($idxObsoleto, $nombresIdxFest, true)) {
            $pdo->exec("ALTER TABLE dias_festivos DROP INDEX `{$idxObsoleto}`");
            echo " - Eliminado índice obsoleto {$idxObsoleto}.\n";
        }
    }

    if (!in_array('uq_festivo_fecha_ficha_franja', $nombresIdxFest, true)) {
        $pdo->exec("
            ALTER TABLE dias_festivos
            ADD UNIQUE KEY uq_festivo_fecha_ficha_franja (fecha, id_ficha_normalizada, hora_inicio_normalizada, hora_fin_normalizada)
        ");
        echo " - Creado índice único uq_festivo_fecha_ficha_franja.\n";
    } else {
        echo " - Índice único uq_festivo_fecha_ficha_franja ya existe.\n";
    }

    //  Normalizar AUTO_INCREMENT de dias_festivos (opcional seguro: MAX(id_festivo) + 1)
    $maxId = (int) $pdo->query("SELECT COALESCE(MAX(id_festivo), 0) FROM dias_festivos")->fetchColumn();
    $nextAuto = $maxId + 1;
    $pdo->exec("ALTER TABLE dias_festivos AUTO_INCREMENT = {$nextAuto}");
    echo " - AUTO_INCREMENT de dias_festivos verificado/normalizado a >= {$nextAuto}.\n";

    // Índice compuesto en horarios para optimizar listados por ficha/estado/fecha
    $idxHorarios = $pdo->query("SHOW INDEX FROM horarios")->fetchAll(PDO::FETCH_ASSOC);
    $nombresIdxHor = array_unique(array_column($idxHorarios, 'Key_name'));

    if (!in_array('idx_horarios_ficha_estado_fecha', $nombresIdxHor, true)) {
        $pdo->exec("
            ALTER TABLE horarios
            ADD INDEX idx_horarios_ficha_estado_fecha (id_ficha, estado, dia_semana, hora_entrada_programada)
        ");
        echo " - Creado índice compuesto idx_horarios_ficha_estado_fecha en horarios.\n";
    } else {
        echo " - Índice compuesto idx_horarios_ficha_estado_fecha ya existe en horarios.\n";
    }

    echo "[Fase 3] Migración completada sin errores.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "[Fase 3] Error: " . $e->getMessage() . "\n");
    exit(1);
}


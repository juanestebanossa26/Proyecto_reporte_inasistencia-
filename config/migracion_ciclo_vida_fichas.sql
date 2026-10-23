-- =============================================================================
-- MIGRACIÓN INTEGRAL: CICLO DE VIDA DE FICHAS, HORARIOS Y AUDITORÍA
-- Sistema de Control de Ingreso y Asistencia RFID · SENA
-- Base de Datos: control_asistencia
-- Compatible con MariaDB 10.4+ / MySQL 8.0+
-- =============================================================================

-- 1. MODIFICACIONES EN TABLA FICHAS
-- Incorpora estados de negocio (Activa, Finalizada, Archivada) y metadatos de cierre
ALTER TABLE fichas
    ADD COLUMN estado ENUM('Activa', 'Finalizada', 'Archivada') NOT NULL DEFAULT 'Activa' AFTER nombre_programa,
    ADD COLUMN fecha_finalizacion DATETIME NULL DEFAULT NULL AFTER estado,
    ADD COLUMN id_usuario_finalizacion INT(11) NULL DEFAULT NULL AFTER fecha_finalizacion,
    ADD COLUMN motivo_finalizacion TEXT NULL DEFAULT NULL AFTER id_usuario_finalizacion,
    ADD COLUMN fecha_archivado DATETIME NULL DEFAULT NULL AFTER motivo_finalizacion,
    ADD COLUMN id_usuario_archivado INT(11) NULL DEFAULT NULL AFTER fecha_archivado,
    ADD COLUMN motivo_archivado TEXT NULL DEFAULT NULL AFTER id_usuario_archivado;

-- Claves foráneas y optimización para fichas
ALTER TABLE fichas
    ADD CONSTRAINT fk_fichas_usuario_fin FOREIGN KEY (id_usuario_finalizacion) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    ADD CONSTRAINT fk_fichas_usuario_arc FOREIGN KEY (id_usuario_archivado) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    ADD INDEX idx_fichas_estado (estado);

-- 2. MODIFICACIONES EN TABLA HORARIOS
-- Distingue estado operativo (Programado vs Cancelado) sin alterar condición temporal histórica
ALTER TABLE horarios
    ADD COLUMN estado ENUM('Programado', 'Cancelado') NOT NULL DEFAULT 'Programado' AFTER dia_semana,
    ADD COLUMN fecha_cancelacion DATETIME NULL DEFAULT NULL AFTER estado,
    ADD COLUMN motivo_cancelacion VARCHAR(255) NULL DEFAULT NULL AFTER fecha_cancelacion;

ALTER TABLE horarios
    ADD INDEX idx_horarios_estado_fecha (estado, dia_semana),
    ADD INDEX idx_horarios_ficha_estado (id_ficha, estado);

-- 3. TABLA DE VINCULACIÓN HISTÓRICA DE APRENDICES (aprendiz_ficha)
-- Permite separar la identidad del aprendiz de su pertenencia temporal a una ficha
CREATE TABLE IF NOT EXISTS aprendiz_ficha (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Poblar inicialmente aprendiz_ficha con los aprendices actuales vinculados a sus fichas
INSERT IGNORE INTO aprendiz_ficha (id_aprendiz, id_ficha, estado, fecha_vinculacion)
SELECT id_aprendiz, id_ficha, 'Activo', NOW()
FROM aprendices
WHERE id_ficha IS NOT NULL;

-- 4. TABLA DE BITÁCORA Y AUDITORÍA DE FICHAS (auditoria_fichas)
-- Registra eventos críticos con trazabilidad institucional completa
CREATE TABLE IF NOT EXISTS auditoria_fichas (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

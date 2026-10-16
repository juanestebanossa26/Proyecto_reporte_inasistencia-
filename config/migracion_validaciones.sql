-- =============================================================================
-- Migración de Validaciones y Prevención de Duplicados
-- Base de Datos: control_asistencia
-- Sistema de Control de Ingreso de Aprendices · SENA
-- =============================================================================

-- 1. Restricción UNIQUE en fichas (numero_ficha)
-- Impide crear fichas duplicadas con el mismo número académico
ALTER TABLE fichas 
  ADD UNIQUE KEY uq_fichas_numero_ficha (numero_ficha);

-- 2. Restricción UNIQUE en usuarios (usuario / documento)
-- Protege que no existan dos aprendices o usuarios con el mismo documento
ALTER TABLE usuarios 
  ADD UNIQUE KEY uq_usuarios_usuario (usuario);

-- 3. Modificación en aprendices (tag_rfid)
-- Permite valor NULL para aprendices que aún no tengan un carné físico asignado,
-- sin entrar en conflicto con la restricción UNIQUE existente de MySQL.
ALTER TABLE aprendices 
  MODIFY COLUMN tag_rfid VARCHAR(50) NULL DEFAULT NULL;

-- 4. Restricción UNIQUE en horarios (bloque académico único por ficha)
-- Impide registrar horarios idénticos simultáneos para la misma ficha
ALTER TABLE horarios 
  ADD UNIQUE KEY uq_horarios_bloque (id_ficha, dia_semana, hora_entrada_programada, hora_salida_programada);

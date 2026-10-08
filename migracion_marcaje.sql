-- Necesario para que un marcaje sepa a qué bloque de horario pertenece
-- (sin esto, no se puede distinguir el bloque de la mañana del de la tarde).
ALTER TABLE asistencias
  ADD COLUMN id_horario INT NULL AFTER id_aprendiz,
  ADD CONSTRAINT fk_asistencias_horario
      FOREIGN KEY (id_horario) REFERENCES horarios(id_horario);

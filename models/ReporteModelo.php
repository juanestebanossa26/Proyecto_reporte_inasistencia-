<?php
// Acceso a datos para los reportes de asistencia.
// La misma consulta se usaba, duplicada, en la vista de reportes, en el
// generador de PDF y en el generador de Excel; aquí vive en un solo lugar.

class ReporteModelo
{
    public function listarFichas(): array
    {
        $sql = "SELECT id_ficha, numero_ficha, nombre_programa FROM fichas ORDER BY numero_ficha ASC";

        return Conexion::obtener()->query($sql)->fetchAll();
    }

    // Normaliza los filtros de rango de fechas (día / semana / personalizado)
    // igual que hacía cada archivo por su cuenta en el proyecto original.
    public static function normalizarFiltros(string $rango, string $desde, string $hasta, string $fichaId): array
    {
        if ($rango === 'dia') {
            $desde = $desde !== '' ? $desde : date('Y-m-d');
            $hasta = $desde;
        } elseif ($rango === 'semana') {
            $desde = date('Y-m-d', strtotime('monday this week'));
            $hasta = date('Y-m-d', strtotime('sunday this week'));
        }

        return ['desde' => $desde, 'hasta' => $hasta, 'ficha_id' => $fichaId];
    }

    private function construirWhere(array $filtros): array
    {
        $whereSql = ' WHERE 1=1 ';
        $params = [];

        if (!empty($filtros['desde'])) {
            $whereSql .= ' AND (asistencias.fecha >= :desde OR asistencias.fecha IS NULL) ';
            $params[':desde'] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $whereSql .= ' AND (asistencias.fecha <= :hasta OR asistencias.fecha IS NULL) ';
            $params[':hasta'] = $filtros['hasta'];
        }
        if (!empty($filtros['ficha_id'])) {
            $whereSql .= ' AND fichas.id_ficha = :ficha_id ';
            $params[':ficha_id'] = $filtros['ficha_id'];
        }

        return [$whereSql, $params];
    }

    // Detalle por aprendiz: retardos, minutos de retardo e inasistencias
    // (excluyendo las que ya tienen excusa médica aprobada).
    public function obtenerDetalle(array $filtros): array
    {
        [$whereSql, $params] = $this->construirWhere($filtros);

        $sql = "SELECT
                    u_aprendiz.nombre_completo,
                    u_aprendiz.apellido,
                    fichas.numero_ficha,
                    fichas.nombre_programa,
                    u_instructor.nombre_completo AS nombre_instructor,
                    u_instructor.apellido AS apellido_instructor,
                    SUM(asistencias.estado = 'Retardo') AS retardos,
                    COALESCE(SUM(asistencias.tiempo_retraso), 0) AS min_retardo,
                    SUM(asistencias.estado = 'Inasistencia' AND (excusas_medicas.estado_excusa IS NULL OR excusas_medicas.estado_excusa != 'Aprobada')) AS inasistencias
                FROM aprendices
                JOIN usuarios u_aprendiz ON aprendices.id_usuario = u_aprendiz.id_usuario
                JOIN fichas ON aprendices.id_ficha = fichas.id_ficha
                LEFT JOIN usuarios u_instructor ON fichas.id_instructor = u_instructor.id_usuario
                LEFT JOIN asistencias ON asistencias.id_aprendiz = aprendices.id_aprendiz
                LEFT JOIN excusas_medicas ON excusas_medicas.id_asistencia = asistencias.id_asistencia
                $whereSql
                GROUP BY aprendices.id_aprendiz, u_aprendiz.nombre_completo, u_aprendiz.apellido,
                         fichas.numero_ficha, fichas.nombre_programa, u_instructor.nombre_completo, u_instructor.apellido";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    // Tarjetas KPI del encabezado de la vista de reportes.
    public function obtenerContadores(array $filtros): array
    {
        [$whereSql, $params] = $this->construirWhere($filtros);

        $sql = "SELECT
                    COUNT(DISTINCT aprendices.id_aprendiz) AS aprendices_en_rango,
                    COUNT(NULLIF(asistencias.estado = 'Retardo', 0)) AS total_retardos,
                    COUNT(NULLIF(asistencias.estado = 'Inasistencia' AND (excusas_medicas.estado_excusa IS NULL OR excusas_medicas.estado_excusa != 'Aprobada'), 0)) AS total_inasistencias,
                    COALESCE(SUM(asistencias.tiempo_retraso), 0) AS minutos_retardo
                FROM aprendices
                JOIN fichas ON aprendices.id_ficha = fichas.id_ficha
                LEFT JOIN asistencias ON asistencias.id_aprendiz = aprendices.id_aprendiz
                LEFT JOIN excusas_medicas ON excusas_medicas.id_asistencia = asistencias.id_asistencia
                $whereSql";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() ?: [];
    }
}

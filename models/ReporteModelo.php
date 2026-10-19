<?php
// Acceso a datos para los reportes de asistencia.
// La relación ficha-instructor es muchos-a-muchos (tabla
// fichas_has_usuarios: una ficha puede tener varios instructores), no una
// columna fichas.id_instructor como se asumía antes.

class ReporteModelo
{
    // Área del usuario autenticado (instructor o administrador). Se usa
    // para acotar qué fichas puede ver un Administrador.
    public function obtenerAreaUsuario(int $idUsuario): ?string
    {
        $sql = "SELECT area FROM usuarios WHERE id_usuario = :id";
        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id', $idUsuario, PDO::PARAM_INT);
        $stmt->execute();

        $area = $stmt->fetchColumn();
        return $area !== false ? $area : null;
    }

    // Fichas visibles para el usuario actual, según su rol. Se usa tanto
    // para el selector de fichas del formulario como base de los reportes.
    public function listarFichas(int $idUsuarioActual, string $rolActual): array
    {
        [$whereSql, $params] = $this->construirWhereAcceso($idUsuarioActual, $rolActual);

        $sql = "SELECT DISTINCT fichas.id_ficha, fichas.numero_ficha, fichas.nombre_programa
                FROM fichas
                $whereSql
                ORDER BY fichas.numero_ficha ASC";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    // Normaliza los filtros de rango de fechas (día / semana / personalizado)
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

    // Condición de acceso según el rol de quien pide el reporte. Vive en
    // un solo lugar y la reutilizan listarFichas(), obtenerDetalle() y
    // obtenerContadores(), para que el mismo criterio de alcance nunca
    // quede desalineado entre las tres consultas.
    private function construirWhereAcceso(int $idUsuarioActual, string $rolActual): array
    {
        $rol = strtolower(trim($rolActual));

        if (in_array($rol, ['instructor', 'docente', 'profesor'], true)) {
            return [
                ' WHERE (fichas.id_ficha IN (
                     SELECT fhu.fichas_id_ficha
                     FROM fichas_has_usuarios fhu
                     WHERE fhu.id_instructor = :id_usuario_actual
                 ) OR fichas.id_ficha IN (
                     SELECT h.id_ficha
                     FROM horarios h
                     WHERE h.id_instructor = :id_usuario_horario
                 )) ',
                [
                    ':id_usuario_actual'  => $idUsuarioActual,
                    ':id_usuario_horario' => $idUsuarioActual,
                ],
            ];
        }

        if (in_array($rol, ['administrador', 'admin'], true)) {
            // El administrador puede generar reportes de cualquier ficha sin importar el área
            return [' WHERE 1=1 ', []];
        }

        // Cualquier otro rol: por seguridad, no ve fichas de nadie.
        return [' WHERE 1=0 ', []];
    }

    private function construirWhere(array $filtros, int $idUsuarioActual, string $rolActual): array
    {
        [$whereSql, $params] = $this->construirWhereAcceso($idUsuarioActual, $rolActual);

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
    // (excluyendo las que ya tienen excusa médica aprobada), acotado a las
    // fichas que el usuario actual puede ver. Como una ficha puede tener
    // varios instructores, sus nombres se combinan con GROUP_CONCAT.
 public function obtenerDetalle(array $filtros, int $idUsuarioActual, string $rolActual): array
{
    [$whereSql, $params] = $this->construirWhere($filtros, $idUsuarioActual, $rolActual);

    $sql = "SELECT
                u_aprendiz.nombre_completo,
                fichas.numero_ficha,
                fichas.nombre_programa,
                (
                    SELECT GROUP_CONCAT(DISTINCT ui.nombre_completo SEPARATOR ', ')
                    FROM fichas_has_usuarios fhu
                    JOIN usuarios ui ON ui.id_usuario = fhu.id_instructor
                    WHERE fhu.fichas_id_ficha = fichas.id_ficha
                ) AS nombre_instructor,
                SUM(asistencias.estado = 'Retardo') AS retardos,
                COALESCE(SUM(asistencias.tiempo_retraso), 0) AS min_retardo,
                SUM(asistencias.estado = 'Inasistencia') AS inasistencias
            FROM aprendices
            JOIN usuarios u_aprendiz ON aprendices.id_usuario = u_aprendiz.id_usuario
            JOIN fichas ON aprendices.id_ficha = fichas.id_ficha
            LEFT JOIN asistencias ON asistencias.id_aprendiz = aprendices.id_aprendiz
            LEFT JOIN excusas_medicas ON excusas_medicas.id_asistencia = asistencias.id_asistencia
            $whereSql
            GROUP BY aprendices.id_aprendiz, u_aprendiz.nombre_completo,
                     fichas.id_ficha, fichas.numero_ficha, fichas.nombre_programa";

    $stmt = Conexion::obtener()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

    // Tarjetas KPI del encabezado de la vista de reportes, con el mismo
    // alcance por rol que obtenerDetalle().
    public function obtenerContadores(array $filtros, int $idUsuarioActual, string $rolActual): array
    {
        [$whereSql, $params] = $this->construirWhere($filtros, $idUsuarioActual, $rolActual);

        $sql = "SELECT
                    COUNT(DISTINCT aprendices.id_aprendiz) AS aprendices_en_rango,
                    COUNT(NULLIF(asistencias.estado = 'Retardo', 0)) AS total_retardos,
                    COUNT(NULLIF(asistencias.estado = 'Inasistencia' , 0)) AS total_inasistencias,
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
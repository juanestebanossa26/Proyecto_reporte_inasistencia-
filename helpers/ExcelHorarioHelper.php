<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExcelHorarioHelper
{
    private HorarioModelo $modelo;
    private array $fichasCache = [];
    private array $instructoresCache = [];
    private ?string $ultimoErrorEstructura = null;

    public function __construct(HorarioModelo $modelo)
    {
        $this->modelo = $modelo;
    }

    /**
     * Valida el archivo subido en el formulario.
     */
    public function validarArchivo(array $archivo): array
    {
        if (!isset($archivo['error']) || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
            return ['valido' => false, 'error' => 'No seleccionaste ningún archivo Excel.'];
        }

        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            return ['valido' => false, 'error' => 'Ocurrió un error al cargar el archivo en el servidor.'];
        }

        $nombreOriginal = $archivo['name'] ?? '';
        $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls'], true)) {
            return ['valido' => false, 'error' => 'Formato no válido. Solo se permiten archivos Excel (.xlsx, .xls).'];
        }

        $rutaTemp = $archivo['tmp_name'] ?? '';
        if ((PHP_SAPI !== 'cli' && !is_uploaded_file($rutaTemp)) || !file_exists($rutaTemp)) {
            return ['valido' => false, 'error' => 'El archivo recibido no es una carga válida.'];
        }

        // Validación de archivo vacío (0 bytes)
        $tamano = (int) ($archivo['size'] ?? 0);
        if ($tamano === 0 || filesize($rutaTemp) === 0) {
            return ['valido' => false, 'error' => 'El archivo seleccionado está vacío (0 bytes). Selecciona un archivo con datos.'];
        }

        // Validación de tamaño máximo (15MB)
        if ($tamano > 15 * 1024 * 1024) {
            return ['valido' => false, 'error' => 'El archivo supera el tamaño máximo permitido (15MB).'];
        }

        // Validación de tipo MIME real en el servidor
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $rutaTemp);
        finfo_close($finfo);

        $mimesPermitidos = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'application/zip',
            'application/x-zip-compressed',
            'application/octet-stream'
        ];

        if (!in_array($mime, $mimesPermitidos, true)) {
            return ['valido' => false, 'error' => 'Formato no válido. El contenido del archivo no corresponde a una hoja de cálculo Excel.'];
        }

        // Comprobar que el archivo no esté corrupto o protegido verificando lectura con PhpSpreadsheet
        try {
            $reader = IOFactory::createReaderForFile($rutaTemp);
            $reader->setReadDataOnly(true);
        } catch (\Throwable $e) {
            return ['valido' => false, 'error' => 'El archivo Excel está dañado, corrupto o protegido de una forma que impide su lectura.'];
        }

        return ['valido' => true, 'error' => null];
    }

    /**
     * Procesa el archivo Excel y registra los datos en la base de datos.
     */
    public function importar(string $rutaArchivo): array
    {
        $this->ultimoErrorEstructura = null;

        $resultado = [
            'exito'                => false,
            'mensaje'              => '',
            'total_procesados'     => 0,
            'guardados'            => 0,
            'duplicados'           => 0,
            'fichas_creadas'       => 0,
            'instructores_creados' => 0,
            'errores'              => [],
            'detalles'             => []
        ];

        try {
            $reader = IOFactory::createReaderForFile($rutaArchivo);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($rutaArchivo);
            $sheet = $spreadsheet->getActiveSheet();
        } catch (\Throwable $e) {
            $resultado['mensaje'] = 'El archivo Excel no se pudo leer. Puede estar dañado, corrupto o protegido: ' . $e->getMessage();
            return $resultado;
        }

        // Comprobar que el archivo no esté vacío de filas
        $maxRow = $sheet->getHighestRow();
        if ($maxRow < 2) {
            $resultado['mensaje'] = 'El archivo seleccionado no contiene información para procesar.';
            return $resultado;
        }

        // 1. Extraer los registros estructurados (soporta formato matriz SENA y formato tabular)
        $registros = $this->extraerRegistros($sheet);

        if (empty($registros)) {
            $resultado['mensaje'] = $this->ultimoErrorEstructura 
                ?? 'El archivo no tiene las columnas necesarias para realizar la importación. Se requieren al menos: Número de ficha, Fecha, Hora de entrada y Hora de salida (o el formato oficial de Matriz de Horarios SENA).';
            return $resultado;
        }

        $resultado['total_procesados'] = count($registros);

        // Pre-cargar instructores existentes en memoria para optimizar búsquedas
        $instructoresExistentes = $this->modelo->listarInstructores();
        foreach ($instructoresExistentes as $inst) {
            $nomNorm = $this->modelo->normalizarTexto($inst['nombre_completo']);
            $this->instructoresCache[$nomNorm] = (int)$inst['id_usuario'];
        }

        // Iniciar transacción de base de datos para garantizar atomicidad e integridad
        $this->modelo->iniciarTransaccion();

        try {
            foreach ($registros as $idx => $reg) {
                $fila = $reg['fila'] ?? ($idx + 1);
                $numeroFicha = trim((string)($reg['numero_ficha'] ?? ''));
                $programa    = trim((string)($reg['programa'] ?? 'Formación SENA'));
                $fecha       = trim((string)($reg['fecha'] ?? ''));
                $horaEntrada = trim((string)($reg['hora_entrada'] ?? ''));
                $horaSalida  = trim((string)($reg['hora_salida'] ?? ''));
                $instructor  = trim((string)($reg['instructor'] ?? ''));
                $area        = trim((string)($reg['area'] ?? ''));

                // Validaciones de datos obligatorios por fila
                if ($numeroFicha === '') {
                    $resultado['errores'][] = "Fila {$fila}: Número de ficha vacío.";
                    continue;
                }

                if (!preg_match('/^[a-zA-Z0-9\-_]{3,20}$/', $numeroFicha)) {
                    $resultado['errores'][] = "Fila {$fila}: Formato de ficha inválido ('{$numeroFicha}'). Debe contener entre 3 y 20 caracteres alfanuméricos.";
                    continue;
                }

                if (!$this->esFechaValida($fecha)) {
                    $resultado['errores'][] = "Fila {$fila}: Fecha inválida ('{$fecha}'). Se requiere formato AAAA-MM-DD.";
                    continue;
                }

                if (!$this->esHoraValida($horaEntrada) || !$this->esHoraValida($horaSalida)) {
                    $resultado['errores'][] = "Fila {$fila}: Formato de horario inválido ({$horaEntrada} - {$horaSalida}).";
                    continue;
                }

                if (strtotime($horaEntrada) >= strtotime($horaSalida)) {
                    $resultado['errores'][] = "Fila {$fila}: La hora de entrada ({$horaEntrada}) debe ser anterior a la hora de salida ({$horaSalida}).";
                    continue;
                }

                // 2. Comprobar / Obtener Ficha (crearla automáticamente si no existe)
                $idFicha = null;
                if (isset($this->fichasCache[$numeroFicha])) {
                    $idFicha = $this->fichasCache[$numeroFicha]['id'];
                } else {
                    $idFichaExistente = $this->modelo->buscarIdFichaPorNumero($numeroFicha);
                    if ($idFichaExistente > 0) {
                        $this->fichasCache[$numeroFicha] = ['id' => $idFichaExistente, 'nueva' => false];
                        $idFicha = $idFichaExistente;
                    } else {
                        // Crear ficha nueva en la base de datos
                        $idFichaNuevo = $this->modelo->crearFicha($numeroFicha, $programa);
                        $this->fichasCache[$numeroFicha] = ['id' => $idFichaNuevo, 'nueva' => true];
                        $resultado['fichas_creadas']++;
                        $idFicha = $idFichaNuevo;
                    }
                }

                // 3. Comprobar / Obtener Instructor
                $idInstructor = null;
                if ($instructor !== '') {
                    $normInstructor = $this->modelo->normalizarTexto($instructor);
                    if (isset($this->instructoresCache[$normInstructor])) {
                        $idInstructor = $this->instructoresCache[$normInstructor];
                    } else {
                        $infoInst = $this->modelo->obtenerOCrearInstructor($instructor, $area);
                        $idInstructor = $infoInst['id_usuario'];
                        $this->instructoresCache[$normInstructor] = $idInstructor;
                        if (!empty($infoInst['es_nuevo'])) {
                            $resultado['instructores_creados']++;
                        }
                    }

                    // Asociar instructor a la ficha en fichas_has_usuarios
                    if ($idFicha && $idInstructor) {
                        $this->modelo->asociarInstructor($idFicha, $idInstructor);
                    }
                }

                // 4. Comprobar si el horario ya existe o tiene conflicto de solapamiento
                $conflicto = $this->modelo->verificarConflictoHorario($idFicha, $fecha, $horaEntrada, $horaSalida);
                if ($conflicto !== null) {
                    $hConfIni = substr($conflicto['hora_entrada_programada'], 0, 5);
                    $hConfFin = substr($conflicto['hora_salida_programada'], 0, 5);

                    if ($conflicto['tipo_conflicto'] === 'identico') {
                        $resultado['duplicados']++;
                        $resultado['detalles'][] = "Fila {$fila}: Horario ya existía (Ficha {$numeroFicha}, {$fecha} {$horaEntrada}-{$horaSalida}). Omitido.";
                    } else {
                        $resultado['errores'][] = "Fila {$fila}: Conflicto de horario. La ficha {$numeroFicha} ya tiene una clase en {$fecha} ({$hConfIni}-{$hConfFin}) que se solapa con {$horaEntrada}-{$horaSalida}.";
                    }
                    continue;
                }

                // 5. Guardar horario en la tabla horarios
                $this->modelo->guardarHorario($idFicha, $horaEntrada, $horaSalida, $fecha, $idInstructor);
                $resultado['guardados']++;
                $resultado['detalles'][] = "Fila {$fila}: Guardado correctamente (Ficha {$numeroFicha}, {$fecha} {$horaEntrada}-{$horaSalida}).";
            }

            // Confirmar transacción
            $this->modelo->confirmarTransaccion();
            $resultado['exito'] = true;
            $resultado['mensaje'] = "Importación finalizada. {$resultado['guardados']} horarios guardados correctamente.";

        } catch (\Throwable $e) {
            $this->modelo->revertirTransaccion();
            $resultado['exito'] = false;
            $resultado['mensaje'] = 'Error durante la importación en base de datos: ' . $e->getMessage();
            error_log('Error en importación Excel: ' . $e->getMessage());
        }

        return $resultado;
    }

    /**
     * Extrae registros detectando si el archivo es tabular o matriz de horario SENA.
     */
    private function extraerRegistros(Worksheet $sheet): array
    {
        $maxRow = $sheet->getHighestRow();
        $maxCol = $sheet->getHighestColumn();

        // 1. Intentar formato tabular
        $tabular = $this->intentarTabular($sheet, $maxRow, $maxCol);
        if (!empty($tabular)) {
            return $tabular;
        }

        // 2. Intentar formato matriz SENA (Plantilla Horario de Ficha)
        return $this->extraerMatrizSena($sheet, $maxRow, $maxCol);
    }

    /**
     * Detecta y extrae datos si el Excel tiene columnas tabulares directas.
     */
    private function intentarTabular(Worksheet $sheet, int $maxRow, string $maxCol): array
    {
        for ($r = 1; $r <= min(15, $maxRow); $r++) {
            $row = $sheet->rangeToArray("A$r:$maxCol$r", null, true, true, false)[0];
            $map = [];
            foreach ($row as $idx => $cell) {
                if ($cell === null) continue;
                $val = mb_strtolower(trim((string)$cell), 'UTF-8');
                if (preg_match('/(n[uú]m.*ficha|n[uú]mero.*ficha|nro.*ficha|no.*ficha|c[oó]digo.*ficha|^ficha$)/iu', $val)) $map['ficha'] = $idx;
                elseif (preg_match('/(programa|formaci[oó]n)/iu', $val)) $map['programa'] = $idx;
                elseif (preg_match('/(fecha|d[ií]a)/iu', $val)) $map['fecha'] = $idx;
                elseif (preg_match('/(entrada|hora.*ini|hora_inicio|h\.\s*ini)/iu', $val)) $map['entrada'] = $idx;
                elseif (preg_match('/(salida|hora.*fin|hora_salida|h\.\s*fin)/iu', $val)) $map['salida'] = $idx;
                elseif (preg_match('/(instructor|docente|profesor)/iu', $val)) $map['instructor'] = $idx;
            }

            if (isset($map['ficha'], $map['fecha'], $map['entrada'], $map['salida'])) {
                $registros = [];
                for ($rowNum = $r + 1; $rowNum <= $maxRow; $rowNum++) {
                    $rowData = $sheet->rangeToArray("A$rowNum:$maxCol$rowNum", null, true, true, false)[0];
                    $ficha = trim((string)($rowData[$map['ficha']] ?? ''));
                    if ($ficha === '') continue;

                    $fechaRaw = trim((string)($rowData[$map['fecha']] ?? ''));
                    $fechaNormalizada = $this->normalizarFecha($fechaRaw);

                    $hEntrada = $this->normalizarHora((string)($rowData[$map['entrada']] ?? ''));
                    $hSalida  = $this->normalizarHora((string)($rowData[$map['salida']] ?? ''));

                    $registros[] = [
                        'fila'         => $rowNum,
                        'numero_ficha' => $ficha,
                        'programa'     => trim((string)($rowData[$map['programa'] ?? -1] ?? 'Formación SENA')),
                        'fecha'        => $fechaNormalizada,
                        'hora_entrada' => $hEntrada,
                        'hora_salida'  => $hSalida,
                        'instructor'   => trim((string)($rowData[$map['instructor'] ?? -1] ?? '')),
                        'area'         => ''
                    ];
                }
                return $registros;
            } elseif (isset($map['ficha']) || isset($map['fecha']) || isset($map['entrada'])) {
                // Se detectó una fila de encabezados pero faltan columnas obligatorias
                $faltantes = [];
                if (!isset($map['ficha']))   $faltantes[] = 'Número de ficha';
                if (!isset($map['fecha']))   $faltantes[] = 'Fecha';
                if (!isset($map['entrada'])) $faltantes[] = 'Hora de entrada';
                if (!isset($map['salida']))  $faltantes[] = 'Hora de salida';
                if (!empty($faltantes)) {
                    $this->ultimoErrorEstructura = "No se encontró la columna obligatoria '" . implode("', '", $faltantes) . "' en el archivo.";
                }
            }
        }

        return [];
    }

    /**
     * Extrae horarios a partir de la estructura de matriz de horario oficial SENA.
     */
    private function extraerMatrizSena(Worksheet $sheet, int $maxRow, string $maxCol): array
    {
        $numeroFichaGlobal = '';
        $nombreProgramaGlobal = '';
        $defaultYear = (int)date('Y');
        $defaultMonth = (int)date('m');

        $meses = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
            'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
            'septiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12
        ];

        // 1. Escanear cabecera en busca de Ficha, Programa y Mes/Año
        for ($r = 1; $r <= min(15, $maxRow); $r++) {
            $row = $sheet->rangeToArray("A$r:$maxCol$r", null, true, true, false)[0];
            foreach ($row as $cell) {
                if (!$cell) continue;
                $str = trim((string)$cell);

                if (preg_match('/ficha[:\s]*([0-9]{5,10})/iu', $str, $m)) {
                    $numeroFichaGlobal = $m[1];
                } elseif (preg_match('/^\s*([0-9]{6,8})\s*$/', $str) && $numeroFichaGlobal === '') {
                    $numeroFichaGlobal = $str;
                }

                if (preg_match('/programa[:\s]*(.+)/iu', $str, $m)) {
                    $nombreProgramaGlobal = trim($m[1]);
                } elseif (preg_match('/(tecn[oó]log[oa]|t[eé]cnico|especializaci[oó]n).+/iu', $str) && $nombreProgramaGlobal === '') {
                    $nombreProgramaGlobal = $str;
                }

                if (preg_match('/mes[:\s]*([a-záéíóú]+)\s*(\d{4})?/iu', $str, $m)) {
                    $mNom = mb_strtolower($m[1], 'UTF-8');
                    if (isset($meses[$mNom])) $defaultMonth = $meses[$mNom];
                    if (!empty($m[2])) $defaultYear = (int)$m[2];
                }
            }
        }

        if ($numeroFichaGlobal === '') $numeroFichaGlobal = 'Ficha SENA';
        if ($nombreProgramaGlobal === '') $nombreProgramaGlobal = 'Programa de Formación SENA';

        $diasSemanaNombres = ['lunes', 'martes', 'miercoles', 'miércoles', 'jueves', 'viernes', 'sabado', 'sábado', 'domingo'];
        $rawSlots = [];

        // 2. Escanear bloques de semanas en la hoja
        for ($r = 1; $r <= $maxRow; $r++) {
            $row = $sheet->rangeToArray("A$r:$maxCol$r", null, true, true, false)[0];
            $matchDias = 0;
            $dayCols = [];

            foreach ($row as $cIdx => $cell) {
                if (!$cell) continue;
                $val = mb_strtolower(trim((string)$cell), 'UTF-8');
                if (in_array($val, $diasSemanaNombres, true)) {
                    $matchDias++;
                    $dayCols[$cIdx] = $val;
                }
            }

            if ($matchDias >= 3) {
                // Fila de días encontrada
                $currentMonth = $defaultMonth;
                $currentYear  = $defaultYear;

                // Revisar filas inmediatamente anteriores para identificar el mes
                for ($chk = max(1, $r - 4); $chk < $r; $chk++) {
                    $chkRow = $sheet->rangeToArray("A$chk:$maxCol$chk", null, true, true, false)[0];
                    foreach ($chkRow as $cell) {
                        if (!$cell) continue;
                        $mStr = mb_strtolower(trim((string)$cell), 'UTF-8');
                        foreach ($meses as $mKey => $mNum) {
                            if (str_contains($mStr, $mKey)) {
                                $currentMonth = $mNum;
                                if (preg_match('/\b(202\d)\b/', $mStr, $yM)) {
                                    $currentYear = (int)$yM[1];
                                }
                                break 2;
                            }
                        }
                    }
                }

                // Buscar la fila con los números de día (ej: 29, 30, 1, 2, 3...)
                $numbersRow = $r + 1;
                $rowNumbers = $sheet->rangeToArray("A$numbersRow:$maxCol$numbersRow", null, true, true, false)[0];
                $hasNums = false;
                foreach ($dayCols as $cIdx => $dName) {
                    if (isset($rowNumbers[$cIdx]) && is_numeric(trim((string)$rowNumbers[$cIdx]))) {
                        $hasNums = true;
                        break;
                    }
                }

                if (!$hasNums && $numbersRow + 1 <= $maxRow) {
                    $numbersRow++;
                    $rowNumbers = $sheet->rangeToArray("A$numbersRow:$maxCol$numbersRow", null, true, true, false)[0];
                }

                // Asignar a cada columna su fecha YYYY-MM-DD
                $colDates = [];
                $prevDayNum = 0;
                $activeMonth = $currentMonth;
                $activeYear = $currentYear;

                foreach ($dayCols as $cIdx => $dName) {
                    $valNum = isset($rowNumbers[$cIdx]) ? trim((string)$rowNumbers[$cIdx]) : '';
                    if (is_numeric($valNum)) {
                        $dayNum = (int)$valNum;
                        if ($dayNum >= 1 && $dayNum <= 31) {
                            // Salto de fin de mes (ej. 30 a 1)
                            if ($prevDayNum > 20 && $dayNum < 10) {
                                $activeMonth++;
                                if ($activeMonth > 12) {
                                    $activeMonth = 1;
                                    $activeYear++;
                                }
                            }
                            $prevDayNum = $dayNum;
                            $colDates[$cIdx] = sprintf('%04d-%02d-%02d', $activeYear, $activeMonth, $dayNum);
                        }
                    }
                }

                // Leer franjas horarias de esta semana (hasta 25 horas o próxima sección)
                $slotStartRow = $numbersRow + 1;
                $slotEndRow = min($maxRow, $slotStartRow + 25);

                for ($rowSlot = $slotStartRow; $rowSlot <= $slotEndRow; $rowSlot++) {
                    $slotRow = $sheet->rangeToArray("A$rowSlot:$maxCol$rowSlot", null, true, true, false)[0];

                    // Detectar horas de inicio y fin en las primeras columnas
                    $hIni = null;
                    $hFin = null;
                    foreach (array_slice($slotRow, 0, 5, true) as $cVal) {
                        if ($cVal === null) continue;
                        $strV = trim((string)$cVal);
                        if (is_numeric($strV) && (int)$strV >= 5 && (int)$strV <= 23) {
                            if ($hIni === null) {
                                $hIni = (int)$strV;
                            } elseif ($hFin === null) {
                                $hFin = (int)$strV;
                                break;
                            }
                        } elseif (preg_match('/^(\d{1,2}):(\d{2})$/', $strV, $tm)) {
                            if ($hIni === null) $hIni = (int)$tm[1];
                            elseif ($hFin === null) { $hFin = (int)$tm[1]; break; }
                        }
                    }

                    if ($hIni === null || $hFin === null) {
                        continue;
                    }

                    $horaIniStr = sprintf('%02d:00:00', $hIni);
                    $horaFinStr = sprintf('%02d:00:00', $hFin);

                    // Para cada columna con fecha válida, leer el instructor asignado
                    foreach ($colDates as $cIdx => $dateStr) {
                        $cellVal = isset($slotRow[$cIdx]) ? trim((string)$slotRow[$cIdx]) : '';
                        if ($cellVal !== '') {
                            $lines = preg_split('/[\r\n]+/', $cellVal);
                            $instructor = trim($lines[0] ?? '');
                            $area = trim($lines[1] ?? '');

                            if ($instructor !== '') {
                                $rawSlots[] = [
                                    'fila'         => $rowSlot,
                                    'numero_ficha' => $numeroFichaGlobal,
                                    'programa'     => $nombreProgramaGlobal,
                                    'fecha'        => $dateStr,
                                    'h_ini_num'    => $hIni,
                                    'h_fin_num'    => $hFin,
                                    'hora_entrada' => $horaIniStr,
                                    'hora_salida'  => $horaFinStr,
                                    'instructor'   => $instructor,
                                    'area'         => $area
                                ];
                            }
                        }
                    }
                }

                // Avanzar el cursor para evitar volver a procesar filas de horarios como cabeceras
                $r = $slotStartRow + 15;
            }
        }

        // 3. Consolidar bloques horarios consecutivos del mismo instructor en el mismo día
        return $this->consolidarBloquesConsecutivos($rawSlots);
    }

    /**
     * Une bloques horarios consecutivos para el mismo día, ficha e instructor.
     * Ejemplo: 07:00 a 08:00 y 08:00 a 09:00 -> 07:00 a 09:00.
     */
    private function consolidarBloquesConsecutivos(array $slots): array
    {
        if (empty($slots)) return [];

        // Agrupar por [fecha][ficha][instructor]
        $grupos = [];
        foreach ($slots as $slot) {
            $key = $slot['fecha'] . '_' . $slot['numero_ficha'] . '_' . mb_strtolower($slot['instructor']);
            $grupos[$key][] = $slot;
        }

        $resultado = [];
        foreach ($grupos as $lista) {
            // Ordenar por hora de inicio
            usort($lista, fn($a, $b) => $a['h_ini_num'] <=> $b['h_ini_num']);

            $bloqueActual = null;
            foreach ($lista as $s) {
                if ($bloqueActual === null) {
                    $bloqueActual = $s;
                    continue;
                }

                // Si la hora de inicio coincide con la hora de fin del bloque anterior, se fusionan
                if ($s['h_ini_num'] === $bloqueActual['h_fin_num']) {
                    $bloqueActual['h_fin_num'] = $s['h_fin_num'];
                    $bloqueActual['hora_salida'] = sprintf('%02d:00:00', $s['h_fin_num']);
                } else {
                    $resultado[] = $bloqueActual;
                    $bloqueActual = $s;
                }
            }

            if ($bloqueActual !== null) {
                $resultado[] = $bloqueActual;
            }
        }

        return $resultado;
    }

    private function esFechaValida(string $fecha): bool
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $m)) {
            return false;
        }
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
    }

    private function esHoraValida(string $hora): bool
    {
        return (bool)preg_match('/^(\d{2}):(\d{2})(:(\d{2}))?$/', $hora);
    }

    private function normalizarFecha(string $fechaRaw): string
    {
        $fechaRaw = trim($fechaRaw);
        if (is_numeric($fechaRaw)) {
            // Conversión de timestamp o número serial de fecha de Excel
            try {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$fechaRaw);
                return $dt->format('Y-m-d');
            } catch (\Throwable $e) {}
        }

        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $fechaRaw, $m)) {
            return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
        }

        if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $fechaRaw, $m)) {
            return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
        }

        $time = strtotime($fechaRaw);
        if ($time !== false) {
            return date('Y-m-d', $time);
        }

        return $fechaRaw;
    }

    private function normalizarHora(string $horaRaw): string
    {
        $horaRaw = trim($horaRaw);
        if (is_numeric($horaRaw)) {
            $num = (float)$horaRaw;
            if ($num < 1.0) {
                // Fracción de día en Excel
                $segundos = (int)round($num * 86400);
                $h = floor($segundos / 3600);
                $m = floor(($segundos % 3600) / 60);
                return sprintf('%02d:%02d:00', $h, $m);
            }
            if ($num >= 5 && $num <= 23) {
                return sprintf('%02d:00:00', (int)$num);
            }
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(:(\d{2}))?$/', $horaRaw, $m)) {
            return sprintf('%02d:%02d:00', (int)$m[1], (int)$m[2]);
        }

        return $horaRaw;
    }
}

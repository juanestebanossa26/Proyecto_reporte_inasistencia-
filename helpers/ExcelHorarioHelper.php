<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExcelHorarioHelper
{
    public const DEFAULT_MAX_MB = 5;
    public const DEFAULT_MAX_BYTES = 5242880; // 5 * 1024 * 1024

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
        if (empty($archivo) || !isset($archivo['error'])) {
            return ['valido' => false, 'error' => 'No se recibió ningún archivo para procesar.'];
        }

        // Interpretación exhaustiva de códigos de error de PHP en $_FILES
        switch ($archivo['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
                $maxServer = ini_get('upload_max_filesize');
                return ['valido' => false, 'error' => "El archivo supera el tamaño máximo permitido por el servidor (upload_max_filesize: {$maxServer})."];
            case UPLOAD_ERR_FORM_SIZE:
                return ['valido' => false, 'error' => 'El archivo supera el tamaño máximo permitido por el formulario web.'];
            case UPLOAD_ERR_PARTIAL:
                return ['valido' => false, 'error' => 'El archivo sólo se subió de forma parcial. Por favor, intenta de nuevo.'];
            case UPLOAD_ERR_NO_FILE:
                return ['valido' => false, 'error' => 'No seleccionaste ningún archivo Excel para importar.'];
            case UPLOAD_ERR_NO_TMP_DIR:
                return ['valido' => false, 'error' => 'Error interno de configuración del servidor: falta el directorio temporal de subida.'];
            case UPLOAD_ERR_CANT_WRITE:
                return ['valido' => false, 'error' => 'Error interno del servidor: no se pudo escribir el archivo en el disco.'];
            case UPLOAD_ERR_EXTENSION:
                return ['valido' => false, 'error' => 'Una extensión de PHP detuvo la carga del archivo en el servidor.'];
            default:
                return ['valido' => false, 'error' => 'Ocurrió un error inesperado al subir el archivo (código ' . (int)$archivo['error'] . ').'];
        }

        $nombreOriginal = (string)($archivo['name'] ?? '');
        $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls'], true)) {
            return ['valido' => false, 'error' => 'Formato no válido. Solo se permiten archivos Excel oficiales (.xlsx, .xls).'];
        }

        $rutaTemp = (string)($archivo['tmp_name'] ?? '');
        if ((PHP_SAPI !== 'cli' && !is_uploaded_file($rutaTemp)) || !file_exists($rutaTemp)) {
            return ['valido' => false, 'error' => 'El archivo recibido no es una carga válida del servidor.'];
        }

        // Validación de archivo vacío (0 bytes)
        $tamano = (int)($archivo['size'] ?? 0);
        $tamanoReal = filesize($rutaTemp);
        if ($tamano === 0 || $tamanoReal === 0) {
            return ['valido' => false, 'error' => 'El archivo seleccionado está vacío (0 bytes). Selecciona un archivo con datos.'];
        }

        // Validación estricta de tamaño máximo (5MB centralizado en constante)
        $limiteMaximoBytes = defined('LIMITE_EXCEL_HORARIOS_BYTES') ? (int)LIMITE_EXCEL_HORARIOS_BYTES : self::DEFAULT_MAX_BYTES;
        if ($tamano > $limiteMaximoBytes || $tamanoReal > $limiteMaximoBytes) {
            $mb = defined('LIMITE_EXCEL_HORARIOS_MB') ? (int)LIMITE_EXCEL_HORARIOS_MB : self::DEFAULT_MAX_MB;
            return ['valido' => false, 'error' => "El archivo supera el límite de tamaño permitido (máximo {$mb}MB). Las plantillas oficiales de horarios SENA suelen pesar menos de 1MB."];
        }

        // Validación de tipo MIME real en el servidor (inspección binaria)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $rutaTemp);
        finfo_close($finfo);

        $mimesPermitidos = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'application/zip',
            'application/x-zip-compressed',
            'application/octet-stream',
            'application/x-ole-storage',
            'application/cdfv2',
        ];

        if (!in_array($mime, $mimesPermitidos, true)) {
            return ['valido' => false, 'error' => 'Formato no válido. El contenido binario del archivo no corresponde a una hoja de cálculo Excel.'];
        }

        // Comprobación de integridad estructural ultrarrápida sin sobrecarga de memoria
        if ($ext === 'xlsx') {
            if (class_exists('ZipArchive')) {
                $zip = new \ZipArchive();
                $resZip = $zip->open($rutaTemp);
                if ($resZip !== true) {
                    return ['valido' => false, 'error' => 'El archivo Excel está dañado, corrupto o no es un paquete válido.'];
                }
                $esWorkbookValido = ($zip->locateName('[Content_Types].xml') !== false || $zip->locateName('xl/workbook.xml') !== false);
                $zip->close();
                if (!$esWorkbookValido) {
                    return ['valido' => false, 'error' => 'El archivo no contiene la estructura interna de una hoja de cálculo Excel (.xlsx).'];
                }
            }
        } elseif ($ext === 'xls') {
            $fp = @fopen($rutaTemp, 'rb');
            if ($fp) {
                $header = fread($fp, 8);
                fclose($fp);
                if ($header !== "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
                    return ['valido' => false, 'error' => 'El archivo no contiene una estructura válida de Excel 97-2003 (.xls).'];
                }
            }
        }

        return ['valido' => true, 'error' => null];
    }

    /**
     * Procesa el archivo Excel y registra los datos en la base de datos.
     * Soporta la plantilla oficial de Horarios SENA (Matriz horizontal por semanas) y formato tabular.
     * Identifica y procesa los festivos sin registrarlos como instructores o aprendices.
     */
    public function importar(string $rutaArchivo): array
    {
        $this->ultimoErrorEstructura = null;

        $resultado = [
            'exito'                => false,
            'mensaje'              => '',
            'total_procesados'     => 0,
            'guardados'            => 0,
            'festivos_registrados' => 0,
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

        $maxRow = (int)$sheet->getHighestRow();
        if ($maxRow < 2) {
            $resultado['mensaje'] = 'El archivo seleccionado no contiene información suficiente para procesar.';
            return $resultado;
        }

        // Límite de seguridad DoS: evitar procesar archivos artificialmente gigantescos
        if ($maxRow > 3000) {
            $resultado['mensaje'] = 'El archivo contiene un número excesivo de filas (' . $maxRow . '). El límite permitido es de 3,000 filas por seguridad del servidor.';
            return $resultado;
        }

        // 1. Extraer registros de clases y festivos
        $extraccion = $this->extraerDatos($sheet);
        $registros = $extraccion['clases'];
        $festivos = $extraccion['festivos'];
        $fichaDetectada = $extraccion['ficha'];

        if (empty($registros) && empty($festivos)) {
            $resultado['mensaje'] = $this->ultimoErrorEstructura 
                ?? 'El archivo no corresponde a la plantilla oficial de horarios SENA ni contiene datos válidos. Verifique que incluya la identificación de ficha, días, fechas y franjas horarias.';
            return $resultado;
        }

        $resultado['total_procesados'] = count($registros) + count($festivos);

        // Pre-cargar instructores existentes en memoria
        $instructoresExistentes = $this->modelo->listarInstructores();
        foreach ($instructoresExistentes as $inst) {
            $nomNorm = $this->modelo->normalizarTexto($inst['nombre_completo']);
            $this->instructoresCache[$nomNorm] = (int)$inst['id_usuario'];
        }

        // ======================================================================
        // FASE 1: PROCESAMIENTO ESPECIAL DE FESTIVOS (Sin crear usuarios/aprendices)
        // ======================================================================
        $idFichaGlobal = null;
        if (!empty($fichaDetectada['numero_ficha'])) {
            $numFichaLim = preg_replace('/[\x{00A0}\x{200B}\s]+/u', '', $fichaDetectada['numero_ficha']);
            $fichaExistente = $this->modelo->buscarFichaPorNumero($numFichaLim);
            if ($fichaExistente) {
                if (($fichaExistente['estado'] ?? 'Activa') !== 'Activa') {
                    $resultado['mensaje'] = "La ficha '{$numFichaLim}' identificada en el archivo se encuentra en estado '{$fichaExistente['estado']}'. Por políticas institucionales, no es posible importar horarios para fichas finalizadas o archivadas.";
                    return $resultado;
                }
                $idFichaGlobal = (int)$fichaExistente['id_ficha'];
            } else {
                $prog = !empty($fichaDetectada['programa']) ? $fichaDetectada['programa'] : 'Formación SENA';
                $idFichaGlobal = $this->modelo->crearFicha($numFichaLim, $prog);
                $this->fichasCache[$numFichaLim] = ['id' => $idFichaGlobal, 'nueva' => true];
                $resultado['fichas_creadas']++;
            }
        }

        foreach ($festivos as $fest) {
            $fechaFest = $fest['fecha'];
            $hIniFest  = $fest['hora_inicio'] ?? '06:00:00';
            $hFinFest  = $fest['hora_fin'] ?? '22:00:00';
            $esDiaComp = $fest['es_dia_completo'] ?? 1;

            // Se registra el festivo a nivel institucional (id_ficha = null) para que aplique
            // a todas las fichas del centro de formación. Si ya existe un festivo registrado
            // en el sistema para esta fecha (sea institucional o de cualquier ficha), se omite
            // para garantizar que los días festivos no se dupliquen al importar múltiples fichas.
            if ($this->modelo->existeFestivoParaFecha($fechaFest)) {
                $resultado['duplicados']++;
                continue;
            }

            $this->modelo->guardarFestivo(null, $fechaFest, $hIniFest, $hFinFest, $esDiaComp, 'FESTIVO');
            $resultado['festivos_registrados']++;
            $resultado['detalles'][] = "Festivo oficial institucional registrado para el día {$fechaFest} ({$hIniFest} - {$hFinFest}). Marcador de calendario sin crear instructor.";
        }

        // Si solo venían festivos y ninguna clase regular
        if (empty($registros)) {
            $resultado['exito'] = true;
            $resultado['mensaje'] = "Importación finalizada. Se registraron {$resultado['festivos_registrados']} días festivos oficiales en el calendario.";
            return $resultado;
        }

        // ======================================================================
        // FASE 2: VALIDACIÓN INICIAL DE CAMPOS EN REGISTROS DE CLASES
        // ======================================================================
        $registrosValidados = [];
        $erroresValidacion = [];

        foreach ($registros as $idx => $reg) {
            $fila = $reg['fila'] ?? ($idx + 1);
            $numeroFicha = trim(preg_replace('/[\x{00A0}\x{200B}\s]+/u', ' ', (string)($reg['numero_ficha'] ?? '')));
            $numeroFicha = str_replace(' ', '', $numeroFicha);

            if ($numeroFicha === '') {
                $erroresValidacion[] = "Fila {$fila}: El número de ficha está vacío o no es legible.";
                continue;
            }

            if (!preg_match('/^[a-zA-Z0-9\-_]{3,20}$/', $numeroFicha)) {
                $erroresValidacion[] = "Fila {$fila}: El número de ficha '{$numeroFicha}' contiene caracteres no permitidos.";
                continue;
            }

            $fecha = trim((string)($reg['fecha'] ?? ''));
            if (!$this->esFechaValida($fecha)) {
                $erroresValidacion[] = "Fila {$fila}: La fecha '{$fecha}' no tiene un formato válido (YYYY-MM-DD).";
                continue;
            }

            $hEntrada = trim((string)($reg['hora_entrada'] ?? ''));
            $hSalida  = trim((string)($reg['hora_salida'] ?? ''));

            if (!$this->esHoraValida($hEntrada)) {
                $erroresValidacion[] = "Fila {$fila}: La hora de entrada '{$hEntrada}' no es válida.";
                continue;
            }

            if (!$this->esHoraValida($hSalida)) {
                $erroresValidacion[] = "Fila {$fila}: La hora de salida '{$hSalida}' no es válida.";
                continue;
            }

            if (strlen($hEntrada) === 5) $hEntrada .= ':00';
            if (strlen($hSalida) === 5)  $hSalida  .= ':00';

            if ($hEntrada >= $hSalida) {
                $erroresValidacion[] = "Fila {$fila}: La hora de entrada ({$hEntrada}) debe ser anterior a la hora de salida ({$hSalida}).";
                continue;
            }

            // Normalizar Instructor
            $instructorRaw = trim((string)($reg['instructor'] ?? ''));
            $instructor = html_entity_decode($instructorRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $instructor = preg_replace('/[\x{00A0}\x{200B}\s]+/u', ' ', $instructor);
            $instructor = trim($instructor);

            // Regla estricta: FESTIVO no es un instructor
            if (strcasecmp($instructor, 'FESTIVO') === 0 || strcasecmp($instructor, 'FESTIVOS') === 0) {
                continue;
            }

            $programaRaw = trim((string)($reg['programa'] ?? ''));
            $programa = html_entity_decode($programaRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $programa = preg_replace('/[\x{00A0}\x{200B}\s]+/u', ' ', $programa);
            $programa = trim($programa);
            if ($programa === '') {
                $programa = 'Formación SENA';
            }

            $area = trim((string)($reg['area'] ?? ''));
            if ($area !== '' && (strpos($area, '-') !== false || strpos($area, '–') !== false || strpos($area, '—') !== false)) {
                $partesArea = preg_split('/[-–—]/u', $area);
                $area = trim($partesArea[0] ?? '');
            }

            $registrosValidados[] = [
                'fila'         => $fila,
                'numero_ficha' => $numeroFicha,
                'programa'     => $programa,
                'fecha'        => $fecha,
                'hora_entrada' => $hEntrada,
                'hora_salida'  => $hSalida,
                'instructor'   => $instructor,
                'area'         => $area
            ];
        }

        // ======================================================================
        // FASE 3: EVALUACIÓN DE CONFLICTOS INTERNOS Y SOLAPAMIENTOS (Optimizado O(N))
        // ======================================================================
        $conflictosInternos = [];
        $duplicadosInternosExactos = [];

        // Agrupación en memoria por ficha|fecha e instructor|fecha para evitar doble bucle O(N^2)
        $gruposFichaFecha = [];
        $gruposInstructorFecha = [];

        foreach ($registrosValidados as $reg) {
            $keyFF = $reg['numero_ficha'] . '|' . $reg['fecha'];
            $gruposFichaFecha[$keyFF][] = $reg;

            if (!empty($reg['instructor'])) {
                $normInst = mb_strtolower(trim($reg['instructor']), 'UTF-8');
                $keyIF = $normInst . '|' . $reg['fecha'];
                $gruposInstructorFecha[$keyIF][] = $reg;
            }
        }

        // 3.1 Solapamientos y duplicados en la misma ficha y fecha
        foreach ($gruposFichaFecha as $grupo) {
            $cnt = count($grupo);
            if ($cnt < 2) continue;
            for ($i = 0; $i < $cnt; $i++) {
                $a = $grupo[$i];
                for ($j = $i + 1; $j < $cnt; $j++) {
                    $b = $grupo[$j];
                    if ($a['hora_entrada'] === $b['hora_entrada'] && $a['hora_salida'] === $b['hora_salida']) {
                        $duplicadosInternosExactos[$b['fila']] = true;
                    } elseif ($a['hora_entrada'] < $b['hora_salida'] && $a['hora_salida'] > $b['hora_entrada']) {
                        $conflictosInternos[] = "Fila {$b['fila']}: Conflicto interno con Fila {$a['fila']}. La ficha {$a['numero_ficha']} tiene horarios superpuestos el día {$a['fecha']} ({$a['hora_entrada']}-{$a['hora_salida']} vs {$b['hora_entrada']}-{$b['hora_salida']}).";
                    }
                }
            }
        }

        // 3.2 Solapamientos de un mismo instructor en fichas diferentes en la misma fecha
        foreach ($gruposInstructorFecha as $grupo) {
            $cnt = count($grupo);
            if ($cnt < 2) continue;
            for ($i = 0; $i < $cnt; $i++) {
                $a = $grupo[$i];
                for ($j = $i + 1; $j < $cnt; $j++) {
                    $b = $grupo[$j];
                    if ($a['numero_ficha'] !== $b['numero_ficha']) {
                        if ($a['hora_entrada'] < $b['hora_salida'] && $a['hora_salida'] > $b['hora_entrada']) {
                            $conflictosInternos[] = "Fila {$b['fila']}: El instructor {$a['instructor']} tiene clases cruzadas en fichas diferentes ({$a['numero_ficha']} y {$b['numero_ficha']}) el día {$a['fecha']} de {$a['hora_entrada']} a {$a['hora_salida']}.";
                        }
                    }
                }
            }
        }

        // ======================================================================
        // FASE 4: EVALUACIÓN CONTRA LA BASE DE DATOS
        // ======================================================================
        $conflictosBaseDatos = [];
        $duplicadosExistentesBD = [];

        foreach ($registrosValidados as $rItem) {
            if (isset($duplicadosInternosExactos[$rItem['fila']])) {
                continue;
            }

            $numFicha = $rItem['numero_ficha'];
            if (!array_key_exists($numFicha, $this->fichasCache)) {
                $this->fichasCache[$numFicha] = $this->modelo->buscarFichaPorNumero($numFicha);
            }
            $fichaBD = $this->fichasCache[$numFicha];

            if ($fichaBD !== null) {
                if (($fichaBD['estado'] ?? 'Activa') !== 'Activa') {
                    $conflictosBaseDatos[] = "Fila {$rItem['fila']}: La ficha '{$numFicha}' se encuentra en estado '{$fichaBD['estado']}'. No se permite importar horarios para fichas finalizadas o archivadas.";
                    continue;
                }
                $idFichaBD = (int)$fichaBD['id_ficha'];
            } else {
                $idFichaBD = $this->fichasCache[$numFicha]['id'] ?? null;
            }

            if ($idFichaBD !== null && $idFichaBD > 0) {
                $idInstTemp = null;
                if (!empty($rItem['instructor'])) {
                    $normInst = $this->modelo->normalizarTexto($rItem['instructor']);
                    $idInstTemp = $this->instructoresCache[$normInst] ?? null;
                }

                $conflictoBD = $this->modelo->verificarConflictoHorario(
                    $idFichaBD,
                    $rItem['fecha'],
                    $rItem['hora_entrada'],
                    $rItem['hora_salida'],
                    null,
                    $idInstTemp
                );

                if ($conflictoBD !== null) {
                    $hConfIni = substr($conflictoBD['hora_entrada_programada'], 0, 5);
                    $hConfFin = substr($conflictoBD['hora_salida_programada'], 0, 5);
                    $docenteBD = $conflictoBD['instructor_nombre'] ?? 'Sin asignar';

                    if ($conflictoBD['tipo_conflicto'] === 'identico') {
                        $duplicadosExistentesBD[$rItem['fila']] = true;
                    } elseif ($conflictoBD['tipo_conflicto'] === 'instructor_ocupado') {
                        $conflictosBaseDatos[] = "Fila {$rItem['fila']}: Conflicto de instructor. {$rItem['instructor']} ya tiene clase asignada en Ficha {$conflictoBD['numero_ficha']} el día {$rItem['fecha']} de {$hConfIni} a {$hConfFin}.";
                    } else {
                        $conflictosBaseDatos[] = "Fila {$rItem['fila']}: Conflicto con horario guardado. La ficha {$numFicha} ya tiene una clase programada el día {$rItem['fecha']} de {$hConfIni} a {$hConfFin} (Docente: {$docenteBD}), la cual se superpone con la clase del archivo ({$rItem['hora_entrada']} - {$rItem['hora_salida']}).";
                    }
                }
            }
        }

        // ======================================================================
        // FASE 5: EVALUACIÓN ATÓMICA DE RECHAZO
        // ======================================================================
        $todosLosErrores = array_merge($erroresValidacion, $conflictosInternos, $conflictosBaseDatos);

        if (!empty($todosLosErrores)) {
            $resultado['exito']   = false;
            $resultado['errores'] = $todosLosErrores;
            $resultado['mensaje'] = "Se encontraron " . count($todosLosErrores) . " conflictos o errores en el archivo. Para proteger la integridad de los horarios y evitar cruces de clases, la importación de clases fue cancelada.";
            return $resultado;
        }

        // ======================================================================
        // FASE 6: INSERCIÓN ATÓMICA EN BASE DE DATOS
        // ======================================================================
        $this->modelo->iniciarTransaccion();

        try {
            foreach ($registrosValidados as $reg) {
                $fila        = $reg['fila'];
                $numeroFicha = $reg['numero_ficha'];
                $programa    = $reg['programa'];
                $fecha       = $reg['fecha'];
                $horaEntrada = $reg['hora_entrada'];
                $horaSalida  = $reg['hora_salida'];
                $instructor  = $reg['instructor'];
                $area        = $reg['area'];

                if (isset($duplicadosInternosExactos[$fila]) || isset($duplicadosExistentesBD[$fila])) {
                    $resultado['duplicados']++;
                    $resultado['detalles'][] = "Fila {$fila}: Horario ya existía (Ficha {$numeroFicha}, {$fecha} {$horaEntrada}-{$horaSalida}). Omitido.";
                    continue;
                }

                // Ficha
                if (isset($this->fichasCache[$numeroFicha])) {
                    $idFicha = $this->fichasCache[$numeroFicha]['id'];
                } else {
                    $idFichaExistente = $this->modelo->buscarIdFichaPorNumero($numeroFicha);
                    if ($idFichaExistente > 0) {
                        $this->fichasCache[$numeroFicha] = ['id' => $idFichaExistente, 'nueva' => false];
                        $idFicha = $idFichaExistente;
                        if ($programa !== '' && $programa !== 'Formación SENA') {
                            $this->modelo->actualizarFicha($idFicha, $numeroFicha, $programa);
                        }
                    } else {
                        $idFichaNuevo = $this->modelo->crearFicha($numeroFicha, $programa);
                        $this->fichasCache[$numeroFicha] = ['id' => $idFichaNuevo, 'nueva' => true];
                        $resultado['fichas_creadas']++;
                        $idFicha = $idFichaNuevo;
                    }
                }

                // Instructor
                $idInstructor = null;
                if ($instructor !== '') {
                    $normInstructor = $this->modelo->normalizarTexto($instructor);
                    if (isset($this->instructoresCache[$normInstructor])) {
                        $idInstructor = $this->instructoresCache[$normInstructor];
                    } else {
                        $infoInst = $this->modelo->obtenerOCrearInstructor($instructor, $area);
                        if (empty($infoInst['ignorar'])) {
                            $idInstructor = (int)($infoInst['id_usuario'] ?? 0);
                            if ($idInstructor > 0) {
                                $this->instructoresCache[$normInstructor] = $idInstructor;
                                if (!empty($infoInst['es_nuevo'])) {
                                    $resultado['instructores_creados']++;
                                }
                            } else {
                                $idInstructor = null;
                            }
                        }
                    }

                    if ($idFicha && $idInstructor) {
                        $this->modelo->asociarInstructor($idFicha, $idInstructor);
                    }
                }

                // Guardar horario
                $this->modelo->guardarHorario($idFicha, $horaEntrada, $horaSalida, $fecha, $idInstructor);
                $resultado['guardados']++;
                $resultado['detalles'][] = "Fila {$fila}: Guardado correctamente (Ficha {$numeroFicha}, {$fecha} {$horaEntrada}-{$horaSalida}).";
            }

            $this->modelo->confirmarTransaccion();
            $resultado['exito'] = true;
            $msgFestivos = $resultado['festivos_registrados'] > 0 ? " y {$resultado['festivos_registrados']} festivos oficiales registrados" : "";
            $resultado['mensaje'] = "Importación finalizada exitosamente. {$resultado['guardados']} horarios guardados correctamente{$msgFestivos} (" . $resultado['duplicados'] . " duplicados omitidos).";

        } catch (\Throwable $e) {
            $this->modelo->revertirTransaccion();
            $resultado['exito'] = false;
            $resultado['mensaje'] = 'Error durante la inserción en base de datos: ' . $e->getMessage();
            error_log('Error en importación Excel: ' . $e->getMessage());
        }

        return $resultado;
    }

    /**
     * Extrae datos de la hoja detectando si es la plantilla de matriz oficial SENA o tabular.
     */
    private function extraerDatos(Worksheet $sheet): array
    {
        $maxRow = $sheet->getHighestRow();
        $maxCol = $sheet->getHighestColumn();

        // 1. Intentar matriz de horario oficial SENA (semanas horizontales, columnas D..N)
        $matriz = $this->extraerMatrizOficialSena($sheet, $maxRow, $maxCol);
        if (!empty($matriz['clases']) || !empty($matriz['festivos'])) {
            return $matriz;
        }

        // 2. Intentar formato tabular
        $tabular = $this->intentarTabular($sheet, $maxRow, $maxCol);
        return [
            'clases'   => $tabular,
            'festivos' => [],
            'ficha'    => []
        ];
    }

    /**
     * Extrae horarios y festivos a partir de la plantilla oficial SENA.
     */
    private function extraerMatrizOficialSena(Worksheet $sheet, int $maxRow, string $maxCol): array
    {
        $numeroFicha = '';
        $nombrePrograma = '';

        $diasYMeses = [
            'lunes', 'martes', 'miércoles', 'miercoles', 'jueves', 'viernes', 'sábado', 'sabado',
            'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
        ];

        // Buscar Ficha y Programa en la cabecera (Filas 1 a 3)
        for ($r = 1; $r <= min(3, $maxRow); $r++) {
            $valD = trim((string)$sheet->getCell('D' . $r)->getValue());
            $valE = trim((string)$sheet->getCell('E' . $r)->getValue());
            $valH = trim((string)$sheet->getCell('H' . $r)->getValue());

            if (stripos($valD, 'Ficha') !== false || preg_match('/^\d{6,8}$/', $valE)) {
                if (preg_match('/(\d{5,10})/', $valE, $m)) {
                    $numeroFicha = $m[1];
                } elseif (preg_match('/(\d{5,10})/', $valD, $m)) {
                    $numeroFicha = $m[1];
                }

                if (!empty($valH) && !in_array(mb_strtolower($valH, 'UTF-8'), $diasYMeses, true)) {
                    $nombrePrograma = trim($valH);
                    break;
                }
            }
        }

        // Si no se encontró en celdas fijas, escanear rango A1:O3
        if ($numeroFicha === '' || $nombrePrograma === '') {
            for ($r = 1; $r <= min(3, $maxRow); $r++) {
                foreach (range('A', 'O') as $col) {
                    $cellVal = trim((string)$sheet->getCell($col . $r)->getValue());
                    if ($numeroFicha === '') {
                        if (preg_match('/ficha[:\s]*([0-9]{5,10})/iu', $cellVal, $m)) {
                            $numeroFicha = $m[1];
                        } elseif (preg_match('/^\d{6,8}$/', $cellVal)) {
                            $numeroFicha = $cellVal;
                        }
                    }
                    if ($nombrePrograma === '') {
                        if (preg_match('/(tecn[oó]log[oa]|t[eé]cnico|especializaci[oó]n|curso).+/iu', $cellVal, $m)) {
                            $nombrePrograma = trim($cellVal);
                        }
                    }
                }
            }
        }

        $meses = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
            'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
            'septiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12
        ];

        // Determinar año base: año actual o año deducible
        $year = (int)date('Y');
        $currentMonth = 1;
        $currentYear = $year;

        $diasNombres = ['lunes', 'martes', 'miércoles', 'miercoles', 'jueves', 'viernes', 'sábado', 'sabado'];
        $rawSlots = [];
        $festivoSlots = [];

        // Escanear todas las semanas en la hoja
        for ($r = 1; $r <= $maxRow; $r++) {
            $valD = mb_strtolower(trim((string)$sheet->getCell('D' . $r)->getValue()), 'UTF-8');
            $valF = mb_strtolower(trim((string)$sheet->getCell('F' . $r)->getValue()), 'UTF-8');

            if (in_array($valD, $diasNombres, true) && in_array($valF, $diasNombres, true)) {
                // Fila de encabezado de días (LUNES, MARTES...)
                // Buscar nombre del mes en las 3 filas anteriores
                for ($mr = max(1, $r - 3); $mr < $r; $mr++) {
                    foreach (['D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O'] as $mc) {
                        $mVal = mb_strtolower(trim((string)$sheet->getCell($mc . $mr)->getValue()), 'UTF-8');
                        foreach ($meses as $mNom => $mNum) {
                            if (str_contains($mVal, $mNom)) {
                                $currentMonth = $mNum;
                                if (preg_match('/\b(202\d)\b/', $mVal, $yMatch)) {
                                    $currentYear = (int)$yMatch[1];
                                }
                                break 2;
                            }
                        }
                    }
                }

                // Fila de números de día
                $dateRow = $r + 1;
                $colDates = [];
                $prevDayNum = 0;
                $activeMonth = $currentMonth;
                $activeYear = $currentYear;
                $hasAnyDate = false;

                foreach (['D', 'F', 'H', 'J', 'L', 'N'] as $c) {
                    $numVal = trim((string)$sheet->getCell($c . $dateRow)->getValue());
                    if (is_numeric($numVal)) {
                        $dNum = (int)$numVal;
                        if ($dNum >= 1 && $dNum <= 31) {
                            $hasAnyDate = true;
                            // Salto de fin de mes (ej. 30 a 1)
                            if ($prevDayNum > 20 && $dNum < 10) {
                                $activeMonth++;
                                if ($activeMonth > 12) {
                                    $activeMonth = 1;
                                    $activeYear++;
                                }
                            }
                            $prevDayNum = $dNum;
                            $colDates[$c] = sprintf('%04d-%02d-%02d', $activeYear, $activeMonth, $dNum);
                        }
                    }
                }

                if (!$hasAnyDate) {
                    continue;
                }

                // Leer franjas horarias (hasta 22 filas)
                for ($hr = $dateRow + 1; $hr <= min($maxRow, $dateRow + 22); $hr++) {
                    $b = trim((string)$sheet->getCell('B' . $hr)->getValue());
                    $c = trim((string)$sheet->getCell('C' . $hr)->getValue());

                    if (!is_numeric($b) || !is_numeric($c)) {
                        continue;
                    }

                    $hIni = (int)$b;
                    $hFin = (int)$c;
                    $horaIniStr = sprintf('%02d:00:00', $hIni);
                    $horaFinStr = sprintf('%02d:00:00', $hFin);

                    foreach ($colDates as $col => $dateStr) {
                        $cellVal = trim((string)$sheet->getCell($col . $hr)->getValue());

                        // Si la celda está vacía pero pertenece a un rango combinado
                        if ($cellVal === '') {
                            foreach ($sheet->getMergeCells() as $range) {
                                if ($sheet->getCell($col . $hr)->isInRange($range)) {
                                    $topLeft = explode(':', $range)[0];
                                    $cellVal = trim((string)$sheet->getCell($topLeft)->getValue());
                                    break;
                                }
                            }
                        }

                        if ($cellVal === '') {
                            continue;
                        }

                        // Identificación estricta de FESTIVOS
                        if (stripos($cellVal, 'FESTIVO') !== false) {
                            $festivoSlots[$dateStr][] = [
                                'h_ini' => $hIni,
                                'h_fin' => $hFin
                            ];
                            continue;
                        }

                        // Clases regulares
                        $lines = preg_split('/[\r\n]+/', $cellVal);
                        $instructor = trim($lines[0] ?? '');
                        $area = trim($lines[1] ?? '');
                        if ($area !== '' && (strpos($area, '-') !== false || strpos($area, '–') !== false || strpos($area, '—') !== false)) {
                            $partesArea = preg_split('/[-–—]/u', $area);
                            $area = trim($partesArea[0] ?? '');
                        }

                        // Ignorar términos que no sean instructores
                        $ignorar = ['festivo', 'festivos', 'vacaciones', 'receso', 'sin instructor', 'por definir'];
                        if (in_array(mb_strtolower($instructor, 'UTF-8'), $ignorar, true)) {
                            continue;
                        }

                        if ($instructor !== '') {
                            $rawSlots[] = [
                                'fila'         => $hr,
                                'numero_ficha' => $numeroFicha !== '' ? $numeroFicha : '3064749',
                                'programa'     => $nombrePrograma !== '' ? $nombrePrograma : 'Formación SENA',
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
        }

        $clasesConsolidadas = $this->consolidarBloquesConsecutivos($rawSlots);
        $festivosConsolidados = $this->consolidarFestivos($festivoSlots);

        return [
            'clases'   => $clasesConsolidadas,
            'festivos' => $festivosConsolidados,
            'ficha'    => [
                'numero_ficha' => $numeroFicha,
                'programa'     => $nombrePrograma
            ]
        ];
    }

    /**
     * Une bloques horarios consecutivos para el mismo día, ficha e instructor.
     */
    private function consolidarBloquesConsecutivos(array $slots): array
    {
        if (empty($slots)) return [];

        $grupos = [];
        foreach ($slots as $slot) {
            $key = $slot['fecha'] . '_' . $slot['numero_ficha'] . '_' . mb_strtolower($slot['instructor'], 'UTF-8');
            $grupos[$key][] = $slot;
        }

        $resultado = [];
        foreach ($grupos as $lista) {
            usort($lista, fn($a, $b) => $a['h_ini_num'] <=> $b['h_ini_num']);

            $bloqueActual = null;
            foreach ($lista as $s) {
                if ($bloqueActual === null) {
                    $bloqueActual = $s;
                    continue;
                }

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

    /**
     * Consolida los marcadores de festivos para cada día.
     */
    private function consolidarFestivos(array $festivoSlots): array
    {
        $resultado = [];
        foreach ($festivoSlots as $fecha => $slots) {
            usort($slots, fn($a, $b) => $a['h_ini'] <=> $b['h_ini']);
            // En el calendario oficial SENA, cuando un día está marcado como FESTIVO,
            // corresponde a una jornada completa no lectiva (06:00 a 22:00) y es_dia_completo = 1,
            // garantizando que no se generen inasistencias en ninguna franja horaria de ese día.
            $resultado[] = [
                'fecha'           => $fecha,
                'hora_inicio'     => '06:00:00',
                'hora_fin'        => '22:00:00',
                'es_dia_completo' => 1,
                'descripcion'     => 'FESTIVO'
            ];
        }
        return $resultado;
    }

    /**
     * Formato tabular alternativo.
     */
    private function intentarTabular(Worksheet $sheet, int $maxRow, string $maxCol): array
    {
        for ($r = 1; $r <= min(15, $maxRow); $r++) {
            $row = $sheet->rangeToArray("A$r:$maxCol$r", null, true, true, false)[0];
            $map = [];
            foreach ($row as $idx => $cell) {
                if ($cell === null) continue;
                $val = mb_strtolower(trim((string)$cell), 'UTF-8');
                if (preg_match('/(n[uú]m.*ficha|n[uú]mero.*ficha|nro.*ficha|c[oó]digo.*ficha|^ficha$)/iu', $val)) $map['ficha'] = $idx;
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
                    $rawFicha = (string)($rowData[$map['ficha']] ?? '');
                    $ficha = trim(preg_replace('/[\x{00A0}\x{200B}\s]+/u', '', $rawFicha));
                    if ($ficha === '') continue;

                    $fechaRaw = trim((string)($rowData[$map['fecha']] ?? ''));
                    $fechaNormalizada = $this->normalizarFecha($fechaRaw);

                    $hEntrada = $this->normalizarHora((string)($rowData[$map['entrada']] ?? ''));
                    $hSalida  = $this->normalizarHora((string)($rowData[$map['salida']] ?? ''));

                    $rawProg = (string)($rowData[$map['programa'] ?? -1] ?? 'Formación SENA');
                    $programa = trim(html_entity_decode($rawProg, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    if ($programa === '') $programa = 'Formación SENA';

                    $rawInst = (string)($rowData[$map['instructor'] ?? -1] ?? '');
                    $instructor = trim(html_entity_decode($rawInst, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                    // Festivos en modo tabular
                    if (strcasecmp($instructor, 'FESTIVO') === 0) {
                        continue;
                    }

                    $registros[] = [
                        'fila'         => $rowNum,
                        'numero_ficha' => $ficha,
                        'programa'     => $programa,
                        'fecha'        => $fechaNormalizada,
                        'hora_entrada' => $hEntrada,
                        'hora_salida'  => $hSalida,
                        'instructor'   => $instructor,
                        'area'         => ''
                    ];
                }
                return $registros;
            }
        }

        return [];
    }

    /**
     * Exporta el horario oficial de una ficha conservando la estructura visual,
     * diseño, celdas combinadas y colores de la plantilla de referencia.
     */
    public function exportarOficial(int $idFicha, ?int $idInstructor = null, ?string $fechaDesde = null, ?string $fechaHasta = null): void
    {
        $ficha = $this->modelo->buscarFichaPorId($idFicha);
        if (!$ficha) {
            throw new RuntimeException("La ficha con ID {$idFicha} no existe.");
        }

        $horarios = $this->modelo->listarHorariosParaExportar($idFicha, $idInstructor, $fechaDesde, $fechaHasta);
        $festivos = $this->modelo->listarFestivos($idFicha, $fechaDesde, $fechaHasta);

        $templatePath = BASE_PATH . '/assets/templates/plantilla_horario_oficial.xlsx';
        if (!file_exists($templatePath)) {
            throw new RuntimeException("No se encontró la plantilla oficial de horarios en {$templatePath}.");
        }

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // 1. Identificación de la Ficha en Cabecera (Fila 2)
        $sheet->setCellValue('E2', $ficha['numero_ficha']);
        $sheet->setCellValue('H2', $ficha['nombre_programa']);

        // 2. Mapear horarios por [fecha][hora]
        $mapClases = [];
        foreach ($horarios as $h) {
            $fecha = $h['fecha'];
            $hIni = (int)explode(':', $h['hora_entrada_programada'])[0];
            $hFin = (int)explode(':', $h['hora_salida_programada'])[0];
            $texto = trim((string)$h['instructor_nombre']);
            if (!empty($h['instructor_area']) && trim((string)$h['instructor_area']) !== 'General') {
                $texto .= "\n" . trim((string)$h['instructor_area']);
            }
            for ($hr = $hIni; $hr < $hFin; $hr++) {
                $mapClases[$fecha][$hr] = $texto;
            }
        }

        // 3. Mapear festivos por [fecha][hora]
        $mapFestivos = [];
        foreach ($festivos as $f) {
            $fecha = $f['fecha'];
            $hIni = (int)explode(':', $f['hora_inicio'] ?? '06:00:00')[0];
            $hFin = (int)explode(':', $f['hora_fin'] ?? '22:00:00')[0];
            for ($hr = $hIni; $hr < $hFin; $hr++) {
                $mapFestivos[$fecha][$hr] = $f['descripcion'] ?? 'FESTIVO';
            }
        }

        // 4. Recorrer bloques de semanas y actualizar celdas
        $maxRow = $sheet->getHighestRow();
        $diasNombres = ['lunes', 'martes', 'miércoles', 'miercoles', 'jueves', 'viernes', 'sábado', 'sabado'];
        $meses = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
            'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
            'septiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12
        ];

        $currentMonth = 6;
        $currentYear = 2026;

        for ($r = 1; $r <= $maxRow; $r++) {
            $d = mb_strtolower(trim((string)$sheet->getCell('D' . $r)->getValue()), 'UTF-8');
            $f = mb_strtolower(trim((string)$sheet->getCell('F' . $r)->getValue()), 'UTF-8');

            if (in_array($d, $diasNombres, true) && in_array($f, $diasNombres, true)) {
                // Mes arriba
                for ($mr = max(1, $r - 3); $mr < $r; $mr++) {
                    foreach (['D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O'] as $mc) {
                        $mVal = mb_strtolower(trim((string)$sheet->getCell($mc . $mr)->getValue()), 'UTF-8');
                        foreach ($meses as $mNom => $mNum) {
                            if (str_contains($mVal, $mNom)) {
                                $currentMonth = $mNum;
                                break 2;
                            }
                        }
                    }
                }

                $dateRow = $r + 1;
                $colDates = [];
                $prevDayNum = 0;
                $activeMonth = $currentMonth;
                $activeYear = $currentYear;
                $hasAnyDate = false;

                foreach (['D', 'F', 'H', 'J', 'L', 'N'] as $c) {
                    $numVal = trim((string)$sheet->getCell($c . $dateRow)->getValue());
                    if (is_numeric($numVal)) {
                        $dNum = (int)$numVal;
                        if ($dNum >= 1 && $dNum <= 31) {
                            $hasAnyDate = true;
                            if ($prevDayNum > 20 && $dNum < 10) {
                                $activeMonth++;
                                if ($activeMonth > 12) {
                                    $activeMonth = 1;
                                    $activeYear++;
                                }
                            }
                            $prevDayNum = $dNum;
                            $colDates[$c] = sprintf('%04d-%02d-%02d', $activeYear, $activeMonth, $dNum);
                        }
                    }
                }

                if (!$hasAnyDate) continue;

                // Franjas horarias
                for ($hr = $dateRow + 1; $hr <= min($maxRow, $dateRow + 22); $hr++) {
                    $b = trim((string)$sheet->getCell('B' . $hr)->getValue());
                    $c = trim((string)$sheet->getCell('C' . $hr)->getValue());
                    if (!is_numeric($b) || !is_numeric($c)) continue;

                    $hour = (int)$b;

                    foreach ($colDates as $col => $dateStr) {
                        $cell = $col . $hr;
                        if (isset($mapFestivos[$dateStr][$hour])) {
                            $sheet->setCellValue($cell, $mapFestivos[$dateStr][$hour]);
                            $sheet->getStyle($cell)->getAlignment()->setWrapText(true);
                        } elseif (isset($mapClases[$dateStr][$hour])) {
                            $sheet->setCellValue($cell, $mapClases[$dateStr][$hour]);
                            $sheet->getStyle($cell)->getAlignment()->setWrapText(true);
                        } else {
                            $sheet->setCellValue($cell, '');
                        }
                    }
                }
            }
        }

        // Descarga directa HTTP
        $nombreSeguro = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $ficha['numero_ficha']);
        $nombreArchivo = 'HORARIO_' . $nombreSeguro . '_' . date('Ymd_His') . '.xlsx';

        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Descarga la plantilla oficial vacía/base para que el usuario pueda diligenciarla.
     */
    public function descargarPlantilla(): void
    {
        $templatePath = BASE_PATH . '/assets/templates/plantilla_horario_oficial.xlsx';
        if (!file_exists($templatePath)) {
            throw new RuntimeException("La plantilla oficial no se encuentra en el servidor.");
        }

        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="PLANTILLA_OFICIAL_HORARIO_SENA.xlsx"');
        header('Content-Length: ' . filesize($templatePath));
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        readfile($templatePath);
        exit;
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

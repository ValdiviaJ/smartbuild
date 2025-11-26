<?php

namespace App\Services;

class SoilAnalysisService
{
    private $knowledgeBase;
    private $knowledgeBasePath;

    // ✅ CONSTANTES NORMATIVAS (Normas Peruanas E.030 y E.050)
    const MIN_CAPACIDAD_PORTANTE = 300; // kPa mínimo para edificaciones
    const MIN_FACTOR_SEGURIDAD = 3.0; // Factor de seguridad mínimo
    const MAX_ASENTAMIENTO = 2.5; // cm máximo permitido

    public function __construct()
    {
        $this->knowledgeBasePath = storage_path('app/knowledge/knowledge_base_suelos.json');
        $this->loadKnowledgeBase();
    }

    private function loadKnowledgeBase()
    {
        if (!file_exists($this->knowledgeBasePath)) {
            throw new \Exception("Base de conocimientos no encontrada en: {$this->knowledgeBasePath}");
        }

        $jsonContent = file_get_contents($this->knowledgeBasePath);
        $this->knowledgeBase = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Error al decodificar JSON: " . json_last_error_msg());
        }
    }

    /**
     * ✅ ANÁLISIS INTELIGENTE COHERENTE
     */
    public function analizarSuelo(array $datos)
    {
        $tipoSuelo = $datos['tipo_suelo'] ?? null;
        
        if (!$tipoSuelo || !isset($this->knowledgeBase['tipos_suelo'][$tipoSuelo])) {
            return [
                'valido' => false,
                'mensaje' => 'Tipo de suelo no válido o no encontrado en la base de conocimientos'
            ];
        }

        $conocimiento = $this->knowledgeBase['tipos_suelo'][$tipoSuelo];
        
        // 1. Validar propiedades
        $validaciones = $this->validarPropiedades($datos, $conocimiento);
        
        // 2. Realizar cálculos
        $calculos = $this->realizarCalculosAvanzados($datos);
        
        // 3. Evaluar ALERTAS primero (determina criticidad)
        $alertas = $this->evaluarAlertas($datos, $calculos, $conocimiento);
        
        // 4. Generar recomendaciones BASADAS EN ALERTAS (COHERENCIA)
        $recomendaciones = $this->generarRecomendacionesInteligentes(
            $datos, 
            $conocimiento, 
            $calculos, 
            $alertas
        );
        
        // 5. Evaluar calidad
        $calidad = $this->evaluarCalidadAnalisis($datos, $validaciones);
        
        return [
            'valido' => true,
            'conocimiento_tipo_suelo' => $conocimiento,
            'validaciones' => $validaciones,
            'calculos_avanzados' => $calculos,
            'recomendaciones' => $recomendaciones,
            'alertas_criticas' => $alertas,
            'calidad_analisis' => $calidad,
        ];
    }

    /**
     * Validar propiedades según conocimientos técnicos
     */
    /**
 * Validar propiedades según conocimientos técnicos
 */
/**
 * Validar propiedades según conocimientos técnicos
 */
private function validarPropiedades(array $datos, array $conocimiento)
{
    $validaciones = [];
    $propiedades = $conocimiento['propiedades_tipicas'] ?? [];

    // ✅ PROTECCIÓN: Verificar que propiedades existen antes de acceder
    
    // Validar peso_unitario
    if (isset($datos['peso_unitario']) && is_numeric($datos['peso_unitario']) && $datos['peso_unitario'] !== '') {
        $peso = (float) $datos['peso_unitario'];
        
        $pesoMin = $propiedades['peso_unitario_min'] ?? 15;
        $pesoMax = $propiedades['peso_unitario_max'] ?? 22;
        
        $valido = $peso >= $pesoMin && $peso <= $pesoMax;
        
        $validaciones['peso_unitario'] = [
            'valido' => $valido,
            'valor' => $peso,
            'rango_esperado' => [
                'min' => $pesoMin,
                'max' => $pesoMax
            ],
            'mensaje' => $valido 
                ? 'Peso unitario dentro del rango esperado' 
                : 'ADVERTENCIA: Peso unitario fuera del rango típico para este tipo de suelo'
        ];
    }

    // Validar ángulo de fricción
    if (isset($datos['angulo_friccion']) && is_numeric($datos['angulo_friccion']) && $datos['angulo_friccion'] !== '') {
        $angulo = (float) $datos['angulo_friccion'];
        
        $anguloMin = $propiedades['angulo_friccion_min'] ?? 20;
        $anguloMax = $propiedades['angulo_friccion_max'] ?? 45;
        
        $valido = $angulo >= $anguloMin && $angulo <= $anguloMax;
        
        $validaciones['angulo_friccion'] = [
            'valido' => $valido,
            'valor' => $angulo,
            'rango_esperado' => [
                'min' => $anguloMin,
                'max' => $anguloMax
            ],
            'mensaje' => $valido 
                ? 'Ángulo de fricción adecuado' 
                : 'ADVERTENCIA: Ángulo de fricción inusual para este suelo'
        ];
    }

    // Validar capacidad portante
    if (isset($datos['capacidad_portante']) && is_numeric($datos['capacidad_portante']) && $datos['capacidad_portante'] !== '') {
        $capacidad = (float) $datos['capacidad_portante'];
        
        $capacidadMin = $propiedades['capacidad_portante_min'] ?? 100;
        $capacidadMax = $propiedades['capacidad_portante_max'] ?? 500;
        
        $valido = $capacidad >= $capacidadMin && $capacidad <= $capacidadMax;
        
        $validaciones['capacidad_portante'] = [
            'valido' => $valido,
            'valor' => $capacidad,
            'rango_esperado' => [
                'min' => $capacidadMin,
                'max' => $capacidadMax
            ],
            'mensaje' => $valido 
                ? 'Capacidad portante dentro del rango típico' 
                : 'ADVERTENCIA: Capacidad portante inusual - verificar ensayos'
        ];
    }

    // ✅ VALIDAR COHESIÓN CON MÁXIMA PROTECCIÓN
    if (isset($datos['cohesion']) && is_numeric($datos['cohesion']) && $datos['cohesion'] !== '') {
        $cohesion = (float) $datos['cohesion'];
        
        // ✅ Usar ?? para evitar Undefined index - proteger TODOS los accesos
        $cohesionRef = $propiedades['cohesion'] ?? null;
        $cohesionMin = $propiedades['cohesion_min'] ?? 0;
        $cohesionMax = $propiedades['cohesion_max'] ?? 100;
        
        // ✅ Si cohesion_ref es nulo o 0, usar el rango
        if (is_numeric($cohesionRef) && $cohesionRef > 0) {
            $valido = abs($cohesion - $cohesionRef) < 5;
            $mensaje = $valido 
                ? 'Cohesión acorde al tipo de suelo' 
                : 'ADVERTENCIA: Cohesión atípica para este tipo de suelo';
        } else {
            // Usar rango si cohesion_ref no existe o es 0
            $valido = $cohesion >= $cohesionMin && $cohesion <= $cohesionMax;
            $mensaje = $valido 
                ? 'Cohesión dentro del rango esperado' 
                : 'ADVERTENCIA: Cohesión fuera del rango típico';
        }
        
        $validaciones['cohesion'] = [
            'valido' => $valido,
            'valor' => $cohesion,
            'mensaje' => $mensaje
        ];
    }

    return $validaciones;
}
private function realizarCalculosAvanzados(array $datos)
{
    $calculos = [];

    // ✅ Asegurar que todos los valores tienen defaults y son numéricos
    $humedad = $this->obtenerValorNumerico($datos['humedad_natural'] ?? null, 15);
    $pesoUnitario = $this->obtenerValorNumerico($datos['peso_unitario'] ?? null, 18.5);
    $densidad = $this->obtenerValorNumerico($datos['densidad_bulk'] ?? null, 1.85);
    $angulo = $this->obtenerValorNumerico($datos['angulo_friccion'] ?? null, 30);
    $cohesion = $this->obtenerValorNumerico($datos['cohesion'] ?? null, 0); // ← IMPORTANTE
    $capacidad = $this->obtenerValorNumerico($datos['capacidad_portante'] ?? null, 200);
    $profundidad = $this->obtenerValorNumerico($datos['profundidad_sondeo'] ?? null, 10);
    $nivelFreatico = $this->obtenerValorNumerico($datos['nivel_freatico'] ?? null, 5);

    $calculos['indice_plasticidad'] = round($humedad * 0.15, 2);
    $calculos['esfuerzo_vertical_total'] = round($pesoUnitario * $profundidad, 2);

    $pesoSumergido = $pesoUnitario - 9.81;
    $esfuerzoEfectivo = ($pesoUnitario * ($profundidad - $nivelFreatico)) + 
                       ($pesoSumergido * $nivelFreatico);
    $calculos['esfuerzo_efectivo'] = round($esfuerzoEfectivo, 2);

    // ✅ Validar que $angulo no sea cero para tan()
    $anguloRad = deg2rad($angulo);
    $resistenciaCorte = $cohesion + ($esfuerzoEfectivo * tan($anguloRad));
    $calculos['resistencia_cortante'] = round($resistenciaCorte, 2);

    $cargaAplicada = $pesoUnitario * $profundidad;
    $calculos['factor_seguridad_portancia'] = $cargaAplicada > 0 
        ? round($capacidad / $cargaAplicada, 2) 
        : 0;

    $calculos['coeficiente_permeabilidad'] = round(0.01 * pow($densidad, 2), 6);
    $moduloElasticidad = 5000 * $cohesion + 20000 * ($angulo / 45);
    $calculos['modulo_elasticidad'] = round($moduloElasticidad, 2);

    $asentamiento = ($pesoUnitario * $profundidad) / (2 * (5000 + $angulo * 100));
    $calculos['asentamiento_estimado_cm'] = round($asentamiento, 3);

    $calculos['capacidad_portante_admisible'] = round($capacidad / 3, 2);

    if ($angulo < 35 && $cohesion < 50 && $nivelFreatico < $profundidad) {
        $potencialLicuefaccion = (35 - $angulo) / 35 * 100;
        $calculos['potencial_licuefaccion_percent'] = round($potencialLicuefaccion, 1);
    } else {
        $calculos['potencial_licuefaccion_percent'] = 0;
    }

    $nSPT = $capacidad / 10;
    $calculos['n_spt_estimado'] = round($nSPT, 0);
    $calculos['consistencia'] = $this->obtenerConsistenciaSPT($nSPT);

    if (isset($datos['tipo_suelo']) && in_array($datos['tipo_suelo'], ['Arcilla', 'Limo'])) {
        $tiempoConsolidacion = pow($profundidad, 2) / ($calculos['coeficiente_permeabilidad'] * 100);
        $calculos['tiempo_consolidacion_meses'] = round($tiempoConsolidacion / 30, 1);
    }

    return $calculos;
}

/**
 * ✅ NUEVA FUNCIÓN: Convertir valor a numérico con default
 */
private function obtenerValorNumerico($valor, $default = 0)
{
    // Si es null, string vacío o no es numérico
    if ($valor === null || $valor === '' || !is_numeric($valor)) {
        return (float) $default;
    }
    
    return (float) $valor;
}

    /**
     * ✅ EVALUAR ALERTAS PRIMERO
     */
    private function evaluarAlertas(array $datos, array $calculos, array $conocimiento)
    {
        $alertas = [];

        // 1. CAPACIDAD PORTANTE CRÍTICA
        if ($datos['capacidad_portante'] < self::MIN_CAPACIDAD_PORTANTE) {
            $severidad = $datos['capacidad_portante'] < 100 ? 'critica' : 'alta';
            $alertas[] = [
                'condicion' => 'capacidad_portante_baja',
                'severidad' => $severidad,
                'mensaje' => 'Capacidad portante insuficiente para construcción normal',
                'valor_detectado' => $datos['capacidad_portante'] . ' kPa',
                'recomendaciones' => [
                    'NO CONSTRUIR sin mejoramiento de suelo',
                    'Considerar cimentación profunda obligatoria',
                    'Evaluación por especialista geotécnico requerida',
                    'Análisis de alternativas de mejoramiento'
                ]
            ];
        }

        // 2. NIVEL FREÁTICO ALTO
        if (isset($datos['nivel_freatico']) && $datos['nivel_freatico'] < 2.0) {
            $alertas[] = [
                'condicion' => 'nivel_freatico_alto',
                'severidad' => 'alta',
                'mensaje' => 'Nivel freático muy superficial detectado',
                'valor_detectado' => $datos['nivel_freatico'] . ' m',
                'recomendaciones' => [
                    'Implementar sistema de drenaje perimetral',
                    'Considerar impermeabilización de cimentación',
                    'Evaluar flotación en sótanos',
                    'Usar concreto con aditivos impermeabilizantes'
                ]
            ];
        }

        // 3. LICUEFACCIÓN CRÍTICA
        if (isset($calculos['potencial_licuefaccion_percent']) && 
            $calculos['potencial_licuefaccion_percent'] > 40) {
            $alertas[] = [
                'condicion' => 'licuefaccion_alta',
                'severidad' => 'critica',
                'mensaje' => 'Potencial de licuefacción muy alto detectado',
                'valor_detectado' => $calculos['potencial_licuefaccion_percent'] . '%',
                'recomendaciones' => [
                    'Análisis de licuefacción detallado OBLIGATORIO',
                    'Considerar densificación del suelo',
                    'Implementar sistema de drenaje profundo',
                    'Evaluación de cimentación profunda'
                ]
            ];
        }

        // 4. ASENTAMIENTO EXCESIVO
        if (isset($calculos['asentamiento_estimado_cm']) && 
            $calculos['asentamiento_estimado_cm'] > self::MAX_ASENTAMIENTO) {
            $alertas[] = [
                'condicion' => 'asentamiento_alto',
                'severidad' => 'alta',
                'mensaje' => 'Asentamiento estimado muy alto detectado',
                'valor_detectado' => $calculos['asentamiento_estimado_cm'] . ' cm',
                'recomendaciones' => [
                    'Análisis detallado de consolidación requerido',
                    'Considerar precarga del terreno',
                    'Evaluar sistema de drenaje profundo'
                ]
            ];
        }

        // 5. SUELO EXPANSIVO
        if (isset($datos['clasificacion_suelo']) && 
            in_array($datos['clasificacion_suelo'], ['CH', 'MH']) && 
            $datos['capacidad_portante'] < 150) {
            $alertas[] = [
                'condicion' => 'suelo_expansivo',
                'severidad' => 'alta',
                'mensaje' => 'Potencial expansivo detectado en suelo cohesivo',
                'clasificacion' => $datos['clasificacion_suelo'],
                'recomendaciones' => [
                    'Análisis de potencial expansivo (ensayo de hinchamiento)',
                    'Considerar remoción de suelo expansivo',
                    'Implementar sistemas de control de humedad'
                ]
            ];
        }

        return $alertas;
    }

    /**
     * ✅ GENERAR RECOMENDACIONES COHERENTES CON ALERTAS
     */
    private function generarRecomendacionesInteligentes(
        array $datos, 
        array $conocimiento, 
        array $calculos, 
        array $alertas
    ) {
        $recomendaciones = [
            'tipo' => 'informativo',
            'prioridad' => 'media',
            'sugerencias' => [],
            'cimentacion' => [],
            'mejoras' => [],
            'monitoreo' => []
        ];

        // ✅ DETERMINAR CRITICIDAD BASADA EN ALERTAS
        $tieneCritica = false;
        $tieneAlta = false;

        foreach ($alertas as $alerta) {
            if ($alerta['severidad'] === 'critica') {
                $tieneCritica = true;
            } elseif ($alerta['severidad'] === 'alta') {
                $tieneAlta = true;
            }
        }

        // Asignar tipo y prioridad coherentes
        if ($tieneCritica) {
            $recomendaciones['tipo'] = 'critico';
            $recomendaciones['prioridad'] = 'critica';
        } elseif ($tieneAlta) {
            $recomendaciones['tipo'] = 'advertencia';
            $recomendaciones['prioridad'] = 'alta';
        } else {
            $recomendaciones['tipo'] = 'valido';
            $recomendaciones['prioridad'] = 'media';
        }

        // ✅ RECOMENDACIONES DE CIMENTACIÓN - COHERENTES CON CAPACIDAD
        if ($datos['capacidad_portante'] < 100) {
            $recomendaciones['cimentacion'] = [
                'Cimentación profunda (pilotes o pilas) OBLIGATORIA',
                'Análisis geotécnico detallado por especialista',
                'Evaluación de capacidad de carga de estratos profundos',
                'Diseño de sistema de mejoramiento o sustitución'
            ];
            $recomendaciones['mejoras'] = [
                'Sustitución de suelo débil con material de mejor calidad',
                'Sistema de inyecciones de cemento o químico',
                'Densificación mediante explosivos o vibración',
                'Consideración de micropilotes complementarios'
            ];
        } elseif ($datos['capacidad_portante'] < self::MIN_CAPACIDAD_PORTANTE) {
            $recomendaciones['cimentacion'] = [
                'Cimentación semiprofunda (vigas de cimentación)',
                'Zapatas corridas con análisis de asentamiento detallado',
                'Considerar cimentación profunda si hay restricciones',
                'Evaluación de mejoramiento de suelo'
            ];
            $recomendaciones['mejoras'] = [
                'Mejoramiento de suelo mediante compactación',
                'Consideración de estabilización química',
                'Análisis de precarga del terreno',
                'Inyecciones de lechada de cemento si aplica'
            ];
        } else {
            // ✅ Usar base de conocimientos pero validar coherencia
            $recomendaciones['cimentacion'] = $conocimiento['recomendaciones_cimentacion'] ?? [];
            $recomendaciones['mejoras'] = $conocimiento['mejoras_sugeridas'] ?? [];
        }

        // ✅ SUGERENCIAS DERIVADAS DE ALERTAS
        foreach ($alertas as $alerta) {
            switch ($alerta['condicion']) {
                case 'capacidad_portante_baja':
                    $recomendaciones['sugerencias'][] = 
                        "ALERTA CRÍTICA: Capacidad portante muy baja ({$datos['capacidad_portante']} kPa). " .
                        "No apto para cimentación superficial. Considerar cimentación profunda o mejoramiento.";
                    break;

                case 'nivel_freatico_alto':
                    $recomendaciones['sugerencias'][] = 
                        "Nivel freático muy superficial ({$datos['nivel_freatico']} m). " .
                        "Implementar sistema de drenaje y considerar impermeabilización.";
                    $recomendaciones['monitoreo'][] = "Monitoreo de nivel freático durante y post-construcción";
                    break;

                case 'licuefaccion_alta':
                    $recomendaciones['sugerencias'][] = 
                        "Potencial de licuefacción alto ({$calculos['potencial_licuefaccion_percent']}%). " .
                        "Análisis detallado de licuefacción requerido según norma E.030.";
                    $recomendaciones['monitoreo'][] = "Monitoreo sísmico durante construcción";
                    $recomendaciones['monitoreo'][] = "Verificación de densidad relativa in-situ";
                    break;

                case 'asentamiento_alto':
                    $recomendaciones['sugerencias'][] = 
                        "Asentamiento estimado de {$calculos['asentamiento_estimado_cm']} cm excede límites. " .
                        "Se requiere análisis detallado de consolidación.";
                    $recomendaciones['monitoreo'][] = "Monitoreo de asentamientos a largo plazo (mínimo 12 meses)";
                    break;

                case 'suelo_expansivo':
                    $recomendaciones['sugerencias'][] = 
                        "Potencial expansivo detectado ({$datos['clasificacion_suelo']}). " .
                        "Análisis de potencial expansivo requerido.";
                    $recomendaciones['monitoreo'][] = "Monitoreo de cambios de volumen (estaciones seca/lluviosa)";
                    break;
            }
        }

        // Factor de seguridad bajo
        if (isset($calculos['factor_seguridad_portancia']) && 
            $calculos['factor_seguridad_portancia'] < self::MIN_FACTOR_SEGURIDAD) {
            $recomendaciones['sugerencias'][] = 
                "Factor de seguridad bajo ({$calculos['factor_seguridad_portancia']}). " .
                "La norma E.050 requiere FS ≥ 3.0 para cargas estáticas.";
        }

        // Recomendaciones específicas por tipo
        if ($datos['tipo_suelo'] === 'Roca' && $datos['capacidad_portante'] > 0) {
            $recomendaciones['sugerencias'][] = 
                "Para roca: Verificar clasificación de roca intacta. " .
                "Realizado: estudios de calidad RQD (Rock Quality Designation).";
        }

        return $recomendaciones;
    }

    /**
     * Evaluar calidad del análisis
     */
    private function evaluarCalidadAnalisis(array $datos, array $validaciones)
    {
        $camposEvaluados = [
            'humedad_natural',
            'peso_unitario',
            'angulo_friccion',
            'cohesion',
            'capacidad_portante',
            'profundidad_sondeo',
            'nivel_freatico'
        ];

        $camposCompletos = 0;
        $camposValidos = 0;

        foreach ($camposEvaluados as $campo) {
            if (!empty($datos[$campo])) {
                $camposCompletos++;
                
                if (isset($validaciones[$campo]) && $validaciones[$campo]['valido']) {
                    $camposValidos++;
                }
            }
        }

        $porcentajeCompletitud = ($camposCompletos / count($camposEvaluados)) * 100;
        $porcentajeValidez = $camposCompletos > 0 ? ($camposValidos / $camposCompletos) * 100 : 0;

        if ($porcentajeCompletitud >= 85 && $porcentajeValidez >= 85) {
            return 'Excelente';
        } elseif ($porcentajeCompletitud >= 70 && $porcentajeValidez >= 70) {
            return 'Buena';
        } elseif ($porcentajeCompletitud >= 50) {
            return 'Regular';
        } else {
            return 'Deficiente';
        }
    }

    /**
     * Obtener consistencia según N-SPT
     */
    private function obtenerConsistenciaSPT($nSPT)
    {
        $correlacion = $this->knowledgeBase['correlaciones_empiricas']['spt_capacidad_portante'];
        
        foreach ($correlacion['rangos'] as $rango) {
            if ($nSPT >= $rango['n_min'] && $nSPT <= $rango['n_max']) {
                return $rango['consistencia'];
            }
        }

        return 'No determinada';
    }

    public function obtenerInfoTipoSuelo($tipoSuelo)
    {
        return $this->knowledgeBase['tipos_suelo'][$tipoSuelo] ?? null;
    }

    public function obtenerTodosTiposSuelo()
    {
        return array_keys($this->knowledgeBase['tipos_suelo']);
    }

    public function obtenerNormasTecnicas()
    {
        return $this->knowledgeBase['normas_tecnicas'] ?? [];
    }
}
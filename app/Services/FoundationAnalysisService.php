<?php

namespace App\Services;

use App\Models\AnalisisSuelo;

class FoundationAnalysisService
{
    // CONSTANTES NORMATIVAS (Norma E.050)
    const FACTOR_SEGURIDAD_MINIMO = 1.0;
    const FACTOR_SEGURIDAD_RECOMENDADO = 2.5;
    const ASENTAMIENTO_MAXIMO_MM = 25.0;
    const PORCENTAJE_ACERO_MINIMO = 0.8;
    const PORCENTAJE_ACERO_MAXIMO = 3.0;

    /**
     * Analizar y optimizar diseño de cimentación
     */
    public function analizarCimentacion(array $datos, ?AnalisisSuelo $analisisSuelo = null)
    {
        // 1. Calcular área de apoyo si no se proporciona
        if (isset($datos['ancho']) && isset($datos['largo']) && !isset($datos['area_apoyo'])) {
            $datos['area_apoyo'] = $datos['ancho'] * $datos['largo'];
        }

        // 2. Calcular presión portante admisible
        if (isset($datos['carga_diseno_total']) && isset($datos['area_apoyo']) && $datos['area_apoyo'] > 0) {
            $presion = $datos['carga_diseno_total'] / $datos['area_apoyo'];
            $datos['presion_portante_admisible'] = round($presion, 2);
        }

        // 3. Validar factor de seguridad
        $validaciones = $this->validarFactorSeguridad($datos);

        // 4. Calcular optimización
        $optimizacion = $this->calcularOptimizacion($datos, $analisisSuelo);

        // 5. Generar recomendaciones
        $recomendaciones = $this->generarRecomendaciones($datos, $analisisSuelo, $validaciones, $optimizacion);

        // 6. Calcular volumen de concreto si falta
        if (!isset($datos['volumen_concreto']) && $this->puedeCalcularVolumen($datos)) {
            $datos['volumen_concreto'] = $this->calcularVolumenConcreto($datos);
        }

        return [
            'valido' => $validaciones['es_valido'],
            'datos_calculados' => $datos,
            'validaciones' => $validaciones,
            'optimizacion' => $optimizacion,
            'recomendaciones' => $recomendaciones,
            'alertas_criticas' => $this->evaluarAlertas($datos, $validaciones),
        ];
    }

    /**
     * Validar factor de seguridad
     */
    private function validarFactorSeguridad(array $datos): array
    {
        $validaciones = [
            'es_valido' => true,
            'factor_seguridad' => [],
            'asentamiento' => [],
            'porcentaje_acero' => [],
        ];

        // Validar factor de seguridad
        if (isset($datos['factor_seguridad'])) {
            $fs = (float) $datos['factor_seguridad'];
            
            if ($fs < self::FACTOR_SEGURIDAD_MINIMO) {
                $validaciones['es_valido'] = false;
                $validaciones['factor_seguridad'] = [
                    'valido' => false,
                    'valor' => $fs,
                    'minimo_requerido' => self::FACTOR_SEGURIDAD_MINIMO,
                    'mensaje' => 'CRÍTICO: Factor de seguridad por debajo del mínimo permitido (E.050)',
                ];
            } elseif ($fs < self::FACTOR_SEGURIDAD_RECOMENDADO) {
                $validaciones['factor_seguridad'] = [
                    'valido' => true,
                    'valor' => $fs,
                    'recomendado' => self::FACTOR_SEGURIDAD_RECOMENDADO,
                    'mensaje' => 'ADVERTENCIA: Factor de seguridad por debajo del valor recomendado',
                ];
            } else {
                $validaciones['factor_seguridad'] = [
                    'valido' => true,
                    'valor' => $fs,
                    'mensaje' => 'Factor de seguridad adecuado',
                ];
            }
        }

        // Validar asentamiento
        if (isset($datos['asentamiento_mm'])) {
            $asentamiento = (float) $datos['asentamiento_mm'];
            
            if ($asentamiento > self::ASENTAMIENTO_MAXIMO_MM) {
                $validaciones['es_valido'] = false;
                $validaciones['asentamiento'] = [
                    'valido' => false,
                    'valor' => $asentamiento,
                    'maximo_permitido' => self::ASENTAMIENTO_MAXIMO_MM,
                    'mensaje' => 'CRÍTICO: Asentamiento excede el límite permitido',
                ];
            } else {
                $validaciones['asentamiento'] = [
                    'valido' => true,
                    'valor' => $asentamiento,
                    'mensaje' => 'Asentamiento dentro de límites aceptables',
                ];
            }
        }

        // Validar porcentaje de acero
        if (isset($datos['porcentaje_acero'])) {
            $acero = (float) $datos['porcentaje_acero'];
            
            if ($acero < self::PORCENTAJE_ACERO_MINIMO || $acero > self::PORCENTAJE_ACERO_MAXIMO) {
                $validaciones['porcentaje_acero'] = [
                    'valido' => false,
                    'valor' => $acero,
                    'rango' => [self::PORCENTAJE_ACERO_MINIMO, self::PORCENTAJE_ACERO_MAXIMO],
                    'mensaje' => 'ADVERTENCIA: Porcentaje de acero fuera del rango recomendado',
                ];
            } else {
                $validaciones['porcentaje_acero'] = [
                    'valido' => true,
                    'valor' => $acero,
                    'mensaje' => 'Porcentaje de acero adecuado',
                ];
            }
        }

        return $validaciones;
    }

    /**
     * Calcular optimización de costos
     */
    private function calcularOptimizacion(array $datos, ?AnalisisSuelo $analisisSuelo): array
    {
        $optimizacion = [
            'esta_optimizada' => false,
            'potencial_ahorro' => 0,
            'sugerencias' => [],
        ];

        // Si no hay análisis de suelo, no se puede optimizar
        if (!$analisisSuelo) {
            return $optimizacion;
        }

        $capacidadSuelo = $analisisSuelo->capacidad_portante ?? 0;
        $presionAplicada = $datos['presion_portante_admisible'] ?? 0;

        // 1. Sobredimensionamiento
        if ($capacidadSuelo > 0 && $presionAplicada > 0) {
            $ratio = $capacidadSuelo / $presionAplicada;
            
            if ($ratio > 1.5) {
                $ahorroEstimado = ($ratio - 1.5) * 10; // Estimación simplificada
                $optimizacion['potencial_ahorro'] = min($ahorroEstimado, 30);
                $optimizacion['sugerencias'][] = "Cimentación posiblemente sobredimensionada. Considerar reducir dimensiones.";
            } else {
                $optimizacion['esta_optimizada'] = true;
            }
        }

        // 2. Tipo de cimentación vs capacidad del suelo
        $tipo = $datos['tipo_cimentacion'] ?? '';
        
        if ($capacidadSuelo > 300 && in_array($tipo, ['Pilotes', 'Plateas'])) {
            $optimizacion['potencial_ahorro'] += 15;
            $optimizacion['sugerencias'][] = "Con capacidad portante alta, considerar zapatas aisladas (más económicas).";
        }

        // 3. Volumen de concreto excesivo
        if (isset($datos['volumen_concreto']) && isset($datos['area_apoyo'])) {
            $espesorPromedio = $datos['volumen_concreto'] / $datos['area_apoyo'];
            
            if ($espesorPromedio > 1.5) {
                $optimizacion['potencial_ahorro'] += 10;
                $optimizacion['sugerencias'][] = "Espesor de cimentación alto. Evaluar reducción de altura.";
            }
        }

        $optimizacion['potencial_ahorro'] = round($optimizacion['potencial_ahorro'], 1);

        return $optimizacion;
    }

    /**
     * Generar recomendaciones inteligentes
     */
    private function generarRecomendaciones(array $datos, ?AnalisisSuelo $analisisSuelo, array $validaciones, array $optimizacion): array
    {
        $recomendaciones = [
            'tipo' => 'informativo',
            'prioridad' => 'media',
            'diseno' => [],
            'construccion' => [],
            'control_calidad' => [],
            'optimizacion' => $optimizacion['sugerencias'] ?? [],
        ];

        // Determinar criticidad
        if (!$validaciones['es_valido']) {
            $recomendaciones['tipo'] = 'critico';
            $recomendaciones['prioridad'] = 'critica';
        }

        // Recomendaciones según tipo de cimentación
        $tipo = $datos['tipo_cimentacion'] ?? '';
        
        switch ($tipo) {
            case 'Zapata Aislada':
                $recomendaciones['diseno'][] = "Verificar separación mínima entre zapatas (3 veces el ancho)";
                $recomendaciones['construccion'][] = "Compactar base de apoyo antes de vaciar concreto";
                $recomendaciones['control_calidad'][] = "Verificar recubrimiento mínimo: 7.5 cm para concreto en contacto con suelo";
                break;
                
            case 'Zapata Corrida':
                $recomendaciones['diseno'][] = "Considerar juntas de dilatación cada 15-20 metros";
                $recomendaciones['construccion'][] = "Verificar nivelación continua del fondo de excavación";
                $recomendaciones['control_calidad'][] = "Control de asentamiento diferencial entre secciones";
                break;
                
            case 'Losa de Cimentación':
                $recomendaciones['diseno'][] = "Diseñar sistema de drenaje perimetral";
                $recomendaciones['diseno'][] = "Considerar vigas de rigidez si área > 200 m²";
                $recomendaciones['construccion'][] = "Usar concreto premezclado para garantizar uniformidad";
                $recomendaciones['control_calidad'][] = "Monitoreo de asentamiento en puntos críticos";
                break;
                
            case 'Pilotes':
                $recomendaciones['diseno'][] = "Realizar prueba de carga en pilote de prueba";
                $recomendaciones['diseno'][] = "Verificar capacidad de grupo de pilotes (eficiencia de grupo)";
                $recomendaciones['construccion'][] = "Supervisar verticalidad durante hincado/perforación";
                $recomendaciones['control_calidad'][] = "Ensayo de integridad de pilotes (PIT o Cross-hole)";
                break;
        }

        // Recomendaciones según análisis de suelo
        if ($analisisSuelo) {
            if ($analisisSuelo->riesgo_licuefaccion === 'alto') {
                $recomendaciones['diseno'][] = "CRÍTICO: Implementar mejoramiento de suelo o cimentación profunda";
            }
            
            if ($analisisSuelo->nivel_freatico && $analisisSuelo->nivel_freatico < 3.0) {
                $recomendaciones['construccion'][] = "Implementar sistema de bombeo durante excavación";
                $recomendaciones['construccion'][] = "Usar concreto con aditivos impermeabilizantes";
            }
            
            if ($analisisSuelo->capacidad_portante < 200) {
                $recomendaciones['diseno'][] = "Considerar precarga del terreno antes de construcción";
            }
        }

        // Recomendaciones según factor de seguridad
        if (!$validaciones['factor_seguridad']['valido']) {
            $recomendaciones['diseno'][] = "URGENTE: Rediseñar cimentación para aumentar factor de seguridad";
        }

        return $recomendaciones;
    }

    /**
     * Evaluar alertas críticas
     */
    private function evaluarAlertas(array $datos, array $validaciones): array
    {
        $alertas = [];

        // Alerta de factor de seguridad bajo
        if (isset($validaciones['factor_seguridad']) && !$validaciones['factor_seguridad']['valido']) {
            $alertas[] = [
                'severidad' => 'critica',
                'condicion' => 'factor_seguridad_bajo',
                'mensaje' => 'Factor de seguridad insuficiente - RIESGO DE FALLA ESTRUCTURAL',
                'valor' => $validaciones['factor_seguridad']['valor'],
                'accion_requerida' => 'Rediseño obligatorio antes de construcción',
            ];
        }

        // Alerta de asentamiento excesivo
        if (isset($validaciones['asentamiento']) && !$validaciones['asentamiento']['valido']) {
            $alertas[] = [
                'severidad' => 'alta',
                'condicion' => 'asentamiento_excesivo',
                'mensaje' => 'Asentamiento estimado excede límites normativos',
                'valor' => $validaciones['asentamiento']['valor'] . ' mm',
                'accion_requerida' => 'Ajustar dimensiones o considerar mejoramiento de suelo',
            ];
        }

        // Alerta de presión portante alta
        if (isset($datos['presion_portante_admisible']) && isset($datos['capacidad_suelo'])) {
            $ratio = $datos['presion_portante_admisible'] / $datos['capacidad_suelo'];
            
            if ($ratio > 0.8) {
                $alertas[] = [
                    'severidad' => 'media',
                    'condicion' => 'presion_alta',
                    'mensaje' => 'Presión aplicada cercana a capacidad del suelo',
                    'ratio' => round($ratio * 100, 1) . '%',
                    'accion_requerida' => 'Considerar aumentar área de cimentación',
                ];
            }
        }

        return $alertas;
    }

    /**
     * Calcular volumen de concreto
     */
    private function calcularVolumenConcreto(array $datos): float
    {
        $tipo = $datos['tipo_cimentacion'] ?? '';
        
        if ($tipo === 'Pilotes' && isset($datos['longitud_pilote'], $datos['diametro_pilote'], $datos['numero_pilotes'])) {
            $radio = $datos['diametro_pilote'] / 2;
            $volumenPorPilote = pi() * pow($radio, 2) * $datos['longitud_pilote'];
            return round($volumenPorPilote * $datos['numero_pilotes'], 2);
        }
        
        if (isset($datos['area_apoyo'], $datos['profundidad'])) {
            return round($datos['area_apoyo'] * $datos['profundidad'], 2);
        }
        
        return 0;
    }

    /**
     * Verificar si se puede calcular volumen
     */
    private function puedeCalcularVolumen(array $datos): bool
    {
        $tipo = $datos['tipo_cimentacion'] ?? '';
        
        if ($tipo === 'Pilotes') {
            return isset($datos['longitud_pilote'], $datos['diametro_pilote'], $datos['numero_pilotes']);
        }
        
        return isset($datos['area_apoyo'], $datos['profundidad']);
    }
}
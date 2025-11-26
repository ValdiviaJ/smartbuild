<?php

namespace App\Services;

use App\Models\Cimentacion;

class StructuralAnalysisService
{
    // CONSTANTES NORMATIVAS (Norma E.030)
    const FLECHA_MAXIMA_MM = 25.0;
    const CUMPLIMIENTO_MINIMO = 90.0;
    const FACTOR_SEGURIDAD_MINIMO_DISEÑO = 1.5;
    const ESFUERZO_COMPRESION_MAXIMO = 280.0; // MPa

    /**
     * Analizar y optimizar diseño estructural
     */
    public function analizarDiseño(array $datos, ?Cimentacion $cimentacion = null)
    {
        // 1. Validar cargas y esfuerzos
        $validaciones = $this->validarDiseño($datos);

        // 2. Calcular optimización
        $optimizacion = $this->calcularOptimizacion($datos, $cimentacion);

        // 3. Generar recomendaciones
        $recomendaciones = $this->generarRecomendaciones($datos, $validaciones, $optimizacion);

        // 4. Evaluar alertas
        $alertas = $this->evaluarAlertas($datos, $validaciones);

        // 5. Calcular cumplimiento de norma
        $cumplimiento = $this->calcularCumplimientoNorma($datos, $validaciones);

        return [
            'valido' => $validaciones['es_valido'],
            'datos_calculados' => $datos,
            'validaciones' => $validaciones,
            'optimizacion' => $optimizacion,
            'recomendaciones' => $recomendaciones,
            'alertas_criticas' => $alertas,
            'cumplimiento_norma' => $cumplimiento,
        ];
    }

    /**
     * Validar diseño estructural
     */
    private function validarDiseño(array $datos): array
    {
        $validaciones = [
            'es_valido' => true,
            'cargas' => [],
            'flecha' => [],
            'esfuerzo' => [],
            'elementos' => [],
        ];

        // Validar cargas mínimas positivas
        if (!isset($datos['carga_muerta']) || $datos['carga_muerta'] <= 0) {
            $validaciones['es_valido'] = false;
            $validaciones['cargas'][] = [
                'valido' => false,
                'campo' => 'carga_muerta',
                'mensaje' => 'Carga muerta debe ser mayor a 0',
            ];
        }

        if (!isset($datos['carga_viva']) || $datos['carga_viva'] <= 0) {
            $validaciones['es_valido'] = false;
            $validaciones['cargas'][] = [
                'valido' => false,
                'campo' => 'carga_viva',
                'mensaje' => 'Carga viva debe ser mayor a 0',
            ];
        }

        // Validar flecha máxima
        if (isset($datos['flecha_maxima_mm'])) {
            $flecha = (float) $datos['flecha_maxima_mm'];
            
            if ($flecha > self::FLECHA_MAXIMA_MM) {
                $validaciones['es_valido'] = false;
                $validaciones['flecha'] = [
                    'valido' => false,
                    'valor' => $flecha,
                    'maximo_permitido' => self::FLECHA_MAXIMA_MM,
                    'mensaje' => 'CRÍTICO: Flecha máxima excede límite normativo (E.030)',
                ];
            } else {
                $validaciones['flecha'] = [
                    'valido' => true,
                    'valor' => $flecha,
                    'mensaje' => 'Flecha dentro de límites aceptables',
                ];
            }
        }

        // Validar esfuerzo de compresión
        if (isset($datos['esfuerzo_compresion'])) {
            $esfuerzo = (float) $datos['esfuerzo_compresion'];
            
            if ($esfuerzo > self::ESFUERZO_COMPRESION_MAXIMO) {
                $validaciones['es_valido'] = false;
                $validaciones['esfuerzo'] = [
                    'valido' => false,
                    'valor' => $esfuerzo,
                    'maximo_permitido' => self::ESFUERZO_COMPRESION_MAXIMO,
                    'mensaje' => 'CRÍTICO: Esfuerzo de compresión excede capacidad del concreto',
                ];
            } else {
                $validaciones['esfuerzo'] = [
                    'valido' => true,
                    'valor' => $esfuerzo,
                    'mensaje' => 'Esfuerzo de compresión adecuado',
                ];
            }
        }

        // Validar elementos estructurales
        if (isset($datos['cantidad_vigas']) && $datos['cantidad_vigas'] <= 0) {
            $validaciones['elementos'][] = [
                'valido' => false,
                'campo' => 'cantidad_vigas',
                'mensaje' => 'Debe haber al menos una viga',
            ];
        }

        if (isset($datos['cantidad_columnas']) && $datos['cantidad_columnas'] <= 0) {
            $validaciones['elementos'][] = [
                'valido' => false,
                'campo' => 'cantidad_columnas',
                'mensaje' => 'Debe haber al menos una columna',
            ];
        }

        // Validar relaciones geométricas
        if (isset($datos['luz_viga'], $datos['peralte_viga'])) {
            $relacion = $datos['luz_viga'] / $datos['peralte_viga'];
            
            if ($relacion > 25) {
                $validaciones['elementos'][] = [
                    'valido' => false,
                    'campo' => 'relacion_luz_peralte',
                    'valor' => round($relacion, 2),
                    'mensaje' => 'Relación luz/peralte muy alta. Considerar aumentar peralte.',
                ];
            }
        }

        return $validaciones;
    }

    /**
     * Calcular optimización
     */
    private function calcularOptimizacion(array $datos, ?Cimentacion $cimentacion): array
    {
        $optimizacion = [
            'esta_optimizada' => false,
            'potencial_ahorro' => 0,
            'sugerencias' => [],
        ];

        $potencial = 0;

        // 1. Analizar redundancia de elementos
        if (isset($datos['cantidad_vigas'])) {
            if ($datos['cantidad_vigas'] > 20) {
                $potencial += 8;
                $optimizacion['sugerencias'][] = 'Número de vigas elevado. Considerar optimizar espaciamiento.';
            }
        }

        // 2. Analizar sobredimensionamiento
        if (isset($datos['peralte_viga'], $datos['luz_viga'])) {
            $relacion = $datos['luz_viga'] / $datos['peralte_viga'];
            
            if ($relacion < 10) {
                $potencial += 12;
                $optimizacion['sugerencias'][] = 'Viga posiblemente sobredimensionada. Reducir peralte.';
            }
        }

        // 3. Analizar sección de columnas
        if (isset($datos['dimension_columna_1'], $datos['dimension_columna_2'])) {
            $area = $datos['dimension_columna_1'] * $datos['dimension_columna_2'];
            
            if ($area > 0.5) {
                $potencial += 10;
                $optimizacion['sugerencias'][] = 'Sección de columna grande. Considerar reducción.';
            }
        }

        // 4. Análisis de losas
        if (isset($datos['espesor_losa'])) {
            if ($datos['espesor_losa'] > 0.30) {
                $potencial += 8;
                $optimizacion['sugerencias'][] = 'Espesor de losa elevado. Evaluar reducción a 0.20-0.25m.';
            }
        }

        // 5. Análisis de momento vs cortante
        if (isset($datos['momento_maximo'], $datos['cortante_maximo'])) {
            $ratio = $datos['cortante_maximo'] / ($datos['momento_maximo'] + 0.01);
            
            if ($ratio < 0.15) {
                $potencial += 5;
                $optimizacion['sugerencias'][] = 'Estructura controlada por momento. Optimizar para cortante.';
            }
        }

        $optimizacion['potencial_ahorro'] = min($potencial, 35);
        $optimizacion['esta_optimizada'] = $potencial <= 15;

        return $optimizacion;
    }

    /**
     * Generar recomendaciones inteligentes
     */
    private function generarRecomendaciones(array $datos, array $validaciones, array $optimizacion): array
    {
        $recomendaciones = [
            'tipo' => 'informativo',
            'prioridad' => 'media',
            'diseno' => [],
            'construccion' => [],
            'control_calidad' => [],
        ];

        // Determinar criticidad
        if (!$validaciones['es_valido']) {
            $recomendaciones['tipo'] = 'critico';
            $recomendaciones['prioridad'] = 'critica';
        }

        // Recomendaciones según tipo de estructura
        $tipo = $datos['tipo_estructura'] ?? '';
        
        switch ($tipo) {
            case 'Pórtico de Concreto Armado':
                $recomendaciones['diseno'][] = 'Verificar capacidad de momento en nudos críticos';
                $recomendaciones['diseno'][] = 'Usar estribos densificados en zonas sísmicas (Z3, Z4)';
                $recomendaciones['construccion'][] = 'Emplear concreto de alta calidad (f\'c ≥ 280 kg/cm²)';
                $recomendaciones['control_calidad'][] = 'Inspeccionar colocación de acero en zona de rótulas plásticas';
                break;
                
            case 'Viga Cajón de Concreto Pretensado':
                $recomendaciones['diseno'][] = 'Calcular pérdidas de pretensado (fricción y anclaje)';
                $recomendaciones['diseno'][] = 'Verificar tensiones en fase de transporte';
                $recomendaciones['construccion'][] = 'Control de tensión de cables durante vaciado';
                $recomendaciones['control_calidad'][] = 'Ensayo de pull-out en cables de pretensado';
                break;
                
            case 'Estructura Metálica':
                $recomendaciones['diseno'][] = 'Verificar conexiones mediante soldadura o pernos';
                $recomendaciones['diseno'][] = 'Considerar pandeo local y global';
                $recomendaciones['construccion'][] = 'Usar soldadores certificados AWS/ASME';
                $recomendaciones['control_calidad'][] = 'Ensayo de radiografía en soldaduras críticas';
                break;
                
            case 'Muros de Corte':
                $recomendaciones['diseno'][] = 'Dimensionar refuerzo distribuido en ambas caras';
                $recomendaciones['diseno'][] = 'Considerar efecto de volteo por sismo';
                $recomendaciones['construccion'][] = 'Vaciado monolítico sin juntas horizontales';
                $recomendaciones['control_calidad'][] = 'Verificar posición de armadura con equipos de escaneo';
                break;
        }

        // Recomendaciones por zona sísmica
        if (isset($datos['zona_sismica'])) {
            if (in_array($datos['zona_sismica'], ['Z3', 'Z4'])) {
                $recomendaciones['diseno'][] = 'Aplicar factor sísmico ampliado según Z' . substr($datos['zona_sismica'], 1);
                $recomendaciones['diseno'][] = 'Considerar amortiguadores sísmicos en estructura';
            }
        }

        // Recomendaciones por esfuerzo
        if (isset($validaciones['esfuerzo']) && !$validaciones['esfuerzo']['valido']) {
            $recomendaciones['diseno'][] = 'URGENTE: Aumentar sección de elementos para reducir esfuerzo';
        }

        // Recomendaciones por flecha
        if (isset($validaciones['flecha']) && !$validaciones['flecha']['valido']) {
            $recomendaciones['diseno'][] = 'URGENTE: Aumentar peralte de vigas para limitar flecha';
        }

        return $recomendaciones;
    }

    /**
     * Evaluar alertas críticas
     */
    private function evaluarAlertas(array $datos, array $validaciones): array
    {
        $alertas = [];

        // Alerta de flecha excesiva
        if (isset($validaciones['flecha']) && !$validaciones['flecha']['valido']) {
            $alertas[] = [
                'severidad' => 'critica',
                'condicion' => 'flecha_excesiva',
                'mensaje' => 'Flecha máxima excede límite normativo - Riesgo de vibraciones y daños',
                'valor' => $validaciones['flecha']['valor'] . ' mm',
                'accion_requerida' => 'Rediseño de vigas - Aumentar peralte',
            ];
        }

        // Alerta de esfuerzo excesivo
        if (isset($validaciones['esfuerzo']) && !$validaciones['esfuerzo']['valido']) {
            $alertas[] = [
                'severidad' => 'critica',
                'condicion' => 'esfuerzo_excesivo',
                'mensaje' => 'Esfuerzo de compresión excede capacidad del concreto',
                'valor' => $validaciones['esfuerzo']['valor'] . ' MPa',
                'accion_requerida' => 'Aumentar sección de elementos o mejorar calidad del concreto',
            ];
        }

        // Alerta de relación luz-peralte alta
        if (isset($datos['luz_viga'], $datos['peralte_viga'])) {
            $relacion = $datos['luz_viga'] / ($datos['peralte_viga'] + 0.001);
            
            if ($relacion > 25) {
                $alertas[] = [
                    'severidad' => 'alta',
                    'condicion' => 'relacion_alta',
                    'mensaje' => 'Relación luz/peralte muy alta - Posible vibración excesiva',
                    'valor' => 'L/h = ' . round($relacion, 1),
                    'accion_requerida' => 'Aumentar peralte de viga',
                ];
            }
        }

        // Alerta de carga viva muy baja
        if (isset($datos['carga_viva']) && $datos['carga_viva'] < 1.5) {
            $alertas[] = [
                'severidad' => 'media',
                'condicion' => 'carga_viva_baja',
                'mensaje' => 'Carga viva baja - Verificar normativa aplicable',
                'valor' => $datos['carga_viva'] . ' kN/m²',
                'accion_requerida' => 'Revisar tabla de cargas de la Norma E.020',
            ];
        }

        return $alertas;
    }

    /**
     * Calcular cumplimiento de norma
     */
    private function calcularCumplimientoNorma(array $datos, array $validaciones): float
    {
        $puntuacion = 100.0;

        // Penalizar por validaciones fallidas
        if (isset($validaciones['flecha']) && !$validaciones['flecha']['valido']) {
            $puntuacion -= 20;
        }

        if (isset($validaciones['esfuerzo']) && !$validaciones['esfuerzo']['valido']) {
            $puntuacion -= 25;
        }

        if (!empty($validaciones['cargas'])) {
            $puntuacion -= count($validaciones['cargas']) * 5;
        }

        if (!empty($validaciones['elementos'])) {
            $puntuacion -= count($validaciones['elementos']) * 3;
        }

        // Bonificar por buenas prácticas
        if (isset($datos['zona_sismica']) && in_array($datos['zona_sismica'], ['Z3', 'Z4'])) {
            if (isset($datos['factor_sismico']) && $datos['factor_sismico'] >= 0.35) {
                $puntuacion += 5;
            }
        }

        // Asegurar que esté entre 0 y 100
        return max(0, min(100, round($puntuacion, 1)));
    }
}
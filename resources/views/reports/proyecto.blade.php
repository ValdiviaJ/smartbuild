<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Reporte de Proyecto: {{ $proyecto->nombre }}</title>
    <style>
        body { font-family: sans-serif; color: #333; line-height: 1.5; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #2c3e50; padding-bottom: 10px; }
        .title { font-size: 20px; font-weight: bold; color: #2c3e50; text-transform: uppercase; }
        .subtitle { font-size: 12px; color: #7f8c8d; }
        
        .section { margin-bottom: 20px; page-break-inside: avoid; }
        .section-title { 
            font-size: 14px; font-weight: bold; color: #fff; background-color: #2c3e50; 
            padding: 5px 10px; margin-bottom: 10px; border-radius: 3px;
        }
        
        .item-card { border: 1px solid #eee; padding: 10px; margin-bottom: 10px; background-color: #fcfcfc; }
        .item-header { font-weight: bold; color: #2980b9; border-bottom: 1px solid #eee; margin-bottom: 8px; padding-bottom: 3px; }
        
        .grid-2 { width: 100%; }
        .grid-2 td { width: 50%; vertical-align: top; padding: 2px; }
        
        .label { font-weight: bold; color: #555; font-size: 11px; }
        .value { color: #333; }
        
        /* Gráficos CSS Simples */
        .chart-container { margin-top: 5px; margin-bottom: 10px; }
        .bar-label { font-size: 10px; color: #666; display: flex; justify-content: space-between; margin-bottom: 2px; }
        .bar-bg { background-color: #ecf0f1; height: 8px; border-radius: 4px; overflow: hidden; width: 100%; }
        .bar-fill { height: 100%; border-radius: 4px; }
        .bar-blue { background-color: #3498db; }
        .bar-green { background-color: #2ecc71; }
        .bar-orange { background-color: #e67e22; }
        .bar-red { background-color: #e74c3c; }
        
        .recommendation-box { 
            background-color: #e8f6f3; border-left: 3px solid #1abc9c; 
            padding: 8px; margin-top: 8px; font-size: 11px; color: #16a085; 
        }
        
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #eee; padding-top: 5px; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">SmartBuild IA</div>
        <div class="subtitle">Reporte Técnico Detallado - {{ $proyecto->nombre }}</div>
    </div>

    <!-- Información General -->
    <div class="section">
        <div class="section-title">1. Información General del Proyecto</div>
        <table class="grid-2">
            <tr>
                <td>
                    <span class="label">Ubicación:</span> <span class="value">{{ $proyecto->ubicacion ?? 'No especificada' }}</span><br>
                    <span class="label">Tipo:</span> <span class="value">{{ ucfirst($proyecto->tipo_proyecto ?? 'N/A') }}</span><br>
                    <span class="label">Estado:</span> <span class="value">{{ ucfirst(str_replace('_', ' ', $proyecto->estado)) }}</span>
                </td>
                <td>
                    <span class="label">Área Total:</span> <span class="value">{{ $proyecto->area_total ? $proyecto->area_total . ' m²' : 'N/A' }}</span><br>
                    <span class="label">Pisos:</span> <span class="value">{{ $proyecto->numero_pisos ?? 'N/A' }}</span><br>
                    <span class="label">Responsable:</span> <span class="value">{{ $proyecto->usuario->nombre ?? 'N/A' }}</span>
                </td>
            </tr>
        </table>
        @if($proyecto->descripcion)
            <div style="margin-top: 8px; font-size: 11px; color: #666; font-style: italic;">
                "{{ $proyecto->descripcion }}"
            </div>
        @endif
    </div>

    @if($include_details)
        
        <!-- Análisis de Suelo -->
        @if($proyecto->analisisSuelo->count() > 0)
            <div class="section">
                <div class="section-title">2. Análisis de Suelo ({{ $proyecto->analisisSuelo->count() }})</div>
                @foreach($proyecto->analisisSuelo as $index => $analisis)
                    <div class="item-card">
                        <div class="item-header">Análisis #{{ $index + 1 }} - {{ $analisis->tipo_suelo }}</div>
                        <table class="grid-2">
                            <tr>
                                <td>
                                    <span class="label">Clasificación:</span> {{ $analisis->clasificacion_suelo ?? 'N/A' }}<br>
                                    <span class="label">Nivel Freático:</span> {{ $analisis->nivel_freatico }} m<br>
                                    <span class="label">Profundidad Sondeo:</span> {{ $analisis->profundidad_sondeo }} m
                                </td>
                                <td>
                                    <span class="label">Ángulo Fricción:</span> {{ $analisis->angulo_friccion }}°<br>
                                    <span class="label">Cohesión:</span> {{ $analisis->cohesion }} kg/cm²<br>
                                    <span class="label">Riesgo Licuefacción:</span> {{ ucfirst($analisis->riesgo_licuefaccion) }}
                                </td>
                            </tr>
                        </table>

                        <!-- Gráfico de Capacidad Portante -->
                        <div class="chart-container">
                            <div class="bar-label">
                                <span>Capacidad Portante: {{ $analisis->capacidad_portante }} kg/cm²</span>
                                <span>(Ref: 4.0 kg/cm²)</span>
                            </div>
                            <div class="bar-bg">
                                @php $width = min(($analisis->capacidad_portante / 4.0) * 100, 100); @endphp
                                <div class="bar-fill bar-blue" style="width: {{ $width }}%;"></div>
                            </div>
                        </div>

                        @php
                            $rec = is_string($analisis->recomendaciones_ia) ? json_decode($analisis->recomendaciones_ia, true) : $analisis->recomendaciones_ia;
                        @endphp

                        @if($rec)
                            <div class="recommendation-box">
                                <div style="border-bottom: 1px solid #16a085; padding-bottom: 3px; margin-bottom: 5px;">
                                    <strong>💡 Análisis Inteligente</strong>
                                    @if(isset($rec['tipo']))
                                        <span style="float: right; font-size: 9px; padding: 2px 6px; border-radius: 3px; color: white; background-color: {{ $rec['tipo'] == 'critico' ? '#c0392b' : ($rec['tipo'] == 'advertencia' ? '#e67e22' : '#27ae60') }}">
                                            {{ strtoupper($rec['tipo']) }}
                                        </span>
                                    @endif
                                </div>

                                @if(is_array($rec))
                                    <table style="width: 100%; border-collapse: collapse;">
                                        @foreach(['sugerencias' => 'Sugerencias', 'cimentacion' => 'Cimentación', 'mejoras' => 'Mejoras', 'monitoreo' => 'Monitoreo'] as $key => $label)
                                            @if(isset($rec[$key]) && is_array($rec[$key]) && count($rec[$key]) > 0)
                                                <tr>
                                                    <td style="vertical-align: top; width: 80px; font-weight: bold; font-size: 10px; color: #16a085; padding: 2px 0;">{{ $label }}:</td>
                                                    <td style="padding: 2px 0;">
                                                        <ul style="margin: 0; padding-left: 15px; list-style-type: disc;">
                                                            @foreach($rec[$key] as $item)
                                                                <li style="margin-bottom: 1px;">{{ $item }}</li>
                                                            @endforeach
                                                        </ul>
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </table>
                                @else
                                    {{ $analisis->recomendaciones_ia }}
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <!-- Cimentaciones -->
        @if($proyecto->cimentaciones->count() > 0)
            <div class="page-break"></div>
            <div class="section">
                <div class="section-title">3. Diseño de Cimentaciones ({{ $proyecto->cimentaciones->count() }})</div>
                @foreach($proyecto->cimentaciones as $index => $cim)
                    <div class="item-card">
                        <div class="item-header">Diseño #{{ $index + 1 }} - {{ $cim->tipo_cimentacion }} ({{ $cim->subtipo ?? 'Estándar' }})</div>
                        <table class="grid-2">
                            <tr>
                                <td>
                                    <span class="label">Dimensiones:</span> {{ $cim->ancho }}m x {{ $cim->largo }}m<br>
                                    <span class="label">Profundidad:</span> {{ $cim->profundidad }} m<br>
                                    <span class="label">Área Apoyo:</span> {{ $cim->area_apoyo }} m²
                                </td>
                                <td>
                                    <span class="label">Carga Diseño:</span> {{ $cim->carga_diseno_total }} ton<br>
                                    <span class="label">Volumen Concreto:</span> {{ $cim->volumen_concreto }} m³<br>
                                    <span class="label">Asentamiento:</span> {{ $cim->asentamiento_mm }} mm
                                </td>
                            </tr>
                        </table>

                        <!-- Gráfico de Factor de Seguridad -->
                        <div class="chart-container">
                            <div class="bar-label">
                                <span>Factor de Seguridad: {{ $cim->factor_seguridad }}</span>
                                <span>(Mínimo: 3.0)</span>
                            </div>
                            <div class="bar-bg">
                                @php 
                                    $fsWidth = min(($cim->factor_seguridad / 5.0) * 100, 100); 
                                    $fsColor = $cim->factor_seguridad >= 3.0 ? 'bar-green' : 'bar-red';
                                @endphp
                                <div class="bar-fill {{ $fsColor }}" style="width: {{ $fsWidth }}%;"></div>
                            </div>
                        </div>

                        @php
                            $recCim = is_string($cim->recomendaciones_ia) ? json_decode($cim->recomendaciones_ia, true) : $cim->recomendaciones_ia;
                        @endphp

                        @if($recCim)
                            <div class="recommendation-box">
                                <div style="border-bottom: 1px solid #16a085; padding-bottom: 3px; margin-bottom: 5px;">
                                    <strong>💡 Análisis Inteligente</strong>
                                    @if(isset($recCim['tipo']))
                                        <span style="float: right; font-size: 9px; padding: 2px 6px; border-radius: 3px; color: white; background-color: {{ $recCim['tipo'] == 'critico' ? '#c0392b' : ($recCim['tipo'] == 'advertencia' ? '#e67e22' : '#27ae60') }}">
                                            {{ strtoupper($recCim['tipo']) }}
                                        </span>
                                    @endif
                                </div>

                                @if(is_array($recCim))
                                    <table style="width: 100%; border-collapse: collapse;">
                                        @foreach(['sugerencias' => 'Sugerencias', 'optimizacion' => 'Optimización', 'riesgos' => 'Riesgos Detectados'] as $key => $label)
                                            @if(isset($recCim[$key]) && is_array($recCim[$key]) && count($recCim[$key]) > 0)
                                                <tr>
                                                    <td style="vertical-align: top; width: 80px; font-weight: bold; font-size: 10px; color: #16a085; padding: 2px 0;">{{ $label }}:</td>
                                                    <td style="padding: 2px 0;">
                                                        <ul style="margin: 0; padding-left: 15px; list-style-type: disc;">
                                                            @foreach($recCim[$key] as $item)
                                                                <li style="margin-bottom: 1px;">{{ $item }}</li>
                                                            @endforeach
                                                        </ul>
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                        
                                        <!-- Fallback para estructura genérica si las claves específicas no existen -->
                                        @if(!isset($recCim['sugerencias']) && !isset($recCim['optimizacion']))
                                            @foreach($recCim as $key => $val)
                                                @if(is_array($val) && $key != 'tipo' && $key != 'prioridad')
                                                    <tr>
                                                        <td style="vertical-align: top; width: 80px; font-weight: bold; font-size: 10px; color: #16a085; padding: 2px 0;">{{ ucfirst($key) }}:</td>
                                                        <td style="padding: 2px 0;">
                                                            <ul style="margin: 0; padding-left: 15px; list-style-type: disc;">
                                                                @foreach($val as $item)
                                                                    <li style="margin-bottom: 1px;">{{ is_string($item) ? $item : json_encode($item) }}</li>
                                                                @endforeach
                                                            </ul>
                                                        </td>
                                                    </tr>
                                                @endif
                                            @endforeach
                                        @endif
                                    </table>
                                @else
                                    {{ $cim->recomendaciones_ia }}
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <!-- Diseño Estructural -->
        @if($proyecto->disenosEstructurales->count() > 0)
            <div class="section">
                <div class="section-title">4. Análisis Estructural ({{ $proyecto->disenosEstructurales->count() }})</div>
                @foreach($proyecto->disenosEstructurales as $index => $est)
                    <div class="item-card">
                        <div class="item-header">Estructura #{{ $index + 1 }} - {{ $est->tipo_estructura }}</div>
                        <table class="grid-2">
                            <tr>
                                <td>
                                    <span class="label">Código Diseño:</span> {{ $est->codigo_diseno }}<br>
                                    <span class="label">Zona Sísmica:</span> {{ $est->zona_sismica }} (Factor: {{ $est->factor_sismico }})<br>
                                    <span class="label">Cargas:</span> Muerta: {{ $est->carga_muerta }} | Viva: {{ $est->carga_viva }}
                                </td>
                                <td>
                                    <span class="label">Elementos:</span> {{ $est->cantidad_columnas }} Columnas, {{ $est->cantidad_vigas }} Vigas<br>
                                    <span class="label">Flecha Máx:</span> {{ $est->flecha_maxima_mm }} mm<br>
                                    <span class="label">Estado:</span> {{ ucfirst($est->estado_diseno) }}
                                </td>
                            </tr>
                        </table>

                        <!-- Gráfico de Cumplimiento -->
                        <div class="chart-container">
                            <div class="bar-label">
                                <span>Cumplimiento Normativo: {{ $est->cumplimiento_norma }}%</span>
                            </div>
                            <div class="bar-bg">
                                <div class="bar-fill bar-blue" style="width: {{ $est->cumplimiento_norma }}%;"></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <!-- Estimaciones de Costo -->
        @if($proyecto->estimacionesCosto->count() > 0)
            <div class="page-break"></div>
            <div class="section">
                <div class="section-title">5. Estimación de Costos</div>
                @foreach($proyecto->estimacionesCosto as $index => $costo)
                    <div class="item-card">
                        <div class="item-header">Estimación #{{ $index + 1 }} - {{ $costo->tipo_estimacion ?? 'General' }}</div>
                        
                        <!-- Gráfico de Distribución de Costos -->
                        <div style="margin-bottom: 15px;">
                            <div class="bar-label" style="margin-bottom: 5px;">Distribución de Costos</div>
                            @php
                                $total = $costo->costo_total > 0 ? $costo->costo_total : 1;
                                $pMat = ($costo->costo_materiales / $total) * 100;
                                $pMano = ($costo->costo_mano_obra / $total) * 100;
                                $pEquip = ($costo->alquiler_equipos / $total) * 100;
                                $pOtros = 100 - $pMat - $pMano - $pEquip;
                            @endphp
                            <div style="display: flex; height: 15px; border-radius: 4px; overflow: hidden; width: 100%;">
                                <div style="width: {{ $pMat }}%; background-color: #3498db;" title="Materiales"></div>
                                <div style="width: {{ $pMano }}%; background-color: #e67e22;" title="Mano de Obra"></div>
                                <div style="width: {{ $pEquip }}%; background-color: #9b59b6;" title="Equipos"></div>
                                <div style="width: {{ $pOtros }}%; background-color: #95a5a6;" title="Otros"></div>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 9px; margin-top: 3px; color: #555;">
                                <span><span style="color: #3498db;">●</span> Mat: {{ number_format($pMat, 1) }}%</span>
                                <span><span style="color: #e67e22;">●</span> MO: {{ number_format($pMano, 1) }}%</span>
                                <span><span style="color: #9b59b6;">●</span> Eq: {{ number_format($pEquip, 1) }}%</span>
                            </div>
                        </div>

                        <table class="grid-2">
                            <tr>
                                <td>
                                    <span class="label">Materiales:</span> {{ number_format($costo->costo_materiales, 2) }}<br>
                                    <span class="label">Mano de Obra:</span> {{ number_format($costo->costo_mano_obra, 2) }}<br>
                                    <span class="label">Equipos:</span> {{ number_format($costo->alquiler_equipos, 2) }}
                                </td>
                                <td>
                                    <span class="label">Subtotal:</span> {{ number_format($costo->subtotal, 2) }}<br>
                                    <span class="label">Impuestos:</span> {{ number_format($costo->impuestos, 2) }}<br>
                                    <span class="label" style="font-size: 12px; color: #2c3e50;">TOTAL:</span> 
                                    <strong style="font-size: 12px;">{{ number_format($costo->costo_total, 2) }} {{ $costo->moneda }}</strong>
                                </td>
                            </tr>
                        </table>
                    </div>
                @endforeach
            </div>
        @endif

    @endif

    <div class="footer">
        Generado por: {{ $generado_por }} | Fecha: {{ $fecha_generacion }} | SmartBuild IA - Sistema Experto de Construcción
    </div>
</body>
</html>

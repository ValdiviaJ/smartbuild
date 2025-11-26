<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstimacionCosto extends Model
{
    protected $table = 'estimaciones_costo';
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'proyecto_id',
        'diseño_estructural_id',
        'tipo_estimacion',
        'costo_materiales',
        'costo_mano_obra',
        'alquiler_equipos',
        'gastos_generales',
        'contingencia',
        'subtotal',
        'impuestos',
        'moneda',
        'tasa_cambio',
        'sugerencias_optimizacion_costo',
        'ahorros_estimados',
        'optimizacion_aplicada',
        'creado_por',
    ];

    protected $casts = [
        'optimizacion_aplicada' => 'boolean',
        'costo_materiales' => 'float',
        'costo_mano_obra' => 'float',
        'alquiler_equipos' => 'float',
        'gastos_generales' => 'float',
        'contingencia' => 'float',
        'subtotal' => 'float',
        'impuestos' => 'float',
        'ahorros_estimados' => 'float',
        'tasa_cambio' => 'float',
    ];

    protected $appends = ['costo_total'];

    public function getCostoTotalAttribute()
    {
        return ($this->subtotal ?? 0) + ($this->impuestos ?? 0);
    }

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function disenoEstructural()
    {
        return $this->belongsTo(DisenoEstructural::class, 'diseño_estructural_id');
    }
}
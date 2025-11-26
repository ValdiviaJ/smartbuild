<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalculoRapido extends Model
{
    protected $table = 'calculos_rapidos';

    // Configurar timestamps en español
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'usuario_id',
        'tipo_calculo',
        'datos_entrada',
        'datos_salida',
        'tiempo_calculo_ms',
        'es_temporal',
        'puede_convertir_proyecto',
    ];

    protected $casts = [
        'datos_entrada' => 'array',
        'datos_salida' => 'array',
        'es_temporal' => 'boolean',
        'puede_convertir_proyecto' => 'boolean',
        'tiempo_calculo_ms' => 'integer',
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
    ];

    // Relación con usuario
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
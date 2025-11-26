<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalisisSuelo extends Model
{
    use HasFactory;

    protected $table = 'analisis_suelo';
    
    // CRÍTICO: Especificar los nombres correctos de las columnas de timestamp
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'proyecto_id',
        'calculo_rapido_id',
        'tipo_suelo',
        'clasificacion_suelo',
        'humedad_natural',
        'peso_unitario',
        'densidad_bulk',
        'razon_porosidad',
        'angulo_friccion',
        'cohesion',
        'capacidad_portante',
        'riesgo_licuefaccion',
        'tasa_consolidacion',
        'profundidad_sondeo',
        'nivel_freatico',
        'recomendaciones_ia',
        'calidad_analisis',
        'creado_por',
        'actualizado_por',
    ];

    protected $casts = [
        'humedad_natural' => 'float',
        'peso_unitario' => 'float',
        'densidad_bulk' => 'float',
        'razon_porosidad' => 'float',
        'angulo_friccion' => 'float',
        'cohesion' => 'float',
        'capacidad_portante' => 'float',
        'tasa_consolidacion' => 'float',
        'profundidad_sondeo' => 'float',
        'nivel_freatico' => 'float',
        'recomendaciones_ia' => 'json',
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
    ];

    /**
     * Un análisis pertenece a un proyecto
     */
    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    /**
     * Usuario que creó el análisis
     */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    /**
     * Usuario que actualizó el análisis
     */
    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'actualizado_por');
    }

    /**
     * Scope: Filtrar por usuario autenticado
     */
    public function scopeDelUsuario($query)
    {
        return $query->whereHas('proyecto', function ($q) {
            $q->where('usuario_id', auth()->id());
        });
    }

    /**
     * Scope: Filtrar por tipo de suelo
     */
    public function scopePorTipoSuelo($query, $tipo)
    {
        return $query->where('tipo_suelo', $tipo);
    }

    /**
     * Scope: Filtrar por riesgo
     */
    public function scopePorRiesgo($query, $riesgo)
    {
        return $query->where('riesgo_licuefaccion', $riesgo);
    }

    /**
     * Obtener análisis recientes
     */
    public static function recientes($limit = 10)
    {
        return static::delUsuario()
            ->orderBy('creado_en', 'desc')
            ->limit($limit)
            ->get();
    }
}
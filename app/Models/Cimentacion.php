<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cimentacion extends Model
{
    use HasFactory;

    protected $table = 'cimentaciones';
    
    // CRÍTICO: Especificar los nombres correctos de las columnas de timestamp
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'proyecto_id',
        'analisis_suelo_id',
        'tipo_cimentacion',
        'subtipo',
        'profundidad',
        'ancho',
        'largo',
        'area_apoyo',
        'carga_diseno_total',
        'presion_portante_admisible',
        'factor_seguridad',
        'asentamiento_mm',
        'grado_concreto',
        'volumen_concreto',
        'porcentaje_acero',
        'longitud_pilote',
        'diametro_pilote',
        'numero_pilotes',
        'recomendaciones_ia',
        'esta_optimizada',
        'potencial_optimizacion_costo',
        'creado_por',
        'actualizado_por',
    ];

    protected $casts = [
        'profundidad' => 'float',
        'ancho' => 'float',
        'largo' => 'float',
        'area_apoyo' => 'float',
        'carga_diseno_total' => 'float',
        'presion_portante_admisible' => 'float',
        'factor_seguridad' => 'float',
        'asentamiento_mm' => 'float',
        'volumen_concreto' => 'float',
        'porcentaje_acero' => 'float',
        'longitud_pilote' => 'float',
        'diametro_pilote' => 'float',
        'numero_pilotes' => 'integer',
        'esta_optimizada' => 'boolean',
        'potencial_optimizacion_costo' => 'float',
        'recomendaciones_ia' => 'json',
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
    ];

    /**
     * Una cimentación pertenece a un proyecto
     */
    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    /**
     * Una cimentación pertenece a un análisis de suelo
     */
    public function analisisSuelo(): BelongsTo
    {
        return $this->belongsTo(AnalisisSuelo::class, 'analisis_suelo_id');
    }

    /**
     * Usuario que creó la cimentación
     */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    /**
     * Usuario que actualizó la cimentación
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
     * Scope: Filtrar por tipo de cimentación
     */
    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo_cimentacion', $tipo);
    }

    /**
     * Scope: Filtrar optimizadas
     */
    public function scopeOptimizadas($query)
    {
        return $query->where('esta_optimizada', true);
    }

    /**
     * Obtener cimentaciones recientes
     */
    public static function recientes($limit = 10)
    {
        return static::delUsuario()
            ->with(['proyecto', 'analisisSuelo'])
            ->orderBy('creado_en', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Calcular área de apoyo automáticamente
     */
    public function calcularAreaApoyo()
    {
        if ($this->ancho && $this->largo) {
            $this->area_apoyo = $this->ancho * $this->largo;
        }
        return $this;
    }

    /**
     * Validar factor de seguridad
     */
    public function validarFactorSeguridad(): bool
    {
        return $this->factor_seguridad >= 1.0;
    }
}
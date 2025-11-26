<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisenoEstructural extends Model
{
    use HasFactory;

    protected $table = 'diseños_estructurales';
    
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'proyecto_id',
        'cimentacion_id',
        'tipo_estructura',
        'codigo_diseno',
        'carga_muerta',
        'carga_viva',
        'carga_viento',
        'zona_sismica',
        'factor_sismico',
        'cantidad_vigas',
        'luz_viga',
        'peralte_viga',
        'ancho_viga',
        'acero_superior_viga',
        'acero_inferior_viga',
        'cantidad_columnas',
        'altura_columna',
        'seccion_columna',
        'dimension_columna_1',
        'dimension_columna_2',
        'acero_columna',
        'cantidad_losas',
        'espesor_losa',
        'acero_losa_x',
        'acero_losa_y',
        'tipo_losa',
        'momento_maximo',
        'cortante_maximo',
        'flecha_maxima_mm',
        'esfuerzo_compresion',
        'optimizacion_ia_aplicada',
        'estado_diseno',
        'cumplimiento_norma',
        'creado_por',
        'actualizado_por',
    ];

    protected $casts = [
        'carga_muerta' => 'float',
        'carga_viva' => 'float',
        'carga_viento' => 'float',
        'factor_sismico' => 'float',
        'cantidad_vigas' => 'integer',
        'luz_viga' => 'float',
        'peralte_viga' => 'float',
        'ancho_viga' => 'float',
        'cantidad_columnas' => 'integer',
        'altura_columna' => 'float',
        'dimension_columna_1' => 'float',
        'dimension_columna_2' => 'float',
        'cantidad_losas' => 'integer',
        'espesor_losa' => 'float',
        'momento_maximo' => 'float',
        'cortante_maximo' => 'float',
        'flecha_maxima_mm' => 'float',
        'esfuerzo_compresion' => 'float',
        'optimizacion_ia_aplicada' => 'boolean',
        'cumplimiento_norma' => 'float',
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function cimentacion(): BelongsTo
    {
        return $this->belongsTo(Cimentacion::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'actualizado_por');
    }

    public function scopeDelUsuario($query)
    {
        return $query->whereHas('proyecto', function ($q) {
            $q->where('usuario_id', auth()->id());
        });
    }

    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo_estructura', $tipo);
    }

    public function scopeOptimizadas($query)
    {
        return $query->where('optimizacion_ia_aplicada', true);
    }

    public static function recientes($limit = 10)
    {
        return static::delUsuario()
            ->with(['proyecto', 'cimentacion'])
            ->orderBy('creado_en', 'desc')
            ->limit($limit)
            ->get();
    }

    public function validarCumplimientoNorma(): bool
    {
        return $this->cumplimiento_norma >= 90;
    }

    public function validarFlecha(): bool
    {
        return $this->flecha_maxima_mm <= 25.0;
    }
}
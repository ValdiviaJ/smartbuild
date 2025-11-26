<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    protected $table = 'materiales';
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'codigo', 'nombre', 'categoria', 'peso_unitario', 'resistencia_traccion',
        'resistencia_compresion', 'modulo_elasticidad', 'razon_poisson', 'coeficiente_termico',
        'unidad_medida', 'precio_base', 'moneda', 'proveedor_id', 'cantidad_disponible',
        'nivel_stock_minimo', 'activo', 'calificacion_sostenibilidad', 'certificaciones'
    ];

    protected $casts = [
        'activo' => 'boolean',
        'certificaciones' => 'json',
    ];

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeStockBajo($query)
    {
        return $query->whereRaw('cantidad_disponible <= nivel_stock_minimo');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proyecto extends Model
{
    use HasFactory;

    protected $table = 'proyectos';
    
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'usuario_id',
        'nombre',
        'descripcion',
        'tipo_proyecto',
        'estado',
        'ubicacion',
        'latitud',
        'longitud',
        'area_total',
        'numero_pisos',
        'carga_maxima',
        'creado_por',
        'actualizado_por',
    ];

    protected $casts = [
        'latitud' => 'float',
        'longitud' => 'float',
        'area_total' => 'float',
        'carga_maxima' => 'float',
        'numero_pisos' => 'integer',
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
    ];

    /**
     * Un proyecto pertenece a un usuario
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /**
     * Usuario que creó el proyecto
     */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    /**
     * Usuario que actualizó el proyecto
     */
    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'actualizado_por');
    }

    /**
     * Un proyecto tiene muchos análisis de suelo
     */
    public function analisisSuelo(): HasMany
    {
        return $this->hasMany(AnalisisSuelo::class);
    }

    /**
     * Scope: Proyectos del usuario autenticado
     */
    public function scopeDelUsuario($query)
    {
        // Si es admin, puede ver todo (opcional, pero por ahora seguimos la regla estricta del usuario)
        // La lógica de admin se puede manejar en el controlador o aquí si tenemos acceso al rol
        return $query->where('usuario_id', auth()->id());
    }

    /**
     * Scope: Proyectos activos
     */
    public function scopeActivos($query)
    {
        return $query->whereIn('estado', ['borrador', 'en_proceso']);
    }

    /**
     * Scope: Proyectos por estado
     */
    public function scopePorEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    /**
     * Scope: Búsqueda por texto (nombre, ubicación)
     */
    public function scopeBuscar($query, $texto)
    {
        if (!$texto) return $query;
        
        return $query->where(function($q) use ($texto) {
            $q->where('nombre', 'like', "%{$texto}%")
              ->orWhere('ubicacion', 'like', "%{$texto}%")
              ->orWhere('descripcion', 'like', "%{$texto}%");
        });
    }

    // Relaciones con módulos técnicos

    public function cimentaciones(): HasMany
    {
        return $this->hasMany(Cimentacion::class);
    }

    public function disenosEstructurales(): HasMany
    {
        return $this->hasMany(DisenoEstructural::class, 'proyecto_id');
    }

    public function estimacionesCosto(): HasMany
    {
        return $this->hasMany(EstimacionCosto::class, 'proyecto_id');
    }

    /*
    public function versiones(): HasMany
    {
        return $this->hasMany(VersionesProyecto::class, 'proyecto_id');
    }

    public function historialCalculos(): HasMany
    {
        return $this->hasMany(HistorialCalculo::class, 'proyecto_id');
    }
    */
}
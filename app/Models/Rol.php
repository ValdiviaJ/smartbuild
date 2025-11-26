<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    protected $table = 'roles';

    // Configurar timestamps en español
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'nombre',
        'descripcion',
        'permisos',
    ];

    protected $casts = [
        'permisos' => 'array',
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
    ];

    // Relación con usuarios
    public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'rol_id');
    }
}
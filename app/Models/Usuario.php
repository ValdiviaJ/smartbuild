<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Usuario extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $table = 'usuarios';

    // IMPORTANTE: Configurar timestamps en español
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'nombre',
        'correo',
        'contraseña',
        'telefono',
        'rol_id',
        'empresa',
        'licencia_profesional',
        'activo',
        'ultimo_acceso',
        'correo_verificado',
        'token_recordar',
        'metadatos',
    ];

    protected $hidden = [
        'contraseña',
        'token_recordar',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'correo_verificado' => 'datetime',
        'ultimo_acceso' => 'datetime',
        'metadatos' => 'array',
    ];

    // IMPORTANTE: Sobreescribir métodos de autenticación para usar 'contraseña' en lugar de 'password'
    public function getAuthPassword()
    {
        return $this->contraseña;
    }

    public function getAuthIdentifierName()
    {
        return 'correo';
    }

    // JWT
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    // ELIMINAR EL MUTATOR - No hashear automáticamente
    // Lo haremos manualmente en el controlador cuando sea necesario

    // Relaciones
    public function rol()
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function proyectos()
    {
        return $this->hasMany(Proyecto::class, 'usuario_id');
    }

    public function calculosRapidos()
    {
        return $this->hasMany(CalculoRapido::class, 'usuario_id');
    }

    // Scope para usuarios activos
    public function scopeActivos($query)
    {
        return $query->where('activo', 1);
    }

    // Método helper para verificar contraseña
    public function verificarContraseña($contraseña)
    {
        return Hash::check($contraseña, $this->contraseña);
    }
}
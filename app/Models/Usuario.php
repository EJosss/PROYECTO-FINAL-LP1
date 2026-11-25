<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * Modelo de usuarios del panel administrativo (ADMINISTRADOR y MARKETING).
 * Incluye autenticación en dos pasos (2FA) mediante el trait TwoFactorAuthenticatable de Fortify.
 */
class Usuario extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $table = 'usuarios';

    // Roles permitidos en el sistema (validados en el middleware VerificarRol)
    public const ROL_ADMINISTRADOR = 'ADMINISTRADOR';
    public const ROL_MARKETING = 'MARKETING';

    // Preferencias de apariencia visual del panel administrativo
    public const TEMA_CLARO = 'claro';
    public const TEMA_OSCURO = 'oscuro';

    // Campos que se permiten asignar masivamente (protección contra Mass Assignment)
    protected $fillable = [
        'nombre',
        'correo',
        'password',
        'rol',
        'activo',
        'tema',
    ];

    // Campos sensibles ocultos al serializar a JSON/arrays (nunca se exponen secretos ni contraseñas)
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    // Conversiones automáticas: 'hashed' encripta la contraseña con bcrypt automáticamente
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'activo' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function esAdministrador(): bool
    {
        return $this->rol === self::ROL_ADMINISTRADOR;
    }

    public function esMarketing(): bool
    {
        return $this->rol === self::ROL_MARKETING;
    }

    public function usaTemaOscuro(): bool
    {
        return $this->tema === self::TEMA_OSCURO;
    }

    public function rankingsCreados(): HasMany
    {
        return $this->hasMany(RankingMensual::class, 'creado_por');
    }

    public function rankingsActualizados(): HasMany
    {
        return $this->hasMany(RankingMensual::class, 'actualizado_por');
    }
}

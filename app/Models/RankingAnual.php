<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cabecera del Ranking Anual ("Top Anual de Asesores RE/MAX Zona").
 * Independiente de rankings_mensuales para no alterar sus triggers ni reglas de días 1–3.
 */
class RankingAnual extends Model
{
    protected $table = 'rankings_anuales';

    public const ESTADO_BORRADOR = 'BORRADOR';
    public const ESTADO_PUBLICADO = 'PUBLICADO';

    protected $fillable = [
        'anio',
        'estado',
        'fecha_publicacion',
        'creado_por',
        'actualizado_por',
    ];

    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'fecha_publicacion' => 'datetime',
        ];
    }

    public function posiciones(): HasMany
    {
        return $this->hasMany(PosicionRankingAnual::class, 'ranking_anual_id')->orderBy('puesto');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    public function actualizador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'actualizado_por');
    }
}

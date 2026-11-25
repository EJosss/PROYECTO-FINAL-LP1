<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Cada uno de los 3 puestos fijos (1, 2, 3) del Ranking Anual.
 * Reutiliza la normalización de celular/homónimos de PosicionRanking y conserva snapshots propios.
 */
class PosicionRankingAnual extends Model
{
    protected $table = 'posiciones_ranking_anual';

    protected $fillable = [
        'ranking_anual_id',
        'puesto',
        'codigo_interno',
        'nombres_snapshot',
        'apellidos_snapshot',
        'celular_snapshot',
        'celular_normalizado',
        'foto_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'puesto' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PosicionRankingAnual $posicion) {
            if (blank($posicion->codigo_interno)) {
                $posicion->codigo_interno = 'ANUAL-' . strtoupper(Str::random(10));
            }
            if (filled($posicion->celular_snapshot)) {
                $posicion->celular_normalizado = PosicionRanking::normalizarCelular($posicion->celular_snapshot);
            }
        });

        static::updating(function (PosicionRankingAnual $posicion) {
            if (filled($posicion->celular_snapshot)) {
                $posicion->celular_normalizado = PosicionRanking::normalizarCelular($posicion->celular_snapshot);
            }
        });
    }

    public function rankingAnual(): BelongsTo
    {
        return $this->belongsTo(RankingAnual::class, 'ranking_anual_id');
    }

    public function getNombreCompletoSnapshotAttribute(): string
    {
        return trim("{$this->nombres_snapshot} {$this->apellidos_snapshot}");
    }

    public function getCelularWhatsappSnapshotAttribute(): string
    {
        $soloDigitos = preg_replace('/[^0-9]/', '', $this->celular_snapshot) ?: '';

        if (strlen($soloDigitos) === 9 && str_starts_with($soloDigitos, '9')) {
            return '51' . $soloDigitos;
        }

        return $soloDigitos;
    }

    public function getUrlFotoSnapshotAttribute(): string
    {
        if (str_starts_with($this->foto_snapshot, 'http://') || str_starts_with($this->foto_snapshot, 'https://')) {
            return $this->foto_snapshot;
        }

        return Storage::disk('public')->url($this->foto_snapshot);
    }
}

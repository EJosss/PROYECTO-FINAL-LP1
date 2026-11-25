<?php

namespace App\Services;

use App\Models\PosicionRanking;
use App\Models\PosicionRankingAnual;
use App\Models\RankingAnual;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Motor transaccional del Ranking Anual: gestiona los 3 puestos anuales con lockForUpdate(),
 * unicidad de celular (hash_equals), advertencia de homónimos, optimización WebP y cero fotos huérfanas.
 */
class ServicioRankingAnual
{
    /**
     * Registra o actualiza a un ganador anual en el puesto 1, 2 o 3.
     *
     * @param  array{nombres: string, apellidos: string, celular: string}  $datos
     * @return array{ranking: RankingAnual, posicion: PosicionRankingAnual, advertencia_homonimo: ?string}
     */
    public function guardarParticipantePuesto(
        int $anio,
        int $puesto,
        array $datos,
        int $usuarioId,
        ?UploadedFile $fotoArchivo = null
    ): array {
        if ($anio < 2020 || $anio > 2100) {
            throw new DomainException('Selecciona un año válido para el ranking anual.');
        }

        if (! in_array($puesto, [1, 2, 3], true)) {
            throw new DomainException('Solo se permiten los tres puestos fijos del ranking anual (1, 2 y 3).');
        }

        $rutaFotoNueva = null;
        $rutaFotoAnterior = null;
        $advertenciaHomonimo = null;

        try {
            $resultado = DB::transaction(function () use (
                $anio,
                $puesto,
                $datos,
                $usuarioId,
                $fotoArchivo,
                &$rutaFotoNueva,
                &$rutaFotoAnterior,
                &$advertenciaHomonimo
            ) {
                RankingAnual::firstOrCreate(
                    ['anio' => $anio],
                    [
                        'estado' => RankingAnual::ESTADO_BORRADOR,
                        'creado_por' => $usuarioId,
                    ]
                );

                $ranking = RankingAnual::where('anio', $anio)->lockForUpdate()->firstOrFail();

                $nombres = trim($datos['nombres']);
                $apellidos = trim($datos['apellidos']);
                $celular = trim($datos['celular']);
                $celularNormalizado = PosicionRanking::normalizarCelular($celular);

                if (strlen($celularNormalizado) < 6) {
                    throw new DomainException('Ingresa un número de celular válido.');
                }

                $posicionesExistentes = $ranking->posiciones()->lockForUpdate()->get()->keyBy('puesto');
                /** @var PosicionRankingAnual|null $posicionActual */
                $posicionActual = $posicionesExistentes->get($puesto);

                foreach ($posicionesExistentes as $otroPuesto => $otraPosicion) {
                    if ((int) $otroPuesto === $puesto) {
                        continue;
                    }

                    $otroCelular = $otraPosicion->celular_normalizado
                        ?: PosicionRanking::normalizarCelular((string) $otraPosicion->celular_snapshot);

                    if ($otroCelular !== '' && hash_equals($otroCelular, $celularNormalizado)) {
                        throw new DomainException("El número de celular ya está registrado en el Puesto #{$otroPuesto} del ranking anual {$anio}.");
                    }

                    $nombreActualNorm = PosicionRanking::normalizarNombreCompleto($nombres, $apellidos);
                    $otroNombreNorm = PosicionRanking::normalizarNombreCompleto(
                        (string) $otraPosicion->nombres_snapshot,
                        (string) $otraPosicion->apellidos_snapshot
                    );

                    if ($nombreActualNorm !== '' && $nombreActualNorm === $otroNombreNorm) {
                        $advertenciaHomonimo = "Aviso: El nombre «{$nombres} {$apellidos}» coincide con el participante del Puesto #{$otroPuesto}. Verifica si se trata de un homónimo.";
                    }
                }

                $rutaFotoFinal = $posicionActual?->foto_snapshot;
                if ($fotoArchivo !== null) {
                    $extension = strtolower($fotoArchivo->getClientOriginalExtension() ?: $fotoArchivo->extension() ?: 'jpg');
                    $directorio = "rankings/anual/{$anio}";
                    $nombreArchivo = "puesto-{$puesto}-" . Str::uuid()->toString() . ".{$extension}";
                    $rutaFotoNueva = $fotoArchivo->storeAs($directorio, $nombreArchivo, 'public');

                    if (! $rutaFotoNueva || ! Storage::disk('public')->exists($rutaFotoNueva)) {
                        throw new DomainException("No se pudo guardar la fotografía del Puesto #{$puesto}.");
                    }

                    app(ServicioOptimizacionImagen::class)->generarWebpDesdeDiscoPublico($rutaFotoNueva, 600);

                    if (! empty($rutaFotoFinal) && $rutaFotoFinal !== $rutaFotoNueva) {
                        $rutaFotoAnterior = $rutaFotoFinal;
                    }
                    $rutaFotoFinal = $rutaFotoNueva;
                }

                if (blank($rutaFotoFinal) || ! Storage::disk('public')->exists($rutaFotoFinal)) {
                    throw new DomainException("Debes cargar una fotografía válida para el Puesto #{$puesto}.");
                }

                if ($posicionActual) {
                    $posicionActual->update([
                        'nombres_snapshot' => $nombres,
                        'apellidos_snapshot' => $apellidos,
                        'celular_snapshot' => $celular,
                        'celular_normalizado' => $celularNormalizado,
                        'foto_snapshot' => $rutaFotoFinal,
                    ]);
                    $posicion = $posicionActual->fresh();
                } else {
                    $posicion = $ranking->posiciones()->create([
                        'puesto' => $puesto,
                        'codigo_interno' => 'ANUAL-' . strtoupper(Str::random(10)),
                        'nombres_snapshot' => $nombres,
                        'apellidos_snapshot' => $apellidos,
                        'celular_snapshot' => $celular,
                        'celular_normalizado' => $celularNormalizado,
                        'foto_snapshot' => $rutaFotoFinal,
                    ]);
                }

                $ranking->update(['actualizado_por' => $usuarioId]);

                return [
                    'ranking' => $ranking->fresh('posiciones'),
                    'posicion' => $posicion,
                    'advertencia_homonimo' => $advertenciaHomonimo,
                ];
            });

            if ($rutaFotoAnterior !== null && Storage::disk('public')->exists($rutaFotoAnterior)) {
                Storage::disk('public')->delete($rutaFotoAnterior);
                $rutaWebp = preg_replace('/\.[^.]+$/', '.webp', $rutaFotoAnterior);
                if (is_string($rutaWebp) && Storage::disk('public')->exists($rutaWebp)) {
                    Storage::disk('public')->delete($rutaWebp);
                }
            }

            return $resultado;
        } catch (Throwable $e) {
            if ($rutaFotoNueva !== null && Storage::disk('public')->exists($rutaFotoNueva)) {
                Storage::disk('public')->delete($rutaFotoNueva);
            }

            throw $e;
        }
    }

    /**
     * Publica el ranking anual en la portada pública solo si los 3 puestos están completos y sin duplicados.
     */
    public function publicarRankingAnual(int $anio, int $usuarioId): RankingAnual
    {
        return DB::transaction(function () use ($anio, $usuarioId) {
            $ranking = RankingAnual::where('anio', $anio)->lockForUpdate()->first();
            if (! $ranking) {
                throw new DomainException('Debes registrar primero los tres puestos del ranking anual antes de publicarlo.');
            }

            $posiciones = $ranking->posiciones()->lockForUpdate()->get();
            if (
                $posiciones->count() !== 3
                || $posiciones->pluck('puesto')->sort()->values()->all() !== [1, 2, 3]
            ) {
                throw new DomainException('Debes completar los tres puestos (1.er, 2.º y 3.er puesto) antes de publicar el ranking anual.');
            }

            $celulares = $posiciones->map(fn (PosicionRankingAnual $p) => $p->celular_normalizado
                ?: PosicionRanking::normalizarCelular((string) $p->celular_snapshot));

            if ($celulares->unique()->count() !== 3) {
                throw new DomainException('No se puede publicar el ranking anual porque existen números de celular duplicados.');
            }

            $ranking->update([
                'estado' => RankingAnual::ESTADO_PUBLICADO,
                'fecha_publicacion' => $ranking->fecha_publicacion ?? now('America/Lima'),
                'actualizado_por' => $usuarioId,
            ]);

            return $ranking->fresh('posiciones');
        });
    }
}

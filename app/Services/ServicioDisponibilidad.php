<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Servicio de monitoreo de salud consumido por GET /salud (EstadoSistemaController).
 * Verifica modo mantenimiento, conectividad MySQL (SELECT 1) y permisos de escritura en storage/,
 * respondiendo HTTP 200 (operativo) o 503 (degradado) sin exponer credenciales ni trazas internas.
 */
class ServicioDisponibilidad
{
    /**
     * Evalúa el estado operativo de la aplicación sin exponer credenciales ni detalles internos.
     *
     * @return array{codigo_http: int, cuerpo: array<string, mixed>}
     */
    public function verificarEstado(): array
    {
        $servicios = [
            'aplicacion' => 'ok',
            'base_datos' => 'error',
            'almacenamiento' => 'error',
        ];

        $operativo = true;

        if (app()->isDownForMaintenance()) {
            $servicios['aplicacion'] = 'mantenimiento';
            $operativo = false;
        }

        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $servicios['base_datos'] = 'ok';
        } catch (Throwable $e) {
            $operativo = false;
            Log::channel('disponibilidad')->critical('Fallo de conectividad con la base de datos en verificación de salud.', [
                'excepcion' => $e::class,
                'mensaje' => $e->getMessage(),
            ]);
        }

        $rutaLogs = storage_path('logs');
        $rutaPublica = storage_path('app/public');
        if (is_dir($rutaLogs) && is_writable($rutaLogs) && is_dir($rutaPublica) && is_writable($rutaPublica)) {
            $servicios['almacenamiento'] = 'ok';
        } else {
            $operativo = false;
            Log::channel('disponibilidad')->error('Directorio de almacenamiento no disponible para escritura.');
        }

        return [
            'codigo_http' => $operativo ? 200 : 503,
            'cuerpo' => [
                'estado' => $operativo ? 'operativo' : 'degradado',
                'servicios' => $servicios,
                'marca_tiempo' => now('America/Lima')->toIso8601String(),
            ],
        ];
    }
}

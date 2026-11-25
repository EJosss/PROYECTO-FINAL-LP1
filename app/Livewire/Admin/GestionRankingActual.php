<?php

namespace App\Livewire\Admin;

use App\Models\RankingAnual;
use App\Models\RankingMensual;
use App\Services\ServicioRankingAnual;
use App\Services\ServicioRankingMensual;
use App\Support\TelefonoPeru;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Gestión del Ranking Mensual vigente ("Vendedores Estrella del Mes").
 * Solo editable durante los 3 primeros días del mes (America/Lima); delega transacciones,
 * bloqueos pesimistas (lockForUpdate), duplicados y publicación atómica a ServicioRankingMensual.
 */
#[Layout('components.layouts.admin')]
#[Title('Rankings | RE/MAX Zona')]
class GestionRankingActual extends Component
{
    use WithFileUploads;

    public string $pestanaActiva = 'mensual';

    /**
     * @var array<int, array{nombres: string, apellidos: string, celular: string, codigo_interno: ?string, url_foto_actual: ?string, registrado: bool}>
     */
    public array $puestos = [];

    public $foto1 = null;

    public $foto2 = null;

    public $foto3 = null;

    /** @var array<int, ?string> */
    public array $advertenciasHomonimo = [1 => null, 2 => null, 3 => null];

    // Estado del Ranking Anual
    public int $anioAnual = 2026;

    /**
     * @var array<int, array{nombres: string, apellidos: string, celular: string, codigo_interno: ?string, url_foto_actual: ?string, registrado: bool}>
     */
    public array $puestosAnual = [];

    public $fotoAnual1 = null;

    public $fotoAnual2 = null;

    public $fotoAnual3 = null;

    /** @var array<int, ?string> */
    public array $advertenciasHomonimoAnual = [1 => null, 2 => null, 3 => null];

    public ?string $mensajeExito = null;

    public ?string $mensajeError = null;

    // Verifica permisos, sincroniza rankings vencidos (día 4+) y carga los 3 puestos del período vigente y anual
    public function mount(ServicioRankingMensual $servicio): void
    {
        $usuario = Auth::user();
        if (! $usuario || ! $usuario->activo || (! $usuario->esAdministrador() && ! $usuario->esMarketing())) {
            abort(403, 'No tienes permisos para acceder al módulo de Rankings.');
        }

        $servicio->sincronizarEstadosVencidos();
        $this->anioAnual = (int) now('America/Lima')->year;
        $this->cargarDatosDelPeriodoActual();
        $this->cargarDatosAnuales();
    }

    // Alterna entre las pestañas internas 'mensual' y 'anual' limpiando alertas previas
    public function cambiarPestana(string $pestana): void
    {
        if (! in_array($pestana, ['mensual', 'anual'], true)) {
            return;
        }

        $this->pestanaActiva = $pestana;
        $this->mensajeExito = null;
        $this->mensajeError = null;
        $this->resetValidation();
    }

    // Recarga los 3 puestos anuales al cambiar el año seleccionado
    public function updatedAnioAnual(): void
    {
        $this->mensajeExito = null;
        $this->mensajeError = null;
        $this->advertenciasHomonimoAnual = [1 => null, 2 => null, 3 => null];
        $this->fotoAnual1 = null;
        $this->fotoAnual2 = null;
        $this->fotoAnual3 = null;
        $this->cargarDatosAnuales();
    }

    // Consulta el ranking anual del año elegido y precarga sus 3 puestos fijos
    private function cargarDatosAnuales(): void
    {
        $rankingAnual = RankingAnual::with('posiciones')
            ->where('anio', $this->anioAnual)
            ->first();

        $posiciones = $rankingAnual ? $rankingAnual->posiciones->keyBy('puesto') : collect();

        foreach ([1, 2, 3] as $numeroPuesto) {
            $pos = $posiciones->get($numeroPuesto);
            $this->puestosAnual[$numeroPuesto] = [
                'nombres' => $pos?->nombres_snapshot ?? '',
                'apellidos' => $pos?->apellidos_snapshot ?? '',
                'celular' => TelefonoPeru::quitarPrefijo($pos?->celular_snapshot),
                'codigo_interno' => $pos?->codigo_interno,
                'url_foto_actual' => $pos?->url_foto_snapshot,
                'registrado' => $pos !== null,
            ];
        }
    }

    // Quita la fotografía temporal seleccionada para uno de los 3 puestos anuales
    public function limpiarFotoAnual(int $puesto): void
    {
        if (! in_array($puesto, [1, 2, 3], true)) {
            return;
        }

        $campoFoto = "fotoAnual{$puesto}";
        $this->{$campoFoto} = null;
        $this->resetErrorBag($campoFoto);
    }

    // Valida y guarda un puesto del Ranking Anual delegando la transacción a ServicioRankingAnual
    public function guardarPuestoAnual(int $puesto, ServicioRankingAnual $servicio): void
    {
        if (! in_array($puesto, [1, 2, 3], true)) {
            return;
        }

        $this->mensajeExito = null;
        $this->mensajeError = null;
        $this->advertenciasHomonimoAnual[$puesto] = null;

        $campoFoto = "fotoAnual{$puesto}";
        $yaRegistrado = (bool) ($this->puestosAnual[$puesto]['registrado'] ?? false);

        $this->validate([
            "puestosAnual.{$puesto}.nombres" => ['required', 'string', 'min:2', 'max:100'],
            "puestosAnual.{$puesto}.apellidos" => ['required', 'string', 'min:2', 'max:100'],
            "puestosAnual.{$puesto}.celular" => ['required', 'string', 'min:6', 'max:20'],
            $campoFoto => $yaRegistrado
                ? ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']
                : ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            "puestosAnual.{$puesto}.nombres.required" => 'Ingresa los nombres del ganador anual.',
            "puestosAnual.{$puesto}.apellidos.required" => 'Ingresa los apellidos del ganador anual.',
            "puestosAnual.{$puesto}.celular.required" => 'Ingresa el número de celular del ganador anual.',
            "{$campoFoto}.required" => 'Debes seleccionar una fotografía para este puesto anual.',
            "{$campoFoto}.image" => 'El archivo seleccionado debe ser una imagen válida.',
            "{$campoFoto}.max" => 'La fotografía no debe superar los 4 MB.',
        ]);

        try {
            $resultado = $servicio->guardarParticipantePuesto(
                $this->anioAnual,
                $puesto,
                [
                    'nombres' => (string) ($this->puestosAnual[$puesto]['nombres'] ?? ''),
                    'apellidos' => (string) ($this->puestosAnual[$puesto]['apellidos'] ?? ''),
                    'celular' => TelefonoPeru::formatearConPrefijo((string) ($this->puestosAnual[$puesto]['celular'] ?? '')),
                ],
                (int) Auth::id(),
                $this->{$campoFoto}
            );

            $this->{$campoFoto} = null;
            $this->advertenciasHomonimoAnual[$puesto] = $resultado['advertencia_homonimo'];
            $this->cargarDatosAnuales();

            $this->mensajeExito = "Puesto #{$puesto} del Ranking Anual {$this->anioAnual} guardado correctamente.";
        } catch (DomainException $e) {
            $this->addError("puestosAnual.{$puesto}.celular", $e->getMessage());
            $this->mensajeError = $e->getMessage();
        } catch (Throwable $e) {
            $this->mensajeError = 'Ocurrió un error al guardar el participante anual: ' . $e->getMessage();
        }
    }

    // Publica el Ranking Anual del año seleccionado en la portada pública (exige 3 puestos completos)
    public function publicarRankingAnual(ServicioRankingAnual $servicio): void
    {
        $this->mensajeExito = null;
        $this->mensajeError = null;

        try {
            $servicio->publicarRankingAnual($this->anioAnual, (int) Auth::id());
            $this->cargarDatosAnuales();
            $this->mensajeExito = "Ranking Anual {$this->anioAnual} publicado correctamente en la portada pública.";
        } catch (DomainException $e) {
            $this->mensajeError = $e->getMessage();
        } catch (Throwable $e) {
            $this->mensajeError = 'Ocurrió un error al publicar el ranking anual: ' . $e->getMessage();
        }
    }

    // Consulta el ranking del mes de desempeño actual y precarga los 3 puestos fijos (1, 2 y 3) en pantalla
    private function cargarDatosDelPeriodoActual(): void
    {
        $periodo = RankingMensual::periodoActual();

        $ranking = RankingMensual::with('posiciones')
            ->where('anio_desempeno', $periodo['anio_desempeno'])
            ->where('mes_desempeno', $periodo['mes_desempeno'])
            ->first();

        $posiciones = $ranking ? $ranking->posiciones->keyBy('puesto') : collect();

        foreach ([1, 2, 3] as $numeroPuesto) {
            $pos = $posiciones->get($numeroPuesto);
            $this->puestos[$numeroPuesto] = [
                'nombres' => $pos?->nombres_snapshot ?? '',
                'apellidos' => $pos?->apellidos_snapshot ?? '',
                'celular' => TelefonoPeru::quitarPrefijo($pos?->celular_snapshot),
                'codigo_interno' => $pos?->codigo_interno,
                'url_foto_actual' => $pos?->url_foto_snapshot,
                'registrado' => $pos !== null,
            ];
        }
    }

    // Quita la fotografía temporal seleccionada para uno de los 3 puestos antes de guardar
    public function limpiarFotoPuesto(int $puesto): void
    {
        if (! in_array($puesto, [1, 2, 3], true)) {
            return;
        }

        $campoFoto = "foto{$puesto}";
        $this->{$campoFoto} = null;
        $this->resetErrorBag($campoFoto);
    }

    // Valida plazo (días 1-3) y datos del puesto, y delega el guardado transaccional (lockForUpdate) a ServicioRankingMensual
    public function guardarPuesto(int $puesto, ServicioRankingMensual $servicio): void
    {
        if (! in_array($puesto, [1, 2, 3], true)) {
            return;
        }

        $this->mensajeExito = null;
        $this->mensajeError = null;
        $this->advertenciasHomonimo[$puesto] = null;

        $servicio->sincronizarEstadosVencidos();
        $periodo = RankingMensual::periodoActual();

        if (! RankingMensual::dentroDePlazoDeGestion($periodo['anio_publicacion'], $periodo['mes_publicacion'])) {
            $this->mensajeError = 'El plazo de gestión (días 1 al 3 del mes) ha finalizado. El ranking se encuentra bloqueado.';

            return;
        }

        $campoFoto = "foto{$puesto}";
        $yaRegistrado = (bool) ($this->puestos[$puesto]['registrado'] ?? false);

        $this->validate([
            "puestos.{$puesto}.nombres" => ['required', 'string', 'min:2', 'max:100'],
            "puestos.{$puesto}.apellidos" => ['required', 'string', 'min:2', 'max:100'],
            "puestos.{$puesto}.celular" => ['required', 'string', 'min:6', 'max:20'],
            $campoFoto => $yaRegistrado
                ? ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']
                : ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            "puestos.{$puesto}.nombres.required" => 'Ingresa los nombres del participante.',
            "puestos.{$puesto}.nombres.min" => 'Los nombres deben tener al menos 2 caracteres.',
            "puestos.{$puesto}.apellidos.required" => 'Ingresa los apellidos del participante.',
            "puestos.{$puesto}.apellidos.min" => 'Los apellidos deben tener al menos 2 caracteres.',
            "puestos.{$puesto}.celular.required" => 'Ingresa el número de celular del participante.',
            "puestos.{$puesto}.celular.min" => 'El número de celular debe tener al menos 6 caracteres.',
            "{$campoFoto}.required" => 'Debes seleccionar una fotografía especial para este puesto.',
            "{$campoFoto}.image" => 'El archivo seleccionado debe ser una imagen válida.',
            "{$campoFoto}.mimes" => 'La fotografía debe estar en formato JPG, JPEG, PNG o WebP.',
            "{$campoFoto}.max" => 'La fotografía no debe superar los 4 MB.',
        ]);

        try {
            $resultado = $servicio->guardarParticipantePuesto(
                $puesto,
                [
                    'nombres' => (string) ($this->puestos[$puesto]['nombres'] ?? ''),
                    'apellidos' => (string) ($this->puestos[$puesto]['apellidos'] ?? ''),
                    'celular' => TelefonoPeru::formatearConPrefijo((string) ($this->puestos[$puesto]['celular'] ?? '')),
                ],
                (int) Auth::id(),
                $this->{$campoFoto}
            );

            $this->{$campoFoto} = null;
            $this->advertenciasHomonimo[$puesto] = $resultado['advertencia_homonimo'];
            $this->cargarDatosDelPeriodoActual();

            $this->mensajeExito = "Participante del Puesto #{$puesto} guardado correctamente.";
        } catch (DomainException $e) {
            $this->addError("puestos.{$puesto}.celular", $e->getMessage());
            $this->mensajeError = $e->getMessage();
        } catch (Throwable $e) {
            $this->mensajeError = 'Ocurrió un error al guardar el participante: ' . $e->getMessage();
        }
    }

    // Publica definitivamente el ranking (solo si los 3 puestos están completos y sin celulares duplicados)
    public function publicarRanking(ServicioRankingMensual $servicio): void
    {
        $this->mensajeExito = null;
        $this->mensajeError = null;

        $servicio->sincronizarEstadosVencidos();

        try {
            $ranking = $servicio->publicarRankingActual((int) Auth::id());
            $this->cargarDatosDelPeriodoActual();
            $this->mensajeExito = 'Ranking publicado y actualizado correctamente en el sitio público.';
        } catch (DomainException $e) {
            $this->mensajeError = $e->getMessage();
        } catch (Throwable $e) {
            $this->mensajeError = 'Ocurrió un error al publicar el ranking: ' . $e->getMessage();
        }
    }

    // Evalúa en tiempo real si hoy está dentro de los días 1-3 (America/Lima) y renderiza la vista del podio
    public function render()
    {
        $periodo = RankingMensual::periodoActual();
        $dentroDePlazo = RankingMensual::dentroDePlazoDeGestion($periodo['anio_publicacion'], $periodo['mes_publicacion']);

        $rankingActual = RankingMensual::with('posiciones')
            ->where('anio_desempeno', $periodo['anio_desempeno'])
            ->where('mes_desempeno', $periodo['mes_desempeno'])
            ->first();

        $nombreMesDesempeno = RankingMensual::NOMBRES_MESES[$periodo['mes_desempeno']] ?? $periodo['mes_desempeno'];
        $nombreMesPublicacion = RankingMensual::NOMBRES_MESES[$periodo['mes_publicacion']] ?? $periodo['mes_publicacion'];

        $totalRegistrados = collect($this->puestos)->where('registrado', true)->count();

        $rankingAnualActual = RankingAnual::with('posiciones')
            ->where('anio', $this->anioAnual)
            ->first();
        $totalRegistradosAnual = collect($this->puestosAnual)->where('registrado', true)->count();
        $anioActualSistema = (int) now('America/Lima')->year;
        $aniosDisponibles = range($anioActualSistema + 1, max(2024, $anioActualSistema - 3));

        return view('livewire.admin.gestion-ranking-actual', [
            'periodo' => $periodo,
            'dentroDePlazo' => $dentroDePlazo,
            'rankingActual' => $rankingActual,
            'totalRegistrados' => $totalRegistrados,
            'rankingAnualActual' => $rankingAnualActual,
            'totalRegistradosAnual' => $totalRegistradosAnual,
            'aniosDisponibles' => $aniosDisponibles,
            'etiquetaDesempeno' => "{$nombreMesDesempeno} {$periodo['anio_desempeno']}",
            'etiquetaPublicacion' => "{$nombreMesPublicacion} {$periodo['anio_publicacion']}",
            'ahoraLima' => now('America/Lima'),
        ]);
    }
}

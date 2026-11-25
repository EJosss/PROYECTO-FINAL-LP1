<?php

namespace App\Livewire\Publico;

use App\Models\ConfiguracionContacto;
use App\Models\Propiedad;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

// Componente Full-Page de Livewire 4: catálogo público con filtros reactivos sin recargar la página
#[Layout('components.layouts.publico')]
#[Title('Propiedades | RE/MAX Zona')]
class PaginaPropiedades extends Component
{
    // #[Url(except: '')] sincroniza el filtro con la barra de direcciones para poder compartir enlaces filtrados
    #[Url(except: '')]
    public string $busqueda = '';

    #[Url(except: '')]
    public string $ubicacion = '';

    #[Url(except: '')]
    public string $moneda = '';

    // Restablece los 3 filtros a vacío y limpia la URL automáticamente
    public function limpiarFiltros(): void
    {
        $this->reset(['busqueda', 'ubicacion', 'moneda']);
    }

    public function render()
    {
        // Consultas Eloquent parametrizadas (sentencias preparadas contra SQL Injection)
        $consulta = Propiedad::query()->where('activo', true);

        $termino = trim($this->busqueda);
        if ($termino !== '') {
            $consulta->where(function ($subconsulta) use ($termino) {
                $subconsulta->where('nombre', 'like', '%' . $termino . '%')
                    ->orWhere('ubicacion', 'like', '%' . $termino . '%');
            });
        }

        if ($this->ubicacion !== '') {
            $consulta->where('ubicacion', $this->ubicacion);
        }

        // Lista blanca estricta: solo acepta PEN o USD
        if (in_array($this->moneda, [Propiedad::MONEDA_SOLES, Propiedad::MONEDA_DOLARES], true)) {
            $consulta->where('moneda', $this->moneda);
        }

        $propiedades = $consulta->orderBy('id')->get();

        // Extrae ubicaciones únicas de propiedades activas para poblar el selector desplegable
        $ubicacionesDisponibles = Propiedad::where('activo', true)
            ->whereNotNull('ubicacion')
            ->where('ubicacion', '!=', '')
            ->distinct()
            ->orderBy('ubicacion')
            ->pluck('ubicacion');

        $totalActivas = Propiedad::where('activo', true)->count();
        $contacto = ConfiguracionContacto::obtenerUnica();

        // Envía datos a la vista e inyecta metadatos SEO + JSON-LD ItemList al layout público
        return view('livewire.publico.pagina-propiedades', [
            'propiedades' => $propiedades,
            'ubicacionesDisponibles' => $ubicacionesDisponibles,
            'totalActivas' => $totalActivas,
            'contacto' => $contacto,
        ])->layoutData([
            'metaDescription' => 'Explora el catálogo de propiedades disponibles en RE/MAX Zona: casas, departamentos, terrenos y locales comerciales con ficha oficial de RE/MAX Perú.',
            'canonicalUrl' => route('propiedades'),
            'datosEstructurados' => Propiedad::construirDatosEstructuradosCatalogo($propiedades, 'Catálogo de propiedades RE/MAX Zona'),
        ]);
    }
}

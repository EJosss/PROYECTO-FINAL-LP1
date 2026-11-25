<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Modelo de propiedades inmobiliarias del catálogo público y panel administrativo.
 * La regla de integridad precio/moneda se refuerza además con CHECK en MySQL (migración 0002).
 */
class Propiedad extends Model
{
    protected $table = 'propiedades';

    // Monedas válidas en el catálogo (Soles y Dólares)
    public const MONEDA_SOLES = 'PEN';
    public const MONEDA_DOLARES = 'USD';

    // Códigos oficiales de las 4 sedes (deben coincidir con la tabla 'oficinas')
    public const OFICINA_AREQUIPA = 'AREQUIPA';
    public const OFICINA_LIMA = 'LIMA';
    public const OFICINA_MOQUEGUA = 'MOQUEGUA';
    public const OFICINA_PUNO = 'PUNO';

    public const OFICINAS = [
        self::OFICINA_AREQUIPA => 'Arequipa',
        self::OFICINA_LIMA => 'Lima',
        self::OFICINA_MOQUEGUA => 'Moquegua',
        self::OFICINA_PUNO => 'Puno',
    ];

    // Mapeo entre slug de URL (/oficinas/{slug}) y código de oficina en BD (usado también en sitemap.xml)
    public const SLUGS_OFICINAS = [
        'arequipa' => self::OFICINA_AREQUIPA,
        'lima' => self::OFICINA_LIMA,
        'moquegua' => self::OFICINA_MOQUEGUA,
        'puno' => self::OFICINA_PUNO,
    ];

    protected $fillable = [
        'nombre',
        'imagen',
        'ubicacion',
        'oficina',
        'precio',
        'moneda',
        'enlace_remax',
        'activo',
        'destacada',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'activo' => 'boolean',
            'destacada' => 'boolean',
        ];
    }

    // Accessor ($propiedad->simbolo_moneda): devuelve 'S/' o '$' solo si tiene precio y moneda
    public function getSimboloMonedaAttribute(): ?string
    {
        if ($this->precio === null || $this->moneda === null) {
            return null;
        }

        return $this->moneda === self::MONEDA_SOLES ? 'S/' : '$';
    }

    // Accessor ($propiedad->precio_formateado): devuelve por ejemplo "$ 150,000.00"
    public function getPrecioFormateadoAttribute(): ?string
    {
        if ($this->precio === null || $this->moneda === null) {
            return null;
        }

        return $this->simbolo_moneda . ' ' . number_format((float) $this->precio, 2, '.', ',');
    }

    // Accessor ($propiedad->url_imagen): resuelve URL pública desde storage/app/public o enlace externo
    public function getUrlImagenAttribute(): string
    {
        if (str_starts_with($this->imagen, 'http://') || str_starts_with($this->imagen, 'https://')) {
            return $this->imagen;
        }

        return Storage::disk('public')->url($this->imagen);
    }

    // Relación N:1 con la tabla 'oficinas' usando la columna 'oficina' -> 'codigo'
    public function oficinaResponsable(): BelongsTo
    {
        return $this->belongsTo(Oficina::class, 'oficina', 'codigo');
    }

    public function getNombreOficinaAttribute(): ?string
    {
        if ($this->oficina === null) {
            return null;
        }

        return self::OFICINAS[$this->oficina] ?? null;
    }

    /**
     * Construye el esquema JSON-LD ItemList para catálogos de propiedades reales.
     *
     * @param  iterable<int, self>  $propiedades
     */
    public static function construirDatosEstructuradosCatalogo(iterable $propiedades, string $nombreLista): array
    {
        $items = [];
        $posicion = 1;

        foreach ($propiedades as $propiedad) {
            $item = [
                '@type' => 'ListItem',
                'position' => $posicion++,
                'name' => $propiedad->nombre,
            ];

            if (! empty($propiedad->enlace_remax)) {
                $item['url'] = $propiedad->enlace_remax;
            }

            if (! empty($propiedad->url_imagen)) {
                $item['image'] = url($propiedad->url_imagen);
            }

            $items[] = $item;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $nombreLista,
            'numberOfItems' => count($items),
            'itemListElement' => $items,
        ];
    }
}

<?php

namespace Tests\Feature;

use App\Livewire\Admin\GestionRankingActual;
use App\Livewire\Publico\PaginaInicio;
use App\Models\RankingAnual;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class RankingAnualTest extends TestCase
{
    use RefreshDatabase;

    public function test_flujo_completo_ranking_anual_en_panel_y_pestanas_en_portada_publica(): void
    {
        Storage::fake('public');

        $admin = Usuario::factory()->create([
            'rol' => Usuario::ROL_ADMINISTRADOR,
            'activo' => true,
        ]);

        $this->actingAs($admin);

        // 1. Cambiar a la pestaña Ranking Anual y registrar los 3 puestos
        $componenteAdmin = Livewire::test(GestionRankingActual::class)
            ->call('cambiarPestana', 'anual')
            ->assertSet('pestanaActiva', 'anual')
            ->set('anioAnual', 2026);

        $ganadores = [
            1 => ['nombres' => 'Jessica', 'apellidos' => 'Sardon', 'celular' => '951111111'],
            2 => ['nombres' => 'Luis', 'apellidos' => 'Mamani', 'celular' => '952222222'],
            3 => ['nombres' => 'Andrea', 'apellidos' => 'Quispe', 'celular' => '953333333'],
        ];

        foreach ($ganadores as $puesto => $datos) {
            $componenteAdmin
                ->set("puestosAnual.{$puesto}.nombres", $datos['nombres'])
                ->set("puestosAnual.{$puesto}.apellidos", $datos['apellidos'])
                ->set("puestosAnual.{$puesto}.celular", $datos['celular'])
                ->set("fotoAnual{$puesto}", UploadedFile::fake()->image("anual{$puesto}.jpg", 600, 600))
                ->call('guardarPuestoAnual', $puesto)
                ->assertHasNoErrors();
        }

        // 2. Bloquear celular duplicado entre puestos anuales
        $componenteAdmin
            ->set('puestosAnual.3.celular', '951111111')
            ->call('guardarPuestoAnual', 3)
            ->assertSee('ya está registrado en el Puesto #1');

        // 3. Publicar el Ranking Anual 2026
        $componenteAdmin
            ->call('publicarRankingAnual')
            ->assertSee('publicado correctamente');

        $this->assertDatabaseHas('rankings_anuales', [
            'anio' => 2026,
            'estado' => RankingAnual::ESTADO_PUBLICADO,
        ]);
        $this->assertDatabaseCount('posiciones_ranking_anual', 3);

        // 4. En la portada pública, alternar a la pestaña 'anual' muestra los 3 ganadores del año
        Livewire::test(PaginaInicio::class)
            ->assertSet('tipoRanking', 'mensual')
            ->assertSee('Top Anual 2026')
            ->call('cambiarTipoRanking', 'anual')
            ->assertSet('tipoRanking', 'anual')
            ->assertSee('Jessica Sardon')
            ->assertSee('Luis Mamani')
            ->assertSee('Andrea Quispe');
    }
}

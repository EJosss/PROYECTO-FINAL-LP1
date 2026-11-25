<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rankings_anuales', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio')->unique('uk_ranking_anual_anio');
            $table->enum('estado', ['BORRADOR', 'PUBLICADO'])->default('BORRADOR');
            $table->timestamp('fecha_publicacion')->nullable();
            $table->foreignId('creado_por')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete();
            $table->foreignId('actualizado_por')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('posiciones_ranking_anual', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ranking_anual_id')
                ->constrained('rankings_anuales')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('puesto');
            $table->string('codigo_interno', 40)->unique('uk_posicion_anual_codigo');
            $table->string('nombres_snapshot', 100);
            $table->string('apellidos_snapshot', 100);
            $table->string('celular_snapshot', 30);
            $table->string('celular_normalizado', 20);
            $table->string('foto_snapshot', 255);
            $table->timestamps();

            $table->unique(['ranking_anual_id', 'puesto'], 'uk_ranking_anual_puesto');
            $table->unique(['ranking_anual_id', 'celular_normalizado'], 'uk_ranking_anual_celular');
        });

        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement('ALTER TABLE `rankings_anuales` ADD CONSTRAINT `chk_ranking_anual_anio` CHECK (`anio` BETWEEN 2020 AND 2100)');
            DB::statement('ALTER TABLE `posiciones_ranking_anual` ADD CONSTRAINT `chk_posicion_anual_puesto` CHECK (`puesto` BETWEEN 1 AND 3)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('posiciones_ranking_anual');
        Schema::dropIfExists('rankings_anuales');
    }
};

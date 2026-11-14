<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * As regiões do piso. A tabela é fixa: as zonas vêm do arquivo da planta, pelo seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zonas', function (Blueprint $table): void {
            $table->string('id', 3)->primary();
            $table->string('nome');
            $table->string('tipo', 20);
            $table->unsignedInteger('area_m2');
            $table->decimal('centroide_x', 6, 1);
            $table->decimal('centroide_y', 6, 1);
            $table->json('vizinhos');
            $table->json('planta');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zonas');
    }
};

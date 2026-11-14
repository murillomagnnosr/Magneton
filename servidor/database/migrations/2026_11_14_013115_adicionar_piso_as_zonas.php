<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Em que pavimento a zona fica.
 *
 * A planta do projeto é a do 1º pavimento, mas nem toda zona está nele: a praça de
 * alimentação fica no piso de cima, sobre a mesma área. O painel precisa saber disso
 * para não desenhá-la como se fosse vizinha de chão das outras.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zonas', function (Blueprint $table): void {
            $table->unsignedTinyInteger('piso')->default(1)->after('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('zonas', function (Blueprint $table): void {
            $table->dropColumn('piso');
        });
    }
};

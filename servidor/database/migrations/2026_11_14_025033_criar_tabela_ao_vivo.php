<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quantas pessoas há agora em cada zona.
 *
 * Esta tabela é o estado atual, não um histórico: há no máximo uma linha por zona e cada
 * envio sobrescreve a anterior. Nada aqui acumula, então não existe série temporal fina
 * para reconstruir o caminho de ninguém — o histórico continua sendo só `contagens`, em
 * janelas de 15 minutos (RNF03).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ao_vivo', function (Blueprint $table): void {
            $table->string('zona_id', 3)->primary();
            $table->unsignedInteger('pessoas');
            $table->timestamp('medido_em');

            $table->foreign('zona_id')->references('id')->on('zonas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ao_vivo');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quantas pessoas havia em cada região, em cada janela de tempo.
 *
 * Estas são todas as colunas que existem. Não há coluna para imagem, vídeo nem para
 * identificador de pessoa, de aparelho ou de sessão, e o contrato de entrada não
 * aceita nenhum campo além destes (REQUISITOS.md, RNF01).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contagens', function (Blueprint $table): void {
            $table->id();
            $table->string('zona_id', 3);
            $table->dateTime('inicio_janela');
            $table->unsignedSmallInteger('duracao_min');
            $table->unsignedInteger('contagem');
            $table->string('fonte', 10);
            $table->timestamps();

            $table->foreign('zona_id')->references('id')->on('zonas');

            // O par (zona, janela) é único (RNF03); o índice também serve às consultas por zona.
            $table->unique(['zona_id', 'inicio_janela']);

            // Toda consulta filtra por dia ou por período, sempre sobre a janela.
            $table->index('inicio_janela');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contagens');
    }
};

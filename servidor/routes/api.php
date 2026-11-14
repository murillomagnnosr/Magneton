<?php

use App\Http\Controllers\Api\Ingestao\AoVivoController as IngestaoAoVivoController;
use App\Http\Controllers\Api\Ingestao\ContagemController as IngestaoContagemController;
use App\Http\Controllers\Api\V1\AoVivoController;
use App\Http\Controllers\Api\V1\ContagemController;
use App\Http\Controllers\Api\V1\CurvaController;
use App\Http\Controllers\Api\V1\DiaController;
use App\Http\Controllers\Api\V1\PicoController;
use App\Http\Controllers\Api\V1\ResumoController;
use App\Http\Controllers\Api\V1\ZonaController;
use App\Http\Middleware\TokenDeIngestao;
use App\Models\Contagem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API de ingestão: recebe os dados
|--------------------------------------------------------------------------
|
| Só escreve. É aqui que o worker de visão computacional entrega as contagens.
| Protegida por token quando MAPA_TOKEN_INGESTAO está definido no .env.
|
*/

Route::prefix('ingestao/v1')->middleware(TokenDeIngestao::class)->name('ingestao.')->group(function (): void {
    Route::post('contagens', [IngestaoContagemController::class, 'store'])->name('contagens.store');
    Route::delete('contagens', [IngestaoContagemController::class, 'destroy'])->name('contagens.destroy');
    Route::post('ao-vivo', [IngestaoAoVivoController::class, 'store'])->name('ao-vivo.store');
});

/*
|--------------------------------------------------------------------------
| API de transmissão: entrega os dados
|--------------------------------------------------------------------------
|
| Só lê, e só o que a camada de publicação autoriza. Nenhuma rota devolve a
| contagem bruta de uma zona abaixo do limiar k.
|
*/

Route::prefix('v1')->name('v1.')->group(function (): void {
    Route::get('zonas', [ZonaController::class, 'index'])->name('zonas.index');
    Route::get('zonas/{zona}/curva', [CurvaController::class, 'show'])->name('zonas.curva');
    Route::get('contagens', [ContagemController::class, 'index'])->name('contagens.index');
    Route::get('pico', [PicoController::class, 'index'])->name('pico.index');
    Route::get('dia', [DiaController::class, 'show'])->name('dia.show');
    Route::get('ao-vivo', [AoVivoController::class, 'index'])->name('ao-vivo.index');
    Route::get('resumo', [ResumoController::class, 'index'])->name('resumo.index');

    // Parâmetros que o painel e o worker precisam conhecer. O token de ingestão fica de fora.
    Route::get('config', fn (): array => Arr::except(config('mapa'), ['token_ingestao']))
        ->name('config');

    Route::get('saude', fn (): array => [
        'ok' => true,
        'registros' => Contagem::query()->count(),
        'fonte' => Contagem::query()->distinct()->limit(2)->pluck('fonte')->count() === 1
            ? Contagem::query()->value('fonte')
            : null,
        // dias com contagem, do mais recente para o mais antigo: alimenta o seletor do painel
        'dias' => Contagem::query()
            ->selectRaw('DISTINCT substr(inicio_janela, 1, 10) AS dia')
            ->orderByDesc('dia')
            ->pluck('dia'),
        'limiar_k' => config('mapa.k'),
        'duracao_min' => config('mapa.duracao_min'),
    ])->name('saude');
});

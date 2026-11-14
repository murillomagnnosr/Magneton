<?php

namespace App\Services;

use App\Models\Contagem;
use App\Models\Zona;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Leitura das contagens para a API de transmissão.
 *
 * Resolve o dia e o período pedidos e devolve os dados brutos. Transformar em resposta
 * é trabalho da `Publicacao`: nenhuma contagem sai daqui direto para o cliente.
 */
class ConsultaDeContagens
{
    /**
     * O dia pedido, ou o mais recente que existe no banco.
     *
     * @throws HttpException quando ainda não há contagem nenhuma
     */
    public function diaOuMaisRecente(?string $dia): string
    {
        if ($dia !== null && $dia !== '') {
            return $dia;
        }

        $maisRecente = Contagem::query()->max('inicio_janela');

        if (! $maisRecente) {
            abort(404, 'Ainda não há contagem gravada.');
        }

        return Carbon::parse($maisRecente)->toDateString();
    }

    /**
     * Períodos são uma lista fechada: não há consulta por faixa livre de horário (RNF02).
     *
     * @throws HttpException
     */
    public function validarPeriodo(string $periodo): string
    {
        $aceitos = array_keys(config('mapa.periodos'));

        if (! in_array($periodo, $aceitos, strict: true)) {
            abort(422, 'Período deve ser um de: '.implode(', ', $aceitos).'.');
        }

        return $periodo;
    }

    /**
     * Contagens de um período, já com a zona carregada.
     *
     * @return Collection<int, Contagem>
     */
    public function doPeriodo(string $dia, string $periodo): Collection
    {
        $contagens = Contagem::query()
            ->with('zona')
            ->doPeriodo($dia, $periodo)
            ->orderBy('inicio_janela')
            ->orderBy('zona_id')
            ->get();

        if ($contagens->isEmpty()) {
            abort(404, "Sem contagem em {$dia} no período {$periodo}.");
        }

        return $contagens;
    }

    /**
     * Contagens agrupadas por zona: zona_id => list<int>.
     *
     * @param  Collection<int, Contagem>  $contagens
     * @return array<string, list<int>>
     */
    public function porZona(Collection $contagens): array
    {
        return $contagens
            ->groupBy('zona_id')
            ->map(fn (Collection $daZona): array => $daZona->pluck('contagem')->all())
            ->all();
    }

    /**
     * Todas as zonas do piso, em ordem de id.
     *
     * @return Collection<string, Zona>
     */
    public function zonas(): Collection
    {
        return Zona::query()->orderBy('id')->get()->keyBy('id');
    }

    /**
     * Rótulo da janela, como "12:45–13:00".
     */
    public function rotuloDaJanela(Carbon $inicio): string
    {
        $fim = $inicio->copy()->addMinutes((int) config('mapa.duracao_min'));

        return $inicio->format('H:i').'–'.$fim->format('H:i');
    }
}

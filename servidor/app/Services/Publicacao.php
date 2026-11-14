<?php

namespace App\Services;

use App\Models\Zona;

/**
 * Camada de publicação: nada sai da API de transmissão sem passar por aqui.
 *
 * Contagem menor que k nunca vira número na resposta; sai como "abaixo do limiar". Um
 * período só ganha número quando todas as suas janelas são publicáveis, para que a
 * diferença entre duas respostas não revele a janela suprimida (REQUISITOS.md, RNF02).
 */
class Publicacao
{
    public function limiar(): int
    {
        return (int) config('mapa.k');
    }

    /**
     * Classe de densidade, em pessoas por 100 m².
     */
    public function classe(float $densidade): string
    {
        foreach (config('mapa.faixas') as [$limite, $nome]) {
            if ($densidade < $limite) {
                return $nome;
            }
        }

        return config('mapa.faixa_acima');
    }

    /**
     * Uma zona em uma janela. Contagem nula significa que não houve registro.
     *
     * @return array<string, mixed>
     */
    public function janela(Zona $zona, ?int $contagem): array
    {
        if ($contagem === null) {
            return ['zona_id' => $zona->id, 'nome' => $zona->nome, 'classe' => 'sem_dado'];
        }

        if ($contagem < $this->limiar()) {
            return $this->suprimida($zona);
        }

        $densidade = $zona->densidade($contagem);

        return [
            'zona_id' => $zona->id,
            'nome' => $zona->nome,
            'classe' => $this->classe($densidade),
            'pessoas' => $contagem,
            'densidade_100m2' => $densidade,
        ];
    }

    /**
     * Média de uma zona em um período. Uma única janela abaixo de k tira o número de todas.
     *
     * @param  list<int>  $contagens
     * @return array<string, mixed>
     */
    public function periodo(Zona $zona, array $contagens): array
    {
        if ($contagens === []) {
            return ['zona_id' => $zona->id, 'nome' => $zona->nome, 'classe' => 'sem_dado', 'janelas' => 0];
        }

        if (min($contagens) < $this->limiar()) {
            return $this->suprimida($zona) + ['janelas' => count($contagens)];
        }

        $media = array_sum($contagens) / count($contagens);
        $densidade = $zona->densidade($media);

        return [
            'zona_id' => $zona->id,
            'nome' => $zona->nome,
            'tipo' => $zona->tipo,
            'area_m2' => $zona->area_m2,
            'classe' => $this->classe($densidade),
            'pessoas_media' => round($media, 1),
            'densidade_100m2' => $densidade,
            'janelas' => count($contagens),
        ];
    }

    /**
     * Curva do dia de uma zona e o horário de pico.
     *
     * Janela abaixo do limiar vira lacuna, e o pico só é anunciado se a janela mais
     * cheia for publicável.
     *
     * @param  array<string, int>  $porJanela  hora "HH:MM" => contagem
     * @return array<string, mixed>
     */
    public function curva(Zona $zona, array $porJanela): array
    {
        ksort($porJanela);

        $pontos = [];
        $publicaveis = [];

        foreach ($porJanela as $hora => $contagem) {
            if ($contagem >= $this->limiar()) {
                $publicaveis[$hora] = $contagem;
                $pontos[] = [
                    'hora' => $hora,
                    'pessoas' => $contagem,
                    'densidade_100m2' => $zona->densidade($contagem),
                ];

                continue;
            }

            $pontos[] = ['hora' => $hora, 'pessoas' => null, 'motivo' => 'abaixo_do_limiar'];
        }

        return [
            'zona_id' => $zona->id,
            'nome' => $zona->nome,
            'limiar' => $this->limiar(),
            'pontos' => $pontos,
            'janelas_suprimidas' => count($pontos) - count($publicaveis),
            'pico' => $this->picoDaCurva($zona, $publicaveis),
        ];
    }

    /**
     * @param  array<string, int>  $publicaveis
     * @return array<string, mixed>|null
     */
    private function picoDaCurva(Zona $zona, array $publicaveis): ?array
    {
        if ($publicaveis === []) {
            return null;
        }

        $maior = max($publicaveis);
        $hora = array_search($maior, $publicaveis, strict: true);

        return [
            'hora' => $hora,
            'pessoas' => $maior,
            'densidade_100m2' => $zona->densidade($maior),
        ];
    }

    /** @return array<string, mixed> */
    private function suprimida(Zona $zona): array
    {
        return [
            'zona_id' => $zona->id,
            'nome' => $zona->nome,
            'classe' => 'abaixo_do_limiar',
            'limiar' => $this->limiar(),
        ];
    }
}

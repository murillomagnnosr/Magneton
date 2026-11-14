<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige uma chave para ler a API quando ela está publicada na internet.
 *
 * Sem MAPA_CHAVE_LEITURA no .env a leitura fica aberta, que é o caso da rede local: o
 * painel e o worker rodam na mesma máquina e não há nada a proteger. Assim que o túnel
 * sobe, porém, o endereço é público, e aí a chave passa a valer — quem atravessa o túnel
 * é a ponte da Vercel, que a envia no cabeçalho `x-api-key`.
 *
 * O token de ingestão também serve como chave aqui, para o worker continuar lendo
 * /api/v1/config com a credencial que já tem.
 */
class ChaveDeLeitura
{
    public function handle(Request $request, Closure $proximo): Response
    {
        $esperada = (string) config('mapa.chave_leitura');

        if ($esperada === '') {
            return $proximo($request);
        }

        $aceitas = array_filter([$esperada, (string) config('mapa.token_ingestao')]);
        $enviada = (string) ($request->header('x-api-key') ?? $request->bearerToken());

        foreach ($aceitas as $valida) {
            if (hash_equals($valida, $enviada)) {
                return $proximo($request);
            }
        }

        abort(401, 'Chave ausente ou inválida no cabeçalho x-api-key.');
    }
}

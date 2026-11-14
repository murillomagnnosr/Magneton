<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege a API de ingestão com um token fixo.
 *
 * Sem MAPA_TOKEN_INGESTAO no .env a ingestão fica aberta, o que serve para a demo numa
 * rede local. Em produção o token é obrigatório.
 */
class TokenDeIngestao
{
    public function handle(Request $request, Closure $proximo): Response
    {
        $esperado = (string) config('mapa.token_ingestao');

        if ($esperado !== '' && ! hash_equals($esperado, (string) $request->bearerToken())) {
            abort(401, 'Token ausente ou inválido no cabeçalho Authorization.');
        }

        return $proximo($request);
    }
}

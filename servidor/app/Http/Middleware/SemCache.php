<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Impede que a resposta da API de transmissão seja guardada por qualquer intermediário.
 *
 * Toda rota de transmissão reflete o estado atual: o ao vivo muda a cada segundos, e o
 * histórico muda quando uma janela fecha. Guardar essa resposta — no navegador, no túnel
 * ou na borda da Vercel — mostra dado velho sem avisar. Já aconteceu: a tela ao vivo
 * ficava parada porque o navegador servia a resposta anterior do próprio cache.
 *
 * O Laravel manda `no-cache, private` por padrão, que ainda permite o navegador guardar e
 * revalidar; `no-store` é mais forte: não guarda de jeito nenhum. Também removemos o ETag,
 * que dispara requisições condicionais (If-None-Match) e respostas 304 que não queremos
 * numa tela que relê de poucos em poucos segundos.
 */
class SemCache
{
    public function handle(Request $request, Closure $proximo): Response
    {
        $resposta = $proximo($request);

        $resposta->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $resposta->headers->remove('ETag');
        $resposta->headers->remove('Last-Modified');

        return $resposta;
    }
}

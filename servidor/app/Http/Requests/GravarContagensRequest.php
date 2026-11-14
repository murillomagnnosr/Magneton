<?php

namespace App\Http\Requests;

use App\Models\Zona;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * O contrato de entrada da API de ingestão (REQUISITOS.md, RNF01, RNF03 e RNF07).
 *
 * O contrato é fechado: qualquer campo além dos cinco previstos é recusado com 422. Não
 * existe campo para imagem, vídeo ou identificador de pessoa, de aparelho ou de sessão,
 * e não há como criar um por aqui.
 */
class GravarContagensRequest extends FormRequest
{
    private const CAMPOS_DO_REGISTRO = ['zona_id', 'inicio_janela', 'duracao_min', 'contagem', 'fonte'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'registros' => ['required', 'array', 'min:1', 'max:10000'],
            'registros.*.zona_id' => ['required', 'string', 'regex:/^Z\d{2}$/', Rule::exists('zonas', 'id')],
            'registros.*.inicio_janela' => ['required', 'string', 'date_format:Y-m-d\TH:i'],
            'registros.*.duracao_min' => ['required', 'integer', Rule::in([config('mapa.duracao_min')])],
            'registros.*.contagem' => ['required', 'integer', 'min:0'],
            'registros.*.fonte' => ['required', Rule::in(config('mapa.fontes'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'registros.required' => 'Envie ao menos um registro em "registros".',
            'registros.*.zona_id.regex' => 'zona_id deve casar com ^Z\d{2}$.',
            'registros.*.zona_id.exists' => 'A zona informada não existe na planta.',
            'registros.*.inicio_janela.date_format' => 'inicio_janela deve ser AAAA-MM-DDTHH:MM, hora local, sem segundos.',
            'registros.*.duracao_min.in' => 'duracao_min deve ser '.config('mapa.duracao_min').' em todo o conjunto.',
            'registros.*.contagem.integer' => 'contagem deve ser um número inteiro de pessoas.',
            'registros.*.contagem.min' => 'contagem não pode ser negativa.',
            'registros.*.fonte.in' => 'fonte deve ser "simulado" ou "sensor".',
        ];
    }

    /**
     * Regras que dependem de mais de um campo ou do conjunto todo.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validacao) => $this->recusarCamposForaDoContrato($validacao),
            fn (Validator $validacao) => $this->conferirJanelas($validacao),
            fn (Validator $validacao) => $this->conferirTetoPorArea($validacao),
            fn (Validator $validacao) => $this->conferirCoerenciaDoLote($validacao),
        ];
    }

    /**
     * Campo a mais é erro, não é ignorado: é assim que `device_id` ou `imagem` são barrados.
     */
    private function recusarCamposForaDoContrato(Validator $validacao): void
    {
        foreach (array_keys($this->all()) as $campo) {
            if ($campo !== 'registros') {
                $validacao->errors()->add($campo, "O campo \"{$campo}\" não faz parte do contrato.");
            }
        }

        foreach ($this->input('registros', []) as $i => $registro) {
            if (! is_array($registro)) {
                continue;
            }

            foreach (array_diff(array_keys($registro), self::CAMPOS_DO_REGISTRO) as $campo) {
                $validacao->errors()->add(
                    "registros.{$i}.{$campo}",
                    "O campo \"{$campo}\" não faz parte do contrato. São aceitos apenas: "
                    .implode(', ', self::CAMPOS_DO_REGISTRO).'.'
                );
            }
        }
    }

    /**
     * A janela é alinhada e cai dentro do horário de funcionamento (RNF03).
     */
    private function conferirJanelas(Validator $validacao): void
    {
        $duracao = (int) config('mapa.duracao_min');
        $abre = config('mapa.horario_abre');
        $fecha = config('mapa.horario_fecha');

        foreach ($this->input('registros', []) as $i => $registro) {
            $campo = "registros.{$i}.inicio_janela";

            // Formato já reprovado acima: não adianta tentar interpretar a data de novo.
            if ($validacao->errors()->has($campo)) {
                continue;
            }

            $texto = $registro['inicio_janela'] ?? null;

            if (! is_string($texto)) {
                continue;
            }

            $quando = Carbon::createFromFormat('Y-m-d\TH:i', $texto);

            if ($quando->minute % $duracao !== 0) {
                $validacao->errors()->add($campo, "inicio_janela deve estar alinhado a {$duracao} minutos.");
            }

            $hora = $quando->format('H:i');

            if ($hora < $abre || $hora >= $fecha) {
                $validacao->errors()->add($campo, "inicio_janela está fora do horário de funcionamento ({$abre}–{$fecha}).");
            }
        }
    }

    /**
     * Teto de sanidade: mais pessoas do que cabem na zona é erro de medição (RNF01).
     */
    private function conferirTetoPorArea(Validator $validacao): void
    {
        $zonas = Zona::query()->findMany(
            collect($this->input('registros', []))->pluck('zona_id')->filter()->unique()
        )->keyBy('id');

        foreach ($this->input('registros', []) as $i => $registro) {
            $zona = $zonas->get($registro['zona_id'] ?? null);
            $contagem = $registro['contagem'] ?? null;

            if (! $zona || ! is_int($contagem)) {
                continue;
            }

            if ($contagem > $zona->tetoDeContagem()) {
                $validacao->errors()->add(
                    "registros.{$i}.contagem",
                    "contagem acima do teto de {$zona->tetoDeContagem()} pessoas para a zona {$zona->id}."
                );
            }
        }
    }

    /**
     * Um lote tem uma fonte só e nenhum par (zona, janela) repetido (RNF03 e RNF07).
     */
    private function conferirCoerenciaDoLote(Validator $validacao): void
    {
        $registros = collect($this->input('registros', []));

        if ($registros->pluck('fonte')->filter()->unique()->count() > 1) {
            $validacao->errors()->add('registros', 'Um lote tem uma única fonte: não misture simulado com sensor.');
        }

        $pares = $registros->map(fn (array $r): string => ($r['zona_id'] ?? '').'|'.($r['inicio_janela'] ?? ''));

        if ($pares->unique()->count() !== $pares->count()) {
            $validacao->errors()->add('registros', 'O par (zona_id, inicio_janela) não pode se repetir no lote.');
        }
    }
}

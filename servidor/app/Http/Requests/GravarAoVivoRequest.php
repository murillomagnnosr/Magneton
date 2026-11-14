<?php

namespace App\Http\Requests;

use App\Models\Zona;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Contrato do estado ao vivo: zona, quantas pessoas, e quando foi medido.
 *
 * Fechado como o das contagens: campo a mais é recusado com 422. Continua não havendo
 * campo para imagem nem para identificador de pessoa, de aparelho ou de sessão.
 */
class GravarAoVivoRequest extends FormRequest
{
    private const CAMPOS = ['zona_id', 'pessoas', 'medido_em'];

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
            'zonas' => ['required', 'array', 'min:1', 'max:200'],
            'zonas.*.zona_id' => ['required', 'string', 'regex:/^Z\d{2}$/', Rule::exists('zonas', 'id')],
            'zonas.*.pessoas' => ['required', 'integer', 'min:0'],
            'zonas.*.medido_em' => ['required', 'date_format:Y-m-d\TH:i:s'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'zonas.required' => 'Envie ao menos uma zona em "zonas".',
            'zonas.*.medido_em.date_format' => 'medido_em deve ser AAAA-MM-DDTHH:MM:SS, hora local.',
            'zonas.*.pessoas.min' => 'pessoas não pode ser negativo.',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validacao) => $this->recusarCamposForaDoContrato($validacao),
            fn (Validator $validacao) => $this->conferirTetoPorArea($validacao),
        ];
    }

    private function recusarCamposForaDoContrato(Validator $validacao): void
    {
        foreach (array_keys($this->all()) as $campo) {
            if ($campo !== 'zonas') {
                $validacao->errors()->add($campo, "O campo \"{$campo}\" não faz parte do contrato.");
            }
        }

        foreach ($this->input('zonas', []) as $i => $zona) {
            if (! is_array($zona)) {
                continue;
            }

            foreach (array_diff(array_keys($zona), self::CAMPOS) as $campo) {
                $validacao->errors()->add(
                    "zonas.{$i}.{$campo}",
                    "O campo \"{$campo}\" não faz parte do contrato. São aceitos apenas: "
                    .implode(', ', self::CAMPOS).'.'
                );
            }
        }
    }

    private function conferirTetoPorArea(Validator $validacao): void
    {
        $zonas = Zona::query()->findMany(
            collect($this->input('zonas', []))->pluck('zona_id')->filter()->unique()
        )->keyBy('id');

        foreach ($this->input('zonas', []) as $i => $linha) {
            $zona = $zonas->get($linha['zona_id'] ?? null);
            $pessoas = $linha['pessoas'] ?? null;

            if (! $zona || ! is_int($pessoas)) {
                continue;
            }

            if ($pessoas > $zona->tetoDeContagem()) {
                $validacao->errors()->add(
                    "zonas.{$i}.pessoas",
                    "pessoas acima do teto de {$zona->tetoDeContagem()} para a zona {$zona->id}."
                );
            }
        }
    }
}

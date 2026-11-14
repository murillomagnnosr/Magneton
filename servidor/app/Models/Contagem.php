<?php

namespace App\Models;

use Database\Factories\ContagemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quantas pessoas havia em uma região, em uma janela de tempo.
 */
class Contagem extends Model
{
    /** @use HasFactory<ContagemFactory> */
    use HasFactory;

    protected $table = 'contagens';

    protected $fillable = ['zona_id', 'inicio_janela', 'duracao_min', 'contagem', 'fonte'];

    /** @return BelongsTo<Zona, $this> */
    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    /**
     * Limita a consulta a um dia.
     *
     * @param  Builder<Contagem>  $consulta
     */
    public function scopeDoDia(Builder $consulta, string $dia): void
    {
        $consulta->whereBetween('inicio_janela', ["{$dia} 00:00:00", "{$dia} 23:59:59"]);
    }

    /**
     * Limita a consulta a um período da lista fechada de `config/mapa.php`.
     *
     * @param  Builder<Contagem>  $consulta
     */
    public function scopeDoPeriodo(Builder $consulta, string $dia, string $periodo): void
    {
        [$comeca, $termina] = config("mapa.periodos.{$periodo}");

        $consulta->whereBetween('inicio_janela', [
            sprintf('%s %02d:00:00', $dia, $comeca),
            sprintf('%s %02d:00:00', $dia, $termina),
        ])->where('inicio_janela', '<', sprintf('%s %02d:00:00', $dia, $termina));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'inicio_janela' => 'datetime',
            'duracao_min' => 'integer',
            'contagem' => 'integer',
        ];
    }
}

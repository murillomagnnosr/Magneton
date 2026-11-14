<?php

namespace App\Models;

use Database\Factories\ZonaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uma região do piso: a unidade mínima de observação.
 *
 * O dado nunca desce abaixo da zona, e não existe consulta por polígono arbitrário.
 */
class Zona extends Model
{
    /** @use HasFactory<ZonaFactory> */
    use HasFactory;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'nome', 'tipo', 'piso', 'area_m2', 'centroide_x', 'centroide_y', 'vizinhos', 'planta',
    ];

    /** @return HasMany<Contagem, $this> */
    public function contagens(): HasMany
    {
        return $this->hasMany(Contagem::class);
    }

    /**
     * Pessoas por 100 m². Comparar contagem bruta favoreceria as zonas grandes.
     */
    public function densidade(float $pessoas): float
    {
        return round(100 * $pessoas / $this->area_m2, 2);
    }

    /**
     * Teto de sanidade da contagem para esta zona (RNF01).
     */
    public function tetoDeContagem(): int
    {
        return (int) config('mapa.teto_por_m2') * $this->area_m2;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'piso' => 'integer',
            'area_m2' => 'integer',
            'centroide_x' => 'float',
            'centroide_y' => 'float',
            'vizinhos' => 'array',
            'planta' => 'array',
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Zona;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Uma região do piso, como o painel precisa dela para desenhar a planta.
 *
 * @mixin Zona
 */
class ZonaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'tipo' => $this->tipo,
            'area_m2' => $this->area_m2,
            'centroide' => [$this->centroide_x, $this->centroide_y],
            'vizinhos' => $this->vizinhos,
            'planta' => $this->planta,
        ];
    }
}

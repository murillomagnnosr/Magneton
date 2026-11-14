<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ZonaResource;
use App\Models\Zona;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * As regiões do piso, para o painel desenhar a planta.
 */
class ZonaController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ZonaResource::collection(Zona::query()->orderBy('id')->get());
    }
}

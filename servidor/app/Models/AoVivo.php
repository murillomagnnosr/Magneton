<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O estado atual de uma zona: quantas pessoas havia na última medição.
 *
 * Serve à operação, não à análise. Uma linha por zona, sobrescrita a cada envio.
 */
class AoVivo extends Model
{
    protected $table = 'ao_vivo';

    protected $primaryKey = 'zona_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = ['zona_id', 'pessoas', 'medido_em'];

    /** @return BelongsTo<Zona, $this> */
    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'pessoas' => 'integer',
            'medido_em' => 'datetime',
        ];
    }
}

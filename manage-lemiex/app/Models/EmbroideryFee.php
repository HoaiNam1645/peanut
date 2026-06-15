<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmbroideryFee extends Model
{
    protected $table = 'embroidery_fee';

    protected $fillable = [
        'tier_id',
        'embroidery_type',
        'min_stitch',
        'max_stitch',
        'amount',
    ];

    protected $casts = [
        'tier_id' => 'integer',
        'min_stitch' => 'integer',
        'max_stitch' => 'integer',
        'amount' => 'decimal:2',
    ];

    /**
     * Embroidery types enum
     */
    public const TYPE_STANDARD = 'standard';
    public const TYPE_METALLIC = 'metallic';
    public const TYPE_GLOW = 'glow';
    public const TYPE_PUFF = 'puff';

    public static function types(): array
    {
        return [
            self::TYPE_STANDARD,
            self::TYPE_METALLIC,
            self::TYPE_GLOW,
            self::TYPE_PUFF,
        ];
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(Tier::class, 'tier_id', 'tier_id');
    }
}

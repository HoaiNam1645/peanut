<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemWorkflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_item_id',
        'position',
        'stage',
        'completed',
        'completed_by',
        'completed_at',
    ];

    protected $casts = [
        'completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    // Positions that can be tracked
    const POSITIONS = ['front', 'back', 'sleeve_left', 'sleeve_right', 'neck'];

    // Workflow stages
    const STAGE_STAFF = 'staff';
    const STAGE_QC = 'qc';
    const STAGE_PACKING = 'packing';
    const STAGE_SHIPOUT = 'shipout';

    const STAGES = [
        self::STAGE_STAFF,
        self::STAGE_QC,
        self::STAGE_PACKING,
        self::STAGE_SHIPOUT,
    ];

    /**
     * Get the order item
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * Get the user who completed this stage
     */
    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}

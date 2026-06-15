<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'group',
        'route',
        'method',
        'removable',
    ];

    protected $casts = [
        'removable' => 'boolean',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Get permissions grouped by group name
     */
    public static function getGrouped()
    {
        return self::orderBy('group')->orderBy('name')->get()->groupBy('group');
    }

    /**
     * Scope to filter by group
     */
    public function scopeInGroup($query, $group)
    {
        return $query->where('group', $group);
    }
}

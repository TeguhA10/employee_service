<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    protected $table = 'positions';

    protected $fillable = [
        'name',
        'level',
        'division',
        'parent_position_id',
        'branch_id',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'level' => 'integer',
            'parent_position_id' => 'integer',
            'branch_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'parent_position_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Position::class, 'parent_position_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Check if a proposed parent position ID would create a circular reference.
     */
    public function createsCircularReference(int $proposedParentId): bool
    {
        if ($this->id === $proposedParentId) {
            return true;
        }

        $ancestorId = $proposedParentId;
        while ($ancestorId !== null) {
            if ($ancestorId === $this->id) {
                return true;
            }
            $ancestor = self::find($ancestorId);
            $ancestorId = $ancestor ? $ancestor->parent_position_id : null;
        }

        return false;
    }
}

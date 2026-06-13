<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $table = 'branches';

    protected $fillable = [
        'name',
        'code',
        'parent_id',
        'address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'parent_id' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Branch::class, 'parent_id');
    }

    /**
     * Check if a proposed parent ID would create a circular reference.
     * Setting parent_id to $proposedParentId is invalid if $this->id is in the ancestry of $proposedParentId.
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
            $ancestorId = $ancestor ? $ancestor->parent_id : null;
        }

        return false;
    }
}

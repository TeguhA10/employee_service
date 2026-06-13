<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    use SoftDeletes;

    protected $table = 'employees';

    protected $fillable = [
        'user_id',
        'nama_lengkap',
        'nomor_induk_karyawan',
        'alamat',
        'branch_id',
        'position_id',
        'tanggal_gabung',
        'tanggal_mulai_kontrak',
        'tanggal_akhir_kontrak',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'integer',
            'branch_id' => 'integer',
            'position_id' => 'integer',
            'tanggal_gabung' => 'date:Y-m-d',
            'tanggal_mulai_kontrak' => 'date:Y-m-d',
            'tanggal_akhir_kontrak' => 'date:Y-m-d',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }
}

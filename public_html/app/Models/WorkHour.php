<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkHour extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    public function master()
    {
        return $this->belongsTo(Master::class, 'student_code', 'student_code');
    }

}

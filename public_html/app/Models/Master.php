<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Master extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    public function workHours()
    {
        return $this->hasMany(WorkHour::class, 'student_code', 'student_code');
    }

}

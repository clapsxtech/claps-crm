<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    use HasFactory;
    protected $fillable = ['id','teacher_name','teacher_code', 'status','type','subject','medium'];
    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_code', 'teacher_code');
    }
}

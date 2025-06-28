<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAnswer extends Model
{
    public $fillable = ['user_id', 'category', 'question_id', 'is_correct'];
}

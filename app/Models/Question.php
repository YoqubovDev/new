<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = [
        'category',
        'question',
        'option_a',
        'option_b',
        'option_c',
        'option_d',
        'correct_option'
    ];
}

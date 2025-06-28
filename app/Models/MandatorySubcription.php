<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MandatorySubcription extends Model
{
    protected $fillable = [
        'type',
        'channelId',
        'link'
    ];
}

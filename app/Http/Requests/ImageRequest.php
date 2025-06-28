<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImageRequest extends FormRequest
{
    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        return [
            'amount'        => 'required|string',
            'sender'        => 'required|string',
            'receiver'      => 'required|string',
            'sender_num'    => 'required|string',
            'receiver_num'  => 'required|string',
        ];
    }
}

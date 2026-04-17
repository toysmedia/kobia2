<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRouterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'connection_type' => 'required|in:public_ip,openvpn',
            'ip_address' => 'required|ip',
            'nas_secret' => 'required|string|max:100',
            'port' => 'required|integer|min:1|max:65535',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }
}

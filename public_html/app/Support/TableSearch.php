<?php
namespace App\Support;
use Illuminate\Http\Request;
class TableSearch
{
    public static function term(Request $request): string
    {
        $value = $request->input('search');
        if (is_array($value)) $value = $value['value'] ?? '';
        return is_string($value) ? mb_substr(trim($value), 0, 255) : '';
    }
}

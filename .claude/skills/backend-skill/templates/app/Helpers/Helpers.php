<?php

namespace App\Helpers;

use Illuminate\Http\Request;

class Helpers
{
    public static function getBasicRequestParams(Request $request)
    {
        return [
            'sortBy' => $request->sortBy ?? 'id',
            'per_page' => $request->per_page ?? Helpers::getPagination(),
            'sortDirection' => $request->sortDirection ?? 'desc',
        ];
    }

    public static function getPagination()
    {
        return request('paginate') ?? 15;
    }

    public static function getCurrentLang()
    {
        return App()->getlocale();
    }

    public static function checkPermissions($permission, $role)
    {
        return in_array($permission->id, $role->permissions()->pluck('id')->toArray()) ? 'checked' : '';
    }
}

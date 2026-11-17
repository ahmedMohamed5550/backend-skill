<?php

namespace App\Helpers;

use App\Constants\Constants;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

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

    public static function getLanguages()
    {
        return ['ar', 'en'];
    }

    public static function getPagination()
    {
        return request('paginate') ?? 15;
    }

    public static function ContextPagination()
    {
        $pagination = request('paginate');
        if ($pagination === 'off') {
            return PHP_INT_MAX;
        }

        return $pagination ?? 15;
    }

    public static function getCurrentLang()
    {
        return App()->getlocale();
    }

    public static function checkPermissions($permission, $role)
    {
        return in_array($permission->id, $role->permissions()->pluck('id')->toArray()) ? 'checked' : '';
    }

    public static function generateCacheKey(Request $request, $data, $isPublic = false)
    {
        $requestData = collect($request->all())->except(['photo', 'file', 'attachment']);

        $requestData = $requestData->filter(function ($value) {
            return ! ($value instanceof UploadedFile);
        });

        $requestData = $requestData->toArray();
        $serializedRequest = md5(serialize($requestData));
        $user_id = null;

        if (! $isPublic) {
            $user_id = auth()->user()->id;
        }

        $scoutPrefix = env('SCOUT_PREFIX');

        return "{$data}_user{$user_id}_{$serializedRequest}_{$scoutPrefix}";
    }

    public static function getPaginationMeta($items, $count)
    {
        if (request()->all == 1) {
            return;
        }

        return [
            'path' => url()->current(),
            'per_page' => $items->perPage(),
            'current_page' => $items->currentPage(),
            'next_page_url' => $items->nextPageUrl(),
            'prev_page_url' => $items->previousPageUrl(),
            'total' => $count ?? $items->total(),
            'last_page' => $items->lastPage(),
        ];
    }

    public static function isNullishFilterValue(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return $normalized === 'null' || $normalized === 'undefined';
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (! self::isNullishFilterValue($item)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    public static function toNumericId(mixed $value): ?int
    {
        if (is_bool($value) || is_array($value) || self::isNullishFilterValue($value)) {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    public static function toNumericIds(mixed $value): array
    {
        if (self::isNullishFilterValue($value)) {
            return [];
        }

        $values = is_array($value) ? $value : [$value];

        return array_values(array_filter(
            array_map([self::class, 'toNumericId'], $values),
            static fn (?int $id) => $id !== null
        ));
    }
}

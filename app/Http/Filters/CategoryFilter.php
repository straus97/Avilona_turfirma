<?php
namespace App\Http\Filters;

use Illuminate\Database\Eloquent\Builder;

class CategoryFilter extends AbstractFilter
{
    protected function getCallbacks(): array
    {
        return [
            'category' => function (Builder $builder, $value) {
                $builder->where('category', $value);
            },
        ];
    }
}

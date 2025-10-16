<?php
namespace App\Http\Filters;

use Illuminate\Database\Eloquent\Builder;

class TitleFilter extends AbstractFilter
{
    protected function getCallbacks(): array
    {
        return [
            'title' => function (Builder $builder, $value) {
                $builder->where('title', 'LIKE', '%' . $value . '%');
            },
        ];
    }
}

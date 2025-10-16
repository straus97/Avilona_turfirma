<?php
namespace App\Http\Filters;
use Illuminate\Database\Eloquent\Builder;
class NewsFilter extends AbstractFilter
{
    protected function getCallbacks(): array
    {
        return [
            'date' => [$this, 'date'],
        ];
    }

    public function date(Builder $builder, $value)
    {
        $date = date('Y-m-d H:i:s', strtotime($value));
        $builder->where('pub_date', '<=', $date);
    }
}

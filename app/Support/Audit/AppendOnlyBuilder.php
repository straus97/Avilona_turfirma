<?php

namespace App\Support\Audit;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Построитель запросов для append-only моделей: закрывает массовые операции,
 * которые обходят события модели (Model::query()->delete() и т. п.).
 */
class AppendOnlyBuilder extends Builder
{
    public function update(array $values): never
    {
        throw new LogicException('Audit events are append-only and cannot be updated.');
    }

    public function upsert(array $values, $uniqueBy, $update = null): never
    {
        throw new LogicException('Audit events are append-only and cannot be updated.');
    }

    public function increment($column, $amount = 1, array $extra = []): never
    {
        throw new LogicException('Audit events are append-only and cannot be updated.');
    }

    public function decrement($column, $amount = 1, array $extra = []): never
    {
        throw new LogicException('Audit events are append-only and cannot be updated.');
    }

    public function delete(): never
    {
        throw new LogicException('Audit events are append-only and cannot be deleted.');
    }

    public function forceDelete(): never
    {
        throw new LogicException('Audit events are append-only and cannot be deleted.');
    }

    public function truncate(): never
    {
        throw new LogicException('Audit events are append-only and cannot be deleted.');
    }
}

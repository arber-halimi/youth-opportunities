<?php

namespace App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

interface IRepository
{
    public function find(int|string $id): ?Model;

    public function paginate(int $perPage): LengthAwarePaginator;

    public function create(array $attributes): Model;

    public function update(Model $record, array $attributes): Model;

    public function delete(Model $record): bool;
}

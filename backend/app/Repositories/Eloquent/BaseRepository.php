<?php

namespace App\Repositories\Eloquent;

use App\Repositories\IRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

abstract class BaseRepository implements IRepository
{
    public function __construct(protected Model $model)
    {
    }

    public function find(int|string $id): ?Model
    {
        return $this->model->newQuery()->find($id);
    }

    public function paginate(int $perPage): LengthAwarePaginator
    {
        return $this->model->newQuery()->paginate($perPage)->withQueryString();
    }

    public function create(array $attributes): Model
    {
        return $this->model->newQuery()->create($attributes);
    }

    public function update(Model $record, array $attributes): Model
    {
        if (! $record->update($attributes)) {
            throw new RuntimeException('The record could not be updated.');
        }

        return $record->refresh();
    }

    public function delete(Model $record): bool
    {
        return $record->delete() === true;
    }
}

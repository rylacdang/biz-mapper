<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface BaseRepositoryInterface
{
    /**
     * Get all records.
     */
    public function all(): Collection;

    /**
     * Paginate records.
     */
    public function paginate(int $perPage = 10): LengthAwarePaginator;

    /**
     * Find record by ID.
     */
    public function find(string|int $id): ?Model;

    /**
     * Create new record.
     */
    public function create(array $data): Model;

    /**
     * Update existing record.
     */
    public function update(string|int $id, array $data): bool;

    /**
     * Delete record by ID.
     */
    public function delete(string|int $id): bool;

    /**
     * Get query builder instance.
     */
    public function query(): Builder;
}

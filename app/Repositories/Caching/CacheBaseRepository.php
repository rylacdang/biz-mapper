<?php

namespace App\Repositories\Caching;

use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

abstract class CacheBaseRepository implements BaseRepositoryInterface
{
    protected int $ttl = 3600;

    public function __construct(
        protected readonly BaseRepositoryInterface $eloquentRepo,
        protected readonly string $cacheTag
    ) {}

    public function all(): Collection
    {
        return $this->remember('all', fn () => $this->eloquentRepo->all());
    }

    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        // Pagination bypassed (dynamic parameters not safely cacheable)
        return $this->eloquentRepo->paginate($perPage);
    }

    public function find(string|int $id): ?Model
    {
        return $this->remember("find.{$id}", fn () => $this->eloquentRepo->find($id));
    }

    public function create(array $data): Model
    {
        $model = $this->eloquentRepo->create($data);

        $this->flush();

        return $model;
    }

    public function update(string|int $id, array $data): bool
    {
        $updated = $this->eloquentRepo->update($id, $data);

        $this->flush();

        return $updated;
    }

    public function delete(string|int $id): bool
    {
        $deleted = $this->eloquentRepo->delete($id);

        $this->flush();

        return $deleted;
    }

    public function query(): Builder
    {
        return $this->eloquentRepo->query();
    }

    /**
     * Cache remember helper utilizing Redis tags.
     */
    protected function remember(string $key, callable $callback)
    {
        return Cache::tags([$this->cacheTag])->remember(
            $key,
            $this->ttl,
            $callback
        );
    }

    /**
     * Cache forget helper utilizing Redis tags.
     */
    protected function forget(string|array $keys): void
    {
        $cache = Cache::tags([$this->cacheTag]);

        foreach ((array) $keys as $key) {
            $cache->forget($key);
        }
    }

    /**
     * Flush cache tag.
     */
    protected function flush(): void
    {
        Cache::tags([$this->cacheTag])->flush();
    }
}

<?php

namespace App\Repositories\Caching;

use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

abstract class CacheBaseRepository implements BaseRepositoryInterface
{
    protected int $ttl = 60;

    protected array $relatedTags = [];

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
        return $this->eloquentRepo->paginate($perPage);
    }

    public function find(string|int $id): ?Model
    {
        return $this->remember("find.{$id}", fn () => $this->eloquentRepo->find($id));
    }

    public function create(array $data): Model
    {
        $model = $this->eloquentRepo->create($data);

        $this->invalidate();

        return $model;
    }

    public function update(string|int $id, array $data): bool
    {
        $updated = $this->eloquentRepo->update($id, $data);

        $this->invalidate();

        return $updated;
    }

    public function delete(string|int $id): bool
    {
        $deleted = $this->eloquentRepo->delete($id);

        $this->invalidate();

        return $deleted;
    }

    public function query(): Builder
    {
        return $this->eloquentRepo->query();
    }

    public function invalidate(): void
    {
        if (DB::connection()->transactionLevel() > 0) {
            DB::afterCommit(fn () => $this->flush());

            return;
        }

        $this->flush();
    }

    /**
     * Keep keys distinct for every argument, including exact coordinates.
     */
    protected function key(string $method, array $arguments): string
    {
        return $method.':'.hash('sha256', serialize($arguments));
    }

    /**
     * Cache isolated snapshots and never publish uncommitted database reads.
     */
    protected function remember(string $key, callable $callback): mixed
    {
        if (DB::connection()->transactionLevel() > 0) {
            return $callback();
        }

        $cache = Cache::tags([$this->cacheTag]);
        $key = $cache->getTags()->getNamespace().':'.$key;
        $snapshot = $cache->remember($key, $this->ttl, fn () => serialize($callback()));
        $result = unserialize($snapshot);

        if ($result instanceof LengthAwarePaginator) {
            $result->withPath(Paginator::resolveCurrentPath());
        }

        return $result;
    }

    /**
     * Evict this repository and cached data that embeds its records.
     */
    protected function flush(): void
    {
        Cache::tags([$this->cacheTag, ...$this->relatedTags])->flush();
    }
}

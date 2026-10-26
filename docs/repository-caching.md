# Repository caching

`AppServiceProvider::$repositories` maps every repository interface to its Eloquent implementation and cache decorator. The registration loop resolves the Eloquent repository, then passes it explicitly to `new $caching($eloquentRepo)`. Services and controllers continue to inject their existing interfaces.

## Redis setup

Use the existing Predis dependency with these backend environment settings:

```dotenv
CACHE_STORE=redis
REDIS_CLIENT=predis
```

Redis must be available to the application and queue workers. Repository caches use Laravel cache tags and require a tag-capable cache store; automated tests use Laravel's array store. No Redis connection settings or credentials are hardcoded in repositories.

## Cached reads

- Categories: 60 minutes.
- Business profiles, nearby results and markers, catalogs, saved places, collection pages, reviews, and stored summary state: 60 seconds.
- Bell pages and unread counts: 15 seconds. Existing persistent bell read markers do not expire when query results are invalidated.

Keys include all query inputs. Nearby searches preserve exact coordinates and radius. Private pages include account identifiers, and collection keys distinguish public and private results. Cached models are returned as separate snapshots, so response-only bookmark attributes cannot modify another response. Cached paginators use the current request path.

Live reads remain in place for builders, row locks, slug availability, owner metrics and trash, bookmark annotations, the viewer's editable review, public collection access checks, AI source fingerprints, and scheduled work. Creating a cache decorator does not mean caching locks or authorization decisions.

## Writes and invalidation

Decorators invalidate their own tag and related repository tags after a successful transaction commits. Reads inside a transaction bypass cached snapshots. Rollbacks leave the committed cache intact. Cache keys also include the current tag namespace so a slow request cannot republish an old snapshot after invalidation.

Model callbacks registered in `AppServiceProvider` cover direct model saves, deletes, restoration saves, and account deletion cascades. Repository methods that change collection membership, remove saves, or delete replies explicitly invalidate their tags because query-builder writes do not dispatch model events. Profile view increments keep their existing live analytics behavior.

Custom bulk writes through `query()` or raw SQL must explicitly call the affected decorator's `invalidate()` after the write. Never flush the entire Redis database to invalidate repository data.

## Verification

`tests/Unit/RepositoryCachingTest.php` boots an isolated application with in-memory SQLite, without the project's outer test transaction. This lets it test real cache hits, commit/rollback behavior, privacy isolation, stale-result rejection, TTLs, and cross-repository invalidation.

```shell
php artisan test --compact tests/Unit/RepositoryCachingTest.php
```

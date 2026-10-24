# AI review summaries

BizMapper uses the Laravel AI SDK with Groq and explicitly selects `openai/gpt-oss-120b`. There is no automatic model selection or provider fallback. The agent declares its provider, model, temperature, token limit, and timeout through SDK attributes. Command metadata and fixed job settings use Laravel attributes; the queue connection stays configurable at runtime.

## Setup

1. Install Composer dependencies and run `php artisan migrate`.
2. Set `GROQ_API_KEY` in the backend environment. Never prefix it with `VITE_`.
3. Keep `AI_SUMMARY_QUEUE_CONNECTION=database`, or choose another asynchronous queue connection. Do not use `sync`.
4. Run `php artisan queue:work database --timeout=60 --tries=3`.
5. Run `php artisan schedule:work` locally. In production, run `php artisan schedule:run` every minute through your scheduler.
6. Run `php artisan ai:summaries:dispatch` for initial backfill. The scheduled command also discovers eligible existing businesses.

`AI_SUMMARIES_ENABLED` defaults to true. After correcting credentials or a permanent provider error, operators can run `php artisan ai:summaries:dispatch --retry`.

## Lifecycle

Review creation, body/rating changes, visibility changes, and deletion invalidate stored text within the review transaction. The next due time moves to 60 seconds after the latest change. Owner replies do not trigger generation. Account deletion invalidates affected summaries before foreign-key cascades remove reviews.

An after-commit event queues a unique job. Every job checks the current revision, due time, eligibility, and fingerprint. Network requests run outside database locks. Completion acquires the per-business lock again and rejects superseded output. Business deletion hides summaries; restoration rechecks eligibility.

Scheduler recovery runs every minute, so it may add another minute after the quiet period. It recovers pending work and workers stopped during generation. Transient failures allow at most three generation attempts with bounded backoff. The provider timeout is 30 seconds and the job timeout is 60 seconds.

## Source and output

At least three published reviews are required. Each prompt contains the latest 50 published reviews in descending ID order, including ratings and anonymous labels. Each review body is capped at 1,000 characters after recognizable emails and phone numbers are removed. Hidden reviews, owner replies, and reviewer account fields are excluded.

The structured agent understands English, Filipino, and Taglish and requests a plain-text English summary of two or three sentences. Output must be 40â€“600 characters with at least two valid, distinct source labels. Invalid output becomes unavailable. Raw responses, reasoning, source labels, and fingerprints never enter the public resource.

The public `GET /businesses/{business:slug}/review-summary` endpoint checks stored freshness and never generates or queues work. Below-threshold sections are hidden. Eligible pending/unavailable states do not block reviews or listing actions.

The shared Vue component appears below the map details description and directly above profile reviews. It polls pending/generating states every 15 seconds for at most five minutes. Replacement requests and unmounting cancel earlier work.

## Verification

`php artisan test --filter=ReviewSummaryTest` uses SDK fakes and prevents stray prompts. Automated tests never contact Groq. A live smoke test requires a configured local key, running database, queue worker, and scheduler.

The SDK installation requires a compatible Laravel 13 release; the lockfile currently uses Laravel 13.38.0 and Laravel AI SDK 1.3.0.

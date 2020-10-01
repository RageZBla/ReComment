ReComment
===

[![Build Status](https://travis-ci.com/RageZBla/ReComment.svg?branch=master)](https://travis-ci.com/RageZBla/ReComment)

This is a small exercise to implement an highly scalable comment board using Redis and PHP and Laravel Framework.

Features
---

- allow users to post comments, and view them in a chronological feed
- users need user accounts.  login with a existing username logs you in, using a new username creates that users and logs in automatically.  no password required
- users can delete comments they've posted
- users can like comments from anyone

Technical specifications
---

- user reads/writes should interact with the cache server (redis) by default
- after X time has passed(5 min), content from cache server should be written to database (MySQL)
- data in cache server should be removed if not loaded after Y time (10 min)
- if data is requested that is no longer in cache server, fetch from database and repopulate it into cache server

Requirements
---

PHP 7.3, Redis, MySQL/MariaDB, [composer](https://getcomposer.org/doc/00-intro.md).


Quick start
----

- Install project dependencies `composer install`
- Copy `.env.example` to  `.env`
- run test `composer test`
- Seed redis cache server `php artisan app:seed`. This would create 10 users and 100 comments.
- `php artisan serve`, open your favorite browser to `http://localhost`

Console commands
---

- `app:seed`: seed database
- `app:sync`: sync cache server and RDBM
- `app:purge`: purge stall content out of cache server

The project is configured to run both the sync and purge every minute (in the scenario, you have a proper CRON entry that run `schedule:run` command.).

Environment variables
---

- `APP_SYNC_MINUTES`: number of minutes before content is saved to MariaDB
- `APP_PURGE_MINUTES`: number of minutes before stalled content is removed from Redis
- `APP_SEED_NUMBER_USERS`: number of users created by seed command
- `APP_SEED_NUMBER_COMMENTS`: number of comments created by seed command

Notes
---

We chosen not to use the TTL feature of redis to implement the purging. My feeling was that, we would want to make sure that an objects has been synced to MariaDB before purging it.

On the other hand, the current solution can not handle a cold boot where the redis instance would have been completely wiped. In order to handle this case, we would have to rebuild the "indexes" in redis using all the data available in MariaDB. This is totally possible to implement, I have just not done it yet.

Last, the homepage shows 100 comments in ascending order (oldest on top). The `CommentRepository` has some method to do paging. I have just not implemented it in the HTTP layer.

TODOs and self reflection
---

- `CommentContract` and `CommentRepository` should be split in multiples classes and use composition.
- Feature `LoginTest` is marked as skipped because of some odd issues with shared variables (in views, in this instance `$username` and `$errors`). See [question](https://stackoverflow.com/questions/57847185/laravel-middleware-sharing-variable-view-does-not-work-when-writing-http-test) on Stackoverflow.
- Some of the feature test does not test that flash messages are set.
- It's a lot of code, I am wondering if it is not possible to handle it more elegantly...

Quick benchmark
----

Showing all the comments on the homepage

| number of comments | min resp time | max  |
| ------------------ |:-------------:| ------:|
| 1000               | 430ms         | 500ms  |
| 500                | 225ms         | 300ms  |
| 100                | 60ms          | 100ms  |
| 10                 | 28ms          | 33ms   |

License
----

MIT

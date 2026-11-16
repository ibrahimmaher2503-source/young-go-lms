# Young Go LMS

Custom Laravel LMS. See [AGENTS.md](AGENTS.md) for the development workflow and
[ADR-001](docs/adr/ADR-001-stack.md) for the approved stack. The lockfiles record
the exact dependency versions.

For a fresh local checkout, use PHP 8.4, Composer 2, and Node 24:

```sh
cp .env.example .env
composer install
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate --seed
npm ci
npm run build
composer qa
php artisan app:check-translations
php artisan app:check-rtl
php artisan serve
```

Open http://127.0.0.1:8000/up. In PowerShell use `Copy-Item .env.example .env`
for a fresh checkout only. Enable PHP sockets in the CLI PHP configuration for Pest and Composer child processes. Native Windows lacks
pcntl and posix, so append `--ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix`
to local Composer installation only. SQLite, file cache and sync queues are
the local bootstrap defaults. Use Linux with Redis for Horizon and MySQL for
integration validation. Keep external service credentials outside the repository.

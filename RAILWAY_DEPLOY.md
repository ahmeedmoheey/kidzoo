# Railway deployment for KidZoo

This repository is a monorepo with two deployable services:

- `backend` for the public Laravel API
- `ml_service` for the FastAPI ML service

## Railway services

Create three resources in one Railway project:

- `kidzoo-db` as PostgreSQL
- `kidzoo-backend` from this GitHub repo
- `kidzoo-ml` from this GitHub repo

## Required service settings

### `kidzoo-backend`

- Root Directory: `/backend`
- Build Command: leave empty
- Start Command: leave empty

Railway will use `backend/Procfile`.

### `kidzoo-ml`

- Root Directory: `/ml_service`
- Build Command: leave empty
- Start Command: leave empty

Railway will use `ml_service/Procfile`.

## Backend environment variables

Set these on `kidzoo-backend`:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://<your-backend-domain>`
- `APP_KEY=<generate locally with php artisan key:generate --show>`
- `LOG_CHANNEL=stderr`
- `DB_CONNECTION=pgsql`
- `DB_HOST=<Railway Postgres host>`
- `DB_PORT=<Railway Postgres port>`
- `DB_DATABASE=<Railway Postgres database>`
- `DB_USERNAME=<Railway Postgres username>`
- `DB_PASSWORD=<Railway Postgres password>`
- `SESSION_DRIVER=database`
- `CACHE_STORE=database`
- `QUEUE_CONNECTION=database`
- `MAIL_MAILER=log`
- `ML_SERVICE_URL=https://<your-ml-domain>`
- `ML_SERVICE_TIMEOUT=15`

## ML environment variables

No required variables for `kidzoo-ml`.

## After first successful backend deploy

Run these commands in Railway shell for `kidzoo-backend`:

```bash
php artisan migrate --force --seed
```

## Mobile app base URL

Use:

`https://<your-backend-domain>/api`

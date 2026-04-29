# Deploying KidZoo on Render

This project is ready to deploy on Render as:

- `kidzoo-backend`: public Laravel web service for the mobile app
- `kidzoo-ml`: private FastAPI service for ML inference
- `kidzoo-db`: managed PostgreSQL database

## What this gives you

- A public HTTPS API URL that multiple phones can call
- A private ML service that only the Laravel backend can reach
- A managed production database

## Before deploying

1. Push the latest code to GitHub.
2. In Render, choose `New` -> `Blueprint`.
3. Select this repository so Render reads `render.yaml`.

## Required environment values

Set these in Render during setup:

- `APP_KEY`
  - Generate locally with:
    - `php artisan key:generate --show`
- `APP_URL`
  - Use your backend Render URL, such as:
    - `https://kidzoo-backend.onrender.com`

Optional production values:

- `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`
- `N8N_AI_WEBHOOK_URL`, `N8N_AI_TOKEN`
- `N8N_CHAT_WEBHOOK_URL`, `N8N_CHAT_TOKEN`

## Render architecture

- Phones call the public Laravel backend URL
- Laravel talks to `kidzoo-ml` over Render's private network
- Laravel stores app data in Render Postgres

## Mobile app base URL

After deployment, use the backend service public URL in the mobile app, for example:

`https://kidzoo-backend.onrender.com/api`

## Notes

- The current Render spec uses the `free` plan placeholders. If your workspace no longer offers free instances, switch them to an available paid plan in the dashboard.
- `preDeployCommand` runs database migrations and seeds before each backend deploy.
- The ML service is private by design, so it is not exposed to the public internet.

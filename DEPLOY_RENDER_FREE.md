# KidZoo Free Deploy on Render

This repo now includes a ready-to-use `render.yaml` for a free trial/demo deploy on Render.

## What gets deployed

- `kidzoo-backend`: Laravel API with local built-in ML fallback

## Important limits

- Render free services are for demo/testing, not production.
- The backend is configured with SQLite at `/app/database/database.sqlite`.
- On free hosting, your SQLite data can be lost on rebuild/redeploy/restart.
- If you need stable data, move the backend to Render Postgres.

## Steps

1. Push this repo to GitHub.
2. Open Render.
3. Create a new Blueprint.
4. Select this repository.
5. Render will detect `render.yaml`.
6. During setup, enter:
   - `APP_URL`: the public backend URL Render gives you
   - Optional `N8N_*` values only if you use n8n
7. Deploy.

## Flutter base URL

After deploy, set Flutter `baseUrl` to:

`https://YOUR-BACKEND-NAME.onrender.com/api`

## Health check

- Backend: `/api/health`

## Notes

- The backend auto-runs migrations on startup.
- The backend auto-seeds the game names on startup.
- The backend runs with `ML_SERVICE_MODE=local`, so it does not require the external FastAPI service to complete predictions.

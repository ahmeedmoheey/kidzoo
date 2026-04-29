# KidZoo Backend — API Documentation (for Flutter)

Graduation project: kids educational app with Visual Perception Disorder detection via ML.

- **Base URL (dev):** `http://127.0.0.1:8000/api`
- **Auth:** Bearer token via Laravel Sanctum. Include `Authorization: Bearer <token>` on protected endpoints.
- **Content-Type:** `application/json`
- **Accept:** `application/json` (always send this, otherwise Laravel may redirect on validation errors).

> The ML service runs separately at `http://127.0.0.1:8001`. **Flutter never talks to it directly** — Laravel proxies everything.

---

## Table of Contents
1. [Architecture](#architecture)
2. [Error Format](#error-format)
3. [Parent Authentication](#1-parent-authentication)
4. [Parent Profile](#2-parent-profile)
5. [Children Management](#3-children-management)
6. [Parent Dashboard](#4-parent-dashboard)
7. [Parent Notifications](#5-parent-notifications)
8. [Child Authentication](#6-child-authentication)
9. [Games](#7-games)
10. [Game Sessions + ML Prediction](#8-game-sessions--ml-prediction)
11. [Chatbot](#9-chatbot)
12. [Enums & Reference](#enums--reference)
13. [Demo Credentials](#demo-credentials)
14. [Running Locally](#running-locally)

---

## Architecture

```
Flutter App
    │
    ▼  HTTP/JSON
Laravel API (port 8000)  ───►  FastAPI ML Service (port 8001)  ───►  LogisticRegression model
    │
    ▼
SQLite DB
```

**Flow:** parent registers → verifies OTP → adds child → child logs in → plays a game → Flutter streams trial metrics → on session end Laravel calls ML service → prediction stored + parent notified if disorder detected.

---

## Error Format

Validation error (`422`):
```json
{
  "message": "The email has already been taken.",
  "errors": { "email": ["The email has already been taken."] }
}
```

Other errors:
```json
{ "message": "Invalid credentials." }
```

Status codes:
- `200` success · `201` created · `400` bad request · `401` unauthenticated
- `403` forbidden · `404` not found · `409` conflict · `422` validation · `500` server

---

## 1. Parent Authentication

All OTPs are **6-digit** codes, valid for **10 minutes**. During development OTPs are logged to `storage/logs/laravel.log` (since `MAIL_MAILER=log`).

### 1.1 Register

`POST /api/parent/register`

Request:
```json
{
  "name": "Abdelaty Ahmed",
  "email": "abdelaty@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "phone": "+201000000000"
}
```

Response `201`:
```json
{
  "message": "Registration successful. Please verify your email with the OTP sent.",
  "email": "abdelaty@example.com"
}
```

### 1.2 Verify Email (OTP)

`POST /api/parent/verify-email`

Request:
```json
{ "email": "abdelaty@example.com", "otp": "123456" }
```

Response `200`:
```json
{
  "message": "Email verified successfully.",
  "token": "1|xxxxx",
  "user": { "id": 1, "name": "...", "email": "...", "email_verified_at": "..." }
}
```

Save this `token` and send it as `Authorization: Bearer <token>` on every protected request.

### 1.3 Resend Verification OTP

`POST /api/parent/resend-verification`

Request: `{ "email": "abdelaty@example.com" }`
Response `200`: `{ "message": "OTP sent." }`

### 1.4 Login

`POST /api/parent/login`

Request: `{ "email": "...", "password": "..." }`
Response `200`: same shape as Verify Email.
Response `403` when email not verified:
```json
{
  "message": "Email not verified. A new OTP has been sent.",
  "requires_verification": true,
  "email": "..."
}
```
Response `401` on invalid credentials.

### 1.5 Forgot Password

`POST /api/parent/forgot-password`

Request: `{ "email": "abdelaty@example.com" }`
Response `200`: `{ "message": "If this email is registered, an OTP has been sent." }` (always 200 to avoid email enumeration)

### 1.6 Verify Reset OTP

`POST /api/parent/verify-reset-otp`

Request: `{ "email": "...", "otp": "123456" }`
Response `200`:
```json
{
  "message": "OTP verified. Use the reset_token to set a new password.",
  "reset_token": "1|yyyyy"
}
```

### 1.7 Reset Password

`POST /api/parent/reset-password`
Headers: `Authorization: Bearer <reset_token>`
Request:
```json
{
  "password": "newpass123",
  "password_confirmation": "newpass123"
}
```
Response `200`: `{ "message": "Password reset successfully. Please login again." }`

All previous tokens are invalidated; user must log in again.

### 1.8 Me

`GET /api/parent/me`
Response `200`: `{ "user": { ... } }`

### 1.9 Logout

`POST /api/parent/logout` → revokes current token only.

---

## 2. Parent Profile

### 2.1 Show Profile

`GET /api/parent/profile`

### 2.2 Update Profile

`PUT /api/parent/profile`

Request (any subset):
```json
{
  "name": "New Name",
  "email": "new@example.com",
  "phone": "+20111",
  "avatar_url": "https://...",
  "language": "ar"
}
```
`language` ∈ `en | ar`.

### 2.3 Change Password

`POST /api/parent/profile/change-password`
```json
{
  "current_password": "password123",
  "password": "newpass123",
  "password_confirmation": "newpass123"
}
```

---

## 3. Children Management

### 3.1 List Children

`GET /api/parent/children`

Response:
```json
{
  "children": [
    {
      "id": 1, "parent_id": 1, "name": "Leo", "username": "leo",
      "age": 6, "gender": "boy", "avatar_url": null, "is_active": true,
      "last_login_at": "...", "created_at": "...", "updated_at": "..."
    }
  ]
}
```

### 3.2 Create Child

`POST /api/parent/children`
```json
{
  "name": "Leo",
  "username": "leo",
  "password": "kids1234",
  "password_confirmation": "kids1234",
  "age": 6,
  "gender": "boy",
  "avatar_url": "https://..."
}
```
Rules:
- `username`: 3-50 chars, alpha/dash/underscore, unique
- `password`: min 4 chars (kids, short)
- `age`: 3-17
- `gender`: `boy | girl`

Response `201`: `{ "message": "Child created.", "child": {...} }`

### 3.3 Show / Update / Delete

- `GET /api/parent/children/{id}`
- `PUT /api/parent/children/{id}` — any fields from create (optional `is_active`)
- `DELETE /api/parent/children/{id}`

---

## 4. Parent Dashboard

### 4.1 Child Dashboard

`GET /api/parent/children/{child_id}/dashboard`

Response:
```json
{
  "child": { ... },
  "summary": {
    "total_sessions": 12,
    "completed_sessions": 10,
    "total_trials": 58,
    "avg_accuracy": 76.5
  },
  "latest_prediction": {
    "id": 7, "status": "normal", "label": "Normal",
    "confidence": 0.93, "prob_normal": 0.93, "prob_disorder": 0.07,
    "weak_skills": [], "training_plan": [], "created_at": "..."
  },
  "weekly_activity": [
    { "day": "2026-04-12", "sessions": 0, "minutes": 0 },
    { "day": "2026-04-13", "sessions": 2, "minutes": 14 }
  ],
  "top_games": [
    {
      "game_id": 1, "plays": 3, "avg_accuracy": 82.4,
      "game": { "id": 1, "slug": "shape-matcher", "name": "Shape Matcher", "icon_url": "/icons/games/shape-matcher.png" }
    }
  ]
}
```

### 4.2 Prediction History (paginated)

`GET /api/parent/children/{child_id}/predictions`

### 4.3 Session History (paginated)

`GET /api/parent/children/{child_id}/sessions`

---

## 5. Parent Notifications

### 5.1 List

`GET /api/parent/notifications` (paginated)

Each notification:
```json
{
  "id": 1, "user_id": 1, "child_id": 1,
  "type": "visual_disorder_alert",
  "title": "Attention needed for Leo",
  "body": "Recent gameplay suggests possible visual perception difficulties...",
  "data": { "child_id": 1, "prediction_id": 5, "confidence": 0.83 },
  "read_at": null,
  "created_at": "..."
}
```

### 5.2 Unread Count

`GET /api/parent/notifications/unread-count` → `{ "unread": 3 }`

### 5.3 Mark Read

`POST /api/parent/notifications/{id}/read`

### 5.4 Mark All Read

`POST /api/parent/notifications/read-all`

---

## 6. Child Authentication

### 6.1 Login

`POST /api/child/login`

Request:
```json
{ "username": "leo", "password": "kids1234" }
```

Response `200`:
```json
{
  "message": "Login successful.",
  "token": "2|xxxxx",
  "child": { "id": 1, "parent_id": 1, "name": "Leo", "username": "leo", "age": 6, "gender": "boy", ... }
}
```

### 6.2 Me

`GET /api/child/me`

### 6.3 Logout

`POST /api/child/logout`

---

## 7. Games

### 7.1 List Available Games

`GET /api/child/games`
Returns only games matching the child's age (`min_age <= age <= max_age`).

```json
{
  "games": [
    {
      "id": 1, "slug": "shape-matcher", "name": "Shape Matcher",
      "description": "...",
      "icon_url": "/icons/games/shape-matcher.png",
      "task_type": "Matching", "skill": "Visual Matching",
      "min_age": 3, "max_age": 10, "total_levels": 10
    }
  ]
}
```

`task_type` is one of the four ML-relevant categories (see [enums](#enums--reference)).

### 7.2 Show Game

`GET /api/child/games/{id}`

---

## 8. Game Sessions + ML Prediction

The critical flow. A **session** is one play of a game; it contains many **trials** (individual attempts within the game).

### 8.1 Start Session

`POST /api/child/sessions/start`
```json
{ "game_id": 1, "level": 2, "difficulty_level": "Hard" }
```
`difficulty_level` ∈ `Easy | Medium | Hard` (default `Easy`).

Response `201`:
```json
{
  "message": "Session started.",
  "session": {
    "id": 42, "child_id": 1, "game_id": 1, "level": 2,
    "difficulty_level": "Hard", "status": "in_progress",
    "started_at": "2026-04-18T17:18:32Z"
  }
}
```

### 8.2 Submit a Trial

`POST /api/child/sessions/{session_id}/trials`

Each trial is **one attempt inside the game**. Flutter should POST one of these per attempt (do not batch).

```json
{
  "trial_number": 1,
  "task_type": "Matching",
  "difficulty_level": "Hard",
  "target_type": "Shape",
  "stimulus_count": 6,
  "reaction_time_ms": 1750,
  "correct": true,
  "errors": 0,
  "missed_targets": 0,
  "duration_sec": 15
}
```

Field guide (all REQUIRED):
| Field | Type | Description |
|---|---|---|
| `trial_number` | int ≥1 | Sequential within session. |
| `task_type` | enum | `Tracking` `Discrimination` `Matching` `Orientation` — must match the game's `task_type`. |
| `difficulty_level` | enum | `Easy` `Medium` `Hard`. |
| `target_type` | enum | `Direction` `Color` `Shape` `Position`. |
| `stimulus_count` | int 1–50 | How many items shown. |
| `reaction_time_ms` | int ≥0 | Time from stimulus to answer. |
| `correct` | boolean | Final answer correct or not. |
| `errors` | int ≥0 | Wrong clicks during the trial. |
| `missed_targets` | int ≥0 | Targets not clicked by the child. |
| `duration_sec` | int ≥0 | Total time spent on this trial. |

Response `201`: `{ "message": "Trial recorded.", "trial": {...} }`

### 8.3 End Session (auto-runs ML)

`POST /api/child/sessions/{session_id}/end`

No body. Laravel:
1. Aggregates trial metrics (accuracy, avg RT, counts) → session row.
2. Sends all trials to the ML service `/plan`.
3. Stores a `VisualPrediction`.
4. If label is `Visual_Perception_Disorder`, creates a `visual_disorder_alert` notification for the parent.

Response `200`:
```json
{
  "message": "Session completed.",
  "session": {
    "id": 42, "status": "completed",
    "trials_count": 5, "correct_count": 4,
    "errors_count": 1, "missed_count": 0,
    "accuracy": 80.0, "avg_reaction_time_ms": 1680,
    "duration_sec": 75, "ended_at": "..."
  },
  "prediction": {
    "id": 7, "session_id": 42, "child_id": 1,
    "status": "normal",
    "label": "Normal",
    "confidence": 0.9231,
    "prob_normal": 0.9231,
    "prob_disorder": 0.0769,
    "weak_skills": {},
    "training_plan": {},
    "trials_count": 5,
    "model_version": "v1.0.0"
  }
}
```

When `status == "visual_disorder"`:
```json
"prediction": {
  "status": "visual_disorder",
  "label": "Visual_Perception_Disorder",
  "confidence": 0.83,
  "prob_normal": 0.17,
  "prob_disorder": 0.83,
  "weak_skills": { "Visual Matching": 3, "Visual Tracking": 1 },
  "training_plan": {
    "Day 1": ["Shape matcher game", "Maze navigation (easy)"],
    "Day 2": ["Pair matching puzzle", "Follow-the-dot animation"],
    "..."
  }
}
```

### 8.4 Session History

`GET /api/child/sessions/history` (paginated)

### 8.5 Session Detail (with trials)

`GET /api/child/sessions/{id}`

---

## 9. Chatbot

Simple keyword-based replies (no external LLM).

### 9.1 History

`GET /api/child/chatbot/history` → last 100 messages.

### 9.2 Send

`POST /api/child/chatbot/send`
```json
{ "message": "hi" }
```
Response:
```json
{ "message": { "id": 5, "role": "bot", "message": "Hi Leo! Ready to play?", "created_at": "..." } }
```

---

## Enums & Reference

### Task Types (ML)
| Value | Skill | Games (seeded) |
|---|---|---|
| `Tracking` | Visual Tracking | Maze Runner |
| `Discrimination` | Visual Discrimination | Which One Is It? |
| `Matching` | Visual Matching | Shape Matcher, Animal Puzzle |
| `Orientation` | Spatial Orientation | Letter Tracing |

### Difficulty Levels
`Easy | Medium | Hard`

### Target Types
`Direction | Color | Shape | Position`

### Classes (ML output)
- `Normal` → `status: "normal"`
- `Visual_Perception_Disorder` → `status: "visual_disorder"` (triggers parent notification)

### Skill → Exercises (used in training plan)
- Visual Tracking: Maze navigation, Follow-the-dot, Moving shape tracker
- Visual Discrimination: Spot the difference, Odd-one-out, Color-shade matching
- Visual Matching: Shape matcher, Pair matching puzzle, Pattern completion
- Spatial Orientation: Rotate the shape, Mirror image matching, Direction arrows

---

## Demo Credentials

Seeded by `php artisan db:seed`:

| Role | Credentials |
|---|---|
| Parent | `parent@kidzoo.test` / `password123` (already verified) |
| Child | `leo` / `kids1234` |

---

## Running Locally

**1. Start the ML service** (port 8001):
```bash
cd ml_service
./venv/Scripts/python.exe -m uvicorn api:app --host 127.0.0.1 --port 8001
```

**2. Start Laravel** (port 8000):
```bash
cd backend
php artisan serve --host=127.0.0.1 --port=8000
```

**3. Seed fresh data** (optional):
```bash
cd backend
php artisan migrate:fresh --seed --force
```

**4. Health checks:**
- `GET http://127.0.0.1:8000/api/health` → Laravel
- `GET http://127.0.0.1:8001/health` → ML service + model accuracy

**Environment:**
- `ML_SERVICE_URL` in `.env` points Laravel to the ML service (default `http://127.0.0.1:8001`).
- OTP emails go to `storage/logs/laravel.log` by default. Set `MAIL_MAILER=smtp` and SMTP vars for production.

---

## Notes for Flutter Developer

1. **Persist the token** securely (flutter_secure_storage). Clear it on 401/logout.
2. **Network:** use `dio` or `http` with a base interceptor that attaches the `Authorization` header and `Accept: application/json`.
3. **Emulator/device:** if the device cannot reach `127.0.0.1`, use your machine's LAN IP (e.g. `http://192.168.1.5:8000/api`) and run `php artisan serve --host=0.0.0.0`.
4. **Trial submission cadence:** one POST per trial keeps the UX snappy and avoids losing data if the app crashes mid-game.
5. **Session end UX:** show a "Analyzing your progress..." loader; ML round-trip is ~300-800 ms locally.
6. **Error handling:** if `/sessions/{id}/end` returns without a `prediction` field (null), the ML service was unreachable — show a neutral completion screen and retry later via a background job (future work).
7. **Pagination:** all list endpoints use standard Laravel pagination (`data`, `current_page`, `last_page`, `next_page_url`).

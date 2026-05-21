# KidZoo Dart API Samples

Use these JSON samples with Flutter, Postman, or any "Generate Dart Data Class" tool.

Development note:

- OTP emails in the current local setup are delivered to Mailtrap Sandbox, not to a real Gmail inbox.
- After calling `register`, `resend-verification`, or `forgot-password`, open Mailtrap and copy the OTP from the sandbox message.

Recommended headers for JSON endpoints:

```http
Accept: application/json
Content-Type: application/json
```

For protected endpoints also send:

```http
Authorization: Bearer <token>
```

## 1. Parent Register

- Method: `POST`
- URL: `/api/parent/register`
- Suggested Dart class: `ParentRegisterResponse`

Request:

```json
{
  "name": "Ahmed Ali",
  "email": "ahmed@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "phone": "+201000000000"
}
```

Response `201`:

```json
{
  "message": "Registration successful. Please verify your email with the OTP sent.",
  "email": "ahmed@example.com"
}
```

## 2. Parent Verify Email

- Method: `POST`
- URL: `/api/parent/verify-email`
- Suggested Dart class: `ParentVerifyEmailResponse`

Flow note:

- Use the OTP code captured from Mailtrap Sandbox after registration or resend verification.

Request:

```json
{
  "email": "ahmed@example.com",
  "otp": "123456"
}
```

Response `200`:

```json
{
  "message": "Email verified successfully.",
  "token": "1|parent_token_here",
  "user": {
    "id": 1,
    "name": "Ahmed Ali",
    "email": "ahmed@example.com",
    "email_verified_at": "2026-04-22T18:40:00.000000Z",
    "phone": "+201000000000",
    "avatar_url": null,
    "language": "en",
    "created_at": "2026-04-22T18:35:00.000000Z",
    "updated_at": "2026-04-22T18:40:00.000000Z"
  }
}
```

## 3. Parent Login

- Method: `POST`
- URL: `/api/parent/login`
- Suggested Dart class: `ParentLoginResponse`

Request:

```json
{
  "email": "ahmed@example.com",
  "password": "password123"
}
```

Response `200`:

```json
{
  "message": "Login successful.",
  "token": "2|parent_login_token_here",
  "user": {
    "id": 1,
    "name": "Ahmed Ali",
    "email": "ahmed@example.com",
    "email_verified_at": "2026-04-22T18:40:00.000000Z",
    "phone": "+201000000000",
    "avatar_url": null,
    "language": "en",
    "created_at": "2026-04-22T18:35:00.000000Z",
    "updated_at": "2026-04-22T18:40:00.000000Z"
  }
}
```

Unverified response `403`:

```json
{
  "message": "Email not verified. A new OTP has been sent.",
  "requires_verification": true,
  "email": "ahmed@example.com"
}
```

## 4. Parent Forgot Password

- Method: `POST`
- URL: `/api/parent/forgot-password`
- Suggested Dart class: `ParentForgotPasswordResponse`

Request:

```json
{
  "email": "ahmed@example.com"
}
```

Response `200`:

```json
{
  "message": "If this email is registered, an OTP has been sent."
}
```

## 5. Parent Verify Reset OTP

- Method: `POST`
- URL: `/api/parent/verify-reset-otp`
- Suggested Dart class: `ParentVerifyResetOtpResponse`

Flow note:

- Use the OTP code captured from Mailtrap Sandbox after calling `forgot-password`.

Request:

```json
{
  "email": "ahmed@example.com",
  "otp": "654321"
}
```

Response `200`:

```json
{
  "message": "OTP verified. Use the reset_token to set a new password.",
  "reset_token": "3|password_reset_token_here"
}
```

## 6. Parent Reset Password

- Method: `POST`
- URL: `/api/parent/reset-password`
- Suggested Dart class: `ParentResetPasswordResponse`

Request:

```json
{
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

Response `200`:

```json
{
  "message": "Password reset successfully. Please login again."
}
```

## 7. Parent Profile

- Method: `GET`
- URL: `/api/parent/profile`
- Suggested Dart class: `ParentProfileResponse`

Response `200`:

```json
{
  "user": {
    "id": 1,
    "name": "Ahmed Ali",
    "email": "ahmed@example.com",
    "email_verified_at": "2026-04-22T18:40:00.000000Z",
    "phone": "+201000000000",
    "avatar_url": null,
    "language": "en",
    "created_at": "2026-04-22T18:35:00.000000Z",
    "updated_at": "2026-04-22T18:40:00.000000Z"
  }
}
```

## 8. Parent Update Profile

- Method: `PUT`
- URL: `/api/parent/profile`
- Suggested Dart class: `ParentUpdateProfileResponse`

Request:

```json
{
  "name": "Ahmed Hassan",
  "phone": "+201111111111",
  "language": "ar",
  "avatar_url": "https://example.com/avatar.png"
}
```

Response `200`:

```json
{
  "message": "Profile updated.",
  "user": {
    "id": 1,
    "name": "Ahmed Hassan",
    "email": "ahmed@example.com",
    "email_verified_at": "2026-04-22T18:40:00.000000Z",
    "phone": "+201111111111",
    "avatar_url": "https://example.com/avatar.png",
    "language": "ar",
    "created_at": "2026-04-22T18:35:00.000000Z",
    "updated_at": "2026-04-22T19:00:00.000000Z"
  }
}
```

## 9. Parent Change Password

- Method: `POST`
- URL: `/api/parent/profile/change-password`
- Suggested Dart class: `ParentChangePasswordResponse`

Request:

```json
{
  "current_password": "password123",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

Response `200`:

```json
{
  "message": "Password changed successfully."
}
```

## 10. Create Child

- Method: `POST`
- URL: `/api/parent/children`
- Suggested Dart class: `CreateChildResponse`

Request:

```json
{
  "name": "Omar",
  "username": "omar_kid",
  "password": "1234",
  "password_confirmation": "1234",
  "age": 7,
  "gender": "boy",
  "avatar_url": "https://example.com/kids/omar.png"
}
```

Response `201`:

```json
{
  "message": "Child created.",
  "child": {
    "id": 1,
    "parent_id": 1,
    "name": "Omar",
    "username": "omar_kid",
    "age": 7,
    "gender": "boy",
    "avatar_url": "https://example.com/kids/omar.png",
    "is_active": true,
    "last_login_at": null,
    "created_at": "2026-04-22T19:05:00.000000Z",
    "updated_at": "2026-04-22T19:05:00.000000Z"
  }
}
```

## 11. Children List

- Method: `GET`
- URL: `/api/parent/children`
- Suggested Dart class: `ChildrenListResponse`

Response `200`:

```json
{
  "children": [
    {
      "id": 1,
      "parent_id": 1,
      "name": "Omar",
      "username": "omar_kid",
      "age": 7,
      "gender": "boy",
      "avatar_url": "https://example.com/kids/omar.png",
      "is_active": true,
      "last_login_at": "2026-04-22T19:10:00.000000Z",
      "created_at": "2026-04-22T19:05:00.000000Z",
      "updated_at": "2026-04-22T19:10:00.000000Z"
    }
  ]
}
```

## 12. Child Login

- Method: `POST`
- URL: `/api/child/login`
- Suggested Dart class: `ChildLoginResponse`

Request:

```json
{
  "username": "omar_kid",
  "password": "1234"
}
```

Response `200`:

```json
{
  "message": "Login successful.",
  "token": "4|child_token_here",
  "child": {
    "id": 1,
    "parent_id": 1,
    "name": "Omar",
    "username": "omar_kid",
    "age": 7,
    "gender": "boy",
    "avatar_url": "https://example.com/kids/omar.png",
    "is_active": true,
    "last_login_at": "2026-04-22T19:10:00.000000Z",
    "created_at": "2026-04-22T19:05:00.000000Z",
    "updated_at": "2026-04-22T19:10:00.000000Z"
  }
}
```

## 13. Games List

- Method: `GET`
- URL: `/api/child/games`
- Suggested Dart class: `GamesListResponse`

Response `200`:

```json
{
  "games": [
    {
      "id": 1,
      "slug": "shape_matching",
      "name": "shape_matching",
      "description": "Match similar shapes as fast as you can.",
      "icon_url": "https://example.com/games/shape.png",
      "min_age": 5,
      "max_age": 9,
      "is_active": true,
      "created_at": "2026-04-18T18:00:00.000000Z",
      "updated_at": "2026-04-18T18:00:00.000000Z"
    }
  ]
}
```

## 14. Start Session

- Method: `POST`
- URL: `/api/child/sessions/start`
- Suggested Dart class: `StartSessionResponse`

Request:

```json
{
  "game_id": 1,
  "level": 1,
  "difficulty_level": "Easy"
}
```

Response `201`:

```json
{
  "message": "Session started.",
  "session": {
    "id": 1,
    "child_id": 1,
    "game_id": 1,
    "level": 1,
    "difficulty_level": "Easy",
    "started_at": "2026-04-22T19:15:00.000000Z",
    "ended_at": null,
    "duration_sec": null,
    "trials_count": 0,
    "correct_count": 0,
    "errors_count": 0,
    "missed_count": 0,
    "accuracy": null,
    "avg_reaction_time_ms": null,
    "status": "in_progress",
    "created_at": "2026-04-22T19:15:00.000000Z",
    "updated_at": "2026-04-22T19:15:00.000000Z"
  }
}
```

## 15. Submit Trial

- Method: `POST`
- URL: `/api/child/sessions/{session}/trials`
- Suggested Dart class: `SubmitTrialResponse`

Request:

```json
{
  "trial_number": 1,
  "task_type": "Matching",
  "difficulty_level": "Easy",
  "target_type": "Shape",
  "stimulus_count": 6,
  "reaction_time_ms": 1240,
  "correct": true,
  "errors": 0,
  "missed_targets": 0,
  "duration_sec": 8
}
```

Response `201`:

```json
{
  "message": "Trial recorded.",
  "trial": {
    "id": 1,
    "session_id": 1,
    "trial_number": 1,
    "task_type": "Matching",
    "difficulty_level": "Easy",
    "target_type": "Shape",
    "stimulus_count": 6,
    "reaction_time_ms": 1240,
    "correct": true,
    "errors": 0,
    "missed_targets": 0,
    "duration_sec": 8,
    "created_at": "2026-04-22T19:16:00.000000Z",
    "updated_at": "2026-04-22T19:16:00.000000Z"
  }
}
```

## 16. End Session

- Method: `POST`
- URL: `/api/child/sessions/{session}/end`
- Suggested Dart class: `EndSessionResponse`

Request:

```json
{}
```

Response `200`:

```json
{
  "message": "Session completed.",
  "session": {
    "id": 1,
    "child_id": 1,
    "game_id": 1,
    "level": 1,
    "difficulty_level": "Easy",
    "started_at": "2026-04-22T19:15:00.000000Z",
    "ended_at": "2026-04-22T19:18:00.000000Z",
    "duration_sec": 24,
    "trials_count": 3,
    "correct_count": 2,
    "errors_count": 1,
    "missed_count": 0,
    "accuracy": 66.67,
    "avg_reaction_time_ms": 1325.33,
    "status": "completed",
    "created_at": "2026-04-22T19:15:00.000000Z",
    "updated_at": "2026-04-22T19:18:00.000000Z"
  },
  "prediction": {
    "id": 1,
    "child_id": 1,
    "session_id": 1,
    "status": "normal",
    "label": "Normal",
    "confidence": 0.91,
    "prob_normal": 0.91,
    "prob_disorder": 0.09,
    "weak_skills": [
      "tracking"
    ],
    "training_plan": [
      "Play 10 minutes of shape matching daily",
      "Practice visual tracking exercises"
    ],
    "trials_count": 3,
    "model_version": "v1.0.0",
    "created_at": "2026-04-22T19:18:01.000000Z",
    "updated_at": "2026-04-22T19:18:01.000000Z"
  }
}
```

## 17. Session History

- Method: `GET`
- URL: `/api/child/sessions/history`
- Suggested Dart class: `SessionHistoryResponse`

Response `200`:

```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1,
      "child_id": 1,
      "game_id": 1,
      "level": 1,
      "difficulty_level": "Easy",
      "started_at": "2026-04-22T19:15:00.000000Z",
      "ended_at": "2026-04-22T19:18:00.000000Z",
      "duration_sec": 24,
      "trials_count": 3,
      "correct_count": 2,
      "errors_count": 1,
      "missed_count": 0,
      "accuracy": 66.67,
      "avg_reaction_time_ms": 1325.33,
      "status": "completed",
      "created_at": "2026-04-22T19:15:00.000000Z",
      "updated_at": "2026-04-22T19:18:00.000000Z",
      "game": {
        "id": 1,
        "slug": "shape_matching",
        "name": "shape_matching",
        "icon_url": "https://example.com/games/shape.png"
      }
    }
  ],
  "first_page_url": "http://127.0.0.1:8000/api/child/sessions/history?page=1",
  "from": 1,
  "last_page": 1,
  "last_page_url": "http://127.0.0.1:8000/api/child/sessions/history?page=1",
  "links": [],
  "next_page_url": null,
  "path": "http://127.0.0.1:8000/api/child/sessions/history",
  "per_page": 20,
  "prev_page_url": null,
  "to": 1,
  "total": 1
}
```

## 18. Chatbot Send

- Method: `POST`
- URL: `/api/child/chatbot/send`
- Suggested Dart class: `ChatbotSendResponse`

Request:

```json
{
  "message": "hello"
}
```

Response `200`:

```json
{
  "message": {
    "id": 2,
    "child_id": 1,
    "role": "bot",
    "message": "Hello Omar! Let's have fun learning.",
    "created_at": "2026-04-22T19:20:00.000000Z",
    "updated_at": "2026-04-22T19:20:00.000000Z"
  }
}
```

## 19. Chatbot History

- Method: `GET`
- URL: `/api/child/chatbot/history`
- Suggested Dart class: `ChatbotHistoryResponse`

Response `200`:

```json
{
  "messages": [
    {
      "id": 1,
      "child_id": 1,
      "role": "user",
      "message": "hello",
      "created_at": "2026-04-22T19:19:58.000000Z",
      "updated_at": "2026-04-22T19:19:58.000000Z"
    },
    {
      "id": 2,
      "child_id": 1,
      "role": "bot",
      "message": "Hello Omar! Let's have fun learning.",
      "created_at": "2026-04-22T19:20:00.000000Z",
      "updated_at": "2026-04-22T19:20:00.000000Z"
    }
  ]
}
```

## 20. Health Check

- Method: `GET`
- URL: `/api/health`
- Suggested Dart class: `HealthCheckResponse`

Response `200`:

```json
{
  "status": "ok",
  "service": "KidZoo API",
  "time": "2026-04-22T19:30:00+00:00"
}
```

## Validation Error Shape

- Suggested Dart class: `ApiValidationErrorResponse`

```json
{
  "message": "The email field is required.",
  "errors": {
    "email": [
      "The email field is required."
    ]
  }
}
```

## Notes

- For code generation, paste only the JSON body, not HTML or Markdown.
- Prefer class names in `PascalCase`, like `ParentLoginResponse`.
- Prefer file names in `snake_case`, like `parent_login_response.dart`.

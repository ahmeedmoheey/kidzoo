# n8n Setup

This project can use `n8n` for both:

- child chatbot replies
- AI prediction after `End Session`

## Files

- AI workflow template: [n8n/ai-prediction-workflow.json](/C:/Users/ahmed/OneDrive/Desktop/htdocs/kidzoo%20app/backend/n8n/ai-prediction-workflow.json)
- Chat workflow template: [n8n/ai-chat-workflow.json](/C:/Users/ahmed/OneDrive/Desktop/htdocs/kidzoo%20app/backend/n8n/ai-chat-workflow.json)

## Laravel env

Set these values in [`.env`](/C:/Users/ahmed/OneDrive/Desktop/htdocs/kidzoo%20app/backend/.env):

```env
N8N_AI_WEBHOOK_URL=http://127.0.0.1:5678/webhook/ai-prediction
N8N_AI_TEST_WEBHOOK_URL=http://127.0.0.1:5678/webhook-test/ai-prediction
N8N_AI_TIMEOUT=15
N8N_AI_TOKEN=

N8N_CHAT_WEBHOOK_URL=http://127.0.0.1:5678/webhook/ai-chat
N8N_CHAT_TEST_WEBHOOK_URL=http://127.0.0.1:5678/webhook-test/ai-chat
N8N_CHAT_TIMEOUT=15
N8N_CHAT_TOKEN=
```

Then run:

```powershell
php artisan config:clear
```

## AI prediction workflow

Laravel now tries `n8n` first when this endpoint is called:

```http
POST /api/child/sessions/{session_id}/end
```

Laravel sends JSON like this to `n8n`:

```json
{
  "trials": [
    {
      "User_ID": 1,
      "Trial_ID": 1,
      "Task_Type": "Matching",
      "Stimulus_Count": 6,
      "Difficulty_Level": "Hard",
      "Target_Type": "Shape",
      "Reaction_Time_ms": 1750,
      "Correct": 1,
      "Errors": 0,
      "Missed_Targets": 0,
      "Session_Duration_sec": 15
    }
  ],
  "score_threshold": 60,
  "days": 7
}
```

`n8n` must return JSON like this:

```json
{
  "status": "normal",
  "label": "Normal Child",
  "confidence": 0.91,
  "prob_normal": 0.91,
  "prob_disorder": 0.09,
  "weak_skills": [],
  "training_plan": [],
  "trials_count": 5,
  "model_version": "n8n-v1"
}
```

Allowed `status` values from `n8n`:

- `normal`
- `disorder`

Laravel stores the prediction, then the dashboard returns:

- `latest_prediction.status`
- `latest_prediction.label`
- `latest_prediction.confidence`

## Import the AI workflow

1. Open `n8n`.
2. Choose `Import from File`.
3. Import [n8n/ai-prediction-workflow.json](/C:/Users/ahmed/OneDrive/Desktop/htdocs/kidzoo%20app/backend/n8n/ai-prediction-workflow.json).
4. Either:
   - activate the workflow, or
   - click `Listen for test event`.
5. Put the webhook URL in Laravel `.env`.

## What the provided workflow does

The imported workflow is a ready-to-run template:

- `Webhook` node receives session trials
- `Code` node calculates a simple prediction
- `Respond to Webhook` returns JSON to Laravel

This means it works immediately even without OpenAI.

## If you want real AI inside n8n

Replace the `Build Prediction` code node with:

1. an `HTTP Request` node to OpenAI, or
2. an `OpenAI` node inside n8n

and keep the final response shape exactly the same.

## Chatbot workflow requirements

The chatbot webhook should still expose:

- path: `ai-chat`
- method: `POST`

Laravel sends:

```json
{
  "message": "hello",
  "child_name": "Omar"
}
```

`n8n` should return:

```json
{
  "reply": "Hello Omar!"
}
```

Laravel already returns chatbot responses to Flutter in both shapes:

```json
{
  "message": {
    "message": "Hello Omar!"
  },
  "reply": "Hello Omar!"
}
```

## Import the chat workflow

1. Open `n8n`.
2. Choose `Import from File`.
3. Import [n8n/ai-chat-workflow.json](/C:/Users/ahmed/OneDrive/Desktop/htdocs/kidzoo%20app/backend/n8n/ai-chat-workflow.json).
4. Activate the workflow, or use the test webhook.

The provided chat workflow is ready-to-run and does not require an API key.

## Test checklist

1. Start `n8n`.
2. Import the AI workflow.
3. Set `N8N_AI_WEBHOOK_URL` in `.env`.
4. Run:

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

5. Start a child session.
6. Submit trials.
7. Call `POST /api/child/sessions/{session_id}/end`.
8. Open:

```http
GET /api/parent/children/{child_id}/dashboard
```

You should see `latest_prediction` in the response.

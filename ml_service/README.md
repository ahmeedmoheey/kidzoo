# KidZoo ML Service

FastAPI microservice that serves the **Visual Perception Disorder** classifier used by the Laravel backend.

## Stack
- Python 3.11
- scikit-learn LogisticRegression (`C=0.25`, `penalty="l2"`, `max_iter=1000`)
- FastAPI + Uvicorn

## Structure
```
ml_service/
├── data/visual_perception_dataset.csv   # training data
├── artifacts/                           # generated after train.py
│   ├── best_f1_model.pkl
│   ├── scaler.pkl
│   └── metadata.json
├── train.py                             # trains and persists model
├── api.py                               # FastAPI inference app
├── requirements.txt
└── venv/                                # local virtualenv
```

## Setup (one-time)
```bash
python -m venv venv
./venv/Scripts/python.exe -m pip install -r requirements.txt
```

## Train
```bash
./venv/Scripts/python.exe train.py
```
Produces `artifacts/best_f1_model.pkl`, `artifacts/scaler.pkl`, `artifacts/metadata.json`.

Current scores on the provided dataset:
- accuracy ≈ 0.91
- F1 (Visual_Perception_Disorder) ≈ 0.83

## Run the API
```bash
./venv/Scripts/python.exe -m uvicorn api:app --host 0.0.0.0 --port 8001 --reload
```
Swagger UI: http://localhost:8001/docs

## Endpoints

### `GET /health`
Service status + model metadata.

### `GET /meta`
Enum values (`task_types`, `difficulty_levels`, `target_types`, etc.) that requests must use.

### `POST /predict`
Input: list of trial records. Returns aggregated label + confidence across trials.
```json
{
  "trials": [
    {
      "User_ID": 1, "Trial_ID": 1,
      "Task_Type": "Tracking", "Stimulus_Count": 5,
      "Difficulty_Level": "Hard", "Target_Type": "Direction",
      "Reaction_Time_ms": 1995, "Correct": 1,
      "Errors": 0, "Missed_Targets": 0,
      "Session_Duration_sec": 194
    }
  ]
}
```

### `POST /plan`
Same input as `/predict` plus optional `score_threshold` (default 60) and `days` (default 7).
Returns prediction + weak-skills map + a day-by-day training plan when a disorder is predicted.

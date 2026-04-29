"""
FastAPI inference service for KidZoo Visual Perception model.

Endpoints:
    GET  /health              -> service status + model metadata
    GET  /meta                -> enum values Flutter/Laravel can send
    POST /predict             -> single-trial OR aggregated prediction
    POST /plan                -> weak-skills detection + 7-day training plan
"""
from __future__ import annotations

import json
from pathlib import Path
from typing import Any

import joblib
import numpy as np
import pandas as pd
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field

BASE_DIR = Path(__file__).resolve().parent
ARTIFACTS_DIR = BASE_DIR / "artifacts"

MODEL = joblib.load(ARTIFACTS_DIR / "best_f1_model.pkl")
SCALER = joblib.load(ARTIFACTS_DIR / "scaler.pkl")
with open(ARTIFACTS_DIR / "metadata.json", "r", encoding="utf-8") as f:
    META: dict[str, Any] = json.load(f)

SELECTED_FEATURES: list[str] = META["selected_features"]
ALL_FEATURES: list[str] = META["all_features_after_encoding"]
CATEGORICAL_COLUMNS: list[str] = META["categorical_columns"]
NUMERIC_COLUMNS: list[str] = META["raw_numeric_columns"]
TASK_TO_SKILL: dict[str, str] = META["task_to_skill"]
TARGET_TYPE_TO_SKILL: dict[str, str] = META["target_type_to_skill"]
CLASSES: list[str] = META["classes"]

app = FastAPI(
    title="KidZoo Visual Perception ML Service",
    version="1.0.0",
    description="Classifies visual perception disorder from game trial metrics.",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)


class Trial(BaseModel):
    User_ID: int = Field(..., description="Child ID in the backend DB")
    Trial_ID: int = Field(..., description="Sequential trial number within the session")
    Task_Type: str = Field(..., description="One of: Tracking, Discrimination, Matching, Orientation")
    Stimulus_Count: int = Field(..., ge=1)
    Difficulty_Level: str = Field(..., description="Easy | Medium | Hard")
    Target_Type: str = Field(..., description="Direction | Color | Shape | Position")
    Reaction_Time_ms: int = Field(..., ge=0)
    Correct: int = Field(..., ge=0, le=1)
    Errors: int = Field(..., ge=0)
    Missed_Targets: int = Field(..., ge=0)
    Session_Duration_sec: int = Field(..., ge=0)


class PredictRequest(BaseModel):
    trials: list[Trial] = Field(..., min_length=1)


class PredictResponse(BaseModel):
    status: str
    label: str
    confidence: float
    probabilities: dict[str, float]
    trials_count: int


class PlanRequest(BaseModel):
    trials: list[Trial] = Field(..., min_length=1)
    score_threshold: float = Field(
        default=60.0,
        ge=0,
        le=100,
        description="Trial success % below which its skill is considered weak",
    )
    days: int = Field(default=7, ge=1, le=30)


class PlanResponse(BaseModel):
    status: str
    label: str
    confidence: float
    probabilities: dict[str, float]
    trials_count: int
    weak_skills: dict[str, int]
    training_plan: dict[str, list[str]]
    message: str


def trials_to_dataframe(trials: list[Trial]) -> pd.DataFrame:
    return pd.DataFrame([t.model_dump() for t in trials])


def encode(df_raw: pd.DataFrame) -> np.ndarray:
    for col in CATEGORICAL_COLUMNS:
        allowed = META["raw_categorical_values"][col]
        bad = set(df_raw[col].unique()) - set(allowed)
        if bad:
            raise HTTPException(
                status_code=422,
                detail=f"Invalid values {bad} in column {col}. Allowed: {allowed}",
            )

    encoded = pd.get_dummies(df_raw, columns=CATEGORICAL_COLUMNS, drop_first=True)

    for col in ALL_FEATURES:
        if col not in encoded.columns:
            encoded[col] = 0

    encoded = encoded[SELECTED_FEATURES]
    return SCALER.transform(encoded)


def aggregate_predictions(X_scaled: np.ndarray) -> dict[str, Any]:
    per_trial_probs = MODEL.predict_proba(X_scaled)
    class_index = {c: i for i, c in enumerate(MODEL.classes_)}

    mean_probs = per_trial_probs.mean(axis=0)
    label = MODEL.classes_[int(np.argmax(mean_probs))]
    confidence = float(np.max(mean_probs))

    probabilities = {c: float(mean_probs[class_index[c]]) for c in MODEL.classes_}

    status = "visual_disorder" if label == "Visual_Perception_Disorder" else "normal"

    return {
        "status": status,
        "label": label,
        "confidence": confidence,
        "probabilities": probabilities,
    }


def detect_skill(row: pd.Series) -> str:
    return TASK_TO_SKILL.get(row["Task_Type"], "General Visual Skill")


def compute_trial_score(row: pd.Series) -> float:
    if row["Correct"] == 1 and row["Errors"] == 0 and row["Missed_Targets"] == 0:
        return 100.0
    penalty = 20 * int(row["Errors"]) + 25 * int(row["Missed_Targets"])
    base = 100 if row["Correct"] == 1 else 40
    return max(0.0, float(base - penalty))


def get_weak_skills(df: pd.DataFrame, threshold: float) -> dict[str, int]:
    df = df.copy()
    df["skill_type"] = df.apply(detect_skill, axis=1)
    df["score"] = df.apply(compute_trial_score, axis=1)
    weak = df[df["score"] < threshold]
    return weak["skill_type"].value_counts().to_dict()


SKILL_TO_EXERCISES: dict[str, list[str]] = {
    "Visual Tracking": [
        "Maze navigation (easy)",
        "Follow-the-dot animation",
        "Moving shape tracker",
    ],
    "Visual Discrimination": [
        "Spot the difference",
        "Odd-one-out shapes",
        "Color-shade matching",
    ],
    "Visual Matching": [
        "Shape matcher game",
        "Pair matching puzzle",
        "Pattern completion",
    ],
    "Spatial Orientation": [
        "Rotate the shape",
        "Mirror image matching",
        "Direction arrows game",
    ],
    "General Visual Skill": [
        "Memory cards",
        "Picture puzzle (easy)",
    ],
}


def build_training_plan(weak_skills: dict[str, int], days: int) -> dict[str, list[str]]:
    plan: dict[str, list[str]] = {}
    if not weak_skills:
        return plan

    skills_sorted = sorted(weak_skills.keys(), key=lambda s: -weak_skills[s])
    for day in range(1, days + 1):
        daily: list[str] = []
        for skill in skills_sorted:
            exercises = SKILL_TO_EXERCISES.get(skill, SKILL_TO_EXERCISES["General Visual Skill"])
            daily.append(exercises[(day - 1) % len(exercises)])
        plan[f"Day {day}"] = daily
    return plan


@app.get("/health")
def health():
    return {
        "status": "ok",
        "model": META["model_type"],
        "accuracy": META["accuracy"],
        "f1_disorder": META["f1_disorder"],
        "classes": CLASSES,
    }


@app.get("/meta")
def meta():
    return {
        "task_types": META["task_types"],
        "difficulty_levels": META["difficulty_levels"],
        "target_types": META["target_types"],
        "task_to_skill": TASK_TO_SKILL,
        "target_type_to_skill": TARGET_TYPE_TO_SKILL,
        "classes": CLASSES,
        "numeric_columns": NUMERIC_COLUMNS,
        "categorical_columns": CATEGORICAL_COLUMNS,
    }


@app.post("/predict", response_model=PredictResponse)
def predict(req: PredictRequest):
    df = trials_to_dataframe(req.trials)
    X_scaled = encode(df)
    result = aggregate_predictions(X_scaled)
    return {**result, "trials_count": len(req.trials)}


@app.post("/plan", response_model=PlanResponse)
def plan(req: PlanRequest):
    df = trials_to_dataframe(req.trials)
    X_scaled = encode(df)
    result = aggregate_predictions(X_scaled)

    if result["label"] == "Visual_Perception_Disorder":
        weak = get_weak_skills(df, req.score_threshold)
        training = build_training_plan(weak, req.days)
        message = (
            "Visual perception disorder indicators detected. "
            "Please consult a specialist. A short training plan has been generated."
        )
    else:
        weak = {}
        training = {}
        message = "Child visual perception appears normal."

    return {
        **result,
        "trials_count": len(req.trials),
        "weak_skills": weak,
        "training_plan": training,
        "message": message,
    }

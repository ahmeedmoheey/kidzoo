"""
Real-data validation for the deployed FastAPI ML service.

What it does:
1. Loads the original CSV dataset.
2. Groups trials by User_ID (each user = a 'child' with multiple trials).
3. Starts the FastAPI service if it's not running and POSTs each child's
   trials to /predict.
4. Compares the predicted label against the ground-truth class in the CSV.
5. Prints a per-child table + overall accuracy, F1, confusion matrix.
"""
from __future__ import annotations

import json
import sys
import time
from pathlib import Path

import pandas as pd
import requests

BASE_DIR = Path(__file__).resolve().parent
DATA_PATH = BASE_DIR / "data" / "visual_perception_dataset.csv"
API_URL = "http://127.0.0.1:8001"

POSITIVE = "Visual_Perception_Disorder"
NEGATIVE = "Normal"


def wait_for_api(timeout: int = 15) -> bool:
    for _ in range(timeout):
        try:
            r = requests.get(f"{API_URL}/health", timeout=2)
            if r.status_code == 200:
                return True
        except requests.RequestException:
            pass
        time.sleep(1)
    return False


def build_trial_payload(row: pd.Series) -> dict:
    return {
        "User_ID": int(row["User_ID"]),
        "Trial_ID": int(row["Trial_ID"]),
        "Task_Type": str(row["Task_Type"]),
        "Stimulus_Count": int(row["Stimulus_Count"]),
        "Difficulty_Level": str(row["Difficulty_Level"]),
        "Target_Type": str(row["Target_Type"]),
        "Reaction_Time_ms": int(row["Reaction_Time_ms"]),
        "Correct": int(row["Correct"]),
        "Errors": int(row["Errors"]),
        "Missed_Targets": int(row["Missed_Targets"]),
        "Session_Duration_sec": int(row["Session_Duration_sec"]),
    }


def format_table(rows: list[list], header: list) -> str:
    all_rows = [header] + [[str(c) for c in r] for r in rows]
    widths = [max(len(str(row[i])) for row in all_rows) for i in range(len(header))]
    sep = "-" * (sum(widths) + 2 * (len(widths) - 1))
    out = ["  ".join(str(h).ljust(widths[i]) for i, h in enumerate(header)), sep]
    for r in rows:
        out.append("  ".join(str(r[i]).ljust(widths[i]) for i in range(len(r))))
    return "\n".join(out)


def main():
    print("=" * 78)
    print("  Real-data validation via deployed FastAPI ML service")
    print("=" * 78)

    print("\n[1/3] Waiting for FastAPI at", API_URL, "...")
    if not wait_for_api():
        print("  ERROR: FastAPI not reachable. Start it with:")
        print("    ./venv/Scripts/python.exe -m uvicorn api:app --host 127.0.0.1 --port 8001")
        sys.exit(1)

    health = requests.get(f"{API_URL}/health").json()
    print(f"  service OK. model={health['model']}  baseline_accuracy={health['accuracy']:.4f}")

    print("\n[2/3] Loading dataset...")
    df = pd.read_csv(DATA_PATH)
    print(f"  rows={len(df)}  users={df['User_ID'].nunique()}")

    results = []
    for uid, group in df.groupby("User_ID"):
        true_label = group["Class"].iloc[0]
        trials = [build_trial_payload(r) for _, r in group.iterrows()]

        try:
            resp = requests.post(f"{API_URL}/predict", json={"trials": trials}, timeout=10)
            resp.raise_for_status()
            data = resp.json()
        except requests.RequestException as e:
            print(f"  user {uid}: request failed: {e}")
            continue

        correct = data["label"] == true_label
        results.append({
            "user_id": uid,
            "trials": len(trials),
            "true_label": true_label,
            "pred_label": data["label"],
            "confidence": data["confidence"],
            "prob_normal": data["probabilities"]["Normal"],
            "prob_disorder": data["probabilities"]["Visual_Perception_Disorder"],
            "correct": correct,
        })

    print(f"\n[3/3] Per-child predictions ({len(results)} users)")
    table_rows = [
        [
            r["user_id"], r["trials"],
            r["true_label"][:18], r["pred_label"][:18],
            f"{r['confidence']:.3f}",
            f"{r['prob_disorder']:.3f}",
            "YES" if r["correct"] else "NO",
        ]
        for r in results
    ]
    print(format_table(
        table_rows,
        ["User", "Trials", "True", "Predicted", "Conf.", "P(Disorder)", "Match?"],
    ))

    total = len(results)
    correct = sum(1 for r in results if r["correct"])
    tp = sum(1 for r in results if r["true_label"] == POSITIVE and r["pred_label"] == POSITIVE)
    tn = sum(1 for r in results if r["true_label"] == NEGATIVE and r["pred_label"] == NEGATIVE)
    fp = sum(1 for r in results if r["true_label"] == NEGATIVE and r["pred_label"] == POSITIVE)
    fn = sum(1 for r in results if r["true_label"] == POSITIVE and r["pred_label"] == NEGATIVE)

    accuracy = correct / total if total else 0.0
    precision = tp / (tp + fp) if (tp + fp) else 0.0
    recall = tp / (tp + fn) if (tp + fn) else 0.0
    f1 = (2 * precision * recall / (precision + recall)) if (precision + recall) else 0.0

    print("\n" + "=" * 78)
    print("  Aggregate (child-level) metrics")
    print("=" * 78)
    print(format_table(
        [
            ["Total children", total],
            ["Correctly predicted", correct],
            ["Accuracy", f"{accuracy:.4f}"],
            ["Precision (disorder)", f"{precision:.4f}"],
            ["Recall (disorder)", f"{recall:.4f}"],
            ["F1 (disorder)", f"{f1:.4f}"],
            ["TP", tp],
            ["TN", tn],
            ["FP (false alarms)", fp],
            ["FN (missed disorders)", fn],
        ],
        ["Metric", "Value"],
    ))

    report_path = BASE_DIR / "artifacts" / "real_data_validation.json"
    with open(report_path, "w", encoding="utf-8") as f:
        json.dump({
            "summary": {
                "total": total, "correct": correct, "accuracy": accuracy,
                "precision": precision, "recall": recall, "f1": f1,
                "tp": tp, "tn": tn, "fp": fp, "fn": fn,
            },
            "per_child": results,
        }, f, indent=2, ensure_ascii=False)
    print(f"\nDetailed report saved to: {report_path}")


if __name__ == "__main__":
    main()

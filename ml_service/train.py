"""
Training + full evaluation for KidZoo Visual Perception Classifier.

Covers:
- Base train/test metrics (accuracy, precision, recall, F1, TP/TN/FP/FN per class)
- Stratified K-Fold cross-validation (stability check — LogReg has no 'epochs')
- Multiple-seed runs (to check variance vs. overfitting)
- Train-vs-test gap (overfitting indicator)
- Per-class classification report + confusion matrix
- Persists model / scaler / metadata + full report to evaluation_report.json
"""
from __future__ import annotations

import json
from pathlib import Path
from statistics import mean, pstdev

import joblib
import numpy as np
import pandas as pd
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import (
    accuracy_score,
    classification_report,
    confusion_matrix,
    f1_score,
    precision_score,
    recall_score,
    roc_auc_score,
)
from sklearn.model_selection import StratifiedKFold, train_test_split
from sklearn.preprocessing import StandardScaler

BASE_DIR = Path(__file__).resolve().parent
DATA_PATH = BASE_DIR / "data" / "visual_perception_dataset.csv"
ARTIFACTS_DIR = BASE_DIR / "artifacts"
ARTIFACTS_DIR.mkdir(exist_ok=True)

RANDOM_STATE = 42
POSITIVE_CLASS = "Visual_Perception_Disorder"
NEGATIVE_CLASS = "Normal"
CV_FOLDS = 5
MULTI_SEED_RUNS = 10


def load_data() -> pd.DataFrame:
    return pd.read_csv(DATA_PATH)


def build_feature_matrix(df: pd.DataFrame):
    X = df.drop("Class", axis=1)
    cat_cols = X.select_dtypes(include=["object"]).columns.tolist()
    X = pd.get_dummies(X, columns=cat_cols, drop_first=True)
    all_columns_after_encoding = X.columns.tolist()
    X_sampled = X.sample(frac=0.7, axis=1, random_state=RANDOM_STATE)
    selected_features = X_sampled.columns.tolist()
    return X_sampled, selected_features, all_columns_after_encoding, cat_cols


def confusion_breakdown(y_true, y_pred) -> dict:
    cm = confusion_matrix(y_true, y_pred, labels=[NEGATIVE_CLASS, POSITIVE_CLASS])
    tn, fp, fn, tp = cm.ravel()
    return {
        "true_negative": int(tn),
        "false_positive": int(fp),
        "false_negative": int(fn),
        "true_positive": int(tp),
    }


def per_class_metrics(y_true, y_pred) -> dict:
    labels = [NEGATIVE_CLASS, POSITIVE_CLASS]
    prec = precision_score(y_true, y_pred, labels=labels, average=None, zero_division=0)
    rec = recall_score(y_true, y_pred, labels=labels, average=None, zero_division=0)
    f1 = f1_score(y_true, y_pred, labels=labels, average=None, zero_division=0)
    return {
        label: {
            "precision": float(prec[i]),
            "recall": float(rec[i]),
            "f1": float(f1[i]),
        }
        for i, label in enumerate(labels)
    }


def fit_and_predict(X_train, X_test, y_train):
    scaler = StandardScaler()
    X_train_s = scaler.fit_transform(X_train)
    X_test_s = scaler.transform(X_test)
    model = LogisticRegression(C=0.25, penalty="l2", max_iter=1000)
    model.fit(X_train_s, y_train)
    return model, scaler, X_train_s, X_test_s


def evaluate(y_true, y_pred, y_proba_disorder) -> dict:
    acc = accuracy_score(y_true, y_pred)
    f1_disorder = f1_score(y_true, y_pred, pos_label=POSITIVE_CLASS)
    try:
        auc = roc_auc_score(
            [1 if v == POSITIVE_CLASS else 0 for v in y_true],
            y_proba_disorder,
        )
    except ValueError:
        auc = None
    return {
        "accuracy": float(acc),
        "f1_disorder": float(f1_disorder),
        "roc_auc": float(auc) if auc is not None else None,
        "per_class": per_class_metrics(y_true, y_pred),
        "confusion": confusion_breakdown(y_true, y_pred),
    }


def format_table(rows: list[tuple], header: tuple) -> str:
    widths = [max(len(str(r[i])) for r in ([header] + rows)) for i in range(len(header))]
    line = "  ".join(str(h).ljust(widths[i]) for i, h in enumerate(header))
    sep = "-" * len(line)
    out = [line, sep]
    for r in rows:
        out.append("  ".join(str(r[i]).ljust(widths[i]) for i in range(len(r))))
    return "\n".join(out)


def run_cross_validation(X, y) -> dict:
    skf = StratifiedKFold(n_splits=CV_FOLDS, shuffle=True, random_state=RANDOM_STATE)
    per_fold = []
    for fold_idx, (tr, te) in enumerate(skf.split(X, y), start=1):
        X_tr, X_te = X.iloc[tr], X.iloc[te]
        y_tr, y_te = y.iloc[tr], y.iloc[te]
        model, scaler, X_tr_s, X_te_s = fit_and_predict(X_tr, X_te, y_tr)
        y_pred = model.predict(X_te_s)
        y_proba = model.predict_proba(X_te_s)[:, list(model.classes_).index(POSITIVE_CLASS)]
        metrics = evaluate(y_te, y_pred, y_proba)
        metrics["fold"] = fold_idx
        per_fold.append(metrics)

    def agg(key):
        vals = [m[key] for m in per_fold if m[key] is not None]
        return {"mean": float(mean(vals)), "std": float(pstdev(vals)) if len(vals) > 1 else 0.0}

    return {
        "folds": per_fold,
        "summary": {
            "accuracy": agg("accuracy"),
            "f1_disorder": agg("f1_disorder"),
            "roc_auc": agg("roc_auc"),
        },
    }


def run_multi_seed(X, y) -> dict:
    results = []
    for seed in range(1, MULTI_SEED_RUNS + 1):
        X_tr, X_te, y_tr, y_te = train_test_split(
            X, y, test_size=0.3, random_state=seed, stratify=y
        )
        model, scaler, X_tr_s, X_te_s = fit_and_predict(X_tr, X_te, y_tr)
        train_acc = accuracy_score(y_tr, model.predict(X_tr_s))
        test_acc = accuracy_score(y_te, model.predict(X_te_s))
        f1d = f1_score(y_te, model.predict(X_te_s), pos_label=POSITIVE_CLASS)
        results.append({
            "seed": seed,
            "train_accuracy": float(train_acc),
            "test_accuracy": float(test_acc),
            "gap": float(train_acc - test_acc),
            "f1_disorder": float(f1d),
        })
    mean_gap = mean(r["gap"] for r in results)
    return {
        "runs": results,
        "mean_train_acc": mean(r["train_accuracy"] for r in results),
        "mean_test_acc": mean(r["test_accuracy"] for r in results),
        "mean_gap": mean_gap,
        "overfitting_verdict": "mild_overfit" if mean_gap > 0.10 else ("slight" if mean_gap > 0.05 else "healthy"),
    }


def main():
    print("=" * 70)
    print("  KidZoo Visual Perception Classifier - Full Training & Evaluation")
    print("=" * 70)

    df = load_data()
    class_counts = df["Class"].value_counts().to_dict()
    print(f"\n[1/5] Dataset: rows={len(df)}  unique_users={df['User_ID'].nunique()}")
    print(f"      class distribution: {class_counts}")

    X, selected_features, all_features, cat_cols = build_feature_matrix(df)
    y = df["Class"]
    print(f"      features_used={len(selected_features)} / total_encoded={len(all_features)}")

    print(f"\n[2/5] Base model (train/test split, seed={RANDOM_STATE})")
    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.3, random_state=RANDOM_STATE, stratify=y
    )
    model, scaler, X_train_s, X_test_s = fit_and_predict(X_train, X_test, y_train)
    y_pred = model.predict(X_test_s)
    disorder_idx = list(model.classes_).index(POSITIVE_CLASS)
    y_proba = model.predict_proba(X_test_s)[:, disorder_idx]
    base_metrics = evaluate(y_test, y_pred, y_proba)

    train_acc = accuracy_score(y_train, model.predict(X_train_s))
    test_acc = base_metrics["accuracy"]

    print("\n      -- Headline Metrics --")
    headline = [
        ("Accuracy (test)", f"{test_acc:.4f}"),
        ("Accuracy (train)", f"{train_acc:.4f}"),
        ("Train-Test gap", f"{train_acc - test_acc:+.4f}"),
        ("F1 (Visual_Perception_Disorder)", f"{base_metrics['f1_disorder']:.4f}"),
        ("ROC AUC", f"{base_metrics['roc_auc']:.4f}" if base_metrics["roc_auc"] is not None else "n/a"),
    ]
    print(format_table(headline, ("Metric", "Value")))

    print("\n      -- Per-Class --")
    pc = base_metrics["per_class"]
    rows = []
    for label in [NEGATIVE_CLASS, POSITIVE_CLASS]:
        rows.append((
            label,
            f"{pc[label]['precision']:.4f}",
            f"{pc[label]['recall']:.4f}",
            f"{pc[label]['f1']:.4f}",
        ))
    print(format_table(rows, ("Class", "Precision", "Recall", "F1")))

    print("\n      -- Confusion Matrix --")
    cm = base_metrics["confusion"]
    cm_rows = [
        ("Actual Normal", cm["true_negative"], cm["false_positive"], cm["true_negative"] + cm["false_positive"]),
        ("Actual Disorder", cm["false_negative"], cm["true_positive"], cm["false_negative"] + cm["true_positive"]),
        ("Total predicted", cm["true_negative"] + cm["false_negative"], cm["false_positive"] + cm["true_positive"], sum(cm.values())),
    ]
    print(format_table(cm_rows, ("", "Pred Normal", "Pred Disorder", "Total")))
    print("\n      TP (disorder correctly caught): ", cm["true_positive"])
    print("      TN (normal correctly identified):", cm["true_negative"])
    print("      FP (normal flagged as disorder): ", cm["false_positive"])
    print("      FN (disorder missed as normal):  ", cm["false_negative"])

    print(f"\n[3/5] Stratified {CV_FOLDS}-Fold Cross-Validation (our 'epochs' for LogReg)")
    cv = run_cross_validation(X, y)
    cv_rows = [
        (f"Fold {f['fold']}",
         f"{f['accuracy']:.4f}",
         f"{f['f1_disorder']:.4f}",
         f"{f['roc_auc']:.4f}" if f["roc_auc"] is not None else "n/a",
         f"TP={f['confusion']['true_positive']} FN={f['confusion']['false_negative']} FP={f['confusion']['false_positive']} TN={f['confusion']['true_negative']}",
        )
        for f in cv["folds"]
    ]
    print(format_table(cv_rows, ("Fold", "Accuracy", "F1 Disorder", "ROC AUC", "Confusion")))
    s = cv["summary"]
    print(f"\n      Mean Accuracy: {s['accuracy']['mean']:.4f}  (±{s['accuracy']['std']:.4f})")
    print(f"      Mean F1 Disorder: {s['f1_disorder']['mean']:.4f}  (±{s['f1_disorder']['std']:.4f})")
    print(f"      Mean ROC AUC: {s['roc_auc']['mean']:.4f}  (±{s['roc_auc']['std']:.4f})")

    print(f"\n[4/5] Multi-seed runs ({MULTI_SEED_RUNS} different splits) — overfitting check")
    multi = run_multi_seed(X, y)
    rows = [
        (r["seed"], f"{r['train_accuracy']:.4f}", f"{r['test_accuracy']:.4f}",
         f"{r['gap']:+.4f}", f"{r['f1_disorder']:.4f}")
        for r in multi["runs"]
    ]
    print(format_table(rows, ("Seed", "Train Acc", "Test Acc", "Gap (train-test)", "F1 Disorder")))
    print(f"\n      Mean Train Acc: {multi['mean_train_acc']:.4f}")
    print(f"      Mean Test Acc:  {multi['mean_test_acc']:.4f}")
    print(f"      Mean Gap:       {multi['mean_gap']:+.4f}")
    print(f"      Overfitting verdict: {multi['overfitting_verdict']}")
    print("      (gap ≤ 0.05 = healthy ; 0.05-0.10 = slight ; > 0.10 = overfit)")

    print("\n[5/5] sklearn classification_report:")
    print(classification_report(y_test, y_pred, digits=4))

    print("Saving artifacts...")
    joblib.dump(model, ARTIFACTS_DIR / "best_f1_model.pkl")
    joblib.dump(scaler, ARTIFACTS_DIR / "scaler.pkl")

    metadata = {
        "model_type": "LogisticRegression",
        "model_params": {"C": 0.25, "penalty": "l2", "max_iter": 1000},
        "classes": sorted(y.unique().tolist()),
        "accuracy": test_acc,
        "f1_disorder": base_metrics["f1_disorder"],
        "random_state": RANDOM_STATE,
        "selected_features": selected_features,
        "all_features_after_encoding": all_features,
        "categorical_columns": cat_cols,
        "raw_numeric_columns": [
            c for c in df.drop("Class", axis=1).columns if c not in cat_cols
        ],
        "raw_categorical_values": {c: sorted(df[c].unique().tolist()) for c in cat_cols},
        "task_to_skill": {
            "Tracking": "Visual Tracking",
            "Discrimination": "Visual Discrimination",
            "Matching": "Visual Matching",
            "Orientation": "Spatial Orientation",
        },
        "target_type_to_skill": {
            "Direction": "Direction Perception",
            "Color": "Color Perception",
            "Shape": "Shape Perception",
            "Position": "Spatial Relations",
        },
        "task_types": sorted(df["Task_Type"].unique().tolist()),
        "difficulty_levels": sorted(df["Difficulty_Level"].unique().tolist()),
        "target_types": sorted(df["Target_Type"].unique().tolist()),
    }
    with open(ARTIFACTS_DIR / "metadata.json", "w", encoding="utf-8") as f:
        json.dump(metadata, f, indent=2, ensure_ascii=False)

    full_report = {
        "dataset": {"rows": len(df), "users": df["User_ID"].nunique(), "class_counts": class_counts},
        "base_split": {"train_accuracy": train_acc, **base_metrics},
        "cross_validation": cv,
        "multi_seed": multi,
    }
    with open(ARTIFACTS_DIR / "evaluation_report.json", "w", encoding="utf-8") as f:
        json.dump(full_report, f, indent=2, ensure_ascii=False)

    print(f"\n   saved: {ARTIFACTS_DIR}/best_f1_model.pkl")
    print(f"   saved: {ARTIFACTS_DIR}/scaler.pkl")
    print(f"   saved: {ARTIFACTS_DIR}/metadata.json")
    print(f"   saved: {ARTIFACTS_DIR}/evaluation_report.json")
    print("\n  Done.")


if __name__ == "__main__":
    main()

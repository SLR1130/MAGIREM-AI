"""Reproducible, read-only integrity checks for Emotion35 v3/v4 artifacts."""

from __future__ import annotations

import ast
import hashlib
import json
import re
from pathlib import Path

import torch
from safetensors.torch import load_file
from transformers import AutoModelForSequenceClassification


ROOT = Path(__file__).resolve().parents[1]
AUDIT = ROOT / "storage" / "app" / "audit-recovery"
V3 = AUDIT / "emotion_model_35_multilabel_prototype_v3_20261026"
V4 = AUDIT / "emotion_model_35_multilabel_prototype_v4_weak_20261103"
WARNING_LOG = AUDIT / "emotion35_goemotions_partial_run2.stderr.log"


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def loading_info(path: Path) -> dict:
    _, info = AutoModelForSequenceClassification.from_pretrained(
        path,
        local_files_only=True,
        output_loading_info=True,
    )
    return {
        k: sorted(info.get(k, []), key=str)
        for k in ("missing_keys", "unexpected_keys", "mismatched_keys", "error_msgs")
    }


def warning_keys() -> dict:
    text = WARNING_LOG.read_text(encoding="utf-8", errors="replace")
    result = {}
    for label in ("missing", "unexpected"):
        match = re.search(
            rf"There were {label} keys in the checkpoint model loaded: (\[.*?\])\.",
            text,
            flags=re.DOTALL,
        )
        result[label] = ast.literal_eval(match.group(1)) if match else []
    return result


def layernorm_alias_matches(path: Path, raw: dict) -> dict:
    model = AutoModelForSequenceClassification.from_pretrained(path, local_files_only=True)
    loaded = model.state_dict()
    beta_to_bias = 0
    gamma_to_weight = 0
    for key, tensor in raw.items():
        if key.endswith(".LayerNorm.beta"):
            beta_to_bias += int(torch.equal(tensor, loaded[key.replace(".LayerNorm.beta", ".LayerNorm.bias")]))
        elif key.endswith(".LayerNorm.gamma"):
            gamma_to_weight += int(torch.equal(tensor, loaded[key.replace(".LayerNorm.gamma", ".LayerNorm.weight")]))
    return {
        "beta_to_bias_exact": beta_to_bias,
        "gamma_to_weight_exact": gamma_to_weight,
        "pairs": beta_to_bias + gamma_to_weight,
    }


def main() -> None:
    v3_state = load_file(str(V3 / "model.safetensors"))
    v4_state = load_file(str(V4 / "model.safetensors"))
    v3_keys = sorted(v3_state)
    v4_keys = sorted(v4_state)
    shape_mismatches = [
        key for key in sorted(set(v3_state) & set(v4_state))
        if tuple(v3_state[key].shape) != tuple(v4_state[key].shape)
    ]
    legacy_ln = sorted(
        key for key in v3_state if ".LayerNorm." in key and key.endswith((".beta", ".gamma"))
    )
    config3 = json.loads((V3 / "config.json").read_text(encoding="utf-8"))
    config4 = json.loads((V4 / "config.json").read_text(encoding="utf-8"))
    label3 = json.loads((V3 / "label_mapping.json").read_text(encoding="utf-8"))
    label4 = json.loads((V4 / "label_mapping.json").read_text(encoding="utf-8"))
    tokenizer_hashes = {
        name: {"v3": sha256(V3 / name), "v4": sha256(V4 / name), "equal": sha256(V3 / name) == sha256(V4 / name)}
        for name in ("tokenizer.json", "tokenizer_config.json")
    }
    v3_loading = loading_info(V3)
    v4_loading = loading_info(V4)
    report = {
        "schema": "emotion35-v4-integrity-v1",
        "warning_source": str(WARNING_LOG),
        "legacy_warning": warning_keys(),
        "direct_v3_loading_info": v3_loading,
        "direct_v4_loading_info": v4_loading,
        "state_dict": {
            "v3_count": len(v3_state),
            "v4_count": len(v4_state),
            "same_key_set": v3_keys == v4_keys,
            "shape_mismatches": shape_mismatches,
            "encoder_key_count": sum(key.startswith("roberta.") for key in v4_state),
            "legacy_layernorm_key_count": len(legacy_ln),
            "legacy_layernorm_keys": legacy_ln,
            "v3_loaded_layernorm_alias_matches": layernorm_alias_matches(V3, v3_state),
            "v4_loaded_layernorm_alias_matches": layernorm_alias_matches(V4, v4_state),
            "classifier_out_proj_v3": list(v3_state["classifier.out_proj.weight"].shape),
            "classifier_out_proj_v4": list(v4_state["classifier.out_proj.weight"].shape),
        },
        "architecture": {
            "model_type_match": config3.get("model_type") == config4.get("model_type"),
            "hidden_size_match": config3.get("hidden_size") == config4.get("hidden_size"),
            "layers_match": config3.get("num_hidden_layers") == config4.get("num_hidden_layers"),
            "num_labels_v3": config3.get("num_labels"),
            "num_labels_v4": config4.get("num_labels"),
            "label_mapping_match": label3 == label4,
        },
        "tokenizer": tokenizer_hashes,
        "conclusion": "legacy_layernorm_alias_warning_only" if all(
            not info[key]
            for info in (v3_loading, v4_loading)
            for key in ("missing_keys", "unexpected_keys", "mismatched_keys", "error_msgs")
        ) else "requires_investigation",
    }
    out = AUDIT / "emotion35_v4_integrity_20261103.json"
    out.write_text(json.dumps(report, indent=2), encoding="utf-8")
    print(json.dumps(report, indent=2))
    print(f"WROTE={out}")


if __name__ == "__main__":
    main()

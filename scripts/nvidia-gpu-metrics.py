#!/usr/bin/env python3
"""
SNMP Extend script for LibreNMS - NVIDIA GPU Metrics via nvidia-smi.

Collects GPU telemetry using nvidia-smi and outputs JSON compatible
with the LibreNMS custom application polling framework.

Supported platforms: Linux, Windows Server 2022, Windows 11.

Usage:
    Standalone:  python3 nvidia-gpu-metrics.py
    Linux snmpd: extend nvidia-smi /usr/bin/python3 /opt/snmp-monitoring/nvidia-gpu-metrics.py
    Win snmpd:   extend nvidia-smi C:\Python312\python.exe C:\snmp-monitoring\nvidia-gpu-metrics.py

Requires: nvidia-smi in PATH (ships with the NVIDIA driver).
"""

import json
import os
import subprocess
import sys
import shutil

# On Windows, prevent subprocess from spawning visible console windows
# when this script runs under the Net-SNMP service (Session 0).
_SUBPROCESS_FLAGS = {}
if sys.platform == "win32":
    _SUBPROCESS_FLAGS["creationflags"] = (
        subprocess.CREATE_NO_WINDOW  # Python 3.7+
    )

NVIDIA_SMI_FIELDS = [
    "index",
    "name",
    "uuid",
    "driver_version",
    "temperature.gpu",
    "temperature.memory",
    "utilization.gpu",
    "utilization.memory",
    "memory.total",
    "memory.used",
    "memory.free",
    "fan.speed",
    "power.draw",
    "power.limit",
    "pstate",
    "clocks.current.graphics",
    "clocks.current.memory",
    "clocks.max.graphics",
    "clocks.max.memory",
    "pcie.link.gen.current",
    "pcie.link.width.current",
    "encoder.stats.sessionCount",
    "encoder.stats.averageFps",
    "ecc.errors.corrected.volatile.total",
    "ecc.errors.uncorrected.volatile.total",
]


def find_nvidia_smi():
    """Locate the nvidia-smi binary."""
    path = shutil.which("nvidia-smi")
    if path:
        return path
    # Common fallback locations
    for candidate in [
        "/usr/bin/nvidia-smi",
        "/usr/local/bin/nvidia-smi",
        "/opt/nvidia/bin/nvidia-smi",
        r"C:\Windows\System32\nvidia-smi.exe",
        r"C:\Program Files\NVIDIA Corporation\NVSMI\nvidia-smi.exe",
    ]:
        if shutil.which(candidate) or os.path.isfile(candidate):
            return candidate
    return None


def query_nvidia_smi(nvidia_smi_path):
    """Run nvidia-smi and return parsed GPU data."""
    query = ",".join(NVIDIA_SMI_FIELDS)
    try:
        result = subprocess.run(
            [nvidia_smi_path, "--query-gpu=" + query, "--format=csv,noheader,nounits"],
            capture_output=True,
            text=True,
            timeout=10,
            **_SUBPROCESS_FLAGS,
        )
    except FileNotFoundError:
        return None, "nvidia-smi binary not found"
    except subprocess.TimeoutExpired:
        return None, "nvidia-smi timed out"

    if result.returncode != 0:
        return None, f"nvidia-smi exited with code {result.returncode}: {result.stderr.strip()}"

    gpus = []
    for line in result.stdout.strip().splitlines():
        values = [v.strip() for v in line.split(",")]
        if len(values) < len(NVIDIA_SMI_FIELDS):
            continue

        gpu = {}
        for field, value in zip(NVIDIA_SMI_FIELDS, values):
            key = field.replace(".", "_")
            if value in ("[Not Supported]", "[N/A]", "N/A", ""):
                gpu[key] = None
            else:
                gpu[key] = _parse_value(value)
        gpus.append(gpu)

    return gpus, None


def _parse_value(value):
    """Attempt to cast a string value to int or float."""
    try:
        return int(value)
    except ValueError:
        pass
    try:
        return float(value)
    except ValueError:
        pass
    return value


def get_process_info(nvidia_smi_path):
    """Retrieve per-GPU compute process counts."""
    try:
        result = subprocess.run(
            [nvidia_smi_path, "--query-compute-apps=gpu_uuid,pid", "--format=csv,noheader,nounits"],
            capture_output=True,
            text=True,
            timeout=10,
            **_SUBPROCESS_FLAGS,
        )
    except (FileNotFoundError, subprocess.TimeoutExpired):
        return {}

    counts = {}
    if result.returncode == 0:
        for line in result.stdout.strip().splitlines():
            parts = [p.strip() for p in line.split(",")]
            if len(parts) >= 2:
                uuid = parts[0]
                counts[uuid] = counts.get(uuid, 0) + 1
    return counts


def build_output():
    """Build the JSON output for LibreNMS consumption."""
    nvidia_smi_path = find_nvidia_smi()
    if not nvidia_smi_path:
        return {
            "error": 1,
            "errorString": "nvidia-smi not found in PATH",
            "version": 1,
            "data": {},
        }

    gpus, error = query_nvidia_smi(nvidia_smi_path)
    if error:
        return {
            "error": 1,
            "errorString": error,
            "version": 1,
            "data": {},
        }

    # Enrich with process counts
    proc_counts = get_process_info(nvidia_smi_path)
    for gpu in gpus:
        uuid = gpu.get("uuid")
        gpu["compute_processes"] = proc_counts.get(uuid, 0)

    # Build summary totals
    total_mem_used = sum(g.get("memory_used", 0) or 0 for g in gpus)
    total_mem_total = sum(g.get("memory_total", 0) or 0 for g in gpus)
    avg_util = 0
    util_gpus = [g["utilization_gpu"] for g in gpus if g.get("utilization_gpu") is not None]
    if util_gpus:
        avg_util = round(sum(util_gpus) / len(util_gpus), 1)
    total_power = sum(g.get("power_draw", 0) or 0 for g in gpus)

    return {
        "error": 0,
        "errorString": "",
        "version": 1,
        "data": {
            "gpu_count": len(gpus),
            "total_memory_used_mib": total_mem_used,
            "total_memory_total_mib": total_mem_total,
            "average_utilization_pct": avg_util,
            "total_power_draw_w": round(total_power, 2),
            "gpus": gpus,
        },
    }


def main():
    output = build_output()
    print(json.dumps(output))
    sys.exit(0 if output["error"] == 0 else 1)


if __name__ == "__main__":
    main()

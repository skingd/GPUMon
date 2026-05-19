#!/usr/bin/env bash
#
# install.sh - Install the nvidia-smi SNMP extend plugin for LibreNMS
#
# Usage:
#   sudo ./install.sh                        # Install extend script + snmpd config only
#   sudo ./install.sh --librenms /opt/librenms # Also install LibreNMS application modules
#
set -euo pipefail

INSTALL_DIR="/opt/snmp-monitoring"
SNMPD_CONF_DIR="/etc/snmp/snmpd.conf.d"
LIBRENMS_DIR=""

# ── Parse arguments ─────────────────────────────────────────────────
while [[ $# -gt 0 ]]; do
    case "$1" in
        --librenms)
            LIBRENMS_DIR="$2"
            shift 2
            ;;
        --help|-h)
            echo "Usage: sudo $0 [--librenms /path/to/librenms]"
            echo ""
            echo "  --librenms PATH   Also install LibreNMS polling & graph modules"
            echo ""
            exit 0
            ;;
        *)
            echo "Unknown option: $1" >&2
            exit 1
            ;;
    esac
done

# ── Pre-flight checks ──────────────────────────────────────────────
if [[ $EUID -ne 0 ]]; then
    echo "Error: This script must be run as root (sudo)." >&2
    exit 1
fi

if ! command -v python3 &>/dev/null; then
    echo "Error: python3 is required but not found in PATH." >&2
    exit 1
fi

if ! command -v nvidia-smi &>/dev/null; then
    echo "Warning: nvidia-smi not found. The extend script will fail until NVIDIA drivers are installed." >&2
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# ── Install extend script ──────────────────────────────────────────
echo "Installing extend script to ${INSTALL_DIR}/ ..."
mkdir -p "${INSTALL_DIR}"
cp "${SCRIPT_DIR}/scripts/nvidia-gpu-metrics.py" "${INSTALL_DIR}/nvidia-gpu-metrics.py"
chmod 755 "${INSTALL_DIR}/nvidia-gpu-metrics.py"

# ── Install snmpd extend configuration ─────────────────────────────
echo "Installing snmpd extend config to ${SNMPD_CONF_DIR}/ ..."
mkdir -p "${SNMPD_CONF_DIR}"
cp "${SCRIPT_DIR}/snmpd/nvidia-gpu.conf" "${SNMPD_CONF_DIR}/nvidia-gpu.conf"
chmod 644 "${SNMPD_CONF_DIR}/nvidia-gpu.conf"

# ── Install LibreNMS modules (optional) ─────────────────────────────
if [[ -n "${LIBRENMS_DIR}" ]]; then
    if [[ ! -d "${LIBRENMS_DIR}/includes/polling/applications" ]]; then
        echo "Error: ${LIBRENMS_DIR} does not look like a LibreNMS installation." >&2
        exit 1
    fi

    echo "Installing LibreNMS polling module ..."
    cp "${SCRIPT_DIR}/librenms/includes/polling/applications/nvidia-smi.inc.php" \
       "${LIBRENMS_DIR}/includes/polling/applications/nvidia-smi.inc.php"

    echo "Installing LibreNMS graph definitions ..."
    GRAPH_DIR="${LIBRENMS_DIR}/includes/html/graphs/application"
    mkdir -p "${GRAPH_DIR}"
    cp "${SCRIPT_DIR}"/librenms/includes/html/graphs/application/nvidia-smi*.inc.php \
       "${GRAPH_DIR}/"

    # Install the dashboard plugin
    echo "Installing LibreNMS NvidiaGpu plugin ..."
    PLUGIN_DIR="${LIBRENMS_DIR}/app/Plugins/NvidiaGpu"
    mkdir -p "${PLUGIN_DIR}/resources/views"
    cp "${SCRIPT_DIR}/librenms/app/Plugins/NvidiaGpu/Plugin.php" \
       "${PLUGIN_DIR}/Plugin.php"
    cp "${SCRIPT_DIR}/librenms/app/Plugins/NvidiaGpu/resources/views/widget.blade.php" \
       "${PLUGIN_DIR}/resources/views/widget.blade.php"
    cp "${SCRIPT_DIR}/librenms/app/Plugins/NvidiaGpu/resources/views/device-overview.blade.php" \
       "${PLUGIN_DIR}/resources/views/device-overview.blade.php"

    # Fix ownership to match LibreNMS
    LIBRENMS_USER=$(stat -c '%U' "${LIBRENMS_DIR}/includes" 2>/dev/null || echo "librenms")
    chown -R "${LIBRENMS_USER}:${LIBRENMS_USER}" \
        "${LIBRENMS_DIR}/includes/polling/applications/nvidia-smi.inc.php" \
        "${GRAPH_DIR}"/nvidia-smi*.inc.php \
        "${PLUGIN_DIR}" 2>/dev/null || true

    echo "LibreNMS modules and plugin installed."
    echo "Enable the plugin: Settings → Plugins → NvidiaGpu"
fi

# ── Restart snmpd ──────────────────────────────────────────────────
echo ""
echo "Restarting snmpd ..."
if systemctl is-active --quiet snmpd; then
    systemctl restart snmpd
    echo "snmpd restarted successfully."
else
    echo "Warning: snmpd is not running. Start it with: systemctl start snmpd"
fi

# ── Verify ─────────────────────────────────────────────────────────
echo ""
echo "Verifying extend output ..."
if OUTPUT=$(python3 "${INSTALL_DIR}/nvidia-gpu-metrics.py" 2>&1); then
    echo "Extend script output:"
    echo "${OUTPUT}" | python3 -m json.tool 2>/dev/null || echo "${OUTPUT}"
    echo ""
    echo "Installation complete."
else
    echo "Warning: Extend script returned an error (nvidia-smi may not be available)."
    echo "${OUTPUT}"
fi

echo ""
echo "┌──────────────────────────────────────────────────────────┐"
echo "│  Next steps:                                             │"
echo "│                                                          │"
echo "│  1. Verify SNMP extend works:                            │"
echo "│     snmpwalk -v2c -c <community> localhost \\             │"
echo "│       NET-SNMP-EXTEND-MIB::nsExtendOutputFull            │"
echo "│                                                          │"
echo "│  2. In LibreNMS, enable the application on the device:   │"
echo "│     Device → Apps → nvidia-smi (check the box)           │"
echo "│                                                          │"
echo "│  3. Wait for the next polling cycle (5 min) or force:    │"
echo "│     cd /opt/librenms && ./poller.php -h <hostname>       │"
echo "└──────────────────────────────────────────────────────────┘"

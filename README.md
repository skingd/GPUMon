# NVIDIA GPU SNMP Monitoring Plugin for LibreNMS

An SNMP extend plugin that uses `nvidia-smi` to expose GPU telemetry to [LibreNMS](https://www.librenms.org/) via the standard SNMP extend mechanism.

## Metrics Collected

| Metric | Unit | Per-GPU | Summary |
|---|---|---|---|
| GPU Temperature | °C | ✓ | |
| Memory Temperature | °C | ✓ | |
| GPU Utilization | % | ✓ | ✓ (avg) |
| Memory Utilization | % | ✓ | |
| Memory Used / Free / Total | MiB | ✓ | ✓ |
| Fan Speed | % | ✓ | |
| Power Draw / Limit | W | ✓ | ✓ (total) |
| Graphics / Memory Clock | MHz | ✓ | |
| PCIe Generation & Width | | ✓ | |
| ECC Errors (corrected / uncorrected) | count | ✓ | |
| Encoder Sessions & FPS | | ✓ | |
| Compute Process Count | | ✓ | |

## Requirements

- **Target host (GPU):** NVIDIA GPU with driver installed (`nvidia-smi` in PATH), Python 3.6+
  - **Linux:** `net-snmp` (snmpd)
  - **Windows Server 2022 / Windows 11:** [Net-SNMP for Windows](https://sourceforge.net/projects/net-snmp/) installed as a service
- **LibreNMS server:** LibreNMS 24.x+ (for `json_app_get` support)

## Project Structure

```
snmp-monitoring/
├── scripts/
│   └── nvidia-gpu-metrics.py              # SNMP extend script (runs on GPU host)
├── snmpd/
│   └── nvidia-gpu.conf                    # snmpd extend configuration (Linux & Windows)
├── librenms/
│   ├── app/Plugins/NvidiaGpu/             # Dashboard plugin
│   │   ├── Plugin.php                     # Plugin class (widget + device overview hooks)
│   │   └── resources/views/
│   │       ├── widget.blade.php           # Dashboard widget template
│   │       └── device-overview.blade.php  # Per-device GPU panel
│   └── includes/
│       ├── polling/applications/
│       │   └── nvidia-smi.inc.php         # LibreNMS poller module
│       └── html/graphs/application/
│           ├── nvidia-smi.inc.php         # Graph index / registration
│           ├── nvidia-smi_temperature.inc.php
│           ├── nvidia-smi_utilization.inc.php
│           ├── nvidia-smi_memory.inc.php
│           ├── nvidia-smi_power.inc.php
│           ├── nvidia-smi_clocks.inc.php
│           ├── nvidia-smi_fan.inc.php
│           ├── nvidia-smi_pcie.inc.php
│           └── nvidia-smi_processes.inc.php
├── install.sh                             # Automated installer (Linux)
├── install.ps1                            # Automated installer (Windows)
└── README.md
```

## Quick Start

### 1. Install on the GPU Host

#### Windows (Server 2022 / Windows 11)

**Prerequisites:**
1. Install [Python 3](https://www.python.org/downloads/) — ensure "Add to PATH" is checked
2. Install [Net-SNMP for Windows](https://sourceforge.net/projects/net-snmp/) (default: `C:\usr`)
3. Register Net-SNMP as a Windows service:
   ```powershell
   & "C:\usr\bin\snmpd.exe" -register
   ```

**Install the plugin (run as Administrator):**

```powershell
git clone <this-repo> C:\temp\snmp-monitoring
cd C:\temp\snmp-monitoring
.\install.ps1
```

This copies the extend script to `C:\snmp-monitoring\` and appends the extend directive to `snmpd.conf`.

You can customise paths:

```powershell
.\install.ps1 -InstallDir "D:\monitoring" -NetSnmpDir "C:\net-snmp"
```

#### Linux

```bash
git clone <this-repo> /tmp/snmp-monitoring
cd /tmp/snmp-monitoring
sudo ./install.sh
```

This installs the extend script to `/opt/snmp-monitoring/` and configures snmpd.

### 2. Install LibreNMS Modules (on the LibreNMS server)

```bash
sudo ./install.sh --librenms /opt/librenms
```

Or from Windows (if LibreNMS share is accessible):

```powershell
.\install.ps1 -LibreNmsDir "\\librenms-server\librenms"
```

### 3. Verify SNMP Extend

From the LibreNMS server (or any SNMP client):

```bash
snmpwalk -v2c -c <community> <gpu-host> NET-SNMP-EXTEND-MIB::nsExtendOutputFull
```

You should see JSON output containing GPU metrics.

### 4. Enable in LibreNMS

1. Navigate to **Devices → [your GPU host] → Apps**
2. Check **nvidia-smi**
3. Save — graphs will appear after the next polling cycle (~5 min)

## Manual Installation

If you prefer not to use the installer:

### On the GPU host (Linux):

```bash
# Copy the extend script
sudo mkdir -p /opt/snmp-monitoring
sudo cp scripts/nvidia-gpu-metrics.py /opt/snmp-monitoring/nvidia-gpu-metrics.py
sudo chmod 755 /opt/snmp-monitoring/nvidia-gpu-metrics.py

# Add the extend directive to snmpd
echo 'extend nvidia-smi /usr/bin/python3 /opt/snmp-monitoring/nvidia-gpu-metrics.py' \
  | sudo tee -a /etc/snmp/snmpd.conf

sudo systemctl restart snmpd
```

### On the GPU host (Windows Server 2022 / Windows 11):

```powershell
# Copy the extend script
New-Item -ItemType Directory -Path C:\snmp-monitoring -Force
Copy-Item scripts\nvidia-gpu-metrics.py C:\snmp-monitoring\nvidia-gpu-metrics.py

# Get your python path
$pythonPath = (Get-Command python).Source

# Append extend directive to Net-SNMP config
$extendLine = "extend nvidia-smi $pythonPath C:\snmp-monitoring\nvidia-gpu-metrics.py"
Add-Content -Path C:\usr\etc\snmp\snmpd.conf -Value "`n$extendLine"

# Restart Net-SNMP service
Restart-Service snmpd
```

> **Note:** The Windows SNMP Service (built-in) does *not* support SNMP extends.
> You must use [Net-SNMP for Windows](https://sourceforge.net/projects/net-snmp/).

### On the LibreNMS server:

```bash
LIBRENMS=/opt/librenms

# Poller module
sudo cp librenms/includes/polling/applications/nvidia-smi.inc.php \
  $LIBRENMS/includes/polling/applications/

# Graph definitions
sudo cp librenms/includes/html/graphs/application/nvidia-smi*.inc.php \
  $LIBRENMS/includes/html/graphs/application/

sudo chown -R librenms:librenms $LIBRENMS/includes/
```

## Standalone Testing

Run the extend script directly to verify output:

```bash
# Linux
python3 scripts/nvidia-gpu-metrics.py | python3 -m json.tool
```

```powershell
# Windows (PowerShell)
python scripts\nvidia-gpu-metrics.py | python -m json.tool
```

Example output:

```json
{
  "error": 0,
  "errorString": "",
  "version": 1,
  "data": {
    "gpu_count": 1,
    "total_memory_used_mib": 512,
    "total_memory_total_mib": 24576,
    "average_utilization_pct": 35.0,
    "total_power_draw_w": 120.5,
    "gpus": [
      {
        "index": 0,
        "name": "NVIDIA GeForce RTX 4090",
        "uuid": "GPU-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
        "driver_version": "560.35.03",
        "temperature_gpu": 52,
        "temperature_memory": 48,
        "utilization_gpu": 35,
        "utilization_memory": 12,
        "memory_total": 24576,
        "memory_used": 512,
        "memory_free": 24064,
        "fan_speed": 30,
        "power_draw": 120.5,
        "power_limit": 450.0,
        "pstate": "P2",
        "clocks_current_graphics": 2100,
        "clocks_current_memory": 10501,
        "clocks_max_graphics": 2520,
        "clocks_max_memory": 10501,
        "pcie_link_gen_current": 4,
        "pcie_link_width_current": 16,
        "encoder_stats_sessionCount": 0,
        "encoder_stats_averageFps": 0,
        "ecc_errors_corrected_volatile_total": null,
        "ecc_errors_uncorrected_volatile_total": null,
        "compute_processes": 2
      }
    ]
  }
}
```

## LibreNMS Graphs

Once enabled, the following graph groups appear under the device's **Apps** tab:

- **Summary** — Average GPU utilization, total memory usage, total power draw
- **Per-GPU** — Temperature, utilization, memory, power, clocks, fan speed, PCIe link, compute processes

## Dashboard Plugin

In addition to per-device application graphs, this project includes a **LibreNMS v2 Plugin** that adds:

### Dashboard Widget
A fleet-wide GPU overview widget you can add to any LibreNMS dashboard. Shows every GPU across all monitored devices in one table with colour-coded temperature, utilization, memory bars, power draw, fan speed, and performance state.

### Device Overview Panel
A GPU status panel that appears on each device's overview page showing detailed metrics for all GPUs in that host — temps, clocks, VRAM usage, PCIe link, and active compute processes.

### Enabling the Plugin

1. The installer copies the plugin to `<librenms>/app/Plugins/NvidiaGpu/`
2. In LibreNMS, go to **Settings → Plugins**
3. Find **NVIDIA GPU Monitor** and click **Enable**
4. To add the dashboard widget: **Dashboard → Add Widget → NVIDIA GPU Monitor**

### Manual Plugin Install

```bash
LIBRENMS=/opt/librenms

# Copy the plugin
sudo cp -r librenms/app/Plugins/NvidiaGpu $LIBRENMS/app/Plugins/NvidiaGpu
sudo chown -R librenms:librenms $LIBRENMS/app/Plugins/NvidiaGpu
```

Then enable in **Settings → Plugins**.

## Troubleshooting

| Issue | Solution |
|---|---|
| `nvidia-smi not found` | Install NVIDIA drivers; ensure `nvidia-smi` is in PATH |
| No data in LibreNMS | Verify SNMP extend output with `snmpwalk`; check the app is enabled on the device |
| Permission denied | Ensure `snmpd` user can execute the script and `nvidia-smi` |
| `[Not Supported]` for some fields | Normal — not all GPUs support all sensors (e.g., ECC on consumer GPUs) |
| Graphs not showing | Wait for 2+ polling cycles; check `./poller.php -h <host> -m applications -d` |
| **Windows:** Net-SNMP service won't start | Run `snmpd.exe -register` as Administrator; check `C:\usr\etc\snmp\snmpd.conf` syntax |
| **Windows:** Built-in SNMP Service used | The Windows SNMP Service does **not** support extends — install Net-SNMP |
| **Windows:** nvidia-smi not found from service | The Net-SNMP service may have a different PATH; use full path in the extend directive |
| **Windows:** Console window flashes | The extend script sets `CREATE_NO_WINDOW`; ensure Python 3.7+ is used |

## License

MIT

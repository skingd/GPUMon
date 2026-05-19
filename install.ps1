#Requires -RunAsAdministrator
<#
.SYNOPSIS
    Install the nvidia-smi SNMP extend plugin for LibreNMS on Windows.

.DESCRIPTION
    Installs the SNMP extend script and configures Net-SNMP on Windows Server 2022
    or Windows 11 hosts. Optionally installs LibreNMS application modules.

.PARAMETER NetSnmpDir
    Path to the Net-SNMP installation. Default: C:\usr

.PARAMETER InstallDir
    Where to install the extend script. Default: C:\snmp-monitoring

.PARAMETER LibreNmsDir
    If specified, also install the LibreNMS poller and graph modules to this path.

.EXAMPLE
    .\install.ps1
    .\install.ps1 -NetSnmpDir "C:\net-snmp"
    .\install.ps1 -LibreNmsDir "\\librenms-server\librenms"
#>

[CmdletBinding()]
param(
    [string]$NetSnmpDir = "C:\usr",
    [string]$InstallDir = "C:\snmp-monitoring",
    [string]$LibreNmsDir = ""
)

$ErrorActionPreference = "Stop"
$ScriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path

# ── Colours helper ──────────────────────────────────────────────────
function Write-Step  { param([string]$Msg) Write-Host "[*] $Msg" -ForegroundColor Cyan }
function Write-Ok    { param([string]$Msg) Write-Host "[+] $Msg" -ForegroundColor Green }
function Write-Warn  { param([string]$Msg) Write-Host "[!] $Msg" -ForegroundColor Yellow }
function Write-Err   { param([string]$Msg) Write-Host "[-] $Msg" -ForegroundColor Red }

# ── Pre-flight checks ──────────────────────────────────────────────
Write-Step "Running pre-flight checks..."

# Check Python
$python = Get-Command python -ErrorAction SilentlyContinue
if (-not $python) {
    $python = Get-Command python3 -ErrorAction SilentlyContinue
}
if (-not $python) {
    Write-Err "Python 3 is required but not found in PATH."
    Write-Err "Install from https://www.python.org/downloads/ or the Microsoft Store."
    exit 1
}
$pythonPath = $python.Source
$pyVer = & $pythonPath --version 2>&1
Write-Ok "Found $pyVer at $pythonPath"

# Check nvidia-smi
$nvidiaSmi = Get-Command nvidia-smi -ErrorAction SilentlyContinue
if (-not $nvidiaSmi) {
    # Check common Windows locations
    $candidates = @(
        "$env:SystemRoot\System32\nvidia-smi.exe",
        "${env:ProgramFiles}\NVIDIA Corporation\NVSMI\nvidia-smi.exe"
    )
    foreach ($c in $candidates) {
        if (Test-Path $c) {
            $nvidiaSmi = Get-Item $c
            break
        }
    }
}
if ($nvidiaSmi) {
    Write-Ok "Found nvidia-smi at $($nvidiaSmi.Source ?? $nvidiaSmi.FullName)"
} else {
    Write-Warn "nvidia-smi not found. The extend script will fail until NVIDIA drivers are installed."
}

# Check Net-SNMP
$snmpdConf = Join-Path $NetSnmpDir "etc\snmp\snmpd.conf"
$snmpdExe  = Join-Path $NetSnmpDir "bin\snmpd.exe"
if (-not (Test-Path $snmpdExe)) {
    # Try alternate common paths
    foreach ($alt in @("C:\net-snmp", "C:\usr\local", "${env:ProgramFiles}\Net-SNMP")) {
        if (Test-Path (Join-Path $alt "bin\snmpd.exe")) {
            $NetSnmpDir = $alt
            $snmpdConf = Join-Path $NetSnmpDir "etc\snmp\snmpd.conf"
            $snmpdExe  = Join-Path $NetSnmpDir "bin\snmpd.exe"
            break
        }
    }
}
if (Test-Path $snmpdExe) {
    Write-Ok "Found Net-SNMP at $NetSnmpDir"
} else {
    Write-Warn "Net-SNMP not found at $NetSnmpDir."
    Write-Warn "Download from https://sourceforge.net/projects/net-snmp/ and install."
    Write-Warn "Continuing installation — configure SNMP manually afterwards."
}

# ── Install extend script ──────────────────────────────────────────
Write-Step "Installing extend script to $InstallDir ..."
if (-not (Test-Path $InstallDir)) {
    New-Item -ItemType Directory -Path $InstallDir -Force | Out-Null
}
Copy-Item (Join-Path $ScriptRoot "scripts\nvidia-gpu-metrics.py") `
          (Join-Path $InstallDir "nvidia-gpu-metrics.py") -Force
Write-Ok "Extend script installed."

# ── Configure Net-SNMP extend ──────────────────────────────────────
if (Test-Path $snmpdConf) {
    Write-Step "Configuring Net-SNMP extend directive ..."

    $extendLine = "extend nvidia-smi $pythonPath $InstallDir\nvidia-gpu-metrics.py"
    $confContent = Get-Content $snmpdConf -Raw -ErrorAction SilentlyContinue

    if ($confContent -and $confContent -match "extend\s+nvidia-smi") {
        Write-Warn "An nvidia-smi extend directive already exists in snmpd.conf — skipping."
        Write-Warn "Verify the path is correct: $extendLine"
    } else {
        # Append extend directive
        $block = @"

# NVIDIA GPU monitoring extend (added by snmp-monitoring installer)
$extendLine
"@
        Add-Content -Path $snmpdConf -Value $block -Encoding UTF8
        Write-Ok "Extend directive added to $snmpdConf"
    }
} elseif (Test-Path (Split-Path $snmpdConf -Parent)) {
    Write-Step "Creating snmpd.conf with extend directive ..."
    $extendLine = "extend nvidia-smi $pythonPath $InstallDir\nvidia-gpu-metrics.py"
    Set-Content -Path $snmpdConf -Value @"
# Net-SNMP configuration
# NVIDIA GPU monitoring extend (added by snmp-monitoring installer)
$extendLine
"@ -Encoding UTF8
    Write-Ok "Created $snmpdConf"
} else {
    Write-Warn "Cannot find snmpd.conf directory. Add this line manually to your snmpd.conf:"
    Write-Warn "  extend nvidia-smi $pythonPath $InstallDir\nvidia-gpu-metrics.py"
}

# ── Install LibreNMS modules (optional) ─────────────────────────────
if ($LibreNmsDir) {
    $pollerDir = Join-Path $LibreNmsDir "includes\polling\applications"
    $graphDir  = Join-Path $LibreNmsDir "includes\html\graphs\application"

    if (-not (Test-Path $pollerDir)) {
        Write-Err "$LibreNmsDir does not look like a LibreNMS installation (missing $pollerDir)."
        exit 1
    }

    Write-Step "Installing LibreNMS polling module ..."
    Copy-Item (Join-Path $ScriptRoot "librenms\includes\polling\applications\nvidia-smi.inc.php") `
              (Join-Path $pollerDir "nvidia-smi.inc.php") -Force

    Write-Step "Installing LibreNMS graph definitions ..."
    if (-not (Test-Path $graphDir)) {
        New-Item -ItemType Directory -Path $graphDir -Force | Out-Null
    }
    Get-ChildItem (Join-Path $ScriptRoot "librenms\includes\html\graphs\application\nvidia-smi*.inc.php") |
        ForEach-Object { Copy-Item $_.FullName (Join-Path $graphDir $_.Name) -Force }

    # Install the dashboard plugin
    Write-Step "Installing LibreNMS NvidiaGpu plugin ..."
    $pluginDir = Join-Path $LibreNmsDir "app\Plugins\NvidiaGpu\resources\views"
    New-Item -ItemType Directory -Path $pluginDir -Force | Out-Null
    Copy-Item (Join-Path $ScriptRoot "librenms\app\Plugins\NvidiaGpu\Plugin.php") `
              (Join-Path $LibreNmsDir "app\Plugins\NvidiaGpu\Plugin.php") -Force
    Copy-Item (Join-Path $ScriptRoot "librenms\app\Plugins\NvidiaGpu\resources\views\widget.blade.php") `
              (Join-Path $pluginDir "widget.blade.php") -Force
    Copy-Item (Join-Path $ScriptRoot "librenms\app\Plugins\NvidiaGpu\resources\views\device-overview.blade.php") `
              (Join-Path $pluginDir "device-overview.blade.php") -Force

    Write-Ok "LibreNMS modules and plugin installed."
    Write-Ok "Enable the plugin: Settings -> Plugins -> NvidiaGpu"
}

# ── Restart Net-SNMP service ───────────────────────────────────────
Write-Step "Checking Net-SNMP service ..."
$snmpService = Get-Service -Name "snmpd" -ErrorAction SilentlyContinue
if (-not $snmpService) {
    $snmpService = Get-Service -Name "Net-SNMP Agent" -ErrorAction SilentlyContinue
}
if (-not $snmpService) {
    # Look for any service with snmp in the name that isn't the built-in Windows SNMP
    $snmpService = Get-Service | Where-Object {
        $_.Name -like "*snmp*" -and $_.Name -ne "SNMP" -and $_.Name -ne "SNMPTRAP"
    } | Select-Object -First 1
}

if ($snmpService) {
    Write-Step "Restarting $($snmpService.DisplayName) ..."
    Restart-Service -Name $snmpService.Name -Force
    Write-Ok "Service restarted."
} else {
    Write-Warn "Net-SNMP service not found. If using Net-SNMP, register and start the service:"
    Write-Warn "  & `"$snmpdExe`" -register"
    Write-Warn "  Start-Service snmpd"
}

# ── Verify extend script ──────────────────────────────────────────
Write-Step "Verifying extend script output ..."
try {
    $output = & $pythonPath (Join-Path $InstallDir "nvidia-gpu-metrics.py") 2>&1
    $parsed = $output | ConvertFrom-Json
    if ($parsed.error -eq 0) {
        Write-Ok "Script returned $($parsed.data.gpu_count) GPU(s)"
        Write-Host ($output | ConvertFrom-Json | ConvertTo-Json -Depth 5) -ForegroundColor Gray
    } else {
        Write-Warn "Script returned error: $($parsed.errorString)"
    }
} catch {
    Write-Warn "Could not verify extend script: $_"
}

# ── Summary ────────────────────────────────────────────────────────
Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  Installation Complete" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "  Next steps:" -ForegroundColor White
Write-Host ""
Write-Host "  1. Verify SNMP extend works from the LibreNMS server:" -ForegroundColor White
Write-Host "     snmpwalk -v2c -c <community> <this-host> \" -ForegroundColor Gray
Write-Host "       NET-SNMP-EXTEND-MIB::nsExtendOutputFull" -ForegroundColor Gray
Write-Host ""
Write-Host "  2. In LibreNMS, enable the application on this device:" -ForegroundColor White
Write-Host "     Device -> Apps -> nvidia-smi (check the box)" -ForegroundColor Gray
Write-Host ""
Write-Host "  3. Wait for the next polling cycle (~5 min) or force poll:" -ForegroundColor White
Write-Host "     cd /opt/librenms && ./poller.php -h <hostname>" -ForegroundColor Gray
Write-Host ""
Write-Host "  Files installed:" -ForegroundColor White
Write-Host "    Script: $InstallDir\nvidia-gpu-metrics.py" -ForegroundColor Gray
Write-Host "    Config: $snmpdConf" -ForegroundColor Gray
Write-Host "============================================================" -ForegroundColor Cyan

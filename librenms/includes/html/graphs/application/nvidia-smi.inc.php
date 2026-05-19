<?php
/**
 * LibreNMS application overview graph for nvidia-smi.
 *
 * Place in: <librenms>/includes/html/graphs/application/nvidia-smi.inc.php
 *
 * This file registers the available graph types for the nvidia-smi application.
 */

$app_graphs['default'] = [
    'nvidia-smi_summary_utilization'  => 'GPU Utilization (Average)',
    'nvidia-smi_summary_memory'       => 'Total Memory Usage',
    'nvidia-smi_summary_power'        => 'Total Power Draw',
];

// Register per-GPU graphs dynamically
$gpu_data = $app->data['gpus'] ?? [];
foreach ($gpu_data as $index => $info) {
    $label = $info['name'] ?? "GPU $index";
    $app_graphs["gpu$index"] = [
        "nvidia-smi_gpu{$index}_temperature"  => "$label - Temperature",
        "nvidia-smi_gpu{$index}_utilization"  => "$label - Utilization",
        "nvidia-smi_gpu{$index}_memory"       => "$label - Memory",
        "nvidia-smi_gpu{$index}_power"        => "$label - Power",
        "nvidia-smi_gpu{$index}_clocks"       => "$label - Clocks",
        "nvidia-smi_gpu{$index}_fan"          => "$label - Fan Speed",
        "nvidia-smi_gpu{$index}_pcie"         => "$label - PCIe",
        "nvidia-smi_gpu{$index}_processes"    => "$label - Processes",
    ];
}

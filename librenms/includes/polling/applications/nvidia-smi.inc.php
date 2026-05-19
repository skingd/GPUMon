<?php
/**
 * LibreNMS application poller module for NVIDIA GPU metrics.
 *
 * Place this file in:
 *   <librenms_root>/includes/polling/applications/nvidia-smi.inc.php
 *
 * Enable the application on the device in LibreNMS:
 *   Settings -> Apps -> nvidia-smi  (or via device settings)
 *
 * This module reads the JSON output from the SNMP extend script
 * and stores RRD/InfluxDB metrics for each GPU.
 */

use LibreNMS\Exceptions\JsonAppException;
use LibreNMS\RRD\RrdDefinition;

$name = 'nvidia-smi';
$output = 'OK';

try {
    $data = json_app_get($device, $name, 1);
} catch (JsonAppException $e) {
    echo PHP_EOL . $name . ':' . $e->getCode() . ':' . $e->getMessage() . PHP_EOL;
    update_application($app, $e->getCode() . ':' . $e->getMessage(), []);
    return;
}

if (isset($data['error']) && $data['error'] !== 0) {
    $output = $data['errorString'] ?? 'Unknown error from nvidia-smi extend';
    echo PHP_EOL . $name . ': ' . $output . PHP_EOL;
    update_application($app, $output, []);
    return;
}

$app_data = $data['data'] ?? [];
$metrics = [];
$gpu_count = $app_data['gpu_count'] ?? 0;

// ── Summary metrics ────────────────────────────────────────────────
$rrd_name = ['app', $name, $app->app_id, 'summary'];
$rrd_def = RrdDefinition::make()
    ->addDataset('gpu_count',         'GAUGE', 0, 1024)
    ->addDataset('total_mem_used',    'GAUGE', 0)
    ->addDataset('total_mem_total',   'GAUGE', 0)
    ->addDataset('avg_util',          'GAUGE', 0, 100)
    ->addDataset('total_power',       'GAUGE', 0);

$fields = [
    'gpu_count'       => $app_data['gpu_count'] ?? 0,
    'total_mem_used'  => $app_data['total_memory_used_mib'] ?? 0,
    'total_mem_total' => $app_data['total_memory_total_mib'] ?? 0,
    'avg_util'        => $app_data['average_utilization_pct'] ?? 0,
    'total_power'     => $app_data['total_power_draw_w'] ?? 0,
];

$metrics['summary'] = $fields;
$tags = ['name' => $name, 'app_id' => $app->app_id, 'rrd_def' => $rrd_def, 'rrd_name' => $rrd_name];
data_update($device, 'app', $tags, $fields);

// ── Per-GPU metrics ────────────────────────────────────────────────
$gpus = $app_data['gpus'] ?? [];

foreach ($gpus as $gpu) {
    $gpu_index = $gpu['index'] ?? 'unknown';
    $gpu_name  = $gpu['name'] ?? 'GPU ' . $gpu_index;

    $rrd_name = ['app', $name, $app->app_id, 'gpu' . $gpu_index];

    $rrd_def = RrdDefinition::make()
        ->addDataset('temp_gpu',         'GAUGE', -40, 150)
        ->addDataset('temp_mem',         'GAUGE', -40, 150)
        ->addDataset('util_gpu',         'GAUGE', 0, 100)
        ->addDataset('util_mem',         'GAUGE', 0, 100)
        ->addDataset('mem_total',        'GAUGE', 0)
        ->addDataset('mem_used',         'GAUGE', 0)
        ->addDataset('mem_free',         'GAUGE', 0)
        ->addDataset('fan_speed',        'GAUGE', 0, 100)
        ->addDataset('power_draw',       'GAUGE', 0)
        ->addDataset('power_limit',      'GAUGE', 0)
        ->addDataset('clock_graphics',   'GAUGE', 0)
        ->addDataset('clock_memory',     'GAUGE', 0)
        ->addDataset('pcie_gen',         'GAUGE', 0, 6)
        ->addDataset('pcie_width',       'GAUGE', 0, 32)
        ->addDataset('ecc_corrected',    'DERIVE', 0)
        ->addDataset('ecc_uncorrected',  'DERIVE', 0)
        ->addDataset('encoder_sessions', 'GAUGE', 0)
        ->addDataset('encoder_fps',      'GAUGE', 0)
        ->addDataset('compute_procs',    'GAUGE', 0);

    $fields = [
        'temp_gpu'         => $gpu['temperature_gpu'] ?? null,
        'temp_mem'         => $gpu['temperature_memory'] ?? null,
        'util_gpu'         => $gpu['utilization_gpu'] ?? null,
        'util_mem'         => $gpu['utilization_memory'] ?? null,
        'mem_total'        => $gpu['memory_total'] ?? null,
        'mem_used'         => $gpu['memory_used'] ?? null,
        'mem_free'         => $gpu['memory_free'] ?? null,
        'fan_speed'        => $gpu['fan_speed'] ?? null,
        'power_draw'       => $gpu['power_draw'] ?? null,
        'power_limit'      => $gpu['power_limit'] ?? null,
        'clock_graphics'   => $gpu['clocks_current_graphics'] ?? null,
        'clock_memory'     => $gpu['clocks_current_memory'] ?? null,
        'pcie_gen'         => $gpu['pcie_link_gen_current'] ?? null,
        'pcie_width'       => $gpu['pcie_link_width_current'] ?? null,
        'ecc_corrected'    => $gpu['ecc_errors_corrected_volatile_total'] ?? null,
        'ecc_uncorrected'  => $gpu['ecc_errors_uncorrected_volatile_total'] ?? null,
        'encoder_sessions' => $gpu['encoder_stats_sessionCount'] ?? null,
        'encoder_fps'      => $gpu['encoder_stats_averageFps'] ?? null,
        'compute_procs'    => $gpu['compute_processes'] ?? 0,
    ];

    // Replace null with 'U' (unknown) for RRD
    foreach ($fields as $k => $v) {
        if ($v === null) {
            $fields[$k] = 'U';
        }
    }

    $metrics['gpu' . $gpu_index] = $fields;
    $tags = [
        'name'     => $name,
        'app_id'   => $app->app_id,
        'rrd_def'  => $rrd_def,
        'rrd_name' => $rrd_name,
    ];
    data_update($device, 'app', $tags, $fields);

    // Store GPU label for the web UI component table
    $app->data = array_merge($app->data ?? [], [
        'gpus' => array_merge($app->data['gpus'] ?? [], [
            $gpu_index => [
                'name'  => $gpu_name,
                'uuid'  => $gpu['uuid'] ?? '',
                'pstate' => $gpu['pstate'] ?? '',
                'driver' => $gpu['driver_version'] ?? '',
            ],
        ]),
    ]);
}

update_application($app, $output, $metrics);

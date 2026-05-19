<?php
/**
 * LibreNMS v2 Plugin — NVIDIA GPU Monitoring Dashboard.
 *
 * Install by copying to: <librenms>/app/Plugins/NvidiaGpu/
 * Then enable via: Settings → Plugins → NvidiaGpu
 *
 * Provides:
 *   - Dashboard widget: GPU fleet overview (all devices)
 *   - Device overview panel: per-device GPU status
 */

namespace App\Plugins\NvidiaGpu;

use App\Models\Application;
use App\Models\Device;
use App\Plugins\Hooks\DeviceOverviewHook;
use App\Plugins\Hooks\WidgetHook;
use Illuminate\Support\Facades\View;

class Plugin
{
    /**
     * Plugin metadata shown in Settings → Plugins.
     */
    public static function info(): array
    {
        return [
            'name'        => 'NVIDIA GPU Monitor',
            'description' => 'Dashboard widget and device overview for NVIDIA GPU metrics via nvidia-smi.',
            'version'     => '1.0.0',
            'author'      => 'snmp-monitoring',
        ];
    }

    // ── Dashboard Widget ───────────────────────────────────────────

    /**
     * Register the dashboard widget.
     */
    public static function widgetHooks(): array
    {
        return [
            WidgetHook::class => 'widget',
        ];
    }

    /**
     * Render the dashboard widget.
     */
    public function widget(array $settings): \Illuminate\Contracts\View\View
    {
        $apps = Application::where('app_type', 'nvidia-smi')
            ->with('device')
            ->get();

        $gpus = [];
        foreach ($apps as $app) {
            $device = $app->device;
            if (! $device) {
                continue;
            }

            $appData = is_array($app->data) ? $app->data : json_decode($app->data ?? '{}', true);
            $gpuList = $appData['gpus'] ?? [];

            foreach ($gpuList as $index => $info) {
                // Pull latest metrics from the app's metric cache
                $metrics = $app->metrics["gpu{$index}"] ?? [];

                $gpus[] = [
                    'device_id'   => $device->device_id,
                    'hostname'    => $device->displayName(),
                    'gpu_index'   => $index,
                    'gpu_name'    => $info['name'] ?? "GPU {$index}",
                    'pstate'      => $info['pstate'] ?? '-',
                    'driver'      => $info['driver'] ?? '-',
                    'temp_gpu'    => $metrics['temp_gpu'] ?? null,
                    'util_gpu'    => $metrics['util_gpu'] ?? null,
                    'mem_used'    => $metrics['mem_used'] ?? null,
                    'mem_total'   => $metrics['mem_total'] ?? null,
                    'power_draw'  => $metrics['power_draw'] ?? null,
                    'power_limit' => $metrics['power_limit'] ?? null,
                    'fan_speed'   => $metrics['fan_speed'] ?? null,
                ];
            }
        }

        return View::file(
            __DIR__ . '/resources/views/widget.blade.php',
            [
                'gpus'     => $gpus,
                'settings' => $settings,
            ]
        );
    }

    // ── Device Overview Panel ──────────────────────────────────────

    /**
     * Register the device overview hook.
     */
    public static function deviceOverviewHooks(): array
    {
        return [
            DeviceOverviewHook::class => 'deviceOverview',
        ];
    }

    /**
     * Render GPU status on the device overview page.
     */
    public function deviceOverview(Device $device, array $settings = []): \Illuminate\Contracts\View\View
    {
        $app = Application::where('app_type', 'nvidia-smi')
            ->where('device_id', $device->device_id)
            ->first();

        $gpus = [];
        if ($app) {
            $appData = is_array($app->data) ? $app->data : json_decode($app->data ?? '{}', true);
            $gpuList = $appData['gpus'] ?? [];

            foreach ($gpuList as $index => $info) {
                $metrics = $app->metrics["gpu{$index}"] ?? [];

                $gpus[] = [
                    'gpu_index'       => $index,
                    'gpu_name'        => $info['name'] ?? "GPU {$index}",
                    'uuid'            => $info['uuid'] ?? '',
                    'pstate'          => $info['pstate'] ?? '-',
                    'driver'          => $info['driver'] ?? '-',
                    'temp_gpu'        => $metrics['temp_gpu'] ?? null,
                    'temp_mem'        => $metrics['temp_mem'] ?? null,
                    'util_gpu'        => $metrics['util_gpu'] ?? null,
                    'util_mem'        => $metrics['util_mem'] ?? null,
                    'mem_used'        => $metrics['mem_used'] ?? null,
                    'mem_total'       => $metrics['mem_total'] ?? null,
                    'mem_free'        => $metrics['mem_free'] ?? null,
                    'power_draw'      => $metrics['power_draw'] ?? null,
                    'power_limit'     => $metrics['power_limit'] ?? null,
                    'fan_speed'       => $metrics['fan_speed'] ?? null,
                    'clock_graphics'  => $metrics['clock_graphics'] ?? null,
                    'clock_memory'    => $metrics['clock_memory'] ?? null,
                    'pcie_gen'        => $metrics['pcie_gen'] ?? null,
                    'pcie_width'      => $metrics['pcie_width'] ?? null,
                    'compute_procs'   => $metrics['compute_procs'] ?? null,
                ];
            }
        }

        return View::file(
            __DIR__ . '/resources/views/device-overview.blade.php',
            [
                'gpus'   => $gpus,
                'app'    => $app,
                'device' => $device,
            ]
        );
    }
}

<?php
/**
 * GPU Temperature graph definition.
 *
 * Matches: nvidia-smi_gpu{N}_temperature
 */

$name = 'nvidia-smi';
$unit_text = '°C';

// Extract GPU index from the graph subtype (e.g., "gpu0_temperature" -> "gpu0")
preg_match('/gpu(\d+)_temperature/', $vars['type'] ?? '', $m);
$gpu_id = 'gpu' . ($m[1] ?? '0');

$rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, $gpu_id]);

require 'includes/html/graphs/common.inc.php';

$rrd_options .= " DEF:temp_gpu={$rrd_filename}:temp_gpu:AVERAGE";
$rrd_options .= " DEF:temp_mem={$rrd_filename}:temp_mem:AVERAGE";
$rrd_options .= " LINE1.5:temp_gpu#FF0000:'GPU Temp     '";
$rrd_options .= " GPRINT:temp_gpu:LAST:'Cur\\:%5.1lf'";
$rrd_options .= " GPRINT:temp_gpu:AVERAGE:'Avg\\:%5.1lf'";
$rrd_options .= " GPRINT:temp_gpu:MAX:'Max\\:%5.1lf\\n'";
$rrd_options .= " LINE1.5:temp_mem#FF8C00:'Mem Temp     '";
$rrd_options .= " GPRINT:temp_mem:LAST:'Cur\\:%5.1lf'";
$rrd_options .= " GPRINT:temp_mem:AVERAGE:'Avg\\:%5.1lf'";
$rrd_options .= " GPRINT:temp_mem:MAX:'Max\\:%5.1lf\\n'";
$rrd_options .= " HRULE:85#FF000080:'Throttle Threshold (85°C)'";

<?php
/**
 * GPU Compute Processes graph definition.
 *
 * Matches: nvidia-smi_gpu{N}_processes
 */

$name = 'nvidia-smi';
$unit_text = 'Processes';

preg_match('/gpu(\d+)_processes/', $vars['type'] ?? '', $m);
$rrd_id = 'gpu' . ($m[1] ?? '0');
$rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, $rrd_id]);

require 'includes/html/graphs/common.inc.php';

$rrd_options .= " DEF:compute_procs={$rrd_filename}:compute_procs:AVERAGE";
$rrd_options .= " DEF:encoder_sessions={$rrd_filename}:encoder_sessions:AVERAGE";
$rrd_options .= " DEF:encoder_fps={$rrd_filename}:encoder_fps:AVERAGE";
$rrd_options .= " LINE1.5:compute_procs#FF0000:'Compute Processes   '";
$rrd_options .= " GPRINT:compute_procs:LAST:'Cur\\:%4.0lf'";
$rrd_options .= " GPRINT:compute_procs:MAX:'Max\\:%4.0lf\\n'";
$rrd_options .= " LINE1.5:encoder_sessions#0066FF:'Encoder Sessions    '";
$rrd_options .= " GPRINT:encoder_sessions:LAST:'Cur\\:%4.0lf'";
$rrd_options .= " GPRINT:encoder_sessions:MAX:'Max\\:%4.0lf\\n'";
$rrd_options .= " LINE1.5:encoder_fps#00CC00:'Encoder Avg FPS     '";
$rrd_options .= " GPRINT:encoder_fps:LAST:'Cur\\:%6.1lf'";
$rrd_options .= " GPRINT:encoder_fps:MAX:'Max\\:%6.1lf\\n'";

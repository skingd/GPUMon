<?php
/**
 * GPU Fan Speed graph definition.
 *
 * Matches: nvidia-smi_gpu{N}_fan
 */

$name = 'nvidia-smi';
$unit_text = '%';

preg_match('/gpu(\d+)_fan/', $vars['type'] ?? '', $m);
$rrd_id = 'gpu' . ($m[1] ?? '0');
$rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, $rrd_id]);

require 'includes/html/graphs/common.inc.php';

$rrd_options .= " DEF:fan_speed={$rrd_filename}:fan_speed:AVERAGE";
$rrd_options .= " AREA:fan_speed#3366FF80:'Fan Speed  '";
$rrd_options .= " GPRINT:fan_speed:LAST:'Cur\\:%5.0lf%%'";
$rrd_options .= " GPRINT:fan_speed:AVERAGE:'Avg\\:%5.0lf%%'";
$rrd_options .= " GPRINT:fan_speed:MAX:'Max\\:%5.0lf%%\\n'";

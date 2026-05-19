<?php
/**
 * GPU Clocks graph definition.
 *
 * Matches: nvidia-smi_gpu{N}_clocks
 */

$name = 'nvidia-smi';
$unit_text = 'MHz';

preg_match('/gpu(\d+)_clocks/', $vars['type'] ?? '', $m);
$rrd_id = 'gpu' . ($m[1] ?? '0');
$rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, $rrd_id]);

require 'includes/html/graphs/common.inc.php';

$rrd_options .= " DEF:clock_gfx={$rrd_filename}:clock_graphics:AVERAGE";
$rrd_options .= " DEF:clock_mem={$rrd_filename}:clock_memory:AVERAGE";
$rrd_options .= " LINE1.5:clock_gfx#00CC00:'Graphics Clock  '";
$rrd_options .= " GPRINT:clock_gfx:LAST:'Cur\\:%6.0lf MHz'";
$rrd_options .= " GPRINT:clock_gfx:AVERAGE:'Avg\\:%6.0lf MHz'";
$rrd_options .= " GPRINT:clock_gfx:MAX:'Max\\:%6.0lf MHz\\n'";
$rrd_options .= " LINE1.5:clock_mem#0000FF:'Memory Clock    '";
$rrd_options .= " GPRINT:clock_mem:LAST:'Cur\\:%6.0lf MHz'";
$rrd_options .= " GPRINT:clock_mem:AVERAGE:'Avg\\:%6.0lf MHz'";
$rrd_options .= " GPRINT:clock_mem:MAX:'Max\\:%6.0lf MHz\\n'";

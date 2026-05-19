<?php
/**
 * GPU Utilization graph definition.
 *
 * Matches: nvidia-smi_gpu{N}_utilization and nvidia-smi_summary_utilization
 */

$name = 'nvidia-smi';
$unit_text = '%';
$colours = 'mixed';

// Determine if this is a per-GPU or summary graph
if (preg_match('/gpu(\d+)_utilization/', $vars['type'] ?? '', $m)) {
    $rrd_id = 'gpu' . $m[1];
    $rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, $rrd_id]);

    require 'includes/html/graphs/common.inc.php';

    $rrd_options .= " DEF:util_gpu={$rrd_filename}:util_gpu:AVERAGE";
    $rrd_options .= " DEF:util_mem={$rrd_filename}:util_mem:AVERAGE";
    $rrd_options .= " AREA:util_gpu#00CC0080:'GPU Util %   '";
    $rrd_options .= " GPRINT:util_gpu:LAST:'Cur\\:%5.1lf%%'";
    $rrd_options .= " GPRINT:util_gpu:AVERAGE:'Avg\\:%5.1lf%%'";
    $rrd_options .= " GPRINT:util_gpu:MAX:'Max\\:%5.1lf%%\\n'";
    $rrd_options .= " LINE1.5:util_mem#0000FF:'Mem Util %   '";
    $rrd_options .= " GPRINT:util_mem:LAST:'Cur\\:%5.1lf%%'";
    $rrd_options .= " GPRINT:util_mem:AVERAGE:'Avg\\:%5.1lf%%'";
    $rrd_options .= " GPRINT:util_mem:MAX:'Max\\:%5.1lf%%\\n'";
} else {
    // Summary graph
    $rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'summary']);

    require 'includes/html/graphs/common.inc.php';

    $rrd_options .= " DEF:avg_util={$rrd_filename}:avg_util:AVERAGE";
    $rrd_options .= " AREA:avg_util#00CC0080:'Avg GPU Util %  '";
    $rrd_options .= " GPRINT:avg_util:LAST:'Cur\\:%5.1lf%%'";
    $rrd_options .= " GPRINT:avg_util:AVERAGE:'Avg\\:%5.1lf%%'";
    $rrd_options .= " GPRINT:avg_util:MAX:'Max\\:%5.1lf%%\\n'";
}

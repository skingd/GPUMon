<?php
/**
 * GPU Memory graph definition.
 *
 * Matches: nvidia-smi_gpu{N}_memory and nvidia-smi_summary_memory
 */

$name = 'nvidia-smi';
$unit_text = 'MiB';

if (preg_match('/gpu(\d+)_memory/', $vars['type'] ?? '', $m)) {
    $rrd_id = 'gpu' . $m[1];
    $rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, $rrd_id]);

    require 'includes/html/graphs/common.inc.php';

    $rrd_options .= " DEF:mem_total={$rrd_filename}:mem_total:AVERAGE";
    $rrd_options .= " DEF:mem_used={$rrd_filename}:mem_used:AVERAGE";
    $rrd_options .= " DEF:mem_free={$rrd_filename}:mem_free:AVERAGE";
    $rrd_options .= " AREA:mem_used#FF660080:'Used    '";
    $rrd_options .= " GPRINT:mem_used:LAST:'Cur\\:%8.0lf MiB'";
    $rrd_options .= " GPRINT:mem_used:AVERAGE:'Avg\\:%8.0lf MiB'";
    $rrd_options .= " GPRINT:mem_used:MAX:'Max\\:%8.0lf MiB\\n'";
    $rrd_options .= " AREA:mem_free#00CC0080:'Free    ':STACK";
    $rrd_options .= " GPRINT:mem_free:LAST:'Cur\\:%8.0lf MiB'";
    $rrd_options .= " GPRINT:mem_free:AVERAGE:'Avg\\:%8.0lf MiB'";
    $rrd_options .= " GPRINT:mem_free:MAX:'Max\\:%8.0lf MiB\\n'";
    $rrd_options .= " LINE1:mem_total#000000:'Total   '";
    $rrd_options .= " GPRINT:mem_total:LAST:'Cur\\:%8.0lf MiB\\n'";
} else {
    // Summary graph
    $rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'summary']);

    require 'includes/html/graphs/common.inc.php';

    $rrd_options .= " DEF:total_mem_used={$rrd_filename}:total_mem_used:AVERAGE";
    $rrd_options .= " DEF:total_mem_total={$rrd_filename}:total_mem_total:AVERAGE";
    $rrd_options .= " CDEF:total_mem_free=total_mem_total,total_mem_used,-";
    $rrd_options .= " AREA:total_mem_used#FF660080:'Used    '";
    $rrd_options .= " GPRINT:total_mem_used:LAST:'Cur\\:%8.0lf MiB'";
    $rrd_options .= " GPRINT:total_mem_used:AVERAGE:'Avg\\:%8.0lf MiB\\n'";
    $rrd_options .= " AREA:total_mem_free#00CC0080:'Free    ':STACK";
    $rrd_options .= " GPRINT:total_mem_free:LAST:'Cur\\:%8.0lf MiB'";
    $rrd_options .= " GPRINT:total_mem_free:AVERAGE:'Avg\\:%8.0lf MiB\\n'";
    $rrd_options .= " LINE1:total_mem_total#000000:'Total   '";
    $rrd_options .= " GPRINT:total_mem_total:LAST:'Cur\\:%8.0lf MiB\\n'";
}

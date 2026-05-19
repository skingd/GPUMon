<?php
/**
 * GPU Power graph definition.
 *
 * Matches: nvidia-smi_gpu{N}_power and nvidia-smi_summary_power
 */

$name = 'nvidia-smi';
$unit_text = 'Watts';

if (preg_match('/gpu(\d+)_power/', $vars['type'] ?? '', $m)) {
    $rrd_id = 'gpu' . $m[1];
    $rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, $rrd_id]);

    require 'includes/html/graphs/common.inc.php';

    $rrd_options .= " DEF:power_draw={$rrd_filename}:power_draw:AVERAGE";
    $rrd_options .= " DEF:power_limit={$rrd_filename}:power_limit:AVERAGE";
    $rrd_options .= " AREA:power_draw#FF660080:'Power Draw  '";
    $rrd_options .= " GPRINT:power_draw:LAST:'Cur\\:%6.1lf W'";
    $rrd_options .= " GPRINT:power_draw:AVERAGE:'Avg\\:%6.1lf W'";
    $rrd_options .= " GPRINT:power_draw:MAX:'Max\\:%6.1lf W\\n'";
    $rrd_options .= " LINE1.5:power_limit#FF0000:'Power Limit '";
    $rrd_options .= " GPRINT:power_limit:LAST:'Cur\\:%6.1lf W\\n'";
} else {
    // Summary graph
    $rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'summary']);

    require 'includes/html/graphs/common.inc.php';

    $rrd_options .= " DEF:total_power={$rrd_filename}:total_power:AVERAGE";
    $rrd_options .= " AREA:total_power#FF660080:'Total Power Draw  '";
    $rrd_options .= " GPRINT:total_power:LAST:'Cur\\:%6.1lf W'";
    $rrd_options .= " GPRINT:total_power:AVERAGE:'Avg\\:%6.1lf W'";
    $rrd_options .= " GPRINT:total_power:MAX:'Max\\:%6.1lf W\\n'";
}

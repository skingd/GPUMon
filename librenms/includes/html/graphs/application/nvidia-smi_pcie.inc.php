<?php
/**
 * GPU PCIe graph definition.
 *
 * Matches: nvidia-smi_gpu{N}_pcie
 */

$name = 'nvidia-smi';
$unit_text = '';

preg_match('/gpu(\d+)_pcie/', $vars['type'] ?? '', $m);
$rrd_id = 'gpu' . ($m[1] ?? '0');
$rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, $rrd_id]);

require 'includes/html/graphs/common.inc.php';

$rrd_options .= " DEF:pcie_gen={$rrd_filename}:pcie_gen:AVERAGE";
$rrd_options .= " DEF:pcie_width={$rrd_filename}:pcie_width:AVERAGE";
$rrd_options .= " LINE1.5:pcie_gen#FF6600:'PCIe Gen     '";
$rrd_options .= " GPRINT:pcie_gen:LAST:'Cur\\:%3.0lf'";
$rrd_options .= " GPRINT:pcie_gen:MIN:'Min\\:%3.0lf\\n'";
$rrd_options .= " LINE1.5:pcie_width#0066FF:'PCIe Width   '";
$rrd_options .= " GPRINT:pcie_width:LAST:'Cur\\:%3.0lf x'";
$rrd_options .= " GPRINT:pcie_width:MIN:'Min\\:%3.0lf x\\n'";

<?php

/**
 * LibreNMS
 *
 *   This file is part of LibreNMS
 *
 * @author     LibreNMS Contributors <librenms-project@google.groups.com>
 * @copyright  (C) 2006 - 2012 Adam Armstrong (as Observium)
 * @copyright  (C) 2013 LibreNMS Group
 */

use App\Facades\DeviceCache;
use App\Facades\PortCache;
use App\Models\Port;
use LibreNMS\Config;
use LibreNMS\Enum\ImageFormat;
use LibreNMS\Util\Number;
use LibreNMS\Util\Rewrite;
use LibreNMS\Util\Url;

function toner2colour($descr, $percent)
{
    $colour = \LibreNMS\Util\Color::percentage(100 - $percent, null);

    if (substr($descr, -1) == 'C' || stripos($descr, 'cyan') !== false) {
        $colour['left'] = '55D6D3';
        $colour['right'] = '33B4B1';
    }

    if (substr($descr, -1) == 'M' || stripos($descr, 'magenta') !== false) {
        $colour['left'] = 'F24AC8';
        $colour['right'] = 'D028A6';
    }

    if (substr($descr, -1) == 'Y' || stripos($descr, 'yellow') !== false
        || stripos($descr, 'giallo') !== false
        || stripos($descr, 'gul') !== false
    ) {
        $colour['left'] = 'FFF200';
        $colour['right'] = 'DDD000';
    }

    if (substr($descr, -1) == 'K' || stripos($descr, 'black') !== false
        || stripos($descr, 'nero') !== false
    ) {
        $colour['left'] = '000000';
        $colour['right'] = '222222';
    }

    return $colour;
}//end toner2colour()

function generate_link($text, $vars, $new_vars = [])
{
    return '<a href="' . Url::generate($vars, $new_vars) . '">' . $text . '</a>';
}//end generate_link()

function escape_quotes($text)
{
    return str_replace('"', "\'", str_replace("'", "\'", $text));
}//end escape_quotes()

function generate_overlib_content($graph_array, $text)
{
    $overlib_content = '<div class=overlib><span class=overlib-text>' . htmlspecialchars($text) . '</span><br />';
    foreach (['day', 'week', 'month', 'year'] as $period) {
        $graph_array['from'] = Config::get("time.$period");
        $overlib_content .= escape_quotes(Url::graphTag($graph_array));
    }

    $overlib_content .= '</div>';

    return $overlib_content;
}//end generate_overlib_content()

function generate_device_link($device, $text = null, $vars = [], $start = 0, $end = 0, $escape_text = 1, $overlib = 1)
{
    $deviceModel = DeviceCache::get((int) $device['device_id']);

    return Url::deviceLink($deviceModel, $text, $vars, $start, $end, $escape_text, $overlib);
}

function bill_permitted($bill_id)
{
    if (Auth::user()->hasGlobalRead()) {
        return true;
    }

    return \Permissions::canAccessBill($bill_id, Auth::id());
}

function port_permitted($port_id, $device_id = null)
{
    if (! is_numeric($device_id)) {
        $device_id = PortCache::get((int) $port_id)?->device_id;
    }

    if (device_permitted($device_id)) {
        return true;
    }

    return \Permissions::canAccessPort($port_id, Auth::id());
}

function device_permitted($device_id)
{
    if (Auth::user() && Auth::user()->hasGlobalRead()) {
        return true;
    }

    return \Permissions::canAccessDevice($device_id, Auth::id());
}

function alert_layout($severity)
{
    switch ($severity) {
        case 'critical':
            $icon = 'exclamation';
            $color = 'danger';
            $background = 'danger';
            break;
        case 'warning':
            $icon = 'warning';
            $color = 'warning';
            $background = 'warning';
            break;
        case 'ok':
            $icon = 'check';
            $color = 'success';
            $background = 'success';
            break;
        default:
            $icon = 'info';
            $color = 'info';
            $background = 'info';
    }

    return ['icon' => $icon,
        'icon_color' => $color,
        'background_color' => $background, ];
}

function generate_dynamic_graph_tag($args)
{
    $urlargs = [];
    $width = 0;
    foreach ($args as $key => $arg) {
        switch (strtolower($key)) {
            case 'width':
                $width = $arg;
                $value = '{{width}}';
                break;
            case 'from':
                $value = '{{start}}';
                break;
            case 'to':
                $value = '{{end}}';
                break;
            default:
                $value = $arg;
                break;
        }
        $urlargs[] = $key . '=' . $value;
    }

    return '<img style="width:' . $width . 'px;height:100%" class="graph graph-image img-responsive" data-src-template="graph.php?' . implode('&amp;', $urlargs) . '" border="0" />';
}//end generate_dynamic_graph_tag()

function generate_dynamic_graph_js($args)
{
    $from = (is_numeric($args['from']) ? $args['from'] : '(new Date()).getTime() / 1000 - 24*3600');
    $range = (is_numeric($args['to']) ? $args['to'] - $args['from'] : '24*3600');

    $output = '<script src="js/RrdGraphJS/q-5.0.2.min.js"></script>
        <script src="js/RrdGraphJS/moment-timezone-with-data.js"></script>
        <script src="js/RrdGraphJS/rrdGraphPng.js"></script>
          <script type="text/javascript">
              q.ready(function(){
                  var graphs = [];
                  q(\'.graph\').forEach(function(item){
                      graphs.push(
                          q(item).rrdGraphPng({
                              canvasPadding: 120,
                                initialStart: ' . $from . ',
                                initialRange: ' . $range . '
                          })
                      );
                  });
              });
              // needed for dynamic height
              window.onload = function(){ window.dispatchEvent(new Event(\'resize\')); }
          </script>';

    return $output;
}//end generate_dynamic_graph_js()

function generate_graph_js_state($args)
{
    // we are going to assume we know roughly what the graph url looks like here.
    // TODO: Add sensible defaults
    $from = (is_numeric($args['from']) ? $args['from'] : 0);
    $to = (is_numeric($args['to']) ? $args['to'] : 0);
    $width = (is_numeric($args['width']) ? $args['width'] : 0);
    $height = (is_numeric($args['height']) ? $args['height'] : 0);
    $legend = str_replace("'", '', $args['legend'] ?? '');

    $state = <<<STATE
<script type="text/javascript" language="JavaScript">
document.graphFrom = $from;
document.graphTo = $to;
document.graphWidth = $width;
document.graphHeight = $height;
document.graphLegend = '$legend';
</script>
STATE;

    return $state;
}//end generate_graph_js_state()

function print_percentage_bar($width, $height, $percent, $left_text, $left_colour, $left_background, $right_text, $right_colour, $right_background)
{
    return \LibreNMS\Util\Html::percentageBar($width, $height, $percent, $left_text, $right_text, null, null, [
        'left' => $left_background,
        'left_text' => $left_colour,
        'right' => $right_background,
        'right_text' => $right_colour,
    ]);
}

function generate_port_link($port, $text = null, $type = null, $overlib = 1, $single_graph = 0)
{
    $graph_array = [];

    if (! $text) {
        $text = Rewrite::normalizeIfName($port['label'] ?? $port['ifName']);
    }

    if ($type) {
        $port['graph_type'] = $type;
    }

    if (! isset($port['graph_type'])) {
        $port['graph_type'] = 'port_bits';
    }

    $class = ifclass($port['ifOperStatus'], $port['ifAdminStatus']);

    if (! isset($port['hostname'])) {
        $port = array_merge($port, device_by_id_cache($port['device_id']));
    }

    if (! isset($port['label'])) {
        $port = cleanPort($port);
    }

    $content = '<div class=list-large>' . $port['hostname'] . ' - ' . Rewrite::normalizeIfName(addslashes(\LibreNMS\Util\Clean::html($port['label'], []))) . '</div>';
    $content .= addslashes(\LibreNMS\Util\Clean::html($port['ifAlias'], [])) . '<br />';

    $content .= "<div style=\'width: 850px\'>";
    $graph_array['type'] = $port['graph_type'];
    $graph_array['legend'] = 'yes';
    $graph_array['height'] = '100';
    $graph_array['width'] = '340';
    $graph_array['to'] = Config::get('time.now');
    $graph_array['from'] = Config::get('time.day');
    $graph_array['id'] = $port['port_id'];
    $content .= Url::graphTag($graph_array);
    if ($single_graph == 0) {
        $graph_array['from'] = Config::get('time.week');
        $content .= Url::graphTag($graph_array);
        $graph_array['from'] = Config::get('time.month');
        $content .= Url::graphTag($graph_array);
        $graph_array['from'] = Config::get('time.year');
        $content .= Url::graphTag($graph_array);
    }

    $content .= '</div>';

    $url = generate_port_url($port);

    if ($overlib == 0) {
        return $content;
    } elseif (port_permitted($port['port_id'], $port['device_id'])) {
        return Url::overlibLink($url, $text, $content, $class);
    } else {
        return Rewrite::normalizeIfName($text);
    }
}//end generate_port_link()

function generate_sensor_link($args, $text = null, $type = null)
{
    if (! $text) {
        $text = $args['sensor_descr'];
    }

    if (! $type) {
        $args['graph_type'] = 'sensor_' . $args['sensor_class'];
    } else {
        $args['graph_type'] = 'sensor_' . $type;
    }

    if (! isset($args['hostname'])) {
        $args = array_merge($args, device_by_id_cache($args['device_id']));
    }

    $content = '<div class=list-large>' . $text . '</div>';

    $content .= "<div style=\'width: 850px\'>";
    $graph_array = [
        'type' => $args['graph_type'],
        'legend' => 'yes',
        'height' => '100',
        'width' => '340',
        'to' => Config::get('time.now'),
        'from' => Config::get('time.day'),
        'id' => $args['sensor_id'],
    ];
    $content .= Url::graphTag($graph_array);

    $graph_array['from'] = Config::get('time.week');
    $content .= Url::graphTag($graph_array);

    $graph_array['from'] = Config::get('time.month');
    $content .= Url::graphTag($graph_array);

    $graph_array['from'] = Config::get('time.year');
    $content .= Url::graphTag($graph_array);

    $content .= '</div>';

    $url = Url::generate(['page' => 'graphs', 'id' => $args['sensor_id'], 'type' => $args['graph_type'], 'from' => \LibreNMS\Config::get('time.day')], []);

    return Url::overlibLink($url, $text, $content);
}//end generate_sensor_link()

function generate_port_url($port, $vars = [])
{
    return Url::generate(['page' => 'device', 'device' => $port['device_id'], 'tab' => 'port', 'port' => $port['port_id']], $vars);
}//end generate_port_url()

function generate_sap_url($sap, $vars = [])
{
    // Overwrite special QinQ sap identifiers
    if ($sap['sapEncapValue'] == '*') {
        $sap['sapEncapValue'] = '4095';
    }

    return Url::graphPopup(['device' => $sap['device_id'], 'page' => 'graphs', 'type' => 'device_sap', 'tab' => 'routing', 'proto' => 'mpls', 'view' => 'saps', 'traffic_id' => $sap['svc_oid'] . '.' . $sap['sapPortId'] . '.' . $sap['sapEncapValue']], $vars);
}//end generate_sap_url()

function generate_port_image($args)
{
    if (! $args['bg']) {
        $args['bg'] = 'FFFFFF00';
    }

    return "<img src='graph.php?type=" . $args['graph_type'] . '&amp;id=' . $args['port_id'] . '&amp;from=' . $args['from'] . '&amp;to=' . $args['to'] . '&amp;width=' . $args['width'] . '&amp;height=' . $args['height'] . '&amp;bg=' . $args['bg'] . "'>";
}//end generate_port_image()

/**
 * Create image to output text instead of a graph.
 *
 * @param  string  $text
 * @param  int[]  $color
 */
function graph_error($text, $short = null, $color = [128, 0, 0])
{
    header('Content-Type: ' . ImageFormat::forGraph()->contentType());
    echo \LibreNMS\Util\Graph::error($text, $short, 300, null, $color);
}

function print_port_thumbnail($args)
{
    echo generate_port_link($args, generate_port_image($args));
}//end print_port_thumbnail()

function print_optionbar_start($height = 0, $width = 0, $marginbottom = 5)
{
    echo '
        <div class="panel panel-default">
        <div class="panel-heading">
        ';
}//end print_optionbar_start()

function print_optionbar_end()
{
    echo '
        </div>
        </div>
        ';
}//end print_optionbar_end()

/**
 * Get the recursive file size and count for a directory
 *
 * @param  string  $path
 * @return array [size, file count]
 */
function foldersize($path)
{
    $total_size = 0;
    $total_files = 0;

    foreach (glob(rtrim($path, '/') . '/*', GLOB_NOSORT) as $item) {
        if (is_dir($item)) {
            [$folder_size, $file_count] = foldersize($item);
            $total_size += $folder_size;
            $total_files += $file_count;
        } else {
            $total_size += filesize($item);
            $total_files++;
        }
    }

    return [$total_size, $total_files];
}

function generate_ap_link($args, $text = null, $type = null)
{
    $args = cleanPort($args);
    if (! $text) {
        $text = Rewrite::normalizeIfName($args['label']);
    }

    if ($type) {
        $args['graph_type'] = $type;
    }

    if (! isset($args['graph_type'])) {
        $args['graph_type'] = 'port_bits';
    }

    if (! isset($args['hostname'])) {
        $args = array_merge($args, device_by_id_cache($args['device_id']));
    }

    $content = '<div class=list-large>' . $args['text'] . ' - ' . Rewrite::normalizeIfName($args['label']) . '</div>';
    if ($args['ifAlias']) {
        $content .= \LibreNMS\Util\Clean::html($args['ifAlias'], []) . '<br />';
    }

    $content .= "<div style=\'width: 850px\'>";
    $graph_array = [];
    $graph_array['type'] = $args['graph_type'];
    $graph_array['legend'] = 'yes';
    $graph_array['height'] = '100';
    $graph_array['width'] = '340';
    $graph_array['to'] = Config::get('time.now');
    $graph_array['from'] = Config::get('time.day');
    $graph_array['id'] = $args['accesspoint_id'];
    $content .= Url::graphTag($graph_array);
    $graph_array['from'] = Config::get('time.week');
    $content .= Url::graphTag($graph_array);
    $graph_array['from'] = Config::get('time.month');
    $content .= Url::graphTag($graph_array);
    $graph_array['from'] = Config::get('time.year');
    $content .= Url::graphTag($graph_array);
    $content .= '</div>';

    $url = generate_ap_url($args);
    if (port_permitted($args['interface_id'], $args['device_id'])) {
        return Url::overlibLink($url, $text, $content);
    } else {
        return Rewrite::normalizeIfName($text);
    }
}//end generate_ap_link()

function generate_ap_url($ap, $vars = [])
{
    return Url::generate(['page' => 'device', 'device' => $ap['device_id'], 'tab' => 'accesspoints', 'ap' => $ap['accesspoint_id']], $vars);
}//end generate_ap_url()

function generate_pagination($count, $limit, $page, $links = 2)
{
    $end_page = ceil($count / $limit);
    $start = (($page - $links) > 0) ? ($page - $links) : 1;
    $end = (($page + $links) < $end_page) ? ($page + $links) : $end_page;
    $return = '<ul class="pagination">';
    $link_class = ($page == 1) ? 'disabled' : '';
    $return .= "<li><a href='' onClick='changePage(1,event);'>&laquo;</a></li>";
    $return .= "<li class='$link_class'><a href='' onClick='changePage($page - 1,event);'>&lt;</a></li>";

    if ($start > 1) {
        $return .= "<li><a href='' onClick='changePage(1,event);'>1</a></li>";
        $return .= "<li class='disabled'><span>...</span></li>";
    }

    for ($x = $start; $x <= $end; $x++) {
        $link_class = ($page == $x) ? 'active' : '';
        $return .= "<li class='$link_class'><a href='' onClick='changePage($x,event);'>$x </a></li>";
    }

    if ($end < $end_page) {
        $return .= "<li class='disabled'><span>...</span></li>";
        $return .= "<li><a href='' onClick='changePage($end_page,event);'>$end_page</a></li>";
    }

    $link_class = ($page == $end_page) ? 'disabled' : '';
    $return .= "<li class='$link_class'><a href='' onClick='changePage($page + 1,event);'>&gt;</a></li>";
    $return .= "<li class='$link_class'><a href='' onClick='changePage($end_page,event);'>&raquo;</a></li>";
    $return .= '</ul>';

    return $return;
}//end generate_pagination()

function demo_account()
{
    print_error("You are logged in as a demo account, this page isn't accessible to you");
}//end demo_account()

function get_client_ip()
{
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $client_ip = $_SERVER['REMOTE_ADDR'];
    }

    return $client_ip;
}//end get_client_ip()

function clean_bootgrid($string)
{
    $output = str_replace(["\r", "\n"], '', $string);
    $output = addslashes($output);

    return $output;
}//end clean_bootgrid()

function get_url()
{
    // http://stackoverflow.com/questions/2820723/how-to-get-base-url-with-php
    // http://stackoverflow.com/users/184600/ma%C4%8Dek
    return sprintf(
        '%s://%s%s',
        isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
        $_SERVER['SERVER_NAME'],
        $_SERVER['REQUEST_URI']
    );
}//end get_url()

function alert_details($details)
{
    if (is_string($details)) {
        $details = json_decode(gzuncompress($details), true);
    } elseif (! is_array($details)) {
        $details = [];
    }

    $max_row_length = 0;
    $all_fault_detail = '';

    // Check if we have a diff (alert status changed, worse and better)
    if (isset($details['diff'])) {
        // Add a "title" for the modifications
        $all_fault_detail .= '<b>Modifications:</b><br>';

        // Check if we have added
        if (isset($details['diff']['added'])) {
            foreach (array_values($details['diff']['added'] ?? []) as $oa => $tmp_alerts_added) {
                $fault_detail = format_alert_details($oa, $tmp_alerts_added, 'Added');
                $max_row_length = strlen(strip_tags($fault_detail)) > $max_row_length ? strlen(strip_tags($fault_detail)) : $max_row_length;
                $all_fault_detail .= $fault_detail;
            }//end foreach
        }

        // Check if we have resolved
        if (isset($details['diff']['resolved'])) {
            foreach (array_values($details['diff']['resolved'] ?? []) as $or => $tmp_alerts_resolved) {
                $fault_detail = format_alert_details($or, $tmp_alerts_resolved, 'Resolved');
                $max_row_length = strlen(strip_tags($fault_detail)) > $max_row_length ? strlen(strip_tags($fault_detail)) : $max_row_length;
                $all_fault_detail .= $fault_detail;
            }//end foreach
        }

        // Add a "title" for the complete list
        $all_fault_detail .= '<br><b>All current items:</b><br>';
    }

    foreach ($details['rule'] ?? [] as $o => $tmp_alerts_rule) {
        $fault_detail = format_alert_details($o, $tmp_alerts_rule);
        $max_row_length = strlen(strip_tags($fault_detail)) > $max_row_length ? strlen(strip_tags($fault_detail)) : $max_row_length;
        $all_fault_detail .= $fault_detail;
    }//end foreach

    return [$all_fault_detail, $max_row_length];
}//end alert_details()

function format_alert_details($alert_idx, $tmp_alerts, $type_info = null)
{
    $fault_detail = '';
    $fallback = true;
    $fault_detail .= $type_info ? $type_info . '&nbsp;' : '';
    $fault_detail .= '#' . ($alert_idx + 1) . ':&nbsp;';
    if (isset($tmp_alerts['bill_id'])) {
        $fault_detail .= '<a href="' . Url::generate(['page' => 'bill', 'bill_id' => $tmp_alerts['bill_id']], []) . '">' . $tmp_alerts['bill_name'] . '</a>;&nbsp;';
        $fallback = false;
    }

    if (isset($tmp_alerts['port_id'])) {
        if (! empty($tmp_alerts['isisISAdjState'])) {
            $fault_detail .= 'Adjacent ' . $tmp_alerts['isisISAdjIPAddrAddress'];
            $port = Port::find($tmp_alerts['port_id']);
            $fault_detail .= ', Interface ' . Url::portLink($port);
        } else {
            $tmp_alerts = cleanPort($tmp_alerts);
            $fault_detail .= generate_port_link($tmp_alerts) . ';&nbsp;';
        }
        if ((isset($tmp_alerts['ifDescr'])) && (isset($tmp_alerts['ifAlias'])) && ($tmp_alerts['ifDescr'] != $tmp_alerts['ifAlias'])) {
            // IfAlias has been set, so display it on alarms
            $fault_detail .= $tmp_alerts['ifAlias'] . '; ';
            unset($tmp_alerts['label']);
        }
        $fallback = false;
    }

    if (isset($tmp_alerts['accesspoint_id'])) {
        $fault_detail .= generate_ap_link($tmp_alerts, $tmp_alerts['name']) . ';&nbsp;';
        $fallback = false;
    }

    if (isset($tmp_alerts['sensor_id'])) {
        if ($tmp_alerts['sensor_class'] == 'state') {
            // Give more details for a state (textual form)
            $details = 'State: ' . $tmp_alerts['state_descr'] . ' (numerical ' . $tmp_alerts['sensor_current'] . ')<br>  ';
        } else {
            // Other sensors
            $details = 'Value: ' . $tmp_alerts['sensor_current'] . ' (' . $tmp_alerts['sensor_class'] . ')<br>  ';
        }
        $details_a = [];

        if ($tmp_alerts['sensor_limit_low']) {
            $details_a[] = 'low: ' . $tmp_alerts['sensor_limit_low'];
        }
        if ($tmp_alerts['sensor_limit_low_warn']) {
            $details_a[] = 'low_warn: ' . $tmp_alerts['sensor_limit_low_warn'];
        }
        if ($tmp_alerts['sensor_limit_warn']) {
            $details_a[] = 'high_warn: ' . $tmp_alerts['sensor_limit_warn'];
        }
        if ($tmp_alerts['sensor_limit']) {
            $details_a[] = 'high: ' . $tmp_alerts['sensor_limit'];
        }
        $details .= implode(', ', $details_a);

        $fault_detail .= generate_sensor_link($tmp_alerts, $tmp_alerts['name']) . ';&nbsp; <br>' . $details;
        $fallback = false;
    }

    if (isset($tmp_alerts['service_id'])) {
        $fault_detail .= "Service: <a href='" .
            Url::generate([
                'page' => 'device',
                'device' => $tmp_alerts['device_id'],
                'tab' => 'services',
                'view' => 'detail',
            ]) .
            "'>" . ($tmp_alerts['service_name'] ?? '') . ' (' . $tmp_alerts['service_type'] . ')' . '</a>';
        $fault_detail .= 'Service Host: ' . ($tmp_alerts['service_ip'] != '' ? $tmp_alerts['service_ip'] : format_hostname(DeviceCache::get($tmp_alerts['device_id']))) . ',<br>';
        $fault_detail .= ($tmp_alerts['service_desc'] != '') ? ('Description: ' . $tmp_alerts['service_desc'] . ',<br>') : '';
        $fault_detail .= ($tmp_alerts['service_param'] != '') ? ('Param: ' . $tmp_alerts['service_param'] . ',<br>') : '';
        $fault_detail .= 'Msg: ' . $tmp_alerts['service_message'];
        $fallback = false;
    }

    if (isset($tmp_alerts['bgpPeer_id'])) {
        // If we have a bgpPeer_id, we format the data accordingly
        $fault_detail .= "BGP peer <a href='" .
            Url::generate([
                'page' => 'device',
                'device' => $tmp_alerts['device_id'],
                'tab' => 'routing',
                'proto' => 'bgp',
            ]) .
            "'>" . $tmp_alerts['bgpPeerIdentifier'] . '</a>';
        $fault_detail .= ', Desc ' . $tmp_alerts['bgpPeerDescr'] ?? '';
        $fault_detail .= ', AS' . $tmp_alerts['bgpPeerRemoteAs'];
        $fault_detail .= ', State ' . $tmp_alerts['bgpPeerState'];
        $fallback = false;
    }

    if (isset($tmp_alerts['mempool_id'])) {
        // If we have a mempool_id, we format the data accordingly
        $fault_detail .= "MemoryPool <a href='" .
            Url::generate([
                'page' => 'graphs',
                'id' => $tmp_alerts['mempool_id'],
                'type' => 'mempool_usage',
            ]) .
            "'>" . ($tmp_alerts['mempool_descr'] ?? 'link') . '</a>';
        $fault_detail .= '<br> &nbsp; &nbsp; &nbsp; Usage ' . $tmp_alerts['mempool_perc'] . '%, &nbsp; Free ' . Number::formatSi($tmp_alerts['mempool_free']) . ',&nbsp; Size ' . Number::formatSi($tmp_alerts['mempool_total']);
        $fallback = false;
    }

    if ($tmp_alerts['type'] && isset($tmp_alerts['label'])) {
        if ($tmp_alerts['error'] == '') {
            $fault_detail .= ' ' . $tmp_alerts['type'] . ' - ' . $tmp_alerts['label'] . ';&nbsp;';
        } else {
            $fault_detail .= ' ' . $tmp_alerts['type'] . ' - ' . $tmp_alerts['label'] . ' - ' . $tmp_alerts['error'] . ';&nbsp;';
        }
        $fallback = false;
    }

    if (in_array('app_id', array_keys($tmp_alerts))) {
        $fault_detail .= "<a href='" .
            Url::generate([
                'page' => 'device',
                'device' => $tmp_alerts['device_id'],
                'tab' => 'apps',
                'app' => $tmp_alerts['app_type'],
            ]) . "'>";
        $fault_detail .= $tmp_alerts['app_type'];
        $fault_detail .= '</a>';

        if ($tmp_alerts['app_status']) {
            $fault_detail .= ' => ' . $tmp_alerts['app_status'];
        }
        if ($tmp_alerts['metric']) {
            $fault_detail .= ' : ' . $tmp_alerts['metric'] . ' => ' . $tmp_alerts['value'];
        }
        $fallback = false;
    }

    if ($fallback === true) {
        $fault_detail_data = [];
        foreach ($tmp_alerts as $k => $v) {
            if (in_array($k, ['device_id', 'sysObjectID', 'sysDescr', 'location_id'])) {
                continue;
            }
            if (! empty($v) && Str::contains($k, ['id', 'desc', 'msg', 'last'], ignoreCase: true)) {
                $fault_detail_data[] = "$k => '$v'";
            }
        }
        $fault_detail .= count($fault_detail_data) ? implode('<br>&nbsp;&nbsp;&nbsp', $fault_detail_data) : '';

        $fault_detail = rtrim($fault_detail, ', ');
    }

    $fault_detail .= '<br>';

    return $fault_detail;
}

function dynamic_override_config($type, $name, $device)
{
    $attrib_val = get_dev_attrib($device, $name);
    if ($attrib_val == 'true') {
        $checked = 'checked';
    } else {
        $checked = '';
    }
    if ($type == 'checkbox') {
        return '<input type="checkbox" id="override_config" name="override_config" data-attrib="' . htmlentities($name) . '" data-device_id="' . $device['device_id'] . '" data-size="small" ' . $checked . '>';
    } elseif ($type == 'text') {
        return '<input type="text" id="override_config_text" name="override_config_text" data-attrib="' . htmlentities($name) . '" data-device_id="' . $device['device_id'] . '" value="' . htmlentities($attrib_val) . '">';
    }
}//end dynamic_override_config()

/**
 * Return the rows from 'ports' for all ports of a certain type as parsed by port_descr_parser.
 * One or an array of strings can be provided as an argument; if an array is passed, all ports matching
 * any of the types in the array are returned.
 *
 * @param  $types  mixed String or strings matching 'port_descr_type's.
 * @return array Rows from the ports table for matching ports.
 */
function get_ports_from_type($given_types)
{
    // Make the arg an array if it isn't, so subsequent steps only have to handle arrays.
    if (! is_array($given_types)) {
        $given_types = [$given_types];
    }

    // Check the config for a '_descr' entry for each argument. This is how a 'custom_descr' entry can
    //  be key/valued to some other string that's actually searched for in the DB. Merge or append the
    //  configured value if it's an array or a string. Or append the argument itself if there's no matching
    //  entry in config.
    $search_types = [];
    foreach ($given_types as $type) {
        if (Config::has($type . '_descr')) {
            $type_descr = Config::get($type . '_descr');
            if (is_array($type_descr)) {
                $search_types = array_merge($search_types, $type_descr);
            } else {
                $search_types[] = $type_descr;
            }
        } else {
            $search_types[] = $type;
        }
    }

    // Using the full list of strings to search the DB for, build the 'where' portion of a query that
    //  compares 'port_descr_type' with entry in the list. Also, since '@' is the convential wildcard,
    //  replace it with '%' so it functions as a wildcard in the SQL query.
    $type_where = ' (';
    $or = '';
    $type_param = [];

    foreach ($search_types as $type) {
        if (! empty($type)) {
            $type = strtr($type, '@', '%');
            $type_where .= " $or `port_descr_type` LIKE ?";
            $or = 'OR';
            $type_param[] = $type;
        }
    }
    $type_where .= ') ';

    // Run the query with the generated 'where' and necessary parameters, and send it back.
    $ports = dbFetchRows("SELECT * FROM `ports` as I, `devices` AS D WHERE $type_where AND I.device_id = D.device_id ORDER BY I.ifAlias", $type_param);

    return $ports;
}

/**
 * @param  $filename
 * @param  $content
 */
function file_download($filename, $content)
{
    $length = strlen($content);
    header('Content-Description: File Transfer');
    header('Content-Type: text/plain');
    header("Content-Disposition: attachment; filename=$filename");
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: ' . $length);
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    header('Pragma: public');
    echo $content;
}

function get_rules_from_json()
{
    return json_decode(file_get_contents(resource_path('definitions/alert_rules.json')), true);
}

function search_oxidized_config($search_in_conf_textbox)
{
    if (! Auth::user()->hasGlobalRead()) {
        return false;
    }

    $oxidized_search_url = Config::get('oxidized.url') . '/nodes/conf_search?format=json';
    $postdata = http_build_query(
        [
            'search_in_conf_textbox' => $search_in_conf_textbox,
        ]
    );
    $opts = ['http' => [
        'method' => 'POST',
        'header' => 'Content-type: application/x-www-form-urlencoded',
        'content' => $postdata,
    ],
    ];
    $context = stream_context_create($opts);

    $nodes = json_decode(file_get_contents($oxidized_search_url, false, $context), true);
    // Look up Oxidized node names to LibreNMS devices for a link
    foreach ($nodes as &$n) {
        $dev = device_by_name($n['node']);
        $n['dev_id'] = $dev ? $dev['device_id'] : false;
        $n['full_name'] = $n['dev_id'] ? DeviceCache::get($n['dev_id'])->displayName() : $n['full_name'];
    }

    /*
    // Filter nodes we don't have access too
    $nodes = array_filter($nodes, function($device) {
        return \Permissions::canAccessDevice($device['dev_id'], Auth::id());
    });
    */

    return $nodes;
}

/**
 * @param  int  $eventlog_severity
 * @return string $eventlog_severity_icon
 */
function eventlog_severity($eventlog_severity)
{
    switch ($eventlog_severity) {
        case 1:
            return 'label-success'; //OK
        case 2:
            return 'label-info'; //Informational
        case 3:
            return 'label-primary'; //Notice
        case 4:
            return 'label-warning'; //Warning
        case 5:
            return 'label-danger'; //Critical
        default:
            return 'label-default'; //Unknown
    }
} // end eventlog_severity

function get_oxidized_nodes_list()
{
    $context = stream_context_create([
        'http' => [
            'header' => 'Accept: application/json',
        ],
    ]);

    $data = json_decode(file_get_contents(Config::get('oxidized.url') . '/nodes?format=json', false, $context), true);

    foreach ($data as $object) {
        $device = device_by_name($object['name']);
        if (! device_permitted($device['device_id'])) {
            //user cannot see this device, so let's skip it.
            continue;
        }
        try {
            // Convert UTC time string to local timezone set
            $utc_time = $object['time'];
            $utc_date = new DateTime($utc_time, new DateTimeZone('UTC'));
            $local_timezone = new DateTimeZone(date_default_timezone_get());
            $local_date = $utc_date->setTimezone($local_timezone);

            // Generate local time string
            $formatted_local_time = $local_date->format('Y-m-d H:i:s T');
        } catch (Exception $e) {
            // Just display the current value of $object['time'];
            $formatted_local_time = $object['time'];
        }
        echo '<tr>
        <td>' . $device['device_id'] . '</td>
        <td>' . $object['name'] . '</td>
        <td>' . $device['sysName'] . '</td>
        <td>' . $object['status'] . '</td>
        <td>' . $formatted_local_time . '</td>
        <td>' . $object['model'] . '</td>
        <td>' . $object['group'] . '</td>
        <td></td>
        </tr>';
    }
}

/**
 * Return stacked graphs information
 *
 * @param  string  $transparency  value of desired transparency applied to rrdtool options (values 01 - 99)
 * @return array containing transparency and stacked setup
 */
function generate_stacked_graphs($force_stack = false, $transparency = '88')
{
    if (Config::get('webui.graph_stacked') == true || $force_stack == true) {
        return ['transparency' => $transparency, 'stacked' => '1'];
    } else {
        return ['transparency' => '', 'stacked' => '-1'];
    }
}

/**
 * @params int unix time
 * @params int seconds
 *
 * @return int
 *
 * Rounds down to the nearest interval.
 *
 * The first argument is required and it is the unix time being
 * rounded down.
 *
 * The second value is the time interval. If not specified, it
 * defaults to 300, or 5 minutes.
 */
function lowest_time($time, $seconds = 300)
{
    return $time - ($time % $seconds);
}

/**
 * @params int
 *
 * @return string
 *
 * This returns the subpath for working with nfdump.
 *
 * 1 value is taken and that is a unix time stamp. It will be then be rounded
 * off to the lowest five minutes earlier.
 *
 * The return string will be a path partial you can use with nfdump to tell it what
 * file or range of files to use.
 *
 * Below ie a explanation of the layouts as taken from the NfSen config file.
 *  0             no hierachy levels - flat layout - compatible with pre NfSen version
 *  1 %Y/%m/%d    year/month/day
 *  2 %Y/%m/%d/%H year/month/day/hour
 *  3 %Y/%W/%u    year/week_of_year/day_of_week
 *  4 %Y/%W/%u/%H year/week_of_year/day_of_week/hour
 *  5 %Y/%j       year/day-of-year
 *  6 %Y/%j/%H    year/day-of-year/hour
 *  7 %Y-%m-%d    year-month-day
 *  8 %Y-%m-%d/%H year-month-day/hour
 */
function time_to_nfsen_subpath($time)
{
    $time = lowest_time($time);
    $layout = Config::get('nfsen_subdirlayout');

    if ($layout == 0) {
        return 'nfcapd.' . date('YmdHi', $time);
    } elseif ($layout == 1) {
        return date('Y\/m\/d\/\n\f\c\a\p\d\.YmdHi', $time);
    } elseif ($layout == 2) {
        return date('Y\/m\/d\/H\/\n\f\c\a\p\d\.YmdHi', $time);
    } elseif ($layout == 3) {
        return date('Y\/W\/w\/\n\f\c\a\p\d\.YmdHi', $time);
    } elseif ($layout == 4) {
        return date('Y\/W\/w\/H\/\n\f\c\a\p\d\.YmdHi', $time);
    } elseif ($layout == 5) {
        return date('Y\/z\/\n\f\c\a\p\d\.YmdHi', $time);
    } elseif ($layout == 6) {
        return date('Y\/z\/H\/\n\f\c\a\p\d\.YmdHi', $time);
    } elseif ($layout == 7) {
        return date('Y\-m\-d\/\n\f\c\a\p\d\.YmdHi', $time);
    } elseif ($layout == 8) {
        return date('Y\-m\-d\/H\/\n\f\c\a\p\d\.YmdHi', $time);
    }
}

/**
 * @params string hostname
 *
 * @return string
 *
 * Takes a hostname and transforms it to the name
 * used by nfsen.
 */
function nfsen_hostname($hostname)
{
    $nfsen_hostname = str_replace('.', Config::get('nfsen_split_char'), $hostname);

    if (! is_null(Config::get('nfsen_suffix'))) {
        $nfsen_hostname = str_replace(Config::get('nfsen_suffix'), '', $nfsen_hostname);
    }

    return $nfsen_hostname;
}

/**
 * @params string hostname
 *
 * @return string
 *
 * Takes a hostname and returns the path to the nfsen
 * live dir.
 */
function nfsen_live_dir($hostname)
{
    $hostname = nfsen_hostname($hostname);

    foreach (Config::get('nfsen_base') as $base_dir) {
        if (file_exists($base_dir) && is_dir($base_dir)) {
            return $base_dir . '/profiles-data/live/' . $hostname;
        }
    }
}

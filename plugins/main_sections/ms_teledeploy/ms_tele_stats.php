<?php
/*
 * Copyright 2005-2016 OCSInventory-NG/OCSInventory-ocsreports contributors.
 * See the Contributors file for more details about them.
 *
 * This file is part of OCSInventory-NG/OCSInventory-ocsreports.
 *
 * OCSInventory-NG/OCSInventory-ocsreports is free software: you can redistribute
 * it and/or modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation, either version 2 of the License,
 * or (at your option) any later version.
 *
 * OCSInventory-NG/OCSInventory-ocsreports is distributed in the hope that it
 * will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OCSInventory-NG/OCSInventory-ocsreports. if not, write to the
 * Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 */
require('require/function_stats.php');
require('require/charts/StatsChartsRenderer.php');

if ($_SESSION['OCS']['profile']->getConfigValue('TELEDIFF') == "YES" && is_defined($protectedPost["ACTION"])) {
    require('require/function_server.php');
    if ($protectedPost["ACTION"] == "VAL_SUCC") {
        $result_line_delete = find_device_line('SUCCESS%', $protectedGet["stat"]);
    }
    if ($protectedPost["ACTION"] == "DEL_ALL") {
        $result_line_delete = find_device_line('NOTNULL', $protectedGet["stat"]);
    }
    if ($protectedPost["ACTION"] == "DEL_NOT") {
        $result_line_delete = find_device_line('NULL', $protectedGet["stat"]);
    }

    if (isset($result_line_delete) && is_array($result_line_delete)) {
        require('require/function_telediff.php');
        foreach ($result_line_delete as $key => $value) {
            desactive_packet($value, $key);
        }
    }
}

$form_name = "show_stats";
$table_name = $form_name;
echo open_form($form_name, '', '', 'form-horizontal');

$sql = "SELECT name FROM download_available WHERE fileid='%s'";
$arg = $protectedGet["stat"];
$res = mysql2_query_secure($sql, $_SESSION['OCS']["readServer"], $arg);
$row = mysqli_fetch_object($res);
printEnTete($l->g(498) . " <b>" . $row->name . "</b> (" . $l->g(296) . ": " . $protectedGet["stat"] . " )");

//count max values for stats
$sql_count = "SELECT COUNT(id) as nb
			FROM devices d, download_enable e
			WHERE e.fileid='%s'
 				AND e.id=d.ivalue
				AND name='DOWNLOAD'
				AND hardware_id NOT IN (SELECT id FROM hardware WHERE deviceid='_SYSTEMGROUP_' or deviceid='_DOWNLOADGROUP_')";
$arg = $protectedGet["stat"];
$rescount = mysql2_query_secure($sql_count, $_SESSION['OCS']["readServer"], $arg);
$row = mysqli_fetch_object($rescount);
$total = $row->nb;
if ($total <= 0) {
    msg_error($l->g(837));
    require_once(FOOTER_HTML);
    die();
}
$sqlStats = "SELECT COUNT(id) as nb, tvalue as txt
				FROM devices d, download_enable e
				WHERE e.fileid='%s'
	 				AND e.id=d.ivalue
					AND name='DOWNLOAD'
					AND hardware_id NOT IN (SELECT id FROM hardware WHERE deviceid='_SYSTEMGROUP_' or deviceid='_DOWNLOADGROUP_')
					and tvalue not like '%s'
					and tvalue not like '%s'
					and tvalue is not null
					group by tvalue
			union
				SELECT COUNT(id) as nb, '%s'
				FROM devices d, download_enable e
				WHERE e.fileid='%s'
	 				AND e.id=d.ivalue
					AND name='DOWNLOAD'
					AND hardware_id NOT IN (SELECT id FROM hardware WHERE deviceid='_SYSTEMGROUP_' or deviceid='_DOWNLOADGROUP_')
					and (tvalue like '%s'
					or tvalue  like '%s')
			union
				SELECT COUNT(id) as nb, '%s'
				FROM devices d, download_enable e
				WHERE e.fileid='%s'
	 				AND e.id=d.ivalue
					AND name='DOWNLOAD'
					AND hardware_id NOT IN (SELECT id FROM hardware WHERE deviceid='_SYSTEMGROUP_' or deviceid='_DOWNLOADGROUP_')
					and tvalue is null";

$arg = array($arg, 'EXIT_CODE%', 'ERR%', $l->g(573), $arg, 'EXIT_CODE%', 'ERR%', $l->g(482), $arg);
$resStats = mysql2_query_secure($sqlStats . " ORDER BY nb DESC", $_SESSION['OCS']["readServer"], $arg);
$i = 0;
while ($row = mysqli_fetch_object($resStats)) {
    $txt_status = strtoupper($row->txt);
    $name_value[$i] = $txt_status;
    $pourc = round(($row->nb * 100) / $total, 2);
    $legend[$i] = $name_value[$i] . " (" . $pourc . "%)";
    if ($name_value[$i] == strtoupper($l->g(573))) {
        $link[$i] = "***" . $l->g(956) . "***";
    } else {
        $link[$i] = $name_value[$i];
    }
    $lbl[$i] = $name_value[$i] . "<br>(" . $pourc . "%)";
    $count_value[$i] = $row->nb;
    if (isset($arr_FCColors[$i])) {
        $color[$i] = $arr_FCColors[$i];
    } else {
        $color[$i] = $arr_FCColors[$i - 10];
    }
    $color[$i] = "plotProps: {fill: \"" . $color[$i] . "\"}";
    $i++;
}

$stats = new StatsChartsRenderer;
$stats->createChartCanvas("teledeploy_stats");
$stats->createPieChart("teledeploy_stats", "", $legend, $count_value);

if ($_SESSION['OCS']['profile']->getConfigValue('TELEDIFF') == "YES") {
    echo "<table class='Fenetre' align='center' border='1' cellpadding='5' width='50%'><tr BGCOLOR='#C7D9F5'>";
    echo "<td width='33%' align='center'><a OnClick='pag(\"VAL_SUCC\",\"ACTION\",\"" . $form_name . "\");'><b>" . $l->g(483) . "</b></a></td>";
    echo "<td width='33%' align='center'><a OnClick='pag(\"DEL_ALL\",\"ACTION\",\"" . $form_name . "\");'><b>" . $l->g(571) . "</b></a></td>";
    echo "<td width='33%' align='center'><a OnClick='pag(\"DEL_NOT\",\"ACTION\",\"" . $form_name . "\");'><b>" . $l->g(575) . "</b></a></td>";
    echo "</tr></table><br><br>";
    echo "<input type='hidden' id='ACTION' name='ACTION' value=''>";
}
echo "<table class='Fenetre' align='center' border='1' cellpadding='5' width='50%'>
<tr BGCOLOR='#C7D9F5'><td width='30px'>&nbsp;</td><td align='center'><b>" . $l->g(81) . "</b></td><td align='center'><b>" . $l->g(55) . "</b></td></tr>";
$j = 0;
while ($j < $i) {
    $nb += $count_value[$j];
    echo "<tr>";
    if (isset($arr_FCColors[$j])) {
        echo "<td bgcolor='" . $arr_FCColors[$j] . "'>";
    } else {
        echo "<td>";
    }
    echo "&nbsp;</td><td>" . $name_value[$j] . "</td><td>
			<a href='index.php?" . PAG_INDEX . "=" . $pages_refs['ms_multi_search'] . "&prov=stat&id_pack=" . $protectedGet["stat"] . "&stat=" . urlencode($link[$j]) . "'>" . $count_value[$j] . "</a>";
    if (substr_count($link[$j], 'SUC')) {
        echo "<a href=\"index.php?" . PAG_INDEX . "=" . $pages_refs['ms_speed_stat'] . "&head=1&ta=" . urlencode($link[$j]) . "&stat=" . $protectedGet["stat"] . "\">&nbsp<span class='glyphicon glyphicon-stats'></span></a>";
    }
    echo "	</td></tr>";
    $j++;
}
echo "<tr bgcolor='#C7D9F5'><td bgcolor='white'>&nbsp;</td><td><b>" . $l->g(87) . "</b></td><td><b>" . $nb . "</b></td></tr>";
echo "</table><br><br>";
echo close_form();
?>

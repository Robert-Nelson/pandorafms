<?php

// Pandora FMS - http://pandorafms.com
// ==================================================
// Copyright (c) 2005-2011 Artica Soluciones Tecnologicas
// Please see http://pandorafms.org for full contribution list

// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; version 2

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.


require_once ("include/config.php");

// This solves problems in enterprise load
global $config;

check_login ();

/* Call all extensions login function */
//extensions_call_login_function ();

require_once ("include/functions_reporting.php");
require_once ($config["homedir"] . '/include/functions_graph.php');

ui_print_page_header (__('Welcome to Pandora FMS Web Console'),'',false,"",false);

if (tags_has_user_acl_tags()) {
	ui_print_tags_warning();
}


?>
<table border="0" width="100%">
	<tr>
		
		<td width="25%" style="padding-right: 20px;" valign="top">
			
			
			<?php
			$data = reporting_get_group_stats ();
			
			///////////////
			// Overview Table
			///////////////
			
			$table = new stdClass();
			$table->class = "databox";
			$table->cellpadding = 4;
			$table->cellspacing = 4;
			$table->head = array ();
			$table->data = array ();
			$table->headstyle[0] = 'text-align:center;';
			$table->width = "100%";
			$table->head[0] = '<span>' . __('Pandora FMS Overview') . '</span>';
			$table->head_colspan[0] = 4;
			
			// Indicators
			$tdata = array();
			$stats = reporting_get_stats_indicators($data, 120, 10,false);
			$status = '<table class="status_tactical">';
			foreach ( $stats as $stat ) {
				$status .= '<tr><td><b>' . $stat['title'] . '</b>' . '</td><td>' . $stat['graph'] . "</td></tr>" ;
			}
			$status .= '</table>';
			$table->data[0][0] = $status;
			$table->rowclass[] = '';
			
			$table->data[] = $tdata;
			
			// Alerts
			$tdata = array();
			$tdata[0] = reporting_get_stats_alerts($data);
			$table->rowclass[] = '';
			$table->data[] = $tdata;
			
			// Modules by status
			$tdata = array();
			$tdata[0] = reporting_get_stats_modules_status($data,180, 100);
			$table->rowclass[] = '';
			$table->data[] = $tdata;
			
			// Total agents and modules
			$tdata = array();
			$tdata[0] = reporting_get_stats_agents_monitors($data);
			$table->rowclass[] = '';
			$table->data[] = $tdata;
			
			// Users
			$tdata = array();
			$tdata[0] = reporting_get_stats_users($data);
			$table->rowclass[] = '';
			$table->data[] = $tdata;
			
			html_print_table($table);
			unset($table);
			?>
			
			
		</td>
		
		<td width="75%" valign="top">
			
			
			<?php
			
			
			$options = array();
			$options['id_user'] = $config['id_user'];
			$options['modal'] = false;
			$options['limit'] = 3;
			$news = get_news($options);
			
			
			if (!empty($news)) {
				//////////////////NEWS BOARD/////////////////////////////
				echo '<div id="news_board">';
				
				echo '<table cellpadding="4" width=100% cellspacing="4" class="databox">';
				echo '<tr><th><span>' . __('News board') . '</span></th></tr>';
				if ($config["prominent_time"] == "timestamp") {
					$comparation_suffix = "";
				}
				else {
					$comparation_suffix = __('ago');
				}
				foreach ($news as $article) {
					$text = io_safe_output($article["text"]);
					
					
					echo '<tr><th><b>'.$article["subject"].'</b></th></tr>';
					echo '<tr><td>' . __('by') . ' <b>' .
						$article["author"] . '</b> <i>' . ui_print_timestamp ($article["timestamp"], true).'</i> ' . $comparation_suffix . '</td></tr>';
					echo '<tr><td class="datos">';
					echo nl2br($text);
					echo '</td></tr>';
				}
				echo '</table>';
				echo '</div>'; // News board
				
				
				
				echo '<br><br>';
				
				//////////////////END OF NEWS BOARD/////////////////////////////
			}
			
			//////////////////LAST ACTIVITY/////////////////////////////
			
			// Show last activity from this user
			echo '<div id="activity">';
			
			$table = new stdClass();
			$table->width = '100%'; //Don't specify px
			$table->data = array ();
			$table->size = array ();
			$table->size[2] = '150px';
			$table->size[3] = '130px';
			$table->size[5] = '200px';
			$table->head = array ();
			$table->head[0] = __('User');
			$table->head[1] = '';
			$table->head[2] = __('Action');
			$table->head[3] = __('Date');
			$table->head[4] = __('Source IP');
			$table->head[5] = __('Comments');
			$table->title = '<span>' . __('This is your last activity in Pandora FMS console') . '</span>';
			
			switch ($config["dbtype"]) {
				case "mysql":
					$sql = sprintf ("SELECT id_usuario,accion,fecha,ip_origen,descripcion,utimestamp
						FROM tsesion
						WHERE (`utimestamp` > UNIX_TIMESTAMP(NOW()) - " . SECONDS_1WEEK . ") 
							AND `id_usuario` = '%s' ORDER BY `utimestamp` DESC LIMIT 10", $config["id_user"]);
					break;
				case "postgresql":
					$sql = sprintf ("SELECT \"id_usuario\", accion, fecha, \"ip_origen\", descripcion, utimestamp
						FROM tsesion
						WHERE (\"utimestamp\" > ceil(date_part('epoch', CURRENT_TIMESTAMP)) - " . SECONDS_1WEEK . ") 
							AND \"id_usuario\" = '%s' ORDER BY \"utimestamp\" DESC LIMIT 10", $config["id_user"]);
					break;
				case "oracle":
					$sql = sprintf ("SELECT id_usuario, accion, fecha, ip_origen, descripcion, utimestamp
						FROM tsesion
						WHERE ((utimestamp > ceil((sysdate - to_date('19700101000000','YYYYMMDDHH24MISS')) * (" . SECONDS_1DAY . ")) - " . SECONDS_1WEEK . ") 
							AND id_usuario = '%s') AND rownum <= 10 ORDER BY utimestamp DESC", $config["id_user"]);
					break;
			}
			
			$sessions = db_get_all_rows_sql ($sql);
			
			if ($sessions === false)
				$sessions = array ();
			
			foreach ($sessions as $session) {
				$data = array ();
				
				switch ($config["dbtype"]) {
					case "mysql":
					case "oracle":
						$session_id_usuario = $session['id_usuario'];
						$session_ip_origen = $session['ip_origen'];
						break;
					case "postgresql":
						$session_id_usuario = $session['id_usuario'];
						$session_ip_origen = $session['ip_origen'];
						break;
				}
				
				
				$data[0] = '<strong>' . $session_id_usuario . '</strong>';
				$data[1] = ui_print_session_action_icon ($session['accion'], true);
				$data[2] = $session['accion'];
				$data[3] =  ui_print_help_tip($session['fecha'], true) . human_time_comparation($session['utimestamp'], 'tiny');
				$data[4] = $session_ip_origen;
				$data[5] = io_safe_output ($session['descripcion']);
				
				array_push ($table->data, $data);
			}
			echo "<div style='width:100%; overflow-x:auto;'>";
			html_print_table ($table);
			unset($table);
			echo "</div>";
			echo "</div>"; // activity
			
			//////////////////END OF LAST ACTIVIYY/////////////////////////////
			
			
			?>
			
			
		</td>
		
	</tr>
</table>
<?php
return;
// ---------------------------------------------------------------------
// Site news !
// ---------------------------------------------------------------------

//echo '<div id="left_column_logon_ok" id="leftcolumn">';
echo '<div style="width:30%; float:left;" id="leftcolumn">';


$data = reporting_get_group_stats ();

///////////////
// Overview Table
///////////////

$table->class = "databox";
$table->cellpadding = 4;
$table->cellspacing = 4;
$table->head = array ();
$table->data = array ();
$table->style[0] = 'text-align:center;';
$table->width = "100%";
$table->head[0] = '<span>' . __('Pandora FMS Overview') . '</span>';
$table->head_colspan[0] = 4;

// Indicators
$tdata = array();
$tdata[0] = reporting_get_stats_indicators($data);
$table->rowclass[] = '';

$table->data[] = $tdata;

// Alerts
$tdata = array();
$tdata[0] = reporting_get_stats_alerts($data);
$table->rowclass[] = '';
$table->data[] = $tdata;

// Modules by status
$tdata = array();
$tdata[0] = reporting_get_stats_modules_status($data);
$table->rowclass[] = '';
$table->data[] = $tdata;

// Total agents and modules
$tdata = array();
$tdata[0] = reporting_get_stats_agents_monitors($data);
$table->rowclass[] = '';
$table->data[] = $tdata;

// Users
$tdata = array();
$tdata[0] = reporting_get_stats_users($data);
$table->rowclass[] = '';
$table->data[] = $tdata;

html_print_table($table);
unset($table);





echo '</div>';


// ---------------------------------------------------------------------------
// Site stats (global!)
// ---------------------------------------------------------------------------
//echo '<div id="right_column_logon_ok" id="rightcolumn">';
echo '<div style="width: 50%; float:left;" id="rightcolumn">';

$options = array();
	$options['id_user'] = $config['id_user'];
	$options['modal'] = false;
	$options['limit'] = 3;
	$news = get_news($options);
	
	
	if (!empty($news)) {
		//////////////////NEWS BOARD/////////////////////////////
		echo '<div id="news_board">';
		
		echo '<table cellpadding="4" cellspacing="4" class="databox">';
		echo '<tr><th><span>' . __('News board') . '</span></th></tr>';
		if ($config["prominent_time"] == "timestamp") {
			$comparation_suffix = "";
		}
		else {
			$comparation_suffix = __('ago');
		}
		foreach ($news as $article) {
			$text = io_safe_output($article["text"]);
			
			
			echo '<tr><th><b>'.$article["subject"].'</b></th></tr>';
			echo '<tr><td>' . __('by') . ' <b>' .
				$article["author"] . '</b> <i>' . ui_print_timestamp ($article["timestamp"], true).'</i> ' . $comparation_suffix . '</td></tr>';
			echo '<tr><td class="datos">';
			echo nl2br($text);
			echo '</td></tr>';
		}
		echo '</table>';
		echo '</div>'; // News board
		
		
		
		echo '<br><br>';
		
		//////////////////END OF NEWS BOARD/////////////////////////////
	}
	
	//////////////////LAST ACTIVITY/////////////////////////////
	
	// Show last activity from this user
	echo '<div id="activity">';
	
	$table->width = '100%'; //Don't specify px
	$table->data = array ();
	$table->size = array ();
	$table->size[2] = '150px';
	$table->size[3] = '130px';
	$table->size[5] = '200px';
	$table->head = array ();
	$table->head[0] = __('User');
	$table->head[1] = '';
	$table->head[2] = __('Action');
	$table->head[3] = __('Date');
	$table->head[4] = __('Source IP');
	$table->head[5] = __('Comments');
	$table->title = '<span>' . __('This is your last activity in Pandora FMS console') . '</span>';
	
	switch ($config["dbtype"]) {
		case "mysql":
			$sql = sprintf ("SELECT id_usuario,accion,fecha,ip_origen,descripcion,utimestamp
				FROM tsesion
				WHERE (`utimestamp` > UNIX_TIMESTAMP(NOW()) - " . SECONDS_1WEEK . ") 
					AND `id_usuario` = '%s' ORDER BY `utimestamp` DESC LIMIT 10", $config["id_user"]);
			break;
		case "postgresql":
			$sql = sprintf ("SELECT \"id_usuario\", accion, fecha, \"ip_origen\", descripcion, utimestamp
				FROM tsesion
				WHERE (\"utimestamp\" > ceil(date_part('epoch', CURRENT_TIMESTAMP)) - " . SECONDS_1WEEK . ") 
					AND \"id_usuario\" = '%s' ORDER BY \"utimestamp\" DESC LIMIT 10", $config["id_user"]);
			break;
		case "oracle":
			$sql = sprintf ("SELECT id_usuario, accion, fecha, ip_origen, descripcion, utimestamp
				FROM tsesion
				WHERE ((utimestamp > ceil((sysdate - to_date('19700101000000','YYYYMMDDHH24MISS')) * (" . SECONDS_1DAY . ")) - " . SECONDS_1WEEK . ") 
					AND id_usuario = '%s') AND rownum <= 10 ORDER BY utimestamp DESC", $config["id_user"]);
			break;
	}
	
	$sessions = db_get_all_rows_sql ($sql);
	
	if ($sessions === false)
		$sessions = array ();
	
	foreach ($sessions as $session) {
		$data = array ();
		
		switch ($config["dbtype"]) {
			case "mysql":
			case "oracle":
				$session_id_usuario = $session['id_usuario'];
				$session_ip_origen = $session['ip_origen'];
				break;
			case "postgresql":
				$session_id_usuario = $session['id_usuario'];
				$session_ip_origen = $session['ip_origen'];
				break;
		}
		
		
		$data[0] = '<strong>' . $session_id_usuario . '</strong>';
		$data[1] = ui_print_session_action_icon ($session['accion'], true);
		$data[2] = $session['accion'];
		$data[3] =  ui_print_help_tip($session['fecha'], true) . human_time_comparation($session['utimestamp'], 'tiny');
		$data[4] = $session_ip_origen;
		$data[5] = io_safe_output ($session['descripcion']);
		
		array_push ($table->data, $data);
	}
	echo "<div style='width:100%; overflow-x:auto;'>";
	html_print_table ($table);
	unset($table);
	echo "</div>";
	echo "</div>"; // activity
	
	//////////////////END OF LAST ACTIVIYY/////////////////////////////
	


echo "</div>";
echo "<div style='clear:both'></div>";
?>

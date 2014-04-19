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


global $config;

$full_extensions_dir = $config['homedir'].DIRECTORY_SEPARATOR.EXTENSIONS_DIR.DIRECTORY_SEPARATOR;
require_once ($full_extensions_dir."files_repo".DIRECTORY_SEPARATOR."functions_files_repo.php");

$offset = (int) get_parameter('offset');
$filter = array();
$filter['limit'] = $config['block_size'];
$filter['offset'] = $offset;
$filter['order'] = array('field' => 'id', 'order' => 'DESC');
$files = files_repo_get_files($filter);

if (!empty($files)) {

	echo "<br>";

	// Pagination
	if ($manage) {
		$url = ui_get_full_url("index.php?sec=gextensions&sec2=extensions/files_repo");
	} else {
		$url = ui_get_full_url("index.php?sec=extensions&sec2=extensions/files_repo");
	}
	$total_files = files_repo_get_files(false, true);
	ui_pagination($total_files, $url, $offset);

	$table = new stdClass();
	$table->width = '98%';
	$table->style = array();
	$table->style[1] = "max-width: 200px;";
	$table->style[2] = "text-align: center;";
	$table->style[3] = "text-align: center;";
	$table->style[4] = "text-align: center;";
	$table->head = array();
	$table->head[0] = __("Name");
	$table->head[1] = __("Description");
	$table->head[2] = __("Size");
	$table->head[3] = __("Last modification");
	$table->head[4] = "";
	$table->data = array();

	foreach ($files as $file_id => $file) {
		$data = array();

		// Prepare the filename for the get_file.php script
		$relative_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', $file['location']);
		$file_path = base64_encode($relative_path);
		$hash = md5($relative_path . $config['dbpass']);
		$url = ui_get_full_url("include/get_file.php?file=$file_path&hash=$hash");

		$data[0] = "<a href=\"$url\" target=\"_blank\">" . $file['name'] . "</a>"; // Name
		$data[1] = ui_print_truncate_text($file['description'], 'description', true, true); // Description
		$data[2] = ui_format_filesize($file['size']); // Size
		$data[3] = date('F j, Y - H:m', $file['mtime']); // Last modification

		// Public URL
		if (!empty($file['hash'])) {
			$public_url = ui_get_full_url(EXTENSIONS_DIR . "/files_repo/files_repo_get_file.php?file=" . $file['hash']);
			$message = __('Copy to clipboard') . ": Ctrl+C -> Enter";
			$action = "window.prompt('$message', '$public_url');";
			$data[4] .= "<a href=\"javascript:;\" onclick=\"$action\">";
			$data[4] .= html_print_image('images/world.png', true, array('title' => __('Public link'))); // Public link image
			$data[4] .= "</a> ";
		}

		$data[4] .= "<a href=\"$url\" target=\"_blank\">";
		$data[4] .= html_print_image('images/download.png', true, array('title' => __('Download'))); // Download image
		$data[4] .= "</a>";

		if ($manage) {

			$config_url = ui_get_full_url("index.php?sec=gextensions&sec2=extensions/files_repo&file_id=$file_id");
			$data[4] .= " <a href=\"$config_url\">";
			$data[4] .= html_print_image('images/config.png', true, array('title' => __('Edit'))); // Edit image
			$data[4] .= "</a>";

			$delete_url = ui_get_full_url("index.php?sec=gextensions&sec2=extensions/files_repo&delete=1&file_id=$file_id");
			$data[4] .= " <a href=\"$delete_url\" onClick=\"if (!confirm('".__('Are you sure?')."')) return false;\">";
			$data[4] .= html_print_image('images/cross.png', true, array('title' => __('Delete'))); // Delete image
			$data[4] .= "</a>";
		}
		$table->data[] = $data;
	}
	html_print_table($table);

} else {
	ui_print_info_message(__('No items'));
}

?>
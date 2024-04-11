<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Local lib for quizaccess_videocapture plugin.
 *
 * @package    quizaccess_videocapture
 * @copyright  2023 Abaco Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 */


defined('MOODLE_INTERNAL') || die();

function quizaccess_videocapture_mktempdir($dir, $prefix='') {
	global $CFG;

	if (substr($dir, -1) != '/') {
		$dir .= '/';
	}

	do {
		$path = $dir.$prefix.mt_rand(0, 9999999);
	} while (file_exists($path));

	check_dir_exists($path);

	return $path;
}



function quizaccess_videocapture_decode_image($data){
	$data = str_replace(" ", "+", $data);
	list($type, $data) = explode(';', $data);
	list(, $data)      = explode(',', $data);
	$imagedata = base64_decode($data);
	return $imagedata;
}

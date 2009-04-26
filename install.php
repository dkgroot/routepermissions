<?php
/* $Id$ */

// Original Release by Rob Thomas (xrobau@gmail.com)
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of version 2 of the GNU General Public
// License as published by the Free Software Foundation.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

global $db;
global $amp_conf;

echo "Running\n";

if (! function_exists("out")) {
	function out($text) {
		echo $text."<br />";
	}
}

if (! function_exists("outn")) {
	function outn($text) {
		echo $text;
	}
}

// create the tables
$sql = "CREATE TABLE IF NOT EXISTS routepermissions (
  exten int(11) NOT NULL,
  routename varchar(25) NOT NULL,
  allowed varchar(3) default 'YES',
  KEY idx_exten (exten)
);";

$check = $db->query($sql);
if (DB::IsError($check)) {
        die_freepbx( "Can not create `routepermissions` table: " . $check->getMessage() .  "\n");
}

// Check to see if there's data in the table allready - if so, don't touch.
// FIXME - todo.

// If there's not, propogate all extensions and all trunks with YES permissions

$sql = "SELECT extension FROM users ORDER BY extension";
     $extns = $db->getAll($sql);
$sql = "SELECT DISTINCT context FROM extensions WHERE context LIKE 'outrt%';";
     $routes = $db->getAll($sql);

foreach($extns as $ext) {
	foreach ($routes as $r) {
                $rn = substr($r[0], 10);
		$db->query("INSERT INTO routepermissions (exten, routename, allowed) VALUES ('$ext[0]', '$rn', 'YES');");
	}
}

?>

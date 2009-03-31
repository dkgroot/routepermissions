<?php 
/* $Id$ */

// Original Release by Rob Thomas (xrobau@gmail.com)
// Copyright Rob Thomas (2009)
//
// Released under the GPL V2 Licence (only)
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of version 2 of the GNU General Public
// License as published by the Free Software Foundation.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.





$tabindex = 0;
// What are we doing..
isset($_REQUEST['action'])?$action = $_REQUEST['action']:$action='';

// Where we are
$dispnum = "routepermissions"; //used for switch on config.php


?>

</div> <!-- end content div -->

<div class="content">
<?php
	global $dispnum;
?>
	<h2 id='title'><?php echo _("Route Permissions") ?></h2></td>
<?php 
	// Has something been submitted?
	if(isset($_POST['action'])) {
		// Figure out which button has been pushed.
		foreach ($_REQUEST as $r=>$val) {
                	if (!strncmp($r, "on_", 3)) {
				$route=substr($r,3);
				print "<br>You want to ALLOW $route<br>\n";
				rp_allow($route, $_REQUEST["range_$route"]);
                	}
                	if (!strncmp($r, "off_", 3)) {
				$route=substr($r,4);
				print "<br>You want to DENY $route<br>\n";
				rp_deny($route, $_REQUEST["range_$route"]);
                	}
		}
	}
?>
  
	<tr><td colspan=2><span id="instructions">
<?php
	echo "<p><h3>"._("Bulk Changes"); echo "</h3></p> ";
	echo "<p>"._("Select a route and press Allow or Deny to set all extensions. ");
	echo  _("You can enter any normal range - comma or hyphen seperated. For example '123,125,200-300' will select extensions 123, 125 and any extensions between 200 and 300.");
	echo "</p>\n ";
	echo "<p>"._("Note that there is NO UNDO and changes take effect IMMEDATELY. Be cautious.")."</p>";
	

	echo '<form autocomplete="off" name="edit" action="'.$_SERVER['PHP_SELF'].'" method="post">';
	echo "<input type=\"hidden\" name=\"display\" value=\"{$dispnum}\">\n";
	echo "<input type=\"hidden\" name=\"action\" value=\"edit\">\n";
               
	$routes = rp_get_routes();

	echo "<table>\n";
	foreach ($routes as $r) {
		print "<tr><td>$r</td> <td><input type='text' size=15 name='range_$r' value='All' tabindex='++$tabindex'></td>";
		print "<td><input type=submit name=on_$r value=Allow></td><td><input type=submit name=off_$r value=Deny></td></tr>";
	}
	echo "</table>\n";


function rp_allow($route, $range) {
	$rangearray = rp_range($range);
	$extens = rp_get_extens();
	foreach ($rangearray as $r) {
		if ($extens[$r] == "ok") {
			rp_do($route, $r, "YES");
		}
	}
}

function rp_deny($route, $range) {
	$rangearray = rp_range($range);
	$extens = rp_get_extens();
	foreach ($rangearray as $r) {
		if ($extens[$r] === "ok") {
			rp_do($route, $r, "NO");
		}
	}
}

function rp_do($route, $ext, $perm) {
	global $db;
	$Sext = mysql_real_escape_string($ext);
	$Sroute = mysql_real_escape_string($route);
	sql("DELETE FROM routepermissions WHERE exten='$Sext' AND routename='$Sroute'");
	sql("INSERT INTO routepermissions (exten, routename, allowed) VALUES ('$Sext', '$Sroute', '$perm')");
}

function rp_range($range_str) {
	$range_out = array();
	// Strip spaces
	$ranges = explode(",", str_replace(" ", "", $range_str));

	foreach($ranges as $range) {
		if(is_numeric($range)) {
			// Just a number; add it to the list.
			$range_out[] = $range;
			$last_num = $range;
		} else if(is_string($range)) {
			if (preg_match("/(\d+)-(\d+)/", $range, $selection)) {
				$start = $selection[1];
				$end = $selection[2];
	
				if($start > $end) {
					for($i = $start; $i >= $end; $i--) {
						$range_out[] = $i;
					}
				} else {
					for($i = $start; $i <= $end; $i++) {
						$range_out[] = $i;
					}
				}
			}
		}
	}
	return $range_out;
}

function rp_get_extens() {
	global $db;
     	$extens = core_users_list();
	foreach ($extens as $e) {
		$ret[$e[0]]="ok";
	}
	return $ret;
}
?>

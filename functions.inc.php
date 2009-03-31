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



// This MUST be "TheNameOfTheModule_configpageinit" as it's loaded automatically
function routepermissions_configpageinit($pagename) {
        global $currentcomponent;

        $action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
        $extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
        $extension = isset($_REQUEST['extension'])?$_REQUEST['extension']:null;
        $tech_hardware = isset($_REQUEST['tech_hardware'])?$_REQUEST['tech_hardware']:null;

        // We only want to hook 'users' or 'extensions' pages.
        if ($pagename != 'users' && $pagename != 'extensions')
                return true;
        // On a 'new' user, 'tech_hardware' is set, and there's no extension. Hook into the page.
        if ($tech_hardware != null || $pagename == 'users') {
                rp_applyhooks();
                $currentcomponent->addprocessfunc('rp_configprocess', 5);
        } elseif ($action=="add") {
                // We don't need to display anything on an 'add', but we do need to handle returned data.
                $currentcomponent->addprocessfunc('rp_configprocess', 5);
        } elseif ($extdisplay != '') {
                // We're now viewing an extension, so we need to display _and_ process.
                rp_applyhooks();
                $currentcomponent->addprocessfunc('rp_configprocess', 5);
        }
}

function rp_applyhooks() {
        global $currentcomponent;

        // Add Allow/Deny options
        $currentcomponent->addoptlistitem('rpyn', 'YES', _('yes'));
        $currentcomponent->addoptlistitem('rpyn', 'NO', _('no'));
        $currentcomponent->setoptlistopts('rpyn', 'sort', false);

        // Add the 'proces' function
        $currentcomponent->addguifunc('rp_configpageload');
}

function rp_configpageload() {
        global $currentcomponent;

        // Init vars from $_REQUEST[]
        $action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
        $extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;

        // Don't display if this is a 'This xtn has been deleted' page.
        if ($action != 'del') {
                $section = _('Outbound Route Permssions');
		$routes = rp_get_routes();
		foreach ($routes as $route) {
			$currentcomponent->addguielem($section, new gui_radio("rp_$route", $currentcomponent->getoptlist('rpyn'), rp_get_perm($extdisplay,$route), $route, "" , null));
		}
	}
}

function rp_configprocess() {
	// Extract any variables from $REQUEST that start with rp_
        $action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
        $extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;

	foreach ($_REQUEST as $r=>$val) {
		if (!strncmp($_REQUEST[$r], "rp_", 3)) {
			$rps[substr($r, 3)]=$val;
		}
	}
        //if submitting form, update database
        switch ($action) {
                case "add":
                case "edit":
		rp_purge_ext($extdisplay);
		rp_set_perm($extdisplay, $rps);
                break;
                case "del":
		rp_purge_ext($extdisplay);
                break;
        }
}



function rp_get_perm($ext, $route) {
	global $db;
	$Sext = mysql_real_escape_string($ext);
	$Sroute = mysql_real_escape_string($route);
	$sql = "SELECT allowed FROM routepermissions WHERE routename='$Sroute' AND exten='$Sext'";
	$res = $db->getRow($sql);
	if (PEAR::isError($res)) { die($res->getMessage()); }
	if (isset($res[0])) {
		return $res[0];
	} else {
		return "YES";
	}
}

function rp_set_perm($ext, $rps) {
	global $db;
	$Sext = mysql_real_escape_string($ext);
	foreach($rps as $r=>$p) {
		$val = explode("=", $p);
		$Sr =mysql_real_escape_string($r);
		$Sval =mysql_real_escape_string($val[1]);
		$sql = "INSERT INTO routepermissions (exten, routename, allowed) VALUES ('$Sext', '$Sr', '$Sval')";
		sql($sql);
	}
}

function rp_purge_ext($ext) {
	global $db;
	$Sext = mysql_real_escape_string($ext);
	$sql = "DELETE FROM routepermissions WHERE exten='$Sext'";
	sql($sql);
}
	
	

// When outbound routes is rewritten to no longer use the 'extensions' database, the following function will need to be changed

function rp_get_routes() {
	global $db;
	$sql = "SELECT DISTINCT context FROM extensions WHERE context LIKE 'outrt%';";
	$res = $db->getAll($sql);
	foreach ($res as $r) {
		$pieces = explode("-", $r[0]);
		$arr[] = $pieces[2];
	} 
	return $arr;
}
	
?>

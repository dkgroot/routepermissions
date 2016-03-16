<?php
// Based on an original Release by Rob Thomas (xrobau@gmail.com)
// Copyright Rob Thomas (2009)
// Extensive modifications by Michael Newton (miken32@gmail.com)
// Copyright 2016 Michael Newton
/*
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as
    published by the Free Software Foundation, either version 3 of the
    License, or (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

class RoutepermissionsLegacy {
    public function __construct()
    {
        global $db;
        $this->db = $db;
    }

    public function getRoutes()
    {
        $sql = "SELECT DISTINCT name FROM outbound_routes JOIN outbound_route_sequence USING (route_id) ORDER BY seq";
        $result = $this->db->getCol($sql);
        return $result;
    }

    public function getDefaultDest()
    {
        $sql = "SELECT faildest FROM routepermissions where exten = -1 LIMIT 1";
        $result = $this->db->getOne($sql);
        return $result;
    }

    public function updateDefaultDest($dest)
    {
        $sql = "DELETE FROM routepermissions WHERE exten = -1";
        $this->db->query($sql);
        if (DB::isError($res)) {
            return false;
        }
        if (!empty($dest)) {
            $sql = "INSERT INTO routepermissions (exten, routename, faildest, prefix) VALUES ('-1', 'default', ?, '')";
            $res = $this->db->query($sql, array($dest));
            if (DB::isError($res)) {
                return false;
            }
        }
        return true;
    }

    public function setRangePermissions($route, $range, $allowed, $faildest = "", $prefix = "") {
        $allowed = (strtoupper($allowed) === "NO") ? "NO" : "YES";
        $extens = array_intersect(
            $sys_ext = array_map(function($u) {return $u[0];}, core_users_list()),
            $ext_range = (strtoupper($range) === strtoupper(_("All"))) ? $sys_ext : self::getRange($range)
        );
        if (count($extens) === 0) {
            return false;
        }
        $sql = "DELETE FROM routepermissions WHERE exten=? AND routename=?";
        $stmt1 = $this->db->prepare($sql);
        $sql = "INSERT INTO routepermissions (exten, routename, allowed, faildest, prefix) VALUES (?, ?, ?, ?, ?)";
        $stmt2 = $this->db->prepare($sql);
        foreach ($extens as $ext) {
            $res = $this->db->execute($stmt1, array($ext, $route));
            if (DB::isError($res) || $res === false) {
                return $res;
            }
            $res = $this->db->execute($stmt2, array($ext, $route, $allowed, $faildest, $prefix));
            if (DB::isError($res) || $res === false) {
                return $res;
            }
        }
        return true;
    }

    private static function getRange($range_str) {
        $range_out = array();
        // Strip spaces
        $ranges = explode(",", str_replace(" ", "", $range_str));

        foreach($ranges as $range) {
            if (is_numeric($range)) {
                // Just a number; add it to the list.
                $range_out[] = $range;
            } elseif (strpos($range, "-")) {
                list($start, $end) = explode("-", $range);
                if (is_numeric($start) && is_numeric($end) && $start < $end) {
                    for ($i = $start; $i <= $end; $i++) {
                        $range_out[] = $i;
                    }
                }
            }
        }
        return array_unique($range_out, SORT_NUMERIC);
    }
}

/**
 * Runs on reload to modify dialplan
 */
function routepermissions_hookGet_config($engine)
{
    global $ext;
    global $version;

    if ($engine !== "asterisk") {
        return false;
    }

    foreach ($ext->_exts as $context=>$extensions) {
        if (strncmp($context, "macro-dialout-", 14) === 0) {
            $ext->splice($context, "s", 1, new ext_agi("checkperms.agi"));
            $ext->add($context, "barred", 1, new ext_noop("Route administratively banned for this user."));
            $ext->add($context, "barred", 2, new ext_hangup());
            $ext->add($context, "reroute", 1, new ext_goto("1", "\${ARG2}", "from-internal"));
        }
    }

    // Insert the ROUTENAME into each route
    $names = core_routing_list();
    foreach ($names as $name) {
        $context = "outrt-$name[route_id]";
        $routename = $name["name"];
        $routes = core_routing_getroutepatternsbyid($name["route_id"]);
        foreach ($routes as $rt) {
            $extension = $rt["match_pattern_prefix"] . $rt["match_pattern_pass"];
            // If there are any wildcards in there, add a _ to the start
            if (preg_match("/\.|z|x|\[|\]/i", $extension)) {
                $extension = "_".$extension;
            }
            $ext->splice($context, $extension, 1, new ext_setvar("__ROUTENAME", $routename));
        }
    }
}

/**
 * Runs when config.php loads a page up, used to modify extensions page
 */
function routepermissions_configpageinit($pagename)
{
    global $currentcomponent;

    if ($pagename === "extensions") {
        $currentcomponent->addguifunc("routepermissions_configpageload");
        $currentcomponent->addprocessfunc("routepermissions_configpageprocess", 8);
    }
}

/**
 * Adds some form inputs to the extension page
 */
function routepermissions_configpageload()
{
    global $db;
    global $currentcomponent;

    $rp = new RoutepermissionsLegacy;
    $pagename = isset($_REQUEST["display"]) ? $_REQUEST["display"] : "index";
    $extdisplay = isset($_REQUEST["extdisplay"]) ? $_REQUEST["extdisplay"] : null;
    $i = 0;

    $section = _("Outbound Route Permissions");
    if (
        $pagename !== "extensions" ||
        (isset($_REQUEST["action"]) && $_REQUEST["action"] === "del") ||
        empty($extdisplay)
    ) {
        return false;
    }

    $html = "";
    $routes = $rp->getRoutes();
    $stmt = $db->prepare("SELECT allowed, faildest, prefix FROM routepermissions WHERE routename = ? AND exten = ?");
    foreach ($routes as $route) {
        // in 13 this is PDO, returns boolean; in 11 it's PEAR, returns a result object
        $res = $db->execute($stmt, array($route, $extdisplay));
        if (DB::isError($res) || $res === false) {
            continue;
        } elseif ($res === true) {
            list($allowed, $faildest, $prefix) = $stmt->fetch(PDO::FETCH_NUM);
        } elseif ($res->numRows() === 1) {
            list($allowed, $faildest, $prefix) = $res->fetchRow();
        } else {
            $allowed = "YES";
            $faildest = "";
        }
        if ($allowed === "NO" && !empty($prefix)) {
            $allowed = "REDIRECT";
        }
        $route = htmlspecialchars($route);
        $yes = _("Allow");
        $no = _("Deny");
        $redirect = _("Redirect w/prefix");
        $i += 10;
        $js = '$("input[name=" + this.name.replace(/^routepermissions_perm_(\d+)-(.*)$/, "routepermissions_prefix_$1-$2") + "]").val("").prop("disabled", (this.value !== this.name + "=REDIRECT")).prop("required", (this.value === this.name + "=REDIRECT"));var id=$("select[name=" + $("#" + this.name.replace(/^routepermissions_perm_(\d+)-(.*)$/, "routepermissions_faildest_$1-$2")).val() + "]").val("").change().prop("disabled", (this.value !== this.name + "=NO")).data("id");$("select[data-id=" + id + "]").prop("disabled", (this.value !== this.name + "=NO"))';
        $radio = new gui_radio(
            "routepermissions_perm_$i-$route", //element name
            array(
                array("value"=>"YES", "text"=>$yes),
                array("value"=>"NO", "text"=>$no),
                array("value"=>"REDIRECT", "text"=>$redirect),
            ), //radios
            $allowed, //current value
            sprintf(_("Allow access to %s"), $route), //group label text
            "", //help text
            false, //disable
            htmlspecialchars($js), //onclick (13+)
            "", //class (13+)
            true //paired values; needed for 11 compatibility (13+)
        );
        $currentcomponent->addguielem($section, $radio);

        $selects = new gui_drawselects(
            "routepermissions_faildest_$i-$route", //element name
            $i, //index
            $faildest, //current value
            sprintf(_("Failure destination for %s"), $route), //label text
            "", //help text
            false, //can be empty
            "", //fail validation message
            _("Use default"), //empty message
            ($allowed !== "NO"), //disable (13+)
            "" //class (13+)
        );
        $currentcomponent->addguielem($section, $selects);

        $installed_ver = getVersion();
        $newer = version_compare_freepbx($installed_ver, "2.12","gt");

        $input = new gui_textbox(
            "routepermissions_prefix_$i-$route", //element name
            $prefix, //current value
            sprintf(_("Redirect prefix for %s"), $route), //label text
            "", //help text
            "", //js validation
            "", //validation failure msg
            true, //can be empty
            0, //maxchars
            ($newer && $allowed !== "REDIRECT"), // disable; no way to re-enable in 11/12
            false, //input group (13+)
            "", //class (13+)
            true //autocomplete (13+)
        );
        $currentcomponent->addguielem($section, $input);
    }
}

/**
 * Runs when config.php is POSTed
 */
function routepermissions_configpageprocess()
{
    global $db;

    $action = isset($_POST['action']) ? $_POST['action'] : null;
    $extdisplay = isset($_POST["extdisplay"]) ? $_POST["extdisplay"] : null;
    $route_perms = array();
    if (empty($extdisplay) || empty($action)) {
        return false;
    }

    foreach ($_POST as $k=>$v) {
        if (!preg_match("/routepermissions_(faildest|perm|prefix)_\d+-(.*)/", $k, $matches)) {
            continue;
        }
        $route_name = $matches[2];
        if (!isset($route_perms[$route_name])) {
            $route_perms[$route_name] = array("faildest"=>null, "perms"=>"YES", "prefix"=>null);
        }
        switch ($matches[1]) {
            case "faildest":
                $faildest_index = substr($v, 4); // remove "goto" from value
                $faildest_type = isset($_POST[$v]) ? $_POST[$v] : null;
                if ($faildest_type && isset($_POST["$faildest_type$faildest_index"])) {
                    $route_perms[$route_name]["faildest"] = $_POST["$faildest_type$faildest_index"];
                }
                break;
            case "perm":
                list($foo, $perm) = explode("=", $v, 2);
                $route_perms[$route_name]["perms"] = $perm;
                break;
            case "prefix":
                $route_perms[$route_name]["prefix"] = $v;
                break;
        }
    }
    if (count($route_perms) === 0) {
        return false;
    }

    switch ($action) {
        case "add":
        case "edit":
            $result = $db->query("DELETE FROM routepermissions WHERE exten = ?", $extdisplay);
            $stmt = $db->prepare("INSERT INTO routepermissions (exten, routename, allowed, faildest, prefix) VALUES(?, ?, ?, ?, ?)");
            foreach($route_perms as $route_name=>$data) {
                if ($data["perms"] === "REDIRECT") {
                    $data["faildest"] = null;
                    $data["perms"] = "NO";
                    if (empty($data["prefix"])) {
                        $data["prefix"] = null;
                    }
                } elseif ($data["perms"] === "NO") {
                    $data["prefix"] = null;
                } else {
                    $data["perms"] = "YES";
                    $data["faildest"] = $data["prefix"] = null;
                }
                $res = $db->execute($stmt, array(
                    $extdisplay,
                    $route_name, 
                    $data["perms"],
                    $data["faildest"],
                    $data["prefix"],
                ));
            }
            break;
        case "del":
            $db->query("DELETE FROM routepermissions WHERE exten = ?", $extdisplay);
            break;
    }
}

?>

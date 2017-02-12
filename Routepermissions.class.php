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

class Routepermissions extends \FreePBX\FreePBX_Helpers implements \FreePBX\BMO
{
    /** Constructor code for version 13+
     * @param Object $freepbx The FreePBX object
     * @return void
     */
    public function __construct($freepbx = null)
    {
        if ($freepbx === null) {
            die("That ain't gonna fly!");
        }
        $this->FreePBX = $freepbx;
        $this->db = $freepbx->Database;
    }

    /**
     * Show right navigation for version 13+
     * @param array $request The contents of $_REQUEST
     * @return string The right nav HTML
     */
    public function getRightNav($request)
    {
        return "";
    }

    /**
     * Show floating save buttons for version 13+
     * @param array $request The contents of $_REQUEST
     * @return array Associative array including name, id, and value for each button
     */
    public function getActionBar($request)
    {
        return array();
    }

    /**
     * Ajax request check for version 13+; confirm command is okay and optionally pass some settings
     * @param string $command The command name
     * @param string $setting Settings to return back
     * @return boolean
     */
    public function ajaxRequest($command, &$setting){
        return method_exists(get_called_class(), $command);
    }

    /**
     * Handle the ajax request for version 13+, passed in $_REQUEST["command"]
     * @return mixed The result of the command
     */
    public function ajaxHandler()
    {
        $request = $_REQUEST;
        $command = isset($_REQUEST["command"]) ? $_REQUEST["command"] : "";
        if (method_exists(get_called_class(), $command)) {
            $return = self::$command($request);
            if ($return) {
                return $return;
            } else {
                return array("status"=>false, "message"=>sprintf(_("Command %s failed"), $command));
            }
        }
        return array("status"=>false, "message"=>_("Unknown command"));
    }

    /**
     * Handle searches for version 13+
     * @param string $query The search itself
     * @param array $results The results by reference
     * @return boolean
     */
    public function search($query = null, &$results = null)
    {
        return false;
    }

    public function install()
    {
        return;
    }

    public function uninstall()
    {
        return;
    }

    public function backup()
    {
        return;
    }

    public function restore($backup)
    {
        return;
    }

    public function genConfig()
    {
        return;
    }

    public function writeConfig()
    {
        return;
    }

    /**
     * Tell the system we want to hook into the dialplan
     */
    public function myDialplanHooks()
    {
        // signal our intent to hook into the dialplan
        return true;
    }

    /**
     * The actual dialplan hook
     */
    public function doDialplanHook(&$ext, $engine, $pri)
    {
        if ($engine !== "asterisk") {
            return false;
        }

        foreach ($ext->_exts as $context=>$extensions) {
            if (strncmp($context, "macro-dialout-", 14) === 0) {
                $ext->splice($context, "s", 1, new \ext_agi("checkperms.agi"));
                $ext->add($context, "barred", 1, new \ext_noop("Route administratively banned for this user."));
                $ext->add($context, "barred", 2, new \ext_hangup());
                $ext->add($context, "reroute", 1, new \ext_goto("1", "\${ARG2}", "from-internal"));
            }
        }

        // Insert the ROUTENAME into each route
        foreach (\FreePBX::Core()->getAllRoutes() as $route) {
            $name = $route["name"];
            $context = "outrt-$name[route_id]";
            $routename = $name["name"];
            $routes = core_routing_getroutepatternsbyid($name["route_id"]);
            foreach ($routes as $rt) {
                $extension = $rt["match_pattern_prefix"] . $rt["match_pattern_pass"];
                // If there are any wildcards in there, add a _ to the start
                if (preg_match("/\.|z|x|\[|\]/i", $extension)) {
                    $extension = "_".$extension;
                }
                $ext->splice($context, $extension, 1, new \ext_setvar("__ROUTENAME", $routename));
            }
        }
    }

    /**
     * Tell the system which modules' pages we want to hook (hooked)
     *
     * @return array All the modules we want to hook into
     */
    public static function myGuiHooks() {
        // extensions page is part of core.
        return ["core"];
    }

    /**
     * Perform our hook actions on page display (hooked)
     *
     * @param Object $currentcomponent The ugly old page object
     * @param string $module The module name
     * @return boolean Returns false if the page is not modified
     */
    public function doGuiHook(&$currentcomponent, $module) {
        $pagename   = isset($_REQUEST["display"]) ? $_REQUEST["display"] : "";
        $extdisplay = isset($_REQUEST["extdisplay"]) ? $_REQUEST["extdisplay"] : "";
        $action     = isset($_REQUEST["action"]) ? $_REQUEST["action"] : "";
        $i          = 0;

        if (
            $module !== "core" || $action === "del" || empty($extdisplay) ||
            ($pagename !== "extensions" && $pagename !== "users")
        ) {
            return false;
        }

        $routes = $this->getRoutes();
        try {
            $stmt = $db->prepare("SELECT allowed, faildest, prefix FROM routepermissions WHERE routename = ? AND exten = ?");
        } catch (\PDOException $e) {
            return false;
        }
        foreach ($routes as $route) {
            try {
                $stmt->execute(array($route, $extdisplay));
                $res = $stmt->fetch(\PDO::FETCH_NUM);
            } catch (\PDOException $e) {
                continue;
            }
            if (is_array($res) && count($res) > 0) {
                // a result was returned
                list($allowed, $faildest, $prefix) = $res;
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
            $radio = new \gui_radio(
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

            $selects = new \gui_drawselects(
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

            $input = new \gui_textbox(
                "routepermissions_prefix_$i-$route", //element name
                $prefix, //current value
                sprintf(_("Redirect prefix for %s"), $route), //label text
                "", //help text
                "", //js validation
                "", //validation failure msg
                true, //can be empty
                0, //maxchars
                ($allowed !== "REDIRECT"), // disable; no way to re-enable in 11/12
                false, //input group (13+)
                "", //class (13+)
                true //autocomplete (13+)
            );
            $currentcomponent->addguielem($section, $input);
        }
    }

    /**
     * Tell the system which pages' POSTs we want to hook (hooked)
     *
     * @return array All the pages (not modules) we want to hook into
     */
    public static function myConfigPageInits() {
        return ["extensions", "users"];
    }

    /**
     * Perform our hook action on page POST (hooked)
     *
     * @param string $module The module name
     * @return boolean Returns false if there's nothing to do
     */
    public function doConfigPageInit($module)
    {
        $pagename    = isset($_REQUEST["display"]) ? $_REQUEST["display"] : "index";
        $extdisplay  = isset($_REQUEST["extdisplay"]) ? $_REQUEST["extdisplay"] : null;
        $action      = isset($_REQUEST["action"]) ? $_REQUEST["action"] : null;
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
                try {
                    $stmt = $this->db->prepare("DELETE FROM routepermissions WHERE exten = ?");
                    $stmt->execute(array($extdisplay));
                    $stmt = $this->db->prepare("INSERT INTO routepermissions (exten, routename, allowed, faildest, prefix) VALUES(?, ?, ?, ?, ?)");
                } catch (\PDOException $e) {
                    return false;
                }
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
                    try {
                        $res = $stmt->execute(array(
                            $extdisplay,
                            $route_name, 
                            $data["perms"],
                            $data["faildest"],
                            $data["prefix"],
                        ));
                    } catch (\PDOException $e) {
                        return false;
                    }
                }
                break;
            case "del":
                try {
                    $stmt = $this->db->prepare("DELETE FROM routepermissions WHERE exten = ?");
                    $stmt->execute(array($extdisplay));
                } catch (\PDOException $e) {
                    return false;
                }
                break;
        }
    }

    public function getRoutes()
    {
        $sql = "SELECT DISTINCT name FROM outbound_routes JOIN outbound_route_sequence USING (route_id) ORDER BY seq";
        try {
            $result = $this->db->getCol($sql);
            return $result;
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function getDefaultDest()
    {
        $sql = "SELECT faildest FROM routepermissions where exten = -1 LIMIT 1";
        try {
            $result = $this->db->getOne($sql);
            return $result;
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function updateDefaultDest($dest)
    {
        $sql = "DELETE FROM routepermissions WHERE exten = -1";
        try {
            $this->db->query($sql);
        } catch (\PDOException $e) {
            return false;
        }
        if (!empty($dest)) {
            $sql = "INSERT INTO routepermissions (exten, routename, faildest, prefix) VALUES ('-1', 'default', ?, '')";
            try {
                $stmt = $this->db->prepare($sql);
                $stmt->execute(array($dest));
            } catch (\PDOException $e) {
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
        try {
            $sql = "DELETE FROM routepermissions WHERE exten=? AND routename=?";
            $stmt1 = $this->db->prepare($sql);
            $sql = "INSERT INTO routepermissions (exten, routename, allowed, faildest, prefix) VALUES (?, ?, ?, ?, ?)";
            $stmt2 = $this->db->prepare($sql);
        } catch (\PDOException $e) {
            return false;
        }
        foreach ($extens as $ext) {
            try {
                $stmt1->execute(array($ext, $route));
                $stmt2->execute(array($ext, $route, $allowed, $faildest, $prefix));
            } catch (\PDOException $e) {
                return false;
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
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
    static $module = "routepermissions";

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
        $columns = array(
            "exten"     => array("type"=>"integer"),
            "routename" => array("type"=>"string", "length"=>25),
            "allowed"   => array("type"=>"string", "length"=>3, "default"=>"YES", "notnull"=>false),
            "faildest"  => array("type"=>"string", "length"=>255, "default"=>"", "notnull"=>false),
            "prefix"    => array("type"=>"string", "length"=>16, "default"=>"", "notnull"=>false),
        );
        $indices = array(
            "idx_exten" => array("type"=>"index", "cols"=>array("exten")),
            "idx_route" => array("type"=>"index", "cols"=>array("routename")),
        );
        try {
            $table = $this->db->migrate("routepermissions");
            $table->modify($columns, $indices);
        } catch (\Doctrine\DBAL\Exception $e) {
            die_freepbx(sprintf(_("Error updating routepermissions table: %s"), $e->getMessage()));
        }

        $stmt = $this->db->query("SELECT COUNT(exten) FROM routepermissions");
        if ($stmt->fetchColumn() > 0) {
            out(_("Found data, using existing route permissions"));
            try {
                $this->db->exec("UPDATE routepermissions SET prefix=faildest, faildest='' WHERE faildest RLIKE '^[a-d0-9*#]+$'");
            } catch (\PDOException $e) {
                die_freepbx(sprintf(_("Error updating routepermissions table: %s"), $e->getMessage()));
            }
        } else {
            outn(_("New install, populating default allow permission&hellip; "));
            $extens = array();
            $devices = \FreePBX::Core()->getAllUsersByDeviceType();
            foreach($devices as $exten) {
                if ($exten->id) {
                    $extens[] = $exten->id;
                }
            }
            try {
                $routes = \FreePBX::Core()->getAllRoutes();
                $query = "INSERT INTO routepermissions (exten, routename, allowed, faildest) VALUES (?, ?, 'YES', '')";
                $stmt = $this->db->prepare($query);
                foreach($extens as $ext) {
                    foreach ($routes as $r) {
                        $this->db->execute($stmt, array($ext, $r["name"]));
                    }
                }
            } catch (\Exception $e) {
                die_freepbx(sprintf(_("Error populating routepermissions table: %s"), $e->getMessage()));
            }
            out(_("complete"));
        }
    }

    public function uninstall()
    {
        $amp_conf = \FreePBX\Freepbx_conf::create();

        outn(_("Removing routepermissions database table&hellip; "));
        try {
            $this->db->query("DROP TABLE routepermissions");
            out(_("complete"));
        } catch (\PDOException $e) {
            out(sprintf(_("Error removing routepermissions table: %s"), $result->getMessage()));
        }

        outn(_("Removing AGI script&hellip; "));
        $agidir = $amp_conf->get("ASTAGIDIR");
        $result = unlink("$agidir/checkperms.agi");
        if (!$result) {
            out(_("failed! File must be removed manually"));
        } else {
            out(_("complete"));
        }
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
            $context = "outrt-$route[route_id]";
            $routename = $route["name"];
            $routes = core_routing_getroutepatternsbyid($route["route_id"]);
            foreach ($routes as $rt) {
                $extension = $rt["match_pattern_prefix"] . $rt["match_pattern_pass"];
                // If there are any wildcards in there, add a _ to the start
                if (preg_match("/\.|z|x|\[|\]/i", $extension)) {
                    $extension = "_".$extension;
                }
                if (!empty($rt['match_cid'])) {
                    $cid = (preg_match("/\.|z|x|\[|\]/i", $rt['match_cid']))
                        ? '_'.$rt['match_cid']
                        : $rt['match_cid'];
                    $extension = $extension.'/'.$cid;
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
        return array("core");
    }

    /**
     * Perform our hook actions on page display
     *
     * @param Object $currentcomponent The ugly old page object
     * @param string $module The module name
     * @return boolean Returns false if the page is not modified
     */
    public function doGuiHook(&$currentcomponent, $module) {
        $pagename   = isset($_REQUEST["display"]) ? $_REQUEST["display"] : "";
        $extdisplay = isset($_REQUEST["extdisplay"]) ? $_REQUEST["extdisplay"] : "";
        $action     = isset($_REQUEST["action"]) ? $_REQUEST["action"] : "";
        $section    = _("Outbound Route Permissions");
        $i          = 0;

        if (
            $module !== "core" || $action === "del" || empty($extdisplay) ||
            ($pagename !== "extensions" && $pagename !== "users")
        ) {
            return false;
        }

        $routes = $this->getRoutes();
        try {
            $stmt = $this->db->prepare("SELECT allowed, faildest, prefix FROM routepermissions WHERE routename = ? AND exten = ?");
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
                    /* TODO: get rid of this */
            );
            $currentcomponent->addguielem($section, $input);
        }
    }

    /**
     * Tell the system which pages' POSTs we want to hook
     *
     * @return array All the pages (not modules) we want to hook into
     */
    public static function myConfigPageInits() {
        return array("extensions", "users");
    }

    /**
     * Perform our hook action on page POST
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

    /**
     * Handle GET/POST requests to the page
     */
    public function showPage($request = null)
    {
        $cwd          = dirname(__FILE__);
        $message      = "";
        $errormessage = "";

        if ($request !== null) {
            foreach ($request as $k=>$perm) {
                if (strncmp($k, "permission_", 11) === 0) {
                    $route = substr($k, 11);
                    $redir = "";
                    $prefix = "";
                    switch ($perm) {
                    case "YES":
                        break;
                    case "NO":
                        if (isset($request["goto_$route"])) {
                            $type = $request["goto_$route"];
                            if (isset($request["${type}_$route"])) {
                                $redir = $request["${type}_$route"];
                            }
                        }
                        break;
                    case "REDIRECT":
                        $perm = "NO";
                        $prefix = trim($request["prefix_$route"]);
                        if (empty($prefix)) {
                            $errormessage .= sprintf(
                                _("Redirect selected but redirect prefix missing for route %s - no action taken"),
                                $route
                            );
                            $errormessage .= "<br/>";
                            continue;
                        }
                        break;
                    default:
                        continue 2;
                    }
                    $range = $request["range_$route"];
                    try {
                        $result = $this->setRangePermissions($route, $range, $perm, $redir, $prefix);
                        if ($prefix) {
                            $message .= sprintf(
                                _("Route %s set to %s for supplied range %s using redirect prefix %s"),
                                $route,
                                $perm,
                                $range,
                                $prefix
                            );
                            $message .= "<br/>";
                        } else {
                            $message .= sprintf(
                                _("Route %s set to %s for supplied range %s"),
                                $route,
                                $perm,
                                $range
                            );
                            $message .= "<br/>";
                        }
                    } catch (\PDOException $e) {
                        $errormessage .= sprintf(
                            _("Database error, couldn't set permissions for route %s: %s"),
                            $route,
                            $e->getMessage()
                        );
                        $errormessage .= "<br/>";
                    }
                } elseif ($k == "update_default") {
                    $dest_type = $request["gotofaildest"];
                    $dest = $request[$dest_type . "faildest"];
                    try {
                        $result = $this->updateDefaultDest($dest);
                        $message = _("Default destination changed");
                    } catch (\PDOException $e) {
                        $errormessage = sprintf(
                            _("Database error, couldn't set default permissions: %s"),
                            $e->getMessage()
                        );
                    }
                }
            }
        }

        $viewdata = array(
            "module"=>self::$module,
            "message"=>$message,
            "errormessage"=>$errormessage,
            "rp"=>$this,
            "routes"=>$this->getRoutes(),
        );
        show_view("$cwd/views/settings13.php", $viewdata);
    }

    public function getRoutes()
    {
        $sql = "SELECT DISTINCT name FROM outbound_routes JOIN outbound_route_sequence USING (route_id) ORDER BY seq";
        try {
            $result = $this->db->query($sql);
            return $result->fetchAll(\PDO::FETCH_COLUMN, 0);
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function getDefaultDest()
    {
        $sql = "SELECT faildest FROM routepermissions where exten = -1 LIMIT 1";
        try {
            $result = $this->db->query($sql);
            return $result->fetchColumn(0);
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function updateDefaultDest($dest)
    {
        try {
            $sql = "DELETE FROM routepermissions WHERE exten = -1";
            $this->db->exec($sql);
            if (!empty($dest)) {
                $sql = "INSERT INTO routepermissions (exten, routename, faildest, prefix) VALUES ('-1', 'default', ?, '')";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(array($dest));
            }
            return true;
        } catch (\PDOException $e) {
            throw $e;
        }
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
            foreach ($extens as $ext) {
                $stmt1->execute(array($ext, $route));
                $stmt2->execute(array($ext, $route, $allowed, $faildest, $prefix));
            }
            return true;
        } catch (\PDOException $e) {
            throw $e;
        }
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
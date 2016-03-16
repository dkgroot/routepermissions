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

if (!class_exists("RouterpermissionsLegacy")) {
    require_once(dirname(__FILE__) . "/functions.inc.php");
}

class Routepermissions extends RoutepermissionsLegacy implements FreePBX\BMO
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

    public function doConfigPageInit($display)
    {
        return;
    }
}
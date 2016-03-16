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

$cwd          = dirname(__FILE__);
$module       = "routepermissions";
$rp           = new RoutepermissionsLegacy;
$message      = "";
$errormessage = "";

if(isset($_POST)) {
    foreach ($_POST as $k=>$perm) {
        if (strncmp($k, "permission_", 11) === 0) {
            $route = substr($k, 11);
            $redir = "";
            $prefix = "";
            switch ($perm) {
            case "YES":
                break;
            case "NO":
                if (isset($_POST["goto_$route"])) {
                    $type = $_POST["goto_$route"];
                    if (isset($_POST["${type}_$route"])) {
                        $redir = $_POST["${type}_$route"];
                    }
                }
                break;
            case "REDIRECT":
                $perm = "NO";
                $prefix = trim($_POST["prefix_$route"]);
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
            $range = $_POST["range_$route"];
            $result = $rp->setRangePermissions($route, $range, $perm, $redir, $prefix);
            if (DB::isError($result) || $result === false) {
                $errormessage .= sprintf(
                    _("Database error, couldn't set permissions for route %s: %s"),
                    $route,
                    ($result === false) ? "" : $result->getMessage()
                );
                $errormessage .= "<br/>";
            } elseif ($prefix) {
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
        } elseif ($k == "update_default") {
            $dest_type = $_POST["gotofaildest"];
            $dest = $_POST[$dest_type . "faildest"];
            $result = $rp->updateDefaultDest($dest);
            if (DB::isError($result)) {
                $errormessage = sprintf(
                    _("Database error, couldn't set default permissions: %s"),
                    $result->getMessage()
                );
            } else {
                $message = _("Default destination changed");
            }
        }
    }
}

$viewdata = array(
    "module"=>$module,
    "message"=>$message,
    "errormessage"=>$errormessage,
    "rp"=>$rp,
);
if (interface_exists("BMO")) {
    show_view("$cwd/views/settings13.php", $viewdata);
} else {
    show_view("$cwd/views/settings.php", $viewdata);
}
?>
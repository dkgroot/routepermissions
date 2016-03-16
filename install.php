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

global $db;

// create the tables
$query = "CREATE TABLE IF NOT EXISTS routepermissions (
    exten INT(11) NOT NULL,
    routename VARCHAR(25) NOT NULL,
    allowed VARCHAR(3) DEFAULT 'YES',
    faildest VARCHAR(255) DEFAULT '',
    prefix VARCHAR(16) DEFAULT '',
    INDEX idx_exten (exten),
    INDEX idx_route (routename)
)";

$result = $db->query($query);
if (DB::IsError($result)) {
    die_freepbx(sprintf(_("Error creating routepermissions table: %s"), $result->getMessage()));
}

$result = $db->getRow("SELECT faildest FROM routepermissions LIMIT 1");
if(DB::IsError($result)) {
    // 0.3 - add 'faildest' 
    outn(_("Updating old database&hellip; "));
    $query = "ALTER TABLE routepermissions ADD faildest VARCHAR(255) DEFAULT ''";
    $result = $db->query($query);
    if(DB::IsError($result)) {
        die_freepbx(sprintf(_("Error updating routepermissions table: %s"), $result->getMessage()));
    }
    out(_("complete"));
}

$result = $db->getRow("SELECT prefix FROM routepermissions LIMIT 1");
if(DB::IsError($result)) {
    // 1.0 - add 'prefix' and index on route name
    outn(_("Updating old database&hellip; "));
    $query = "ALTER TABLE routepermissions ADD COLUMN prefix varchar(16)";
    $result = $db->query($query);
    if(DB::IsError($result)) {
        die_freepbx(sprintf(_("Error updating routepermissions table: %s"), $result->getMessage()));
    }
    $query = "UPDATE routepermissions SET prefix=faildest, faildest='' WHERE faildest RLIKE '^[a-d0-9*#]+$'";
    $result = $db->query($query);
    if(DB::IsError($result)) {
        die_freepbx(sprintf(_("Error updating routepermissions table: %s"), $result->getMessage()));
    }
    $query = "ALTER TABLE routepermissions ADD INDEX idx_route (routename)";
    $result = $db->query($query);
    if(DB::IsError($result)) {
        die_freepbx(sprintf(_("Error updating routepermissions table: %s"), $result->getMessage()));
    }
    out(_("complete"));
}


$query = "SELECT COUNT(exten) FROM routepermissions";
$count = $db->getOne($query);
if ($count > 0) {
    out(_("Found data, using existing route permissions"));
} else {
    outn(_("New install, populating default allow permission&hellip; "));
    if (class_exists("FreePBX\\Modules\\Core")) {
        $extens = array();
        $devices = \FreePBX::Core()->getAllUsersByDeviceType();
        foreach($devices as $exten) {
            if ($exten->id) {
                $extens[] = $exten->id;
            }
        }
    } elseif (function_exists("core_devices_list")) {
        $extens = array_map(function($u) {return $u[0];}, core_devices_list());
    } else {
        die_freepbx(sprintf(
            _("Error populating routepermissions table: %s"),
            "no devices &mdash; tried core_devices_list and FreePBX::Core()->getAllUsersByDeviceType()"
        ));
    }
    $query = "SELECT DISTINCT name FROM outbound_routes JOIN outbound_route_sequence USING (route_id) ORDER BY seq";
    $routes = $db->getCol($query);
    $query = "INSERT INTO routepermissions (exten, routename, allowed, faildest) VALUES (?, ?, 'YES', '')";
    $stmt = $db->prepare($query);
    if(!DB::IsError($routes) && !DB::IsError($stmt)) {
        foreach($extens as $ext) {
            foreach ($routes as $r) {
                $result = $db->execute($stmt, array($ext, $r));
                if (DB::IsError($result)) {
                    die_freepbx(sprintf(_("Error populating routepermissions table: %s"), $result->getMessage()));
                }
            }
        }
    } else {
        die_freepbx(sprintf(
            _("Error populating routepermissions table: %s"),
            DB::IsError($routes) ? $routes->getMessage() : $stmt->getMessage()
        ));
    }
    out(_("complete"));
}
?>

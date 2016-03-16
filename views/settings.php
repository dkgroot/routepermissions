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

$html = "<div class=\"content\" id=\"routepermissions\"><div id=\"content_header\">";
$html .= heading(_("Route Permissions"), 2);
if (!empty($message)) {
    $html .= "<p class=\"routepermissions_infobox\">$message</p>";
}
if (!empty($errormessage)) {
    $html .= "<p class=\"routepermissions_infobox routepermissions_error\">$errormessage</p>";
}
$html .= "</div>";
$html .= form_open("$_SERVER[PHP_SELF]?display=$module");

$html .= heading(_("Instructions"), 3);
$html .= "<p>";
$html .= _("This module allows you to allow or deny access to certain routes from specified extensions. You can perform bulk changes on this page, and you can change an individual extension's access to routes on that extension's page.");
$html .= "</p>";
$html .= "<p>";
$html .= _("In addition to simple Allow/Deny rules, you can also deny access to a route and then redirect the call, allowing a different outbound route to match the call.");
$html .= "</p>";
$html .= "<p>";
$html .= _("For example, if you wanted to stop an extension from using Route A, selecting <b>Deny</b> would preclude the possibility of trying another route. Instead you could select <b>Redirect with prefix</b> and set the <b>Redirect prefix</b> to <code>9999</code>; assuming you've created Route B with a prefix match of <code>9999</code> and not set a deny rule on it, the call can proceed.");
$html .= "</p>";
$html .= "<p>";
$html .= _("In addition, if you are denying access to a particular route and wish to use something other than the default destination, you can select <b>Redirect with prefix</b>, and create a <b>Miscellaneous Application</b> that matches the specified <b>Redirect prefix</b>. Using the previous example, a <b>Miscellaneous Application</b> with a feature code of <code>_9999x.</code> could be called if it existed on the system.");
$html .= "</p>";

$html .= heading(_("Bulk Changes"), 3);
$html .= "<p>";
$html .= _("Select a route and select <b>Allow</b> or <b>Deny</b> to set permissions for the entered extensions. If you enter a <b>Redirect prefix</b> and click <b>Redirect with prefix</b>, the route will automatically be set to DENIED.");
$html .= _("You can enter one or more extensions or ranges separated by commas; a range is a start and end extension separated by a hyphen. For example <code>123,125,200-300</code> will select extensions 123 and 125 as well as any extensions between 200 and 300.");
$html .= "</p>";
$html .= "<p>";
$html .= _("Note that these changes take effect <em>immediately</em> and do not require a reload.");
$html .= "</p>";

$routes = $rp->getRoutes();

$table = new CI_Table;
$table->set_heading(array(
    _("Route"),
    _("Extensions"),
    _("Permissions"),
    _("Destination"),
    _("Redirect Prefix"),
));

foreach ($routes as $r) {
    $table->add_row(array(
        array("data"=>$r, "id"=>"td_$r"),
        form_input("range_$r", _("All"), "size=\"10\""),
        "<span class=\"radioset\">" . 
            form_radio("permission_$r", "", true, "id=\"permission_{$r}_SKIP\"") .
            form_label(_("No change"), "permission_{$r}_SKIP") .
            form_radio("permission_$r", "YES", false, "id=\"permission_{$r}_YES\"") .
            form_label(_("Allow"), "permission_{$r}_YES") .
            form_radio("permission_$r", "NO", false, "id=\"permission_{$r}_NO\"") .
            form_label(_("Deny"), "permission_{$r}_NO") .
            form_radio("permission_$r", "REDIRECT", false, "id=\"permission_{$r}_REDIRECT\"") .
            form_label(_("Redirect w/prefix"), "permission_{$r}_REDIRECT") .
        "</span>",
        drawselects("", "_$r", false, false, _("Use default")),
        form_input("prefix_$r", "", sprintf("placeholder=\"%s\" size=\"10\"", _("Prefix"))),
    ));
}
$table->add_row(array(
    form_submit("update_permissions", _("Save Changes"))
));
$html .= form_open("$_SERVER[PHP_SELF]?display=$module");
$html .= $table->generate();
$html .= form_close();

$html .= "<p>&nbsp;</p>";

$html .= form_open("$_SERVER[PHP_SELF]?display=$module");
$html .= heading(_("Default Destination if Denied"), 3);
$html .= "<p>";
$html .= _("Select the destination for calls when they are denied without specifying a destination.");
$html .= "</p>";
$html .= drawselects($rp->getDefaultDest(), "faildest");
$html .= "<br/>";
$html .= form_submit("update_default", "Change Destination");
$html .= form_close();

echo $html;

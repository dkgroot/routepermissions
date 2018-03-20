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
?>
<div class="container-fluid">
	<div class="row">
		<div class="col-sm-12">
			<div class="fpbx-container">
				<div class="display no-border">
					<h1><?php echo _("Route Permissions")?></h1>
					<div class="well well-info">
						<p><?php echo _("This module allows you to allow or deny access to certain routes from specified extensions. You can perform bulk changes on this page, and you can change an individual extension's access to routes on that extension's page.");?></p>
						<p><?php echo _("In addition to simple Allow/Deny rules, you can also deny access to a route and then redirect the call, allowing a different outbound route to match the call.");?></p>
						<p><?php echo _("For example, if you wanted to stop an extension from using Route A, selecting <b>Deny</b> would preclude the possibility of trying another route. Instead you could select <b>Redirect with prefix</b> and set the <b>Redirect prefix</b> to <code>9999</code>; assuming you've created Route B with a prefix match of <code>9999</code> and not set a deny rule on it, the call can proceed.");?></p>
						<p><?php echo _("In addition, if you are denying access to a particular route and wish to use something other than the default destination, you can select <b>Redirect with prefix</b>, and create a <b>Miscellaneous Application</b> that matches the specified <b>Redirect prefix</b>. Using the previous example, a <b>Miscellaneous Application</b> with a feature code of <code>_9999x.</code> could be called if it existed on the system.");?></p>
					</div>
<?php if(!empty($message)):?>
					<div class="alert alert-success alert-dismissable" role="alert">
						<button type="button" class="close" data-dismiss="alert" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
						<h3><i class="fa fa-info-circle" aria-hidden="true"></i> <?=_("Messages")?></h3>
						<?php echo $message?>
					</div>
<?php endif;?>
<?php if(!empty($errormessage)):?>
					<div class="alert alert-warning alert-dismissable" role="alert">
						<button type="button" class="close" data-dismiss="alert" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
						<h3><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> <?=_("Errors")?></h3>
						<?php echo $errormessage?>
					</div>
<?php endif;?>
					<h4><?=htmlspecialchars(_("Bulk Changes"))?></h4>
					<p>
						<?=_("Select a route and select <b>Allow</b> or <b>Deny</b> to set permissions for the entered extensions. If you enter a <b>Redirect prefix</b> and click <b>Redirect with prefix</b>, the route will automatically be set to DENIED.")?>
						<?=_("You can enter one or more extensions or ranges separated by commas; a range is a start and end extension separated by a hyphen. For example <code>123,125,200-300</code> will select extensions 123 and 125 as well as any extensions between 200 and 300.")?>
					</p>
					<p>
						<?=_("Note that these changes take effect <em>immediately</em> and do not require a reload.")?>
					</p>
					<form method="post">
						<table>
							<thead>
								<tr>
									<th><?=_("Route")?></th>
									<th><?=_("Extensions")?></th>
									<th><?=_("Permissions")?></th>
									<th><?=_("Destination")?></th>
									<th><?=_("Redirect Prefix")?></th>
								</tr>
							</thead>
							<tbody>
<?php foreach ($routes as $r):?>
								<tr>
									<td id="td_<?=$r?>">
										<?=$r?>
									</td>
									<td>
										<input name="range_<?=$r?>" id="range_<?=$r?>" value=<?=_("All")?> class="form-control" type="text" size="10">
									</td>
									<td>
										<span class="radioset">
											<input name="permission_<?=$r?>" id="permission_<?=$r?>_SKIP" value="" class="form-control" type="radio" checked="checked"/>
											<label for="permission_<?=$r?>_SKIP"><?=_("No change")?></label>
											<input name="permission_<?=$r?>" id="permission_<?=$r?>_YES" value="YES" class="form-control" type="radio"/>
											<label for="permission_<?=$r?>_YES"><?=_("Allow")?></label>
											<input name="permission_<?=$r?>" id="permission_<?=$r?>_NO" value="NO" class="form-control" type="radio"/>
											<label for="permission_<?=$r?>_NO"><?=_("Deny")?></label>
											<input name="permission_<?=$r?>" id="permission_<?=$r?>_REDIRECT" value="REDIRECT" class="form-control" type="radio"/>
											<label for="permission_<?=$r?>_REDIRECT"><?=_("Redirect w/prefix")?></label>
										</span>
									</td>
									<td>
										<?=\drawselects("", "_$r", false, false, _("Use default"))?>
									</td>
									<td>
										<input name="prefix_$r" type="text" class="form-control" placeholder="<?=_("Prefix")?>" size="10"/>
									</td>
								</tr>
<?php endforeach?>
								<tr>
									<td>
										<button name="update_permissions" type="submit"><?=_("Save Changes")?></button>
									</td>
								</tr>
							</tbody>
						</table>
					</form>
					<p>&nbsp;</p>
					<form method="post">
						<h4><?=_("Default Destination if Denied")?></h4>
						<p>
							<?=_("Select the destination for calls when they are denied without specifying a destination.")?>
						</p>
						<p>
							<?=\drawselects($rp->getDefaultDest(), "faildest")?>
						</p>
						<p>
							<button name="update_default" type="submit"><?=_("Change Destination")?></button>
						</p>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
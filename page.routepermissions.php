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
$rp = new Routepermissions;
$rp->showPage($_REQUEST);

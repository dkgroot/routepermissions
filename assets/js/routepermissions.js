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

$(function() {
    // bulk settings page
    $("input[name^='prefix_'], select[name^='goto_']").prop("disabled", true);
    $("input[name^='permission_']").change(function(){
        var rname = this.id.replace(/^permission_(.*)_(?:SKIP|YES|NO|REDIRECT)$/, "prefix_$1");
        var dname = this.id.replace(/^permission_(.*)_(?:SKIP|YES|NO|REDIRECT)$/, "goto_$1");
        $("input[name=" + rname + "]")
            .val("")
            .prop("disabled", (this.value !== "REDIRECT"))
            .prop("required", (this.value === "REDIRECT"));
        $("select[name=" + dname + "]")
            .val("")
            .change()
            .prop("disabled", (this.value !== "NO"));
    });
    // extensions page code is added per element: FUN!!!
});

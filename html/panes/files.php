<?
/*
Copyright (c) 2013 Cimaron Shanahan

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/
?>

<style type="text/css">
#files-pane {
	height: 100%;
	overflow: auto;
	font-size: 13px;
}

#files-pane ul {
	width: 100%;
}

/*
.files-header {
	margin: 0;
	padding: 0;
}

.files-header li {
	display: block;
	list-style: none;
	display: inline;
	background-color: #CCCCCC;
}

.files-header span {
	border-left: solid 1px #EEEEEE;
}

.files-header span.filesize {
	float: right;
	width: 100px;
}
*/
</style>

<div id="files-pane">
	<? /*
	<ul class="files-header">
		<li>
			<span class="filesize"></span>
			<span class="filesize">size</span>
		</li>
	</ul>*/ ?>
	<div id="files-container"></div>
</div>

<script type="text/javascript" src="<? echo $basedir; ?>/assets/js/jquery.jstree.js"></script>
<script type="text/javascript" src="<? echo $basedir; ?>/assets/js/jquery.hotkeys.js"></script>
<script type="text/javascript">
/*
(function($) {

	function resize() {
		//console.log('resize');
		/ *
		var pane = $('#files-pane');
		var cont = $('#files-container');

		cont.height(0);

		var newheight =  pane.height() - (cont.offset().top - pane.offset().top);
		cont.height(newheight);
		* /
	}

	WindowLayout.options.west.onresize = function() {
		resize();
		return true;
	};

	$().ready(function() {
		resize();
	});
	
	
	var tree;
	Debugger.updateFilepane = function(root) {

		if (tree && tree.destroy) {
			tree.destroy();
		}

		$('#files-container').html('');

		tree = $('#files-container').jstree({
			plugins : ["themes", "ui", "crrm", "hotkeys", "json_data", "types", "search"],
			core : {
				html_titles : true,
				animation : 0,
				initially_open : [ "node_root" ]
			},
			themes : {
				url : 'assets/themes/classic/style.css',
				theme : 'classic',
				//dots : false,
				//icons : false
			},
			json_data : {
				data : [{data : "/", attr : {rel : "root", filepath : root, id : "node_root"}, state : 'closed'}],
				ajax : {
					"url" : "fs/local.php",
					// the `data` function is executed in the instance's scope
					// the parameter is the node being loaded 
					// (may be -1, 0, or undefined when loading the root nodes)
					"data" : function (n) {
						// the result is fed to the AJAX request `data` option
						return { 
							"operation" : "get_children", 
							"dir" : n.attr ? n.attr("filepath") : ''
						};
					},
					"progressive_render" : true
				}
			},
			types : {
				valid_children : [ "root", "file", "folder" ],
				types : {
					root : {
						icon : { 
							image : "http://static.jstree.com/v.1.0rc/_docs/_drive.png"
						},
					},
					folder : {
						valid_children : [ "default", "folder" ],
						icon : {
							image : "http://static.jstree.com/v.1.0pre/_demo/folder.png"
						}
					},
					"default" : {
						icon : {
							image : "http://static.jstree.com/v.1.0pre/_demo/file.png"
						},
						valid_children : "none"
					}
				}
			},
			search : {
				ajax : {
					url : "fs/local.php"
				}
			},
		})
		.bind("loaded.jstree", function (event, data) {
			
		})
		.bind("dblclick.jstree", function (event) {
			var node = $(event.target).closest("li");
			if (node.attr('rel') == 'file') {
				if (node.attr('filepath')) {
					Debugger.getSource(node.attr('filepath'));
				}
			}
		})
		
		/ *.bind("select_node.jstree", function (event, data) { 
			console.log(data);
		})* /;  

	};
	
}(jQuery));
*/
</script>
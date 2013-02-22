<?php

$operation = $_REQUEST['operation'];
$data = NULL;

switch ($operation) {

	case 'get_children':

		$dir = $_REQUEST['dir'];

		if (empty($dir)) {
			$dir = '//';
		}
		
		if (substr($dir, -1) == '/') {
			$dir = substr($dir, 0, -1);
		}

		$data = array();

		$path = $docroot . '/' . $dir;

		if (is_dir($path)) {

			$dh  = opendir($path);
			$dirs = array();
			$files = array();

			while (false !== ($filename = readdir($dh))) {

				if ($filename == '.' || $filename == '..') {
					continue;
				}

				$fullpath = $dir . '/' . $filename;
				$isdir = is_dir($fullpath);

				if ($isdir) {

					$node = array(
						'attr' => array(
							'rel' => 'folder',
							'filepath' => $fullpath,						
						),
						'data' => $filename,
						'state' => 'closed',
						//'children' => array(),
					);
					$dirs[] = $node;

				} else {

					$node = array(
						'attr' => array(
							'rel' => 'file',
							'filepath' => $fullpath,						
						),
						'data' => $filename,
						//'data' => '<span class="filename">' . $filename . '</span><span class="filesize">123KB</span>',
					);
					
					$files[] = $node;
				}
			}

			sort($dirs);
			sort($files);

			$data = array_merge($dirs, $files);
		}

		break;
	default:
}

header('Content-Type: application/json');
echo json_encode($data);


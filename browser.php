<?php
$dateFormat = 'd.m.Y H:i';
$root = __DIR__;
$folderOnTop = FALSE;

if (!isset($_GET['C'])) {
	$_GET['C'] = 'N';
}

if (!isset($_GET['O'])) {
	$_GET['O'] = 'A';
}

$sizeFormat = function ($bytes) {
	$size = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
	$factor = (int) floor((strlen($bytes) - 1) / 3);
	$string = round($bytes / pow(1024, $factor), 2);
	if (isset($size[$factor])) {
		return $string . ' ' . $size[$factor];
	}
	return $string;
};

$directory = dirname($_SERVER['REQUEST_URI']);
$path = realpath($root . $directory);
if (strpos($path, $root) !== 0) {
	$directory = '/';
	$path = $root;
}

$parameters = '?C=' . $_GET['C'] . '&amp;O=' . $_GET['O'];

$me = basename($_SERVER['PHP_SELF']);
$items = [];
foreach (scandir($path, SCANDIR_SORT_NONE) as $item) {
	if (strpos($item, '.') === 0 || $me === $item) {
		continue;
	}

	$prefix = rtrim($directory, '/') . '/';
	$itemPath = $path . '/' . $item;
	$dir = is_dir($itemPath);
	$suffix = '';
	if ($dir) {
		$suffix = '/' . $parameters;
	}

	$items[] = [
		'name' => $item,
		'link' => $prefix . $item . $suffix,
		'time' => filemtime($itemPath),
		'size' => $dir ? -1 : filesize($itemPath),
		'dir' => is_dir($itemPath),
	];
}

usort($items, function ($item1, $item2) use ($folderOnTop) {
	if ($folderOnTop && ($item1['dir'] && !$item2['dir'] || !$item1['dir'] && $item2['dir'])) {
		return $item1['dir'] && !$item2['dir'] ? -1 : 1;
	}

	if ($_GET['C'] === 'M') {
		$result = $item1['time'] !== $item2['time'] ? ($item1['time'] < $item2['time'] ? -1 : 1) : 0;
	} else if ($_GET['C'] === 'S') {
		$result = $item1['size'] !== $item2['size'] ? ($item1['size'] < $item2['size'] ? -1 : 1) : 0;
	}

	if (!isset($result) || $result === 0) {
		$result = strcmp($item1['name'], $item2['name']);
	}

	if ($_GET['O'] === 'D') {
		$result = $result * -1;
	}

	return $result;
});

$sort = $_GET['O'] === 'A' ? 'D' : 'A';
?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset=utf-8 />
		<meta http-equiv="X-UA-Compatible" content="IE=edge" />
		<meta name="viewport" content="width=device-width,initial-scale=1" />

		<title><?php echo $directory ?></title>

		<link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" />
		<style>
			body {
				padding: 30px 100px;
				font-family: 'Trebuchet MS', Helvetica, sans-serif;
			}

			table {
				width: 100%;
				border-spacing: 0;
			}

			table a {
				display: block;
				color: #000;
				text-decoration: none;
			}

			table a:visited {
				color: #888888;
			}

			table th {
				text-align: left;
			}

			table td {
				height: 21px;
			}

			table td a,
			table th a {
				padding: 5px;
			}

			table th a {
				color: #000 !important;
			}

			table th a:hover {
				text-decoration: underline;
			}

			table tbody tr:hover {
				background: #e8e8e8;
			}

			table td a .fa {
				color: #000;
			}
		</style>
	</head>
	<body>
		<table>
			<thead>
				<tr>
					<th><a href="?C=N&amp;O=<?php echo $sort ?>">Name</a></th>
					<th><a href="?C=S&amp;O=<?php echo $sort ?>">Size</a></th>
					<th><a href="?C=M&amp;O=<?php echo $sort ?>">Date</a></th>
				</tr>
			</thead>
			<tbody>
				<?php if ($directory !== '/') : ?>
					<tr>
						<td><a href="../<?php echo $parameters ?>">..</a></td>
						<td><a href="../<?php echo $parameters ?>"></a></td>
						<td><a href="../<?php echo $parameters ?>"></a></td>
					</tr>
				<?php endif ?>
				<?php foreach ($items as $item): ?>
					<tr>
						<td>
							<a href="<?php echo $item['link'] ?>">
								<?php if ($item['dir']): ?>
									<span class="fa fa-folder fa-fw"></span>
								<?php else: ?>
									<span class="fa fa-file-o fa-fw"></span>
								<?php endif ?>
								<?php echo $item['name'] ?>
							</a>
						</td>
						<td>
							<a href="<?php echo $item['link'] ?>">
								<?php if ($item['size'] >= 0): ?>
									<?php echo $sizeFormat($item['size']) ?>
								<?php endif ?>
							</a>
						</td>
						<td>
							<a href="<?php echo $item['link'] ?>">
								<?php echo date($dateFormat, $item['time']) ?>
							</a>
						</td>
					</tr>
				<?php endforeach ?>
			</tbody>
		</table>
	</body>
</html>

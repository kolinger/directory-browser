<?php
$dateFormat = 'd.m.Y H:i';
$root = __DIR__;
$folderOnTop = false;

if (!isset($_GET['C'])) {
	$_GET['C'] = 'N';
}

if (!isset($_GET['O'])) {
	$_GET['O'] = 'A';
}
$parameters = '?C=' . $_GET['C'] . '&O=' . $_GET['O'];

$authenticated = true;
$file = __DIR__ . '/.htpasswd';
if (file_exists($file)) {
	session_start();
	$authenticated = false;

	$username = '';
	if (isset($_GET['username']) && isset($_GET['password'])) {
		$username = $_GET['username'];
		$password = $_GET['password'];
	}

	if (isset($_POST['username']) && isset($_POST['password'])) {
		$username = $_POST['username'];
		$password = $_POST['password'];
	}

	if (isset($username) && $username && isset($password) && $password) {
		$contents = file_get_contents($file);
		$lines = explode("\n", $contents);
		foreach ($lines as $line) {
			$parts = explode(':', $line);
			if (count($parts) === 2) {
				if ($parts[0] === $username) {
					if ($password === $parts[1] || password_verify($password, $parts[1])) {
						$_SESSION['authenticated'] = 'yop';
						header('Location: ./' . $parameters);
						exit;
					}
					break;
				}
			}
		}
	}

	if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === 'yop') {
		$authenticated = true;
	}
}

if ($authenticated) {
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

	$title = $directory;
} else {
	$title = 'unauthenticated';
}
?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset=utf-8 />
		<meta http-equiv="X-UA-Compatible" content="IE=edge" />
		<meta name="viewport" content="width=device-width,initial-scale=1" />

		<title><?php echo $title ?></title>

		<link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" />
		<style>
			html {
				box-sizing: border-box;
			}

			*, *:before, *:after {
				box-sizing: inherit;
			}

			body {
				padding: 30px 100px;
				font-family: 'Trebuchet MS', Helvetica, sans-serif;
				font-size: 16px;
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
				white-space: nowrap;
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

			.login {
				display: block;
				width: 300px;
				margin: 0 auto;
			}

			.login input {
				width: 100%;
				margin: 5px 0 10px 0;
				border: 1px solid #ccc;
				padding: 5px 10px;
			}

			.login input[type="submit"] {
				margin: 0;
				padding: 10px;
				background: #333;
				border: none;
				color: #fff;
				cursor: pointer;
			}

			.login input[type="submit"]:hover {
				background: #000;
			}

			.alert {
				padding: 5px 10px;
				margin: 0 0 10px 0;
				background: #f2dede;
				border: 1px solid #ebccd1;
				color: #a94442;
			}

			.break {
				word-break: break-all;
			}

			.no-break {
				white-space: nowrap;
			}

			@media (max-width: 900px) {
				body {
					padding: 0;
					font-size: 14px;
				}

				.login {
					width: 100%;
				}
			}
		</style>
	</head>
	<body>
		<?php if (isset($sort) && isset($directory) && isset($items) && isset($sizeFormat)) : ?>
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
									<span class="break"><?php echo $item['name'] ?></span>
								</a>
							</td>
							<td class="no-break">
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
		<?php else: ?>
			<form method="post" class="login">
				<?php if ($username) : ?><div class="alert">Wrong credentials</div><?php endif ?>
				<label for="username">Username</label>
				<input type="text" name="username" id="username" value="<?php echo $username ?>" />
				<label for="password">Password</label>
				<input type="password" name="password" id="password" />
				<input type="submit" value="Login" />
			</form>
		<?php endif ?>
	</body>
</html>

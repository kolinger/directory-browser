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

// apache like password storage, username:password pairs separated by newline
// password is generated by password_hash()
// special username 'key' must be defined for encryption, password should be 32 byte random string
$file = __DIR__ . '/.htpasswd';
if (file_exists($file)) {
	$authenticated = false;

	$users = [];
	$contents = file_get_contents($file);
	$lines = explode("\n", $contents);
	foreach ($lines as $line) {
		$parts = explode(':', $line);
		if (count($parts) === 2) {
			$username = trim($parts[0]);
			$password = trim($parts[1]);
			$users[$username] = $password;
		}
	}

	if (!defined('SODIUM_LIBRARY_VERSION')) {
		echo 'sodium is needed';
		exit;
	} else if (!isset($users['key'])) {
		echo 'key is missing';
		exit;
	} else {
		$key = $users['key'];
	}

	$username = isset($_POST['username']) ? $_POST['username'] : null;
	$password = isset($_POST['password']) ? $_POST['password'] : null;
	if ($username && $password) {
		if (isset($users[$username])) {
			if (password_verify($password, $users[$username])) {
				$authentication = json_encode([
					'username' => $username,
					'password' => $users[$username],
				]);
				$nonce = random_bytes(SODIUM_CRYPTO_BOX_NONCEBYTES);
				$authentication = $nonce . sodium_crypto_secretbox($authentication, $nonce, $key);
				$authentication = base64_encode($authentication);

				setcookie('authentication', $authentication, time() + 31536000);
				header('Location: ./' . $parameters);
				exit;
			}
		}
	}

	if (isset($_COOKIE['authentication'])) {
		$authentication = base64_decode($_COOKIE['authentication']);
		$nonce = mb_substr($authentication, 0, SODIUM_CRYPTO_BOX_NONCEBYTES, '8bit');
		$cipher = mb_substr($authentication, SODIUM_CRYPTO_BOX_NONCEBYTES, null, '8bit');
		if (mb_strlen($nonce, '8bit') === SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
			$authentication = sodium_crypto_secretbox_open($cipher, $nonce, $key);
			if ($authentication) {
				$authentication = json_decode($authentication, true);
				$username = $authentication['username'];
				$password = $authentication['password'];

				if (isset($users[$username])) {
					if ($password === $users[$username]) {
						$authenticated = true;
						setcookie('authentication', $_COOKIE['authentication'], time() + 31536000);
					}
				}
			}
		}
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

	$directory = preg_replace('~\?.*~', '', $_SERVER['REQUEST_URI']);
	$path = $root . $directory;
	if (!file_exists($path) || !is_dir($path)) {
		$title = 'not found';
		http_response_code(404);
		echo '<center><h1>404 Not Found</h1></center>';
		exit(1);
	}

	$path = realpath($path);
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
	http_response_code(403);
}
?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset=utf-8 />
		<meta http-equiv="X-UA-Compatible" content="IE=edge" />
		<meta name="viewport" content="width=device-width,initial-scale=1" />
		<meta name="robots" content="noindex, nofollow" />

		<title><?php echo $title ?></title>

		<link rel="shortcut icon" type="image/x-icon"
			  href="data:image/x-icon;base64,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Af8BAQH/AQEB/wEBAf8BAQH/AQEB/wEBAf8BAQH/AQEB0QoLCxhhZ2cDQEREBEBERANAREQDQEREBIKKigIHCAgiAQEB4QEBAf8BAQH/AQEB/wEBAf8BAQH/AQEB/wEBAf0BAQH1AQEB7AEBAeUBAQHhAQEB4QEBAeUBAQHsAQEB9QEBAf0BAQH/AQEB/wEBAf8BAQH/AQEB/wEBAf8BAQHhBwgIIoKKigJAREQEQEREA0BERANAREQEgoqKAgcICCIBAQHhAQEB/wEBAf8BAQH9AQEB4gECArECAgKBAwMDWwQEBEEFBQUxBgcHJwcICCMHCAgjBgcHJwUFBTEEBARBAwMDWwICAoEBAgKxAQEB4gEBAf0BAQH/AQEB/wEBAeEHCAgigoqKAkBERARAREQDQEREA0BERASCiooCBwgIIgEBAeEBAQH/AQEB3gICAnQGBwcnHB4eCZSbmwFobW0A////ANfe3gGeqKgCho6OAoaOjgKdp6cC4+rqAf///wBuc3MAqrCwARweHgkGBwcnAgICdAEBAd4BAQH/AQEB4QcICCKCi4sCQEREBEBERANAREQDQEREBIKKigIHCAgiAQEB4gEBAfMDBARKAAAAAHyDgwJHTEwDQEREBEBERARAREQEQEREBEBERARAREQEQEREBEBERARAREQEQEREBEBERARAREQER0xMBH+GhgIAAAAAAwQESgEBAfMBAQHiBwgIIoKLiwJAREQEQEREA0BERANAREQEgoqKAgcICCIBAQHiAQEB8wMEBEoAAAAAfYSEAkdMTARAREQEQEREBEBERARAREQEQEREBEBERARAREQEQEREBEBERARAREQEQEREBEBERARHTEwDf4aGAgAAAAADBARLAQEB8wEBAeEHCAgigoqKAkBERARAREQDQEREA0BERARZX18DDA0NFAEBAcYBAQH/AQEB3gICAnMGBwcnHB4eCZeengFBSEgA////ANzm5gGeqKgCho6OAoaOjgKeqKgC1t/fAf///wBLUVEAkpmZARweHgkGBwcnAgICdAEBAd4BAQH/AQEBxgwNDRVaX18DQEREBEBERANAREQDQEREBEFFRQRhZ2cCBAQERAEBAc4BAQH+AQEB/gEBAeMBAgKxAgICgQMDA1sEBARBBQUFMQYHBycHCAgjBwgIIwYHBycFBQUxBAQEQQMDA1sCAgKBAQICsQEBAeMBAQH+AQEB/gEBAc8EBARDYmhoAkFFRQRAREQEQEREA0BERANAREQEQEREBEFGRgRgZmYCBwgIHwICAnQBAQHCAQEB7wEBAf8BAQH/AQEB/wEBAfgBAQHvAQEB5wEBAeMBAQHjAQEB5wEBAe8BAQH4AQEB/wEBAf8BAQH/AQEB7wEBAcMCAgJ0BwgIH2VrawJCRkYEQEREBEBERARAREQDQEREA0BERARAREQEQEREBEFFRQRfZGQCZWpqAQ8QEBAFBQU1AwMDYwICAo0BAgKuAQEBxgEBAdUBAQHeAQEB4gEBAeIBAQHeAQEB1QEBAcYBAgKuAgICjQMDA2MFBQU1DxAQEGZrawFgZWUCQUVFBEBERARAREQEQEREBEBERANAREQDQEREBEBERARAREQEQEREBEBERARAREQEUldXA6mwsAEAAAAAanBwAiMlJQcQEREPCwsLFwgJCR4HCAghBwgIIQgJCR4LCwsXEBERDyMlJQdscXECAAAAAKmwsAFSWFgDQEREBEBERARAREQEQEREBEBERARAREQEQEREA0BERAJAREQDQEREA0BERANAREQDQEREA0BERANAREQDQEREA0BERANARUUDR0tLA1pgYAJ0e3sBkZmZAaewsAGmr68BkJiYAXuCggFZXl4CR0tLA0FFRQNAREQDQEREA0BERANAREQDQEREA0BERANAREQDQEREA0BERANAREQCAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAgBAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACAEAAEAAIAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAGBgAAQAAgAEAAIAAGBgAAAAAAAAAAAAAAAAAAEACAAAAAAA=" />
		<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" />
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
				<?php if ($username) : ?>
					<div class="alert">Wrong credentials</div>
				<?php endif ?>
				<label for="username">Username</label>
				<input type="text" name="username" id="username" value="<?php echo $username ?>" />
				<label for="password">Password</label>
				<input type="password" name="password" id="password" />
				<input type="submit" value="Login" />
			</form>
		<?php endif ?>
	</body>
</html>

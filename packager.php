<?php
define('C5_BUILD', true);
define('PHP_MIN_VERSION', '5.2.2');
require_once dirname(__FILE__) . '/base.php';

$tempFiles = array();
try {
	Options::CheckWebRoot();
	$sourceFolder = Enviro::MergePath(Options::$WebrootFolder, 'packages', ToolOptions::$packageHandle);
	if(!is_dir($sourceFolder)) {
		Console::WriteLine("'" . ToolOptions::$packageHandle . "' is not a valid package handle.", true);
		die(1);
	}
	Console::Write('Parsing source files... ');
	$filesToZip = array();
	$content = Enviro::GetDirectoryContent($sourceFolder, true);
	for($i = 0; $i < count($content['filesRel']); $i++) {
		$rel = $content['filesRel'][$i];
		$full = $content['filesFull'][$i];
		if(preg_match('|^[^/]*\\.md$|i', $rel)) {
			continue;
		}
		if(preg_match('|^[^/]*\\.svg$|i', $rel)) {
			continue;
		}
		if(preg_match('|^languages/\\w+/LC_MESSAGES/messages\\.po$|i', $rel)) {
			continue;
		}
		switch(strtolower($rel)) {
			case 'languages/messages.pot':
				$rel = '';
				break;
		}
		if(!strlen($rel)) {
			continue;
		}
		if(preg_match('|\\.php$|i', $rel)) {
			prbChecker::checkForbiddenFunctions($full);
		}
		$filesToZip[$rel] = $full;
	}
	Console::WriteLine('done.');
	if(ToolOptions::$transifexDo) {
		Console::Write('Listing Transifex languages... ');
		$languageCodes = Transifexer::getLanguages(1);
		Console::WriteLine(count($languageCodes) . ' languages found.');
		foreach($languageCodes as $languageCode) {
			$moRel = "languages/$languageCode/LC_MESSAGES/messages.mo";
			if(array_key_exists($moRel, $filesToZip)) {
				Console::Write("Use Transifex version of $languageCode? ");
				if(!Console::AskYesNo()) {
					continue;
				}
			}
			Console::Write("Downloading translation for $languageCode... ");
			$poContent = Transifexer::downloadLanguage($languageCode);
			$tempFiles[] = $tempPO = Enviro::GetTemporaryFileName();
			if(@file_put_contents($tempPO, $poContent) === false) {
				throw new Exception('Error writing to a temporary file.');
			}
			Console::WriteLine('done.');
			Console::Write("Compiling $languageCode... ");
			$tempFiles[] = $tempMO = Enviro::GetTemporaryFileName();
			$args = array();
			$args[] = '--output-file=' . escapeshellarg($tempMO);
			$args[] = '--check-format';
			$args[] = '--check-header';
			$args[] = '--check-domain';
			$args[] = escapeshellarg($tempPO);
			Enviro::RunTool('msgfmt', $args);
			$filesToZip[$moRel] = $tempMO;
			Console::WriteLine('done.');
		}
	}
	Console::Write("Creating zip archive... ");
	$tempFiles[] = $tempZip = Enviro::GetTemporaryFileName();
	if(!class_exists('ZipArchive')) {
		throw new Exception('ZipArchive not available');
	}
	$zip = new ZipArchive();
	$rc = @$zip->open($tempZip, ZipArchive::OVERWRITE);
	if($rc !== true) {
		$msgs = array(
			ZipArchive::ER_EXISTS => "File already exists.",
			ZipArchive::ER_INCONS => "Zip archive inconsistent.",
			ZipArchive::ER_INVAL => "Invalid argument.",
			ZipArchive::ER_MEMORY => "Malloc failure.",
			ZipArchive::ER_NOENT => "No such file.",
			ZipArchive::ER_NOZIP => "Not a zip archive.",
			ZipArchive::ER_OPEN => "Can't open file.",
			ZipArchive::ER_READ => "Read error.",
			ZipArchive::ER_SEEK => "Seek error."
		);
		if(array_key_exists($rc, $msgs)) {
			throw new Exception('ZipArchive::open() failed: ' . $msgs[$rc]);
		}
		else {
			throw new Exception("ZipArchive::open() failed (error code: $rc).");
		}
	}
	try {
		foreach($filesToZip as $rel => $abs) {
			if(@$zip->addFile($abs, ToolOptions::$packageHandle . '/' . $rel) === false) {
				throw new Exception('Error zipping file ' . $rel);
			}
		}
	}
	catch(Exception $x) {
		try {
			@$zip->close();
		}
		catch(Exception $foo) {
		}
		throw $x;
	}
	if(@$zip->close() === false) {
		throw new Exception('ZipArchive::close() failed.');
	}
	if(is_file(ToolOptions::$destination)) {
		@unlink(ToolOptions::$destination);
	}
	if(file_exists(ToolOptions::$destination)) {
		throw new Exception(ToolOptions::$destination . ' exists and is not a deletable file.');
	}
	if(@rename($tempZip, ToolOptions::$destination) === false) {
		throw new Exception('Error creating the destination zip file.');
	}
	Console::WriteLine('done.');
	foreach($tempFiles as $tempFile) {
		if(is_file($tempFile)) {
			@unlink($tempFile);
		}
	}
	Console::WriteLine("Zip file created:\n" . ToolOptions::$destination);
	die(0);
}
catch(Exception $x) {
	foreach($tempFiles as $tempFile) {
		if(is_file($tempFile)) {
			@unlink($tempFile);
		}
	}
	DieForException($x);
}


class ToolOptions {

	/** The package handle.
	* @var string
	*/
	public static $packageHandle;

	/** The zip file name.
	* @var string
	*/
	public static $destination;
	
	/** The Transifex user name.
	* @var string
	*/
	public static $transifexUsername;

	/** The Transifex password.
	* @var string
	*/
	public static $transifexPassword;

	/** The Transifex project slug.
	* @var string
	*/
	public static $transifexProject;

	/** The Transifex resource slug.
	* @var string
	*/
	public static $transifexResource;

	/** Shall we work with Transifex?
	* @var bool
	*/
	public static $transifexDo;
	
	/** Initialize the default options. */
	public static function InitializeDefaults() {
		self::$packageHandle = '';
		self::$destination = '';
		self::$transifexUsername = '';
		self::$transifexPassword = '';
		self::$transifexProject = '';
		self::$transifexResource = '';
		self::$transifexDo = false;
	}

	/** Shows info about this tool. */
	public static function ShowIntro() {
		global $argv;
		Console::WriteLine($argv[0] . ' is a tool that creates a zip file from a package, ready to be submitted to the PRB.');
	}

	/** Get the list of the tool-specific options.
	* @return array
	*/
	public static function GetOptions(&$options) {
		$options['--package'] = array('description' => 'the package handle');
		$options['--destination'] = array('description' => 'the zip file name (of the directory that will contain it)');
		$options['--transifex-username'] = array('description' => 'the Transifex user name');
		$options['--transifex-password'] = array('description' => 'the Transifex password');
		$options['--transifex-project'] = array('description' => 'the Transifex project slug');
		$options['--transifex-resource'] = array('description' => 'the Transifex resource slug');
	}

	/** Parses an argument.
	* @param string $argument
	* @param string $value
	* @throws Exception Throws an Exception in case of invalid argument values.
	* @return boolean Return true if $argument is a tool-specific argument (ie it has been recognized), false otherwise.
	*/
	public static function ParseArgument($argument, $value) {
		switch($argument) {
			case '--package':
				self::$packageHandle = $value;
				return true;
			case '--destination':
				self::$destination = $value;
				return true;
			case '--transifex-username':
				self::$transifexUsername = $value;
				return true;
			case '--transifex-password':
				self::$transifexPassword = $value;
				return true;
			case '--transifex-project':
				self::$transifexProject = $value;
				return true;
			case '--transifex-resource':
				self::$transifexResource = $value;
				return true;
			default:
				return false;
		}
	}

	/** Checks the environment state.
	* @throws Exception Throws an Exception in case of errors.
	*/
	public static function ArgumentsRead() {
		if(!strlen(self::$packageHandle)) {
			Console::WriteLine('You need to specify the package handle. Use --help for getting help', true);
			die(1);
		}
		if(!strlen(self::$destination)) {
			self::$destination = Enviro::MergePath(Options::$WebrootFolder, 'packages', self::$packageHandle . '.zip');
		}
		elseif(is_dir(self::$destination)) {
			self::$destination = Enviro::MergePath(realpath(self::$destination), self::$packageHandle . '.zip');
		}
		if(!Enviro::IsFilenameWithoutPath(self::$packageHandle)) {
			Console::WriteLine("'" . self::$packageHandle . "' is not a valid package handle.", true);
			die(1);
		}
		if(strlen(self::$transifexUsername) || strlen(self::$transifexPassword) || strlen(self::$transifexProject) || strlen(self::$transifexResource)) {
			if(!strlen(self::$transifexUsername)) {
				Console::WriteLine('If you want to fetch translations from Transifex you have to specify the Transifex user name.', true);
				die(1);
			}
			if(!strlen(self::$transifexPassword)) {
				Console::WriteLine('If you want to fetch translations from Transifex you have to specify the Transifex password.', true);
				die(1);
			}
			if(!strlen(self::$transifexProject)) {
				Console::WriteLine('If you want to fetch translations from Transifex you have to specify the Transifex project slug.', true);
				die(1);
			}
			if(!strlen(self::$transifexResource)) {
				Console::WriteLine('If you want to fetch translations from Transifex you have to specify the Transifex resource slug.', true);
				die(1);
			}
			self::$transifexDo = true;
		}
		else {
			self::$transifexDo = false;
		}
		if(self::$transifexDo) {
			$commands = array('msgfmt');
			try {
				foreach($commands as $command) {
					Enviro::RunTool('msgfmt', '--version');
				}
			}
			catch(Exception $x) {
				Console::WriteLine('This tool requires the following gettext functions:', true);
				Console::WriteLine(implode(' ', $commands), true);
				switch(Enviro::GetOS()) {
					case Enviro::OS_LINUX:
						Console::WriteLine('Depending on your Linux distro, you could install it with the following command:', true);
						Console::WriteLine('sudo apt-get install gettext', true);
						die(1);
					case Enviro::OS_MAC_OSX:
						Console::WriteLine('MacPorts has a version of them.', true);
						Console::WriteLine('To install MacPorts please see this page: http://www.macports.org/install.php', true);
						Console::WriteLine('Once MacPorts is installed, you may run this command:', true);
						Console::WriteLine('sudo port install gettext', true);
						die(1);
					case Enviro::OS_WIN:
						Console::Write('There\'s a ready-to-use version on ftp.gnome.org. Would you like me to download it automatically? [Y/n] ', true);
						if(!Console::AskYesNo(true, true)) {
							Console::WriteLine('Please put gettext functions in the following folder:', true);
							Console::WriteLine(Options::$Win32ToolsFolder, true);
							die(1);
						}
						self::DownloadZip(
							'http://ftp.gnome.org/pub/gnome/binaries/win32/dependencies/gettext-runtime_0.18.1.1-1_win32.zip',
							array(
								'bin/intl.dll' => Options::$Win32ToolsFolder
							)
						);
						self::DownloadZip(
							'http://ftp.gnome.org/pub/gnome/binaries/win32/dependencies/gettext-tools-dev_0.18.1.1-1_win32.zip',
							array(
								'bin/libgettextlib-0-18-1.dll' => Options::$Win32ToolsFolder,
								'bin/libgettextsrc-0-18-1.dll' => Options::$Win32ToolsFolder,
								'bin/libgcc_s_dw2-1.dll' => Options::$Win32ToolsFolder,
								'bin/xgettext.exe' => Options::$Win32ToolsFolder,
								'bin/msgmerge.exe' => Options::$Win32ToolsFolder,
								'bin/msgfmt.exe' => Options::$Win32ToolsFolder
							)
						);
						break;
					default:
						die(1);
				}
			}
		}
	}

	/** Download a file and extract files to a local path.
	* @param string $url The url of the file to download.
	* @param array $filesToExtract Specify which files to extract (the array keys) and the folders where they should be saved (the values).
	* @throws Exception Throws an Exception in case of errors.
	*/
	private static function DownloadZip($url, $filesToExtract) {
		foreach(array_keys($filesToExtract) as $key) {
			if(!is_dir($filesToExtract[$key])) {
				if(!@mkdir($filesToExtract[$key], 0777, true)) {
					throw new Exception('Error creating the directory \'' . $filesToExtract[$key] . '\'');
				}
			}
			$fullFilename = Enviro::MergePath($filesToExtract[$key], basename($key));
			if(file_exists($fullFilename)) {
				unset($filesToExtract[$key]);
			}
			else {
				if(!is_writable($filesToExtract[$key])) {
					throw new Exception('The directory \'' . $filesToExtract[$key] . '\' is not writable');
				}
				$filesToExtract[$key] = $fullFilename;
			}
		}
		if(empty($filesToExtract)) {
			return;
		}
		Console::Write('Downloading ' . $url . '... ');
		if(!($hUrl = fopen($url, 'rb'))) {
			throw new Exception('fopen() failed!');
		}
		$bufferSize = 8 * 1024;
		try {
			$zipFile = Enviro::GetTemporaryFileName();
			if(!($hZipFile = fopen($zipFile, 'wb'))) {
				throw new Exception('Unable to write local temp file.');
			}
			try {
				while(!feof($hUrl)) {
					fwrite($hZipFile, fread($hUrl, $bufferSize));
				}
				@fflush($hZipFile);
				@fclose($hZipFile);
				@fclose($hUrl);
			}
			catch(Exception $x) {
				@fclose($hZipFile);
				@unlink($zipFile);
				throw $x;
			}
		}
		catch(Exception $x) {
			@fclose($hUrl);
			throw $x;
		}
		Console::WriteLine('done.');
		Console::Write('Extracting files... ');
		try {
			$hZip = @zip_open($zipFile);
			if(!is_resource($hZip)) {
				throw new Exception('zip_open() failed (error code: ' . $hZip . ')!');
			}
			while($hEntry = zip_read($hZip)) {
				if(!is_resource($hEntry)) {
					throw new Exception('zip_read() failed (error code: ' . $hEntry . ')!');
				}
				$name = zip_entry_name($hEntry);
				if(array_key_exists($name, $filesToExtract)) {
					$size = zip_entry_filesize($hEntry);
					if($size <= 0) {
						throw new Exception('zip entry ' . $name . ' is empty!');
					}
					file_put_contents($filesToExtract[$name], zip_entry_read($hEntry, $size));
					unset($filesToExtract[$name]);
				}
			}
			@zip_close($hZip);
			@unlink($zipFile);
		}
		catch(Exception $x) {
			@zip_close($hZip);
			@unlink($zipFile);
			throw $x;
		}
		if(!empty($filesToExtract)) {
			throw new Exception('Files not found in zip file: ' . implode(', ', array_keys($filesToExtract)));
		}
		Console::WriteLine('done.');
	}
}

class prbChecker {
	private static $forbiddenFunctions = array(
		'chmod', 'chown', 'eval', 'exec', 'file_put_contents', 'fwrite', 'json_decode', 'json_encode',
		'mysqli_bind_param', 'mysqli_bind_result', 'mysqli_client_encoding', 'mysqli_connect',
		'mysqli_disable_reads_from_master', 'mysqli_disable_rpl_parse', 'mysqli_driver',
		'mysqli_enable_reads_from_master', 'mysqli_enable_rpl_parse', 'mysqli_escape_string',
		'mysqli_execute', 'mysqli_fetch', 'mysqli_get_cache_stats', 'mysqli_get_metadata',
		'mysqli_master_query', 'mysqli_param_count', 'mysqli_report', 'mysqli_result',
		'mysqli_rpl_parse_enabled', 'mysqli_rpl_probe', 'mysqli_send_long_data', 'mysqli_set_opt',
		'mysqli_slave_query', 'mysqli_sql_exception', 'mysqli_stmt', 'mysqli_warning',
		'mysql_affected_rows', 'mysql_client_encoding', 'mysql_close', 'mysql_connect',
		'mysql_create_db', 'mysql_data_seek', 'mysql_db_name', 'mysql_db_query', 'mysql_drop_db',
		'mysql_errno', 'mysql_error', 'mysql_escape_string', 'mysql_fetch_array', 'mysql_fetch_assoc',
		'mysql_fetch_field', 'mysql_fetch_lengths', 'mysql_fetch_object', 'mysql_fetch_row',
		'mysql_field_flags', 'mysql_field_len', 'mysql_field_name', 'mysql_field_seek',
		'mysql_field_table', 'mysql_field_type', 'mysql_free_result', 'mysql_get_client_info',
		'mysql_get_host_info', 'mysql_get_proto_info', 'mysql_get_server_info', 'mysql_info',
		'mysql_insert_id', 'mysql_list_dbs', 'mysql_list_fields', 'mysql_list_processes',
		'mysql_list_tables', 'mysql_num_fields', 'mysql_num_rows', 'mysql_pconnect', 'mysql_ping',
		'mysql_query', 'mysql_real_escape_string', 'mysql_result', 'mysql_select_db', 'mysql_set_charset',
		'mysql_stat', 'mysql_tablename', 'mysql_thread_id', 'mysql_unbuffered_query', 'passthru',
		'printer_abort', 'printer_close', 'printer_create_brush', 'printer_create_dc', 'printer_create_font',
		'printer_create_pen', 'printer_delete_brush', 'printer_delete_dc', 'printer_delete_font',
		'printer_delete_pen', 'printer_draw_bmp', 'printer_draw_chord', 'printer_draw_elipse',
		'printer_draw_line', 'printer_draw_pie', 'printer_draw_rectangle', 'printer_draw_roundrect',
		'printer_draw_text', 'printer_end_doc', 'printer_end_page', 'printer_get_option', 'printer_list',
		'printer_logical_fontheight', 'printer_open', 'printer_select_brush', 'printer_select_font',
		'printer_select_pen', 'printer_set_option', 'printer_start_doc', 'printer_start_page',
		'printer_write', 'proc_close', 'proc_get_status', 'proc_nice', 'proc_open', 'proc_terminate',
		'rmdir', 'shell_exec', 'system'
	);
	public static function checkForbiddenFunctions($filename) {
		$phpCode = @file_get_contents($filename);
		if($phpCode === false) {
			throw new Exception("Error reading the file '$filename'.");
		}
		$tokens = token_get_all($phpCode);
		$count = count($tokens);
		foreach($tokens as $i => $token) {
			if(is_array($token)) {
				if(($token[0] === T_STRING) && (array_search(strtolower($token[1]), self::$forbiddenFunctions) !== false)) {
					for($j = $i + 1; $j < $count; $j++) {
						if((is_array($tokens[$j]) && $tokens[$j][0] == T_WHITESPACE)) {
							continue;
						}
						if((!is_array($tokens[$j])) && ($tokens[$j] === '(')) {
							Console::WriteLine("\nForbidden function found: {$token[1]}\nFile: $filename\nLine: {$token[2]}");
							Console::Write("Proceed anyway? ");
							if(!Console::AskYesNo()) {
								throw new Exception('Execution halted.');
							}
							break;
						}
					}
				}
			}
		}
	}
}

class Transifexer {
	private static function doCurl($url, $decodeJSON = true) {
		if(!function_exists('curl_init')) {
			throw new Exception('cURL extension not available.');
		}
		$fullUrl = 'http://www.transifex.com/api/2/project/' . ToolOptions::$transifexProject . '/resource/' . ToolOptions::$transifexResource . '/' . ltrim($url, '/');
		$hCurl = @curl_init($fullUrl);
		if($hCurl === false) {
			throw new Exception('curl_init() failed.');
		}
		$ok = true;
		if($ok && (@curl_setopt($hCurl, CURLOPT_BINARYTRANSFER, true) === false)) $ok = false;
		if($ok && (@curl_setopt($hCurl, CURLOPT_FAILONERROR, false) === false)) $ok = false;
		if($ok && (@curl_setopt($hCurl, CURLOPT_FOLLOWLOCATION, true) === false)) $ok = false;
		if($ok && (@curl_setopt($hCurl, CURLOPT_HEADER, false) === false)) $ok = false;
		if($ok && (@curl_setopt($hCurl, CURLINFO_HEADER_OUT, false) === false)) $ok = false;
		if($ok && (@curl_setopt($hCurl, CURLOPT_NOPROGRESS, true) === false)) $ok = false;
		if($ok && (@curl_setopt($hCurl, CURLOPT_RETURNTRANSFER, true) === false)) $ok = false;
		if($ok && (@curl_setopt($hCurl, CURLOPT_SSL_VERIFYPEER, false) === false)) $ok = false;
		if($ok && (@curl_setopt($hCurl, CURLOPT_USERPWD, ToolOptions::$transifexUsername . ':' . ToolOptions::$transifexPassword) === false)) $ok = false;
		if(!$ok) {
			$err = @curl_error($hCurl);
			@curl_close($hCurl);
			if(!strlen($err)) {
				throw new Exception('curl_setopt() failed');
			}
			throw new Exception('curl_setopt() failed: ' . $err);
		}
		$result = @curl_exec($hCurl);
		if($result === false) {
			$err = @curl_error($hCurl);
			@curl_close($hCurl);
			if(!strlen($err)) {
				throw new Exception('curl_exec() failed');
			}
			throw new Exception('curl_exec() failed: ' . $err);
		}
		$info = @curl_getinfo($hCurl);
		$rc = (is_array($info) && array_key_exists('http_code', $info) && is_numeric($info['http_code'])) ? intval($info['http_code']) : 0;
		@curl_close($hCurl);
		if(($rc < 200) || ($rc >= 300)) {
			throw new Exception('curl_exec() with code ' . $rc);
		}
		if($decodeJSON) {
			if(!function_exists('json_decode')) {
				throw new Exception('PECL json not available.');
			}
			$js = @json_decode($result, true);
			if(is_null($js)) {
				throw new Exception('Unable to decode the string ' . $result);
			}
			$result = $js;
		}
		return $result;
	}
	public static function getLanguages($minPercentage = 0) {
		$info = self::doCurl('');
		$sourceLanguageCode = $info['source_language_code'];
		$languages = self::doCurl('stats');
		$result = array();
		foreach($languages as $languageCode => $languageInfo) {
			if($languageCode == $sourceLanguageCode) {
				continue;
			}
			if($languageInfo['completed'] >= $minPercentage) {
				$result[] = $languageCode;
			}
		}
		return $result;
	}
	public static function downloadLanguage($languageCode) {
		$result = self::doCurl("translation/$languageCode");
		if((!is_array($result)) || empty($result['content'])) {
			throw new Exception('Error! ' . print_r($result, 1));
		}
		return $result['content'];
	}
}

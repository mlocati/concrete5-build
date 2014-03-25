<?php /* Common functions and classes used in build scripts */

defined('C5_BUILD') or die('This script should not be called directly.');

SetupIni();

if(!defined('CONSOLE_LINELENGTH')) {
	define('CONSOLE_LINELENGTH', 80);
}
if(!defined('PHP_MIN_VERSION')) {
	define('PHP_MIN_VERSION', '5.1');
}
if(version_compare(PHP_VERSION, PHP_MIN_VERSION, '<')) {
	Console::WriteLine('Minimum required php version: ' . PHP_MIN_VERSION . ', your is ' . PHP_VERSION, true);
	die(1);
}

Options::Initialize();


/** Initializes the enviro (error reporting, timezone, …). */
function SetupIni() {
	@ini_set('error_reporting', E_ALL & ~E_DEPRECATED);
	$ddtz = @date_default_timezone_get();
	if((!is_string($ddtz)) || (!strlen($ddtz))) {
		$ddtz = 'UTC';
	}
	@date_default_timezone_set($ddtz);
	@ini_set('track_errors', true);
	@ini_set('html_errors', false);
	@ini_set('display_errors', 'stderr');
	@ini_set('display_startup_errors', true);
	@ini_set('log_errors', false);
	set_error_handler('ErrorCatcher');
}

/** Catches a php error/warning and raises an exception.
* @param int $errNo The level of the error raised.
* @param string $errstr the error message.
* @param string $errfile The filename that the error was raised in.
* @param int $errline The line number the error was raised at.
* @throws Exception Throws an Exception when an error is detected during the script execution.
*/
function ErrorCatcher($errno, $errstr, $errfile, $errline) {
	throw new Exception("$errstr in $errfile at line $errline", $errno);
}

/** End the execution for the specified exception.
* @param Exception $exception
*/
function DieForException($exception) {
	Console::WriteLine($exception->getMessage(), true);
	if(is_string(Options::$InitialFolder)) {
		@chdir(Options::$InitialFolder);
	}
	die(($exception->getCode() == 0) ? 1 : $exception->getCode());
}


/** Static class holding options. */
class Options {

	/** The current folder.
	* @var string
	*/
	public static $InitialFolder;

	/** The folder containing this script.
	* @var string
	*/
	public static $BuildFolder;

	/** The folder containing Windows-specific tools.
	* @var string
	*/
	public static $Win32ToolsFolder;

	/** The directory containing the web part of concrete5.
	* @var string
	*/
	public static $WebrootFolder;

	/** Show help about the script.
	* @param bool $forInvalidArgs true if we're showning help when we've encountered some unknown arguments (default: false).
	*/
	private static function ShowHelp($forInvalidArgs = false) {
		global $argv;
		if(!$forInvalidArgs) {
			if(class_exists('ToolOptions') && method_exists('ToolOptions', 'ShowIntro')) {
				ToolOptions::ShowIntro();
				Console::WriteLine();
				Console::WriteLine();
			}
		}
		Console::WriteLine('### AVAILABLE OPTIONS');
		$options = array();
		$nameMaxLength = 0;
		foreach(self::GetOptions() as $name => $info) {
			if(isset($info['helpValue'])) {
				$name .= '=' . $info['helpValue'];
			}
			$nameMaxLength = max($nameMaxLength, strlen($name));
			$options[] = array('name' => $name, 'description' => $info['description']);
		}
		foreach($options as $option) {
			Console::Write($option['name'] . str_repeat(' ', 1 + $nameMaxLength - strlen($option['name'])));
			$still = $option['description'];
			$usable = CONSOLE_LINELENGTH - 1 - $nameMaxLength - 1;
			if($usable <= 5) {
				Console::WriteLine($still);
			}
			else {
				$pre = '';
				while(strlen($still)) {
					if(strlen($still) <= $usable) {
						Console::WriteLine($pre . trim($still));
						break;
					}
					$chunk = substr($still, 0, $usable);
					$p = strrpos($chunk, ' ');
					if(($p > 5) && ($p > $usable - 9)) {
						$chunk = substr($chunk, 0, $p);
					}
					Console::WriteLine($pre . trim($chunk));
					$still = substr($still, strlen($chunk));
					if($pre == '') {
						$pre = str_repeat(' ', 1 + $nameMaxLength);
					}
				}
			}
		}
		if(!$forInvalidArgs) {
			if(class_exists('ToolOptions') && method_exists('ToolOptions', 'ShowExamples')) {
				Console::WriteLine();
				Console::WriteLine();
				Console::WriteLine('### EXAMPLES');
				ToolOptions::ShowExamples();
			}
		}
	}

	/** Get the available options.
	* @return array Returns an array whose keys are the <b>option name</b> and the values are arrays with:<ul>
	*	<li>string <b>description</b> description [required]</li>
	*	<li>string <b>helpValue</b> value for option when showing options help [optional]</li>
	* </ul>
	*/
	private static function GetOptions() {
		$options = array();
		$options['--help'] = array('description' => 'show the help for this command.');
		if(!class_exists('ToolOptions') || !property_exists('ToolOptions', 'NeedWebRoot') || ToolOptions::$NeedWebRoot) {
			$options['--webroot'] = array('helpValue' => '<folder>', 'description' => 'set the web root of concrete5 (default: try to automatically detect it)');
		}
		if(class_exists('ToolOptions') && method_exists('ToolOptions', 'GetOptions')) {
			ToolOptions::GetOptions($options);
		}
		return $options;
	}

	/** Read the command line arguments.
	* @throws Exception Throws an Exception in case of parameter errors.
	*/
	public static function Initialize() {
		global $argv;
		try {
			// Let's initialize constant/default values.
			self::$InitialFolder = getcwd();
			self::$BuildFolder = dirname(__FILE__);
			self::$Win32ToolsFolder = Enviro::MergePath(self::$BuildFolder, 'win32tools');
			self::$WebrootFolder = false;
			foreach(array(getcwd(), dirname(__FILE__)) as $base) {
			foreach(array('../web', '..', '../..', 'web', '.') as $rel) {
					$tmp = @realpath(Enviro::MergePath($base, $rel));
					if(is_dir($tmp) && is_file(Enviro::MergePath($tmp, 'index.php')) && is_dir(Enviro::MergePath($tmp, 'concrete')) && is_file(Enviro::MergePath($tmp, 'concrete', 'dispatcher.php'))) {
						self::$WebrootFolder = $tmp;
						break;
					}
				}
				if(self::$WebrootFolder !== false) {
					break;
				}
			}
			if(self::$WebrootFolder === false) {
				self::$WebrootFolder = realpath(dirname(dirname(__FILE__)));
			}
			if(class_exists('ToolOptions') && method_exists('ToolOptions', 'InitializeDefaults')) {
				ToolOptions::InitializeDefaults();
			}
			// Let's analyze the command line arguments
			foreach($argv as $argi => $arg) {
				if($argi == 0) {
					continue;
				}
				$p = strpos($arg, '=');
				$name = strtolower(($p === false) ? $arg : substr($arg, 0, $p));
				$value = ($p === false) ? '' : substr($arg, $p + 1);
				switch($name) {
					case '--help':
						self::ShowHelp();
						die(0);
					case '--webroot':
						if(class_exists('ToolOptions') && property_exists('ToolOptions', 'NeedWebRoot') && !ToolOptions::$NeedWebRoot) {
							Console::WriteLine("Invalid argument '$name'", true);
							self::ShowHelp(true);
							die(1);
						}
						if(!strlen($value)) {
							throw new Exception("Argument '$name' requires a value (a valid path).");
						}
						$dir = @realpath($value);
						if(($dir === false) || (!is_dir($dir))) {
							throw new Exception("Argument '$name' received an invalid path ('$value').");
						}
						self::$WebrootFolder = $dir;
						break;
					default:
						if(!(class_exists('ToolOptions') && method_exists('ToolOptions', 'ParseArgument') && ToolOptions::ParseArgument($name, $value))) {
							Console::WriteLine("Invalid argument '$name'", true);
							self::ShowHelp(true);
							die(1);
						}
						break;
				}
			}
			if(class_exists('ToolOptions') && method_exists('ToolOptions', 'ArgumentsRead')) {
				ToolOptions::ArgumentsRead();
			}
		}
		catch(Exception $x) {
			DieForException($x);
		}
	}

	/** Checks if the currently configured web root is valid. Throws an Exception if not.
	* @throws Exception Throws an Exception if the currently configured web root not is valid.
	*/
	public static function CheckWebroot() {
		self::GetVersionOfConcrete5();
	}

	/** Return the boolean value of a command line option value.
	* @param string $argumentName The argument name.
	* @param string $argumentValue The argument value.
	* @return boolean
	* @throws Exception Throws an Exception if the argument value can't be converted to a boolean value.
	*/
	public static function ArgumentToBool($argumentName, $argumentValue) {
		$v = @trim($argumentValue);
		if(!strlen($v)) {
			throw new Exception("Argument '$argumentName' requires a boolean value (yes or no).");
		}
		$bool = self::StringToBool($argumentValue);
		if(is_null($bool)) {
			throw new Exception("Argument '$argumentName' requires a boolean value (yes or no), given '$argumentValue'.");
		}
		return $bool;
	}

	/** Convert a string into boolean (return null in case of conversion error).
	* @param string $value The value to be analyzed
	* @return boolean|null
	*/
	public static function StringToBool($value) {
		$v = @trim($value);
		if(strlen($v)) {
			switch(strtolower($v)) {
				case 'y':
				case 'yes':
				case 'true':
				case 'on':
				case '1':
					return true;
				case 'n':
				case 'no':
				case 'false':
				case 'off':
				case '0':
					return false;
			}
		}
		return null;
	}

	/** Retrieves the version of a concrete5 installation.
	* @throws Exception Throws an Exception in case of errors.
	* @return string
	*/
	public static function GetVersionOfConcrete5() {
		return self::GetVersionOfConcrete5In(self::$WebrootFolder);
	}

	/** Retrieves the version of a concrete5 installation given its root folder.
	* @param string $webroot The path of the webroot containing concrete5.
	* @throws Exception Throws an Exception in case of errors.
	* @return string
	*/
	public static function GetVersionOfConcrete5In($webroot) {
		if(!defined('C5_EXECUTE')) {
			define('C5_EXECUTE', true);
		}
		if(!is_dir($webroot)) {
			throw new Exception($webroot . ' is not the valid concrete5 web root directory (it does not exist).');
		}
		if(!is_file($fn = Enviro::MergePath($webroot, 'concrete/config/version.php'))) {
			throw new Exception($webroot . ' is not the valid concrete5 web root directory (the version file does not exist).');
		}
		$fc = Enviro::GetEvaluableContent($fn);
		@ob_start();
		$evalued = eval($fc);
		@ob_end_clean();
		if($evalued === false) {
			throw new Exception("Unable to parse the version of CONCRETE5 (file '$fn').");
		}
		if(empty($APP_VERSION)) {
			throw new Exception("Unable to parse the concrete5 version file '$fn'.");
		}
		return $APP_VERSION;
	}
}

/** Console-related functions. */
class Console {

	/** Echoes a string to the console.
	* @param string $str The string to be printed.
	* @param bool $isErr Set to true to echo to stderr, false to echo to stdout.
	*/
	public static function Write($str, $isErr = false) {
		$hOut = fopen($isErr ? 'php://stderr' : 'php://stdout', 'wb');
		fwrite($hOut, $str);
		fflush($hOut);
		fclose($hOut);
	}

	/** Echoes a line to the console.
	* @param string $str The string to be printed (a new-line will be appended to it).
	* @param bool $isErr Set to true to echo to stderr, false to echo to stdout.
	*/
	public static function WriteLine($str = '', $isErr = false) {
		self::Write($str . PHP_EOL, $isErr);
	}

	/** Reads a line from the command line.
	* @return string
	*/
	public static function ReadLine() {
		$hIn = fopen('php://stdin', 'r');
		$line = @fgets($hIn);
		@fclose($hIn);
		return ($line === false) ? '' : $line;
	}

	/** Read a yes/no answer from the command line.
	* @param bool|null $default What to return if user enter an empty string (if null: no default value).
	* @param bool $isErr Set to true to echo to stderr, false to echo to stdout.
	* @return bool
	*/
	public static function AskYesNo($default = null, $msgIsErr = false) {
		for(;;) {
			switch(strtolower(trim(self::ReadLine()))) {
				case 'y':
				case 'yes':
					return true;
				case 'n':
				case 'no':
					return false;
				case '':
					if(!is_null($default)) {
						return $default;
					}
					break;
			}
			self::Write('Please answer with Y[es] or N[o]: ', $msgIsErr ? true : false);
		}
	}
}

/** Functions related to the current executing environment. */
class Enviro {
	/** OS kind: Linux.
	* @var int
	*/
	const OS_LINUX = 1;

	/** OS kind: Mac OS X.
	* @var int
	*/
	const OS_MAC_OSX = 2;

	/** OS kind: Windows.
	* @var int
	*/
	const OS_WIN = 3;

	/** OS kind: FreeBSD.
	* @var int
	*/
	const OS_FREEBSD = 4;

	/** OS kind: NetWare.
	* @var int
	*/
	const OS_NETWARE = 5;

	/** OS kind: Sun Solaris.
	* @var int
	*/
	const OS_SUN = 6;

	/** OS kind: MacOS (old).
	* @var int
	*/
	const OS_MAC_OLD = 7;

	/** OS kind: HP-UX.
	* @var int
	*/
	const OS_HPUX = 8;

	/** OS kind: AIX.
	* @var int
	*/
	const OS_AIX = 9;

	/** OS kind: Cygwin.
	* @var int
	*/
	const OS_CYGWIN = 10;

	/** OS kind: MSYS.
	* @var int
	*/
	const OS_MSYS = 11;

	/** OS kind: UWIN.
	* @var int
	*/
	const OS_UWIN = 12;

	/** OS kind: IRIX.
	* @var int
	*/
	const OS_IRIX = 13;

	/** OS kind: MINIX.
	* @var int
	*/
	const OS_MINIX = 14;

	/** OS kind: DragonFlyBSD.
	* @var int
	*/
	const OS_DRAGONFLYBSD = 15;

	/** OS kind: unknown.
	* @var int
	*/
	const OS_UNKNOWN = 0;

	/** Return the OS kind (one of the Enviro::OS_… constants).
	* Data has been taken from php source code and from http://en.wikipedia.org/wiki/Uname
	* @return int
	*/
	public static function GetOS() {
		if((stripos(PHP_OS , 'Linux') === 0) || (stripos(PHP_OS, 'kFreeBSD') !== false)) {
			return self::OS_LINUX;
		}
		elseif((stripos(PHP_OS , 'Darwin') === 0) || (stripos(PHP_OS , 'OSX') === 0)) {
			return self::OS_MAC_OSX;
		}
		elseif(stripos(PHP_OS , 'WIN') === 0) {
			return self::OS_WIN;
		}
		elseif(stripos(PHP_OS , 'FreeBSD') === 0) {
			return self::OS_FREEBSD;
		}
		elseif(stripos(PHP_OS , 'NetWare') === 0) {
			return self::OS_NETWARE;
		}
		elseif((stripos(PHP_OS , 'Sun') === 0) || (stripos(PHP_OS , 'Solaris') === 0)) {
			return self::OS_NETWARE;
		}
		elseif(stripos(PHP_OS , 'Mac') === 0) {
			return self::OS_MAC_OLD;
		}
			elseif(stripos(PHP_OS , 'HP-UX') === 0) {
			return self::OS_HPUX;
		}
			elseif(stripos(PHP_OS , 'AIX') === 0) {
			return self::OS_AIX;
		}
			elseif(stripos(PHP_OS , 'CYGWIN') === 0) {
			return self::OS_CYGWIN;
		}
		elseif(stripos(PHP_OS , 'MINGW') === 0) {
			return self::OS_MSYS;
		}
			elseif(stripos(PHP_OS , 'UWIN') === 0) {
			return self::OS_UWIN;
		}
		elseif(stripos(PHP_OS , 'IRIX') === 0) {
			return self::OS_IRIX;
		}
			elseif(stripos(PHP_OS , 'Minix') === 0) {
			return self::OS_MINIX;
		}
		elseif(stripos(PHP_OS , 'DragonFly') === 0) {
			return self::OS_DRAGONFLYBSD;
		}
		else {
			return self::OS_UNKNOWN;
		}
	}

	/** Check if a string is a valid filename (without any path info).
	* @param string $filename The string to be checked.
	* @return boolean
	*/
	public static function IsFilenameWithoutPath($filename) {
		$filename = is_null($filename) ? '' : strval($filename);
		if(!strlen($filename)) {
			return false;
		}
		if($filename != trim($filename)) {
			return false;
		}
		switch($filename) {
			case '.':
			case '..':
				return false;
		}
		if(strpbrk($filename, '\\/:?|"<>*') !== false) {
			return false;
		}
		return true;
	}

	/** Merges OS paths.
	* @param {string} Any number of paths to be merged.
	* @return string
	* @throws Exception Throws an Exception if no arguments is given.
	*/
	public static function MergePath() {
		$args = func_get_args();
		switch(count($args)) {
			case 0:
				throw new Exception(__CLASS__ . '::' . __METHOD__ . ': missing arguments');
			case 1:
				return str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $args[0]);
			default:
				$path = '';
				foreach($args as $arg) {
					if(strlen($arg)) {
						$arg = str_replace('\\', '/', $arg);
						if(!strlen($path)) {
							$path = $arg;
						}
						else {
							$path = rtrim($path, '/') . '/' . ltrim($arg, '/');
						}
					}
				}
				return str_replace('/', DIRECTORY_SEPARATOR, $path);
		}
	}

	/** Checks if a path is absolute.
	* @param string $path
	* @return boolean
	*/
	public static function PathIsAbsolute($path) {
		if(strlen($path)) {
			if(($path[0] == '/') || ($path[0] == '\\')) {
				return true;
			}
			switch(Enviro::GetOS()) {
				case Enviro::OS_WIN:
					if(strpos($path, ':') !== false) {
						return true;
					}
					break;
			}
		}
		return false;
	}

	/** Return the system temporary directory (or '' if it can't be found).
	* @return string
	* @throws Exception Throws an Exception in case the temporary directory can't be found.
	*/
	public static function GetTemporaryDirectory() {
		$eligible = array();
		if(function_exists('sys_get_temp_dir')) {
			$eligible[] = @sys_get_temp_dir();
		}
		foreach(array('TMPDIR', 'TEMP', 'TMP') as $env) {
			$eligible[] = @getenv($env);
		}
		foreach($eligible as $f) {
			if(is_string($f) && strlen($f)) {
				$f2 = @realpath($f);
				if(($f2 !== false) && @is_dir($f2)) {
					return $f2;
				}
			}
		}
		throw new Exception('The temporary directory cannot be found.');
	}

	/** Create a temporary file.
	* @return string
	* @throws Exception Throws an Exception in case the temporary file couldn't be created.
	*/
	public static function GetTemporaryFileName() {
		$tempFolder = self::GetTemporaryDirectory();
		$tempFile = @tempnam($tempFolder, 'c5-');
		if($tempFile === false) {
			global $php_errormsg;
			throw new Exception("Unable to create a temporary file in '$tempFolder': $php_errormsg");
		}
		return $tempFile;
	}

	/** Deletes all the files and subfolders in the specified folder.
	* @param unknown $folder
	* @throws Exception Throws an Exception in case of errors.
	*/
	public static function EmptyFolder($folder) {
		self::_emptyFolder($folder, false);
	}

	/** Empties a folder and optionally delete it.
	* @param string $folder The folder to delete/empty.
	* @param bool $delete Should the folder be deleted (true) or just emptied (false)?
	* @throws Exception Throws an Exception in case of errors.
	*/
	private static function _emptyFolder($folder, $delete) {
		$s = @realpath($folder);
		if(($s === false) || (!is_dir($s))) {
			throw new Exception("'" . (($s === false) ? $folder : $s) . "' is not a directory.");
		}
		$folder = $s;
		if(!is_writable($folder)) {
			throw new Exception("'$folder' is not writable!");
		}
		$subFolders = array();
		$subFiles = array();
		if(!($hDir = @opendir($folder))) {
			throw new Exception("Error opening the folder '$folder'.");
		}
		while($item = readdir($hDir)) {
			switch($item) {
				case '.':
				case '..':
					break;
				default:
					$fullItem = Enviro::MergePath($folder, $item);
					if(is_dir($fullItem)) {
						$subFolders[] = $fullItem;
					}
					else {
						$subFiles[] = $fullItem;
					}
					break;
			}
		}
		closedir($hDir);
		foreach($subFolders as $subFolder) {
			self::_emptyFolder($subFolder, true);
		}
		foreach($subFiles as $subFile) {
			if(!@unlink($subFile)) {
				throw new Exception("The file '$subFile' could not be deleted.");
			}
		}
		if($delete) {
			if(!@rmdir($folder)) {
				throw new Exception("The folder '$folder' could not be deleted.");
			}
		}
	}

	/** Return the NPM package name for the specified NodeJS command.
	* @param string $command The command for which you want the package name.
	* @return string
	*/
	private static function GetNodeJSPackageName($command) {
		switch($command) {
			case 'uglifyjs':
				return 'uglify-js';
			case 'lessc':
				return 'less';
			default:
				return $command;
		}
	}

	/** Check if the specified nodejs command is available.
	* @param string $comamnd The command to be verified.
	* @return bool Return false if the command is not available (the user has already been warned about it), true if all is ok.
	*/
	public static function CheckNodeJS($command) {
		$os = self::GetOS();
		$hasCommand = $hasNPM = false;
		switch($os) {
			case self::OS_WIN:
				try {
					self::Run('node.exe', '--version');
					$hasNPM = true;
					if(strlen(self::FindPathFor($command, 'cmd'))) {
						$hasCommand = true;
					}
				}
				catch(Exception $x) {
				}
				break;
			default:
				try {
					self::Run($command, '/dev/null');
					$hasCommand = $hasNPM = true;
				}
				catch(Exception $x) {
					try {
						self::Run('npm', '--version');
						$hasNPM = true;
					}
					catch(Exception $x) {
					}
				}
				break;
		}
		if($hasCommand) {
			return true;
		}
		else {
			$package = self::GetNodeJSPackageName($command);
			Console::WriteLine("In order to use this script you need the '$package' package of nodejs installed on your machine", true);
			if($hasNPM) {
				Console::WriteLine('In order to install it simply open a command line shell and type:', true);
			}
			else {
				Console::WriteLine("You first need to install nodejs and npm.", true);
				switch($os) {
					case self::OS_LINUX:
						Console::WriteLine('Depending on your Linux distro, you could install it with the following command:', true);
						Console::WriteLine('sudo apt-get install npm', true);
						Console::WriteLine('or you can get it from http://nodejs.org/download/', true);
						break;
					case self::OS_WIN:
						Console::WriteLine('You can download it from http://nodejs.org/download/ (please choose the Windows Installer).', true);
						break;
					case self::OS_MAC_OSX:
						Console::WriteLine('You can download it from http://nodejs.org/download/ (please choose the Mac OS X Installer).', true);
						break;
					default:
						Console::WriteLine('You can download it from http://nodejs.org/download/', true);
						break;
				}
				Console::WriteLine('Once you installed nodejs and npm, simply open a command line shell and type:', true);
			}
			switch($os) {
				case self::OS_LINUX:
				case self::OS_MAC_OSX:
					Console::WriteLine("sudo npm install $package --global", true);
					break;
				default;
					Console::WriteLine("npm install $package --global", true);
					break;
			}
			return false;
		}
	}

	/** Execute a nodejs command.
	* @param string $command The command to execute.
	* @param string|array $arguments The argument(s) of the program.
	* @param int|array $goodResult Valid return code(s) of the command (default: 0).
	* @param out array $output The output from stdout/stderr of the command.
	* @return int Return the command result code.
	* @throws Exception Throws an exception in case of errors.
	*/
	public static function RunNodeJS($command, $arguments = '', $goodResult = 0, &$output = null) {
		if(self::GetOS() == self::OS_WIN) {
			$cmdName = self::FindPathFor($command, 'cmd');
			if(!strlen($cmdName)) {
				throw new Exception("Unable to find npm command $command");
			}
			$fullCommandPath = self::MergePath(dirname($cmdName), 'node_modules', self::GetNodeJSPackageName($command), 'bin', $command);
			if(!is_file($fullCommandPath)) {
				throw new Exception('The executable file ' . $fullCommandPath . ' does not exists.');
			}
			if(!is_array($arguments)) {
				if((!is_string($arguments)) || ($arguments === '')) {
					$arguments = array();
				}
				else {
					$arguments = array($arguments);
				}
			}
			$arguments = array_merge(array(escapeshellarg($fullCommandPath)), $arguments);
			$command = 'node.exe';
		}
		return self::Run($command, $arguments, $goodResult, $output);
	}

	/** Execute a shell command (build-in if *nix; an exe file in the Options::$Win32ToolsFolder folder or under the PATH in the if we're in Windows).
	* @param string $command The command to execute (assumes an exe in Options::$Win32ToolsFolder folder if OS is Windows).
	* @param string|array $arguments The argument(s) of the program.
	* @param int|array $goodResult Valid return code(s) of the command (default: 0).
	* @param out array $output The output from stdout/stderr of the command.
	* @return int Return the command result code.
	* @throws Exception Throws an exception in case of errors.
	*/
	public static function RunTool($command, $arguments = '', $goodResult = 0, &$output = null) {
		if(self::GetOS() == self::OS_WIN) {
			$fullname = self::FindPathFor($command, 'exe', Options::$Win32ToolsFolder);
			if(!is_file($fullname)) {
				throw new Exception('The executable file ' . $fullname . ' does not exists.');
			}
			$command = $fullname;
		}
		return self::Run($command, $arguments, $goodResult, $output);
	}

	/** Execute a command.
	* @param string $command The command to execute.
	* @param string|array $arguments The argument(s) of the program.
	* @param int|array $goodResult Valid return code(s) of the command (default: 0).
	* @param out array $output The output from stdout/stderr of the command.
	* @return int Return the command result code.
	* @throws Exception Throws an exception in case of errors.
	*/
	public static function Run($command, $arguments = '', $goodResult = 0, &$output = null) {
		$line = escapeshellarg($command);
		if(is_array($arguments)) {
			if(count($arguments)) {
				$line .= ' ' . implode(' ', $arguments);
			}
		}
		else {
			$arguments = (string)$arguments;
			if(strlen($arguments)) {
				$line .= ' ' . $arguments;
			}
		}
		$output = array();
		exec($line . ' 2>&1', $output, $rc);
		if(!@is_int($rc)) {
			$rc = -1;
		}
		if(!is_array($output)) {
			$output = array();
		}
		if(is_array($goodResult)) {
			if(array_search($rc, $goodResult) === false) {
				throw new Exception("$command failed: " . implode("\n", $output));
			}
		}
		elseif($rc != $goodResult) {
			throw new Exception("$command failed: " . implode("\n", $output));
		}
		return $rc;
	}

	/** Find the path of a command in system paths.
	* @param string $command The command to find.
	* @param string $addExtensionForWindows The extension to be added if current OS is Windows (default: exe)
	* @param string|array A directory (or a list of directories) where to search the program.
	* @return Returns the full path of the command (or an empty string if not found).
	*/
	private static function FindPathFor($command, $addExtensionForWindows = 'exe', $additionalFolders = array()) {
		$checkFolders = array();
		if(is_array($additionalFolders)) {
			$checkFolders = array_merge($checkFolders, $additionalFolders);
		}
		elseif(is_string($additionalFolders)) {
			$checkFolders[] = $additionalFolders;
		}
		$path = @getenv('PATH');
		if(($path !== false) && strlen($path)) {
			$checkFolders = array_merge($checkFolders, explode(PATH_SEPARATOR, $path));
		}
		$folders = array();
		foreach($checkFolders as $f) {
			if(strlen($f) && @is_dir($f)) {
				$f2 = @realpath($f);
				if($f2 !== false) {
					$folders[] = $f2;
				}
			}
		}
		if(self::GetOS() == self::OS_WIN) {
			$addExtensionForWindows = is_string($addExtensionForWindows) ? ltrim($addExtensionForWindows, '.') : '';
			if(strlen($addExtensionForWindows)) {
				$command .= '.' . $addExtensionForWindows;
			}
		}
		foreach($folders as $folder) {
			$fullname = self::MergePath($folder, $command);
			if(@is_file($fullname)) {
				return $fullname;
			}
		}
		return '';
	}

	/** Gets the content of a php file which may be passed to the eval() function.
	* @param string $phpFilename The source php file.
	* @throws Exception Throws an Exception in case of errors.
	* @return string
	*/
	public static function GetEvaluableContent($phpFilename) {
		$fc = @file_get_contents($phpFilename);
		if($fc === false) {
			global $php_errormsg;
			throw new Exception("Unable to read file '$phpFilename': $php_errormsg");
		}
		$fc = str_replace('<?', '<?php', str_replace('<?php', '<?', $fc));
		$fc = preg_replace('/<\?php\s*=s*/', '<?php echo ', $fc);
		$p = strpos($fc, $s = '<?php');
		if($p === false) {
			throw new Exception("Unable to parse the file '$phpFilename'.");
		}
		return trim(substr($fc, $p + strlen($s)));
	}

	/** Returns the content of a directory.
	* @param string $dir The directory to read from.
	* @param bool $recursive Set to true to parse subfolders (matching $dirRXPattern is specified).
	* @param string $dirRXPattern A regular expression pattern to filter directory names (defaults: exclude directories starting with dot).
	* @param string $fileRXPattern A regular expression pattern to filter file names (defaults: exclude files starting with dot).
	* @throws Exception Throws an Exception in case of errors
	* @return array Returns an array with the following keys:<ul>
	*	<li>array <b>dirsFull</b>: full name of the sub-directories found</li>
	*	<li>array <b>dirsRel</b>: relative name of the sub-directories found</li>
	*	<li>array <b>filesFull</b>: full name of the files found</li>
	*	<li>array <b>filesRel</b>: relative name of the files found</li>
	* </ul>
	*/
	public static function GetDirectoryContent($dir, $recursive = false, $dirRXPattern = '/^[^.]/', $fileRXPattern = '/^[^.]/') {
		$dirFull = realpath($dir);
		if(($dirFull === false) ||(!is_dir($dirFull))) {
			throw new Exception("Couldn't find the directory $dir");
		}
		$result = array('dirsFull' => array(), 'dirsRel' => array(), 'filesFull' => array(), 'filesRel' => array());
		self::_getDirContent($result, $dirFull, '', $recursive, $dirRXPattern, $fileRXPattern);
		return $result;
	}

	/** Helper function for self::GetDirectoryContent
	* @param array $result
	* @param string $dirFull
	* @param string $dirRel
	* @param bool $recursive
	* @param string $dirRXPattern
	* @param string $fileRXPattern
	* @throws Exception
	*/
	private static function _getDirContent(&$result, $dirFull, $dirRel, $recursive, $dirRXPattern, $fileRXPattern) {
		$recurseInto = array();
		if(!($hDir = @opendir($dirFull))) {
			throw new Exception("Couldn't read the directory $dirname");
		}
		try {
			while($item = @readdir($hDir)) {
				switch($item) {
					case '.':
					case '..':
						break;
					default:
						$itemRel = (strlen($dirRel) ? "$dirRel/" : '') . $item;
						$itemFull = self::MergePath($dirFull, $item);
						if(is_dir($itemFull)) {
							if((!strlen($dirRXPattern)) || preg_match($dirRXPattern, $item)) {
								$result['dirsFull'][] = $itemFull;
								$result['dirsRel'][] = $itemRel;
								if($recursive) {
									$recurseInto[] = array('full' => $itemFull, 'rel' => $itemRel);
								}
							}
						}
						else {
							if((!strlen($fileRXPattern)) || preg_match($fileRXPattern, $item)) {
								$result['filesFull'][] = $itemFull;
								$result['filesRel'][] = $itemRel;
							}
						}
						break;
				}
			}
			@closedir($hDir);
			$hDir = null;
			foreach($recurseInto as $s) {
				self::_getDirContent($result, $s['full'], $s['rel'], $recursive, $dirRXPattern, $fileRXPattern);
			}
		}
		catch(Exception $x) {
			if($hDir) {
				@closedir($hDir);
				$hDir = null;
			}
			throw $x;
		}
	}
}

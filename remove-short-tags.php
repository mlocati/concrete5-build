<?php

define('C5_BUILD', true);

if(version_compare(PHP_VERSION, '5.1', '<')) {
	Console::WriteLine('Minimum required php version: 5.1, your is ' . PHP_VERSION, true);
	die(1);
}

require_once dirname(__FILE__) . '/base.php';

$sot = ini_get('short_open_tag');
if(empty($sot)) {
	Console::WriteLine('short_open_tag must be enabled. You can do this in php.ini or in the command-line (eg `php -d short_open_tag=On ...', true);
	die(1);
}
unset($sot);

class Options extends OptionsBase {
	
	const BEHAVIOUR_DONTOVERWRITE = 1;
	const BEHAVIOUR_OVERWRITE = 2;
	const BEHAVIOUR_RECREATE = 3;

	public static $DestinationFolder;

	private static $DestinationFolderJustCreated;

	public static $OverwriteBehaviour;

	protected static function ShowIntro() {
		global $argv;
		Console::WriteLine($argv[0] . ' is a tool that replaces all the occurrences of short tags with long tags.');
	}

	protected static function InitializeDefaults() {
		self::$DestinationFolder = '';
		self::$DestinationFolderJustCreated = false;
		self::$OverwriteBehaviour = self::BEHAVIOUR_DONTOVERWRITE;
	}

	protected static function ShowOptions() {
		Console::WriteLine('--destination=<folder>      where to save the reworked copy of the web files');
		Console::WriteLine(sprintf('--overwrite-behaviour=<n>    what to do if the destination folder exists: %1$s to abort execution, %2$s to overwrite, %3$s to empty that folder before proceeding.', self::BEHAVIOUR_DONTOVERWRITE, self::BEHAVIOUR_OVERWRITE, self::BEHAVIOUR_RECREATE)); 
	}

	protected static function ParseArgument($argument, $value) {
		switch($argument) {
			case '--destination':
				if(!strlen($value)) {
					throw new Exception("Argument '$argument' requires a value (the package name).");
				}
				$dir = @realpath($value);
				if($dir === false) {
					if(!Enviro::PathIsAbsolute($value)) {
						$dir = Enviro::MergePath(Options::$InitialFolder, $value);
					}
					if(!@mkdir($dir, null, true)) {
						throw new Exception("Unable to create the folder '$dir'!");
					}
					self::$DestinationFolderJustCreated = true;
					$dir = realpath($dir);
				}
				elseif(is_file($dir)) {
					throw new Exception("Argument '$name' received an invalid path ('$dir' is a file).");
				}
				self::$DestinationFolder = $dir;
				return true;
			case '--overwrite-behaviour':
				if(!strlen($value)) {
					throw new Exception("Argument '$argument' requires a value (what to do when destination folder exists).");
				}
				if(is_numeric($value)) {
					$i = intval($value);
					switch($i) {
						case self::BEHAVIOUR_DONTOVERWRITE:
						case self::BEHAVIOUR_OVERWRITE:
						case self::BEHAVIOUR_RECREATE:
							self::$OverwriteBehaviour = $i;
							return true;
					}
				}
				throw new Exception("Passed an invalid value of argument '$argumentName'.");
			default:
				return false;
		}
	}

	protected static function ArgumentsRead() {
		if(!strlen(self::$DestinationFolder)) {
			throw new Exception('Please specify the destination folder.');
		}
		$w = realpath(self::$WebrootFolder);
		$d = realpath(self::$DestinationFolder);
		if($w == $d) {
			throw new Exception('The source and the destination folder can NOT be the same.');
		}
		if(strpos($w, $d) === 0) {
			throw new Exception('The destination folder can NOT be a subfolder of the webroot folder.');
		}
		if(strpos($d, $w) === 0) {
			throw new Exception('The webroot folder can NOT be a subfolder of the destination folder.');
		}
		if(!Options::$DestinationFolderJustCreated) {
			switch(Options::$OverwriteBehaviour) {
				case Options::BEHAVIOUR_OVERWRITE:
					break;
				case Options::BEHAVIOUR_RECREATE:
					Console::WriteLine("Emptying '" . Options::$DestinationFolder . "'");
					Enviro::EmptyFolder(Options::$DestinationFolder);
					break;
				default:
					throw new Exception("The destination folder '". Options::$DestinationFolder . "' already exists!");
			}
		}
	}
}

try {
	Options::Initialize(true);
	ShortTagsRemover::$IgnoreFilePatterns = array(
		'%^\.gitignore$%i'
	);
	ShortTagsRemover::CreateCopy(Options::$WebrootFolder, Options::$DestinationFolder, '', true);
	if(is_string(Options::$InitialFolder)) {
		@chdir(Options::$InitialFolder);
	}
	die(0);
}
catch(Exception $x) {
	DieForException($x);
}

class ShortTagsRemover {
	public static $IgnoreFilePatterns = array();
	public static function CreateCopy($sourceBase, $destinationBase, $relName, $overwrite = false) {
		$sourceFull = Enviro::MergePath($sourceBase, $relName);
		$destinationFull = Enviro::MergePath($destinationBase, $relName);
		if(is_dir($sourceFull)) {
			if(is_file($destinationFull)) {
				throw new Exception("'$destinationFull' is a file and not a directory.");
			}
			if((!$overwrite) && is_dir($destinationFull)) {
				throw new Exception("The directory '$destinationFull' already exists!");
			}
			Console::WriteLine("Working on $relName");
			$relItems = array();
			if(!($hDir = @opendir($sourceFull))) {
				throw new Exception("Error opening the folder '$sourceFull'.");
			}
			while($item = readdir($hDir)) {
				switch($item) {
					case '.':
					case '..':
						break;
					default:
						$ignore = false;
						if(is_array(self::$IgnoreFilePatterns)) {
							foreach(self::$IgnoreFilePatterns as $rx) {
								if(preg_match($rx, $item)) {
									$ignore = true;
									break;
								}
							}
						}
						if(!$ignore) {
							$relItems[] = str_replace('\\', '/', Enviro::MergePath($relName, $item));
						}
						break;
				}
			}
			closedir($hDir);
			if((!is_dir($destinationFull)) && (!@mkdir($destinationFull, 0777, true))) {
				throw new Exception("Error creating the directory '$destinationFull'!");
			}
			foreach($relItems as $relItem) {
				self::CreateCopy($sourceBase, $destinationBase, $relItem, $overwrite);
			}
		}
		elseif(is_file($sourceFull)) {
			$ext = preg_match('/\.(\w+)$/', $sourceFull, $m) ? strtolower($m[1]) : '';
			switch($ext) {
				case 'php':
					self::RemoveTagsFromFileToFile($sourceFull, $destinationFull, $overwrite);
					break;
				default:
					if((!$overwrite) && is_file($destinationFull)) {
						throw new Exception("The file '$destinationFull' already exists!");
					}
					if(!@copy($sourceFull, $destinationFull)) {
						throw new Exception("Error copying '$sourceFull' to '$destinationFull'!");
					}
					break;
			}
		}
		else {
			throw new Exception("Unable to find '$sourceFull'!");
		}
	}
	public static function RemoveTagsFromFileToFile($sourceFilename, $destinationFilename, $overwrite = false) {
		if(is_dir($destinationFilename)) {
			throw new Exception("'$destinationFilename' is a folder!");
		}
		if((!$overwrite) && is_file($destinationFilename)) {
			throw new Exception("'$destinationFilename' already exists!");
		}
		$content = self::RemoveTagsFromFile($sourceFilename);
		if(!($hFile = @fopen($destinationFilename, 'wb'))) {
			throw new Exception("Error opening '$destinationFilename' for write!");
		}
		fwrite($hFile, $content);
		fclose($hFile);
	}
	public static function RemoveTagsFromFile($sourceFilename) {
		if(!is_file($sourceFilename)) {
			throw new Exception("The file '$sourceFilename' does not exist.");
		}
		$content = @file_get_contents($sourceFilename);
		if($content === false) {
			throw new Exception("Error reading the file '$sourceFilename'.");
		}
		return self::RemoveTagsFromCode($content);
	}
	public static function RemoveTagsFromCode($phpCode) {
		$result = '';
		$tokens = token_get_all($phpCode);
		$count = count($tokens);
		for($i = 0; $i < $count; $i++) {
			$token = $tokens[$i];
			$trim = false;
			if(is_array($token)) {
				switch($token[0]) {
					case T_OPEN_TAG_WITH_ECHO:
						if(preg_match('/([ \t\r\n]+)$/', $token[1], $m)) {
							$result .= '<?php echo' . $m[1];
						}
						else {
							$result .= '<?php echo ';
							$trim = true;
						}
						break;
					case T_OPEN_TAG:
						if(preg_match('/([ \t\r\n]+)$/', $token[1], $m)) {
							$result .= '<?php' . $m[1];
						}
						else {
							$result .= '<?php ';
							$trim = true;
						}
						break;
					default:
						$result .= $token[1];
						break;
				}
			}
			else {
				$result .= $token;
			}
			if($trim && (($i + 1) < $count) && is_array($tokens[$i + 1]) && ($tokens[$i + 1][0] == T_WHITESPACE) ) {
				$nextOne = ltrim($tokens[$i + 1][1], " \t");
				if(strlen($nextOne) && (($nextOne[0] == "\r") || ($nextOne[0] == "\n"))) {
					$result = rtrim($result, ' ');
				}
				$result .= $nextOne;
				$i++;
			}
		}
		return $result;
	}
}

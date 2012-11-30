<?php
define('C5_BUILD', true);

if(version_compare(PHP_VERSION, '5.1', '<')) {
	Console::WriteLine('Minimum required php version: 5.1, your is ' . PHP_VERSION, true);
	die(1);
}

require_once dirname(__FILE__) . '/base.php';

class Options extends OptionsBase {
	public static $Package;
	public static $Theme;
	public static $Compress;
	protected static function ShowIntro() {
		global $argv;
		Console::WriteLine($argv[0] . ' is a tool that generates .css from .less files, used by the concrete5 core.');
	}
	protected static function ShowOptions() {
		Console::WriteLine('--package=<packagename>     work on a package. If not specified we\'ll work on the concrete5 code. If specified, we\'ll create the .css files for each .less files found under the package folder');
		Console::WriteLine('--theme=<themename>         work on a theme in the themes folder (if the package if specified we\'ll work on the .less files of the theme of that package).');
		Console::WriteLine('--compress=<yes|no>         if yes (default) the output files will be compressed; use no for debugging');
	}
	protected static function InitializeDefaults() {
		self::$Package = '';
		self::$Theme = '';
		self::$Compress = true;
	}
	protected static function ParseArgument($argument, $value) {
		switch($argument) {
			case '--package':
				if(!strlen($value)) {
					throw new Exception("Argument '$argument' requires a value (the package name).");
				}
				if(!Enviro::IsFilenameWithoutPath($value)) {
					throw new Exception("Argument '$argument' requires a package name (not its path), given '$value'.");
				}
				self::$Package = $value;
				return true;
			case '--theme':
				if(!strlen($value)) {
					throw new Exception("Argument '$argument' requires a value (the package name).");
				}
				if(!Enviro::IsFilenameWithoutPath($value)) {
					throw new Exception("Argument '$argument' requires a theme name (not its path), given '$value'.");
				}
				self::$Theme = $value;
				return true;
			case '--compress':
				self::$Compress = self::ArgumentToBool($name, $value);
				return true;
			default:
				return false;
		}
	}
	
}

try {
	Options::Initialize(true);
	if(!Enviro::CheckNodeJS('lessc')) {
		die(1);
	}
	if(!(strlen(Options::$Package) || strlen(Options::$Theme))) {
		CreateCSS(
			'concrete/css/ccm_app/build/jquery.ui.less',
			'concrete/css/jquery.ui.css',
			Options::$Compress
		);
		CreateCSS(
			'concrete/css/ccm_app/build/jquery.rating.less',
			'concrete/css/jquery.rating.css',
			Options::$Compress
		);
		CreateCSS(
			'concrete/css/ccm_app/build/ccm.default.theme.less',
			'concrete/css/ccm.default.theme.css',
			Options::$Compress
		);
		CreateCSS(
			'concrete/css/ccm_app/build/ccm.dashboard.less',
			'concrete/css/ccm.dashboard.css',
			Options::$Compress
		);
		CreateCSS(
			'concrete/css/ccm_app/build/ccm.dashboard.1200.less',
			'concrete/css/ccm.dashboard.1200.css',
			Options::$Compress
		);
		CreateCSS(
			'concrete/css/ccm_app/build/ccm.colorpicker.less',
			'concrete/css/ccm.colorpicker.css',
			Options::$Compress
		);
		CreateCSS(
			'concrete/css/ccm_app/build/ccm.app.mobile.less',
			'concrete/css/ccm.app.mobile.css',
			Options::$Compress
		);
		CreateCSS(
			'concrete/css/ccm_app/build/ccm.app.less',
			'concrete/css/ccm.app.css',
			Options::$Compress
		);
	}
	else {
		if(strlen(Options::$Package)) {
			$rootForWork = Enviro::MergePath(Options::$WebrootFolder, 'packages', Options::$Package);
			if(!is_dir($rootForWork)) {
				throw new Exception("Invalid package '" . Options::$Package . "': couldn't find the folder " . $rootForWork);
			}
		}
		else {
			$rootForWork = Options::$WebrootFolder;
		}
		if(strlen(Options::$Theme)) {
			$rootForWork = Enviro::MergePath($rootForWork, 'themes', Options::$Theme);
			if(!is_dir($rootForWork)) {
				throw new Exception("Invalid theme '" . Options::$Theme . "': couldn't find the folder " . $rootForWork);
			}
		}
		$folderContent = Enviro::GetDirectoryContent($rootForWork, true, '/^[^.]/', '/^[^.].*\.less$/i');
		foreach($folderContent['filesFull'] as $sourceFile) {
			CreateCSS(
				$sourceFile,
				substr($sourceFile, 0, -strlen('.less')) . '.css',
				Options::$Compress,
				false
			);
		}
	}
	if(is_string(Options::$InitialFolder)) {
		@chdir(Options::$InitialFolder);
	}
}
catch(Exception $x) {
	DieForException($x);
}

/** Generate a .css file from one or more .less files.
* @param string|array[string] $srcFiles The source .less file(s).
* @param string $dstFile The output .css file name.
* @param bool $compressed Set to true (default) to compress the final .css file, false otherwise.
* @param bool $pathsAreRelativeToRoot Set to true (default) if all the input/output files are relative to Options::$WebrootFolder, false otherwise.
* @throws Exception Throws an exception in case of errors.
*/
function CreateCSS($srcFiles, $dstFile, $compressed = true, $pathsAreRelativeToRoot = true) {
	Console::Write("Generating $dstFile... ");
	if(!is_array($srcFiles)) {
		$srcFiles = array($srcFiles);
	}
	$srcFilesFull = array();
	foreach($srcFiles as $srcFile) {
		$srcFilesFull[] = $pathsAreRelativeToRoot ? Enviro::MergePath(Options::$WebrootFolder, $srcFile) : $srcFile;
	}
	$dstFileFull = $pathsAreRelativeToRoot ? Enviro::MergePath(Options::$WebrootFolder, $dstFile) : $dstFile;
	$tempFileSrc = '';
	$tempFileDst = '';
	try {
		switch(count($srcFilesFull)) {
			case 0:
				throw new Exception('No .less file to compress!');
			case 1:
				if(!is_file($srcFilesFull[0])) {
					throw new Exception("Unable to find the file '" . $srcFilesFull[0] . "'");
				}
				$srcLength = @filesize($srcFilesFull[0]);
				if($srcLength === false) {
					throw new Exception("Unable to check the size of the file '" . $srcFilesFull[0] . "'");
				}
				$workOnMe = $srcFilesFull[0];
				$numSrc = 1;
				break;
			default:
				$srcLength = 0;
				$numSrc = 0;
				$tempFileSrc = Enviro::GetTemporaryFileName();
				foreach($srcFilesFull as $srcFileFull) {
					if(!is_file($srcFileFull)) {
						throw new Exception("Unable to find the file '$srcFileFull'");
					}
					$s = @file_get_contents($srcFileFull);
					if($s === false) {
						throw new Exception("Unable to read the content of the file '$srcFileFull'");
					}
					$srcLength += strlen($s);
					$numSrc++;
					if(!@file_put_contents($tempFileSrc, $s, FILE_APPEND)) {
						throw new Exception("Unable to write data to the temporary file");
					}
				}
				$workOnMe = $tempFileSrc;
				break;
		}
		$options = array();
		if($compressed) {
			$options[] = '-x';
		}
		$options[] = escapeshellarg($workOnMe);
		Enviro::RunNodeJS('lessc', $options, 0, $lines);
		if(strlen($tempFileSrc)) {
			@unlink($tempFileSrc);
			$tempFileSrc = '';
		}
		$tempFileDst = Enviro::GetTemporaryFileName();
		if(!@file_put_contents($tempFileDst, rtrim(implode("\n", $lines), "\n"))) {
			throw new Exception("Unable to write the temporary file name '" . $tempFileDst . "'");
		}
		$dstLength = @filesize($tempFileDst);
		if($dstLength === false) {
			throw new Exception("Unable to check the size of the file '" . $tempFileDst . "'");
		}
		$dstDir = dirname($dstFileFull);
		if(!is_dir($dstDir)) {
			if(!@mkdir($dstDir, 0777, true)) {
				throw new Exception("Unable to create the destination directory '$dstDir'");
			}
		}
		if(is_file($dstFileFull)) {
			@unlink($dstFileFull);
		}
		if(!@rename($tempFileDst, $dstFileFull)) {
			throw new Exception("Unable to save the result to '$dstFileFull'");
		}
		$tempFileDst = '';
		Console::WriteLine('ok.');
		Console::WriteLine("   Number of .less files: $numSrc");
		Console::WriteLine("   Original file" . (($numSrc == 1) ? '' : 's') . " size" . (($numSrc == 1) ? ' ' : '') . "  : " . number_format($srcLength) . " B");
		Console::WriteLine("   Final .css file size : " . number_format($dstLength) . " B");
	}
	catch(Exception $x) {
		if(strlen($tempFileDst)) {
			@unlink($tempFileDst);
		}
		if(strlen($tempFileSrc)) {
			@unlink($tempFileSrc);
		}
		throw $x;
	}
}
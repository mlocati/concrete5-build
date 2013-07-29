<?php
define('C5_BUILD', true);
define('PHP_MIN_VERSION', '5.3');
require_once dirname(__FILE__) . '/base.php';

try {
	if(ToolOptions::$Interactive) {
		Interactive::ShowMainMenu();
	}
	else {
		Options::CheckWebRoot();
		foreach(ToolOptions::$Packages as $package) {
			if($package->CreatePot) {
				POTFile::CreateNew($package);
			}
			if($package->CreatePo) {
				foreach(ToolOptions::$Languages as $language) {
					POFile::CreateNew($package, $language);
				}
			}
			if($package->Compile) {
				foreach(ToolOptions::$Languages as $language) {
					POFile::Compile($package, $language);
				}
			}
		}
	}
	if(is_string(Options::$InitialFolder)) {
		@chdir(Options::$InitialFolder);
	}
	die(0);
} catch(Exception $x) {
	DieForException($x);
}


class ToolOptions {

	/** The email address to which translators should report localization bugs.
	* @var string
	*/
	public static $PotContactConcrete5Default;

	/** The default folders to be excluded from .pot generation for concrete5 (relative to web root).
	* @var array[string]
	*/
	public static $ExcludeDirsFromPotConcrete5Default;

	/** The default folders to be excluded from .pot generation for packages (relative to the folder of the package).
	* @var array[string]
	*/
	public static $ExcludeDirsFromPotPackageDefault;

	/** List of info about concrete5 / packages.
	* @var array[PackageInfo]
	*/
	public static $Packages;

	/** Default value of 'indent' option.
	* @var bool
	*/
	public static $IndentDefault;

	/** Have we to create indented .pot/.po files?
	* @var bool
	*/
	public static $Indent;

	/** List of languages for which we have to create/update .po files. Each array item is an array with the keys <b>language</b> (required) and <b>country</b> (optional).
	* @var array
	*/
	public static $Languages;

	/** We're in an interactive session?
	* @var bool
	*/
	public static $Interactive;

	/** Private state data for initialization process.
	* @var array
	*/
	private static $InitializeData;

	public static function InitializeDefaults() {
		self::$PotContactConcrete5Default = 'andrew@concrete5.org';
		self::$Indent = self::$IndentDefault = false;
		self::$ExcludeDirsFromPotConcrete5Default = array('concrete/libraries/3rdparty');
		self::$ExcludeDirsFromPotPackageDefault = array('libraries/3rdparty');
		self::$Packages = array();
		self::$Languages = null;
		self::$Interactive = false;
		self::$InitializeData = array(
			'packagesMap' => array(),
			'currentPackage' => self::$Packages[] = new PackageInfo('')
		);
	}

	public static function ShowIntro() {
		global $argv;
		Console::WriteLine($argv[0] . ' is a tool that helps extracting localizable strings from concrete5 and/or from packages.');
		Console::WriteLine();
		Console::WriteLine('### HOW IS THE TRANSLATION IMPLEMENTED IN concrete5?');
		Console::WriteLine('concrete5 (and custom packages) use gettext for localization.');
		Console::WriteLine('gettext uses mainly 3 kind of files for translations:');
		Console::WriteLine('- .pot files (\'Portable Object Template\'): these files contains the base translations, extracted directly from the source files. There should be just one .pot file for the concrete5 core and one for each package.');
		Console::WriteLine('- .po files (\'Portable Object\'): these files contains the translations, and can be generated/updated starting from .pot files. There should be one .po file for each language.');
		Console::WriteLine('You can translate the files with Poedit (http://www.poedit.net/)' . ((Enviro::GetOS() == Enviro::OS_WIN) ? ' or with BetterPOEditor (http://sourceforge.net/projects/betterpoeditor)' : '') . '.');
		Console::WriteLine('- .mo files (\'Machine Object\'): these files are a compiled version of the .po files, and are those used at runtime (when distributing concrete5 and/or packages you need to include just those files).');
		Console::WriteLine('The translation process can be summarized as follows:');
		Console::WriteLine('generate .pot -> generate .po for each language -> translate the .po files -> generate the .mo files');
		Console::WriteLine('For more information about gettext: http://www.gnu.org/software/gettext/manual/gettext.html');
	}

	public static function GetOptions(&$options) {
		$options['--list-languages'] = array('description' => 'list all the usable languages');
		$options['--list-countries'] = array('description' => 'list all the usable countries');
		$options['--interactive'] = array('description' => 'start an interactive session');
		$options['--indent'] = array('helpValue' => '<yes|no>', 'description' => 'set to yes to generate indented .pot/.po files, false for not-indented generation (default: ' . (self::$IndentDefault ? 'yes' : 'no') . ')');
		$options['--languages'] = array('helpValue' => '<LanguagesCode>', 'description' => 'list of comma-separated languages for which create the .po files (default: ' . implode(',', Language::GetStandardCodes()) . ')');
		$options['--package'] = array('helpValue' => '<packagename>', 'description' => 'adds a package. Subsequent arguments are relative to the latest package (or to concrete5 itself to ');
		$options['--createpot'] = array('helpValue' => '<yes|no>', 'description' => '(For core and/or each package) set to yes to generate the .pot file, no to skip it (defaults to yes, except for concrete5 when you\'ve specified a --package option)');
		$options['--createpo'] = array('helpValue' => '<yes|no>', 'description' => '(For core and/or each package) set to yes to generate the .po files, no to skip it (defaults to yes, except for concrete5 when you\'ve specified a --package option)');
		$options['--compile'] = array('helpValue' => '<yes|no>', 'description' => '(For core and/or each package) set to yes to generate the .mo files from .po files, no to skip it (defaults to yes, except for concrete5 when you\'ve specified a --package option)');
		$options['--potname'] = array('helpValue' => '<filename>', 'description' => '(For core and/or each package) name of the .pot filename (just the name, without path: it\'ll be saved in the \'languages\' folder)');
		$options['--excludedirfrompot'] = array('helpValue' => '<dir>', 'description' => '(For core and/or each package) folder which may not be parsed when creating a.pot file. To specify multiple values you can specify this argument more than once (default for concrete5: ' . implode(self::$ExcludeDirsFromPotConcrete5Default), ', default for packages: ' . implode(self::$ExcludeDirsFromPotPackageDefault, ', ') . ')');
		$options['--potcontact'] = array('helpValue' => '<email>', 'description' => '(For core and/or each package) email address to send bugs to (for concrete5 the default is ' . self::$PotContactConcrete5Default . ' when working on concrete5, empty when working on a package.');
	}

	public static function ShowExamples() {
		global $argv;
		Console::WriteLine('The simplest: php ' . $argv[0] . ' --interactive');
		Console::WriteLine('To create the .pot/.po/.mo files for concrete5: php ' . $argv[0]);
		Console::WriteLine('To create the files for concrete5 (with path specification): php ' . $argv[0] . ' --webroot=' . ((Enviro::GetOS() == Enviro::OS_WIN) ? 'C:\\Inetpub\\wwwroot' : '/var/www'));
		Console::WriteLine('To create the files for the package foobar: php ' . $argv[0] . ' --package=foobar');
		Console::WriteLine('To create the .pot file for concrete5 AND all the files for the package foobar: php ' . $argv[0] . ' --createpot=yes --package=foobar');
	}

	public static function ParseArgument($argument, $value) {
		switch($argument) {
			case '--list-languages':
				foreach(Language::GetLanguages() as $id => $info) {
					Console::WriteLine("$id\t{$info['name']}");
				}
				die(0);
			case 'countries':
				foreach(Language::GetCountries() as $id => $info) {
					Console::WriteLine("$id\t{$info['name']}");
				}
				die(0);
			case '--interactive':
				self::$Interactive = true;
				return true;
			case '--indent':
				self::$Indent = Options::ArgumentToBool($argument, $value);
				return true;
			case '--package':
				if(!strlen($value)) {
					throw new Exception("Argument '$argument' requires a value (the package name).");
				}
				if(!Enviro::IsFilenameWithoutPath($value)) {
					throw new Exception("Argument '$argument' requires a package name (not its path), given '$value'.");
				}
				if(is_null(self::$Packages[0]->CreatePot)) {
					self::$Packages[0]->CreatePot = false;
				}
				if(is_null(self::$Packages[0]->CreatePo)) {
					self::$Packages[0]->CreatePo = false;
				}
				if(is_null(self::$Packages[0]->Compile)) {
					self::$Packages[0]->Compile = false;
				}
				if(array_key_exists($value, self::$InitializeData['packagesMap'])) {
					self::$InitializeData['currentPackage'] = self::$Packages[self::$InitializeData['packagesMap'][$value]];
				}
				else {
					self::$InitializeData['packagesMap'][$value] = count(self::$Packages);
					self::$Packages[] = self::$InitializeData['currentPackage'] = new PackageInfo($value);
				}
				return true;
			case '--createpot':
				self::$InitializeData['currentPackage']->CreatePot = Options::ArgumentToBool($argument, $value);
				return true;
			case '--createpo':
				self::$InitializeData['currentPackage']->CreatePo = Options::ArgumentToBool($argument, $value);
				return true;
			case '--compile':
				self::$InitializeData['currentPackage']->Compile = Options::ArgumentToBool($argument, $value);
				return true;
			case '--potname':
				if(!strlen($value)) {
					throw new Exception("Argument '$argument' requires a value.");
				}
				if(!Enviro::IsFilenameWithoutPath($value)) {
					throw new Exception("Argument '$argument' requires a .pot filename name (without any path info), given '$value'.");
				}
				self::$InitializeData['currentPackage']->PotName = $value;
				return true;
			case '--potcontact':
				self::$InitializeData['currentPackage']->PotContact = $value;
				return true;
			case '--excludedirfrompot':
				if(!strlen($value)) {
					self::$InitializeData['currentPackage']->ExcludeDirsFromPot = array();
				}
				else {
					if(!is_array(self::$InitializeData['currentPackage']->ExcludeDirsFromPot)) {
						self::$InitializeData['currentPackage']->ExcludeDirsFromPot = array();
					}
					self::$InitializeData['currentPackage']->ExcludeDirsFromPot[] = $value;
				}
				return true;
			case '--languages':
				if(is_null(self::$Languages)) {
					self::$Languages = array();
				}
				if(!strlen($value)) {
					throw new Exception("Argument '$argument' requires a value.");
				}
				foreach(explode(',', $value) as $v1) {
					if(!strlen($v1)) {
						throw new Exception("Argument '$argument' requires values separated by comma.");
					}
					$v1n = Language::NormalizeCode($v1);
					if(array_search($v1n, self::$Languages) === false) {
						self::$Languages[] = Language::NormalizeCode($v1);
					}
				}
				return true;
			default:
				return false;
		}
	}

	/** Checks the environment state.
	* @throws Exception Throws an Exception in case of errors.
	*/
	public static function ArgumentsRead() {
		if(is_null(self::$Languages)) {
			self::$Languages = Language::GetStandardCodes();
		}
		sort(self::$Languages);
		if(!self::$Interactive) {
			foreach(self::$Packages as $packageInfo) {
				$packageInfo->PostInitialize();
			}
		}
		$commands = array('xgettext', 'msgmerge', 'msgfmt');
		try {
			foreach($commands as $command) {
				Enviro::RunTool($command, '--version');
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
		self::$InitializeData = null;
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

/** Static class managing the interactive session. */
class Interactive {

	/** Print out the menu of an interactive menu.
	* @param string $title The menu title.
	*/
	private static function ShowTitle($title) {
		$l = strlen($title);
		Console::WriteLine();
		Console::WriteLine(' -' . str_repeat('-', $l) . '- ');
		Console::WriteLine('| ' . $title . ' |');
		Console::WriteLine(' -' . str_repeat('-', $l) . '- ');
	}

	/** Print out an item of an interactive menu.
	* @param string $key The key associated to the item.
	* @param string $text The description of the item.
	* @param string $note [optional] A note about the item.
	*/
	private static function ShowEntry($key, $text, $note = '') {
		Console::WriteLine("  $key: $text");
		if(strlen($note)) {
			Console::WriteLine("     $note");
		}
	}

	/** Read the user choice for an interactive menu.
	* @var array[string] $validOptions List of valid options.
	* @return string
	*/
	private static function AskOption($validOptions) {
		Console::WriteLine();
		Console::Write('  Option: ');
		for(;;) {
			$option = trim(Console::ReadLine());
			if(!strlen($option)) {
				Console::Write('  Please specify an option: ');
			}
			else {
				foreach($validOptions as $validOption) {
					if(strcasecmp($validOption, $option) === 0) {
						return $validOption;
					}
				}
				Console::Write('  Please a valid option: ');
			}
		}
	}

	/** Shows the main interactive menu. */
	public static function ShowMainMenu() {
		for(;;) {
			$validOptions = array();
			self::ShowTitle('MAIN MENU');
			self::ShowEntry($validOptions[] = 'C', 'Work on concrete5 core');
			self::ShowEntry($validOptions[] = 'P', 'Work on a concrete5 package');
			self::ShowEntry($validOptions[] = 'W', 'Change webroot', 'Current value: ' . Options::$WebrootFolder);
			self::ShowEntry($validOptions[] = 'I', 'Change indentation', 'Current value: ' . (ToolOptions::$Indent ? 'yes' : 'no'));
			self::ShowEntry($validOptions[] = 'L', 'Change .po languages', 'Current value: ' . count(ToolOptions::$Languages) . ' language' . ((count(ToolOptions::$Languages) == 1) ? '' : 's'));
			self::ShowEntry($validOptions[] = 'X', 'Exit');
			switch(self::AskOption($validOptions)) {
				case 'C':
					try {
						Options::CheckWebroot();
					}
					catch(Exception $x) {
						Console::Write('Please fix the webroot!');
						break;
					}
					self::ShowPackageMenu('');
					break;
				case 'P':
					try {
						Options::CheckWebroot();
					}
					catch(Exception $x) {
						Console::Write('Please fix the webroot!');
						break;
					}
					$available = array();
					$parent = Enviro::MergePath(Options::$WebrootFolder, 'packages');
					if(is_dir($parent)) {
						if(!($hDir = @opendir($parent))) {
							Console::WriteLine('Unable to read directory \'' . $parent . '\.');
							break;
						}
						while($item = readdir($hDir)) {
							switch($item) {
								case '.':
								case '..':
									break;
								default:
									if(is_dir(Enviro::MergePath($parent, $item))) {
										$available[] = $item;
									}
									break;
							}
						}
						closedir($hDir);
					}
					if(empty($available)) {
						Console::Write('No packages found in \'' . $parent . '\.');
						break;
					}
					$package = '';
					for(;;) {
						Console::Write('Enter new package name [? to pick it]: ');
						$s = trim(Console::ReadLine());
						if(!strlen($s)) {
							break;
						}
						if($s == '?') {
							foreach($available as $index => $name) {
								Console::WriteLine(($index + 1) . ": $name");
							}
							for(;;) {
								Console::Write('Enter package index: ');
								$i = trim(Console::ReadLine());
								if(!strlen($i)) {
									break;
								}
								if(!preg_match('/^[1-9][0-9]*$/', $i)) {
									$i = -1;
								}
								else {
									$i = intval($i) - 1;
									if(!array_key_exists($i, $available)) {
										$i = -1;
									}
								}
								if($i < 0) {
									Console::WriteLine('Invalid option!');
									continue;
								}
								$package = $available[$i];
								break;
							}
							break;
						}
						else {
							if(Enviro::IsFilenameWithoutPath($s)) {
								foreach($available as $a) {
									if(strcasecmp($a, $s) === 0) {
										$package = $a;
										break;
									}
								}
							}
							if(!strlen($package)) {
								Console::WriteLine('Invalid package name.');
							}
							else
							{
								break;
							}
						}
					}
					if(!strlen($package)) {
						Console::WriteLine('Skipped.');
					}
					else {
						try {
							PackageInfo::GetVersionOfPackage(Options::$WebrootFolder, $package);
						}
						catch(Exception $x) {
							Console::Write($x);
							break;
						}
						self::ShowPackageMenu($package);
					}
					break;
				case 'W':
					for(;;) {
						Console::Write('Enter new webroot path: ');
						$s = str_replace('\\', '/', trim(Console::ReadLine()));
						if(!strlen($s)) {
							Console::WriteLine('Skipped.');
							break;
						}
						if(preg_match('/^\\.\\.?\/?/', $s)) {
							$s = Enviro::MergePath(Options::$InitialFolder, $s);
						}
						else {
							$s = Enviro::MergePath($s);
						}
						$r = @realpath($s);
						if(($r === false) || (!is_dir($r))) {
							Console::WriteLine('The folder \'' . $s . '\' does not exist.');
						}
						else {
							try {
								Options::GetVersionOfConcrete5In($r);
							}
							catch(Exception $x) {
								Console::WriteLine($x->getMessage());
								continue;
							}
							Options::$WebrootFolder = $r;
							break;
						}
					}
					break;
				case 'I':
					for(;;) {
						Console::Write('Enter new indent value [Y/N]: ');
						$s = trim(Console::ReadLine());
						if(!strlen($s)) {
							Console::WriteLine('Skipped.');
							break;
						}
						else {
							$b = Options::StringToBool($s);
							if(is_null($b)) {
								Console::WriteLine('Invalid value!');
							}
							else {
								ToolOptions::$Indent = $b;
								break;
							}
						}
					}
					break;
				case 'L':
					self::ShowLanguagesMenu();
					break;
				case 'X':
					return;
			}
		}
	}

	/** Shows the interactive menu for working on concrete5 core or on a package.
	* @param string $package Empty string for working on concrete5 core, the package name for working on a package.
	*/
	private static function ShowPackageMenu($package) {
		$packageInfo = new PackageInfo($package);
		$packageInfo->CreatePo = false;
		$packageInfo->CreatePot = false;
		$packageInfo->Compile = false;
		$packageInfo->PostInitialize();
		for(;;) {
			$validOptions = array();
			self::ShowTitle('WORK ON ' . (strlen($package) ? "PACKAGE $package" : 'concrete5 core') . ' v' . $packageInfo->Version);
			self::ShowEntry($validOptions[] = 'T', 'Create the language template (.pot file)');
			self::ShowEntry($validOptions[] = 'P', 'Create/update the files to be translated (.po files)');
			self::ShowEntry($validOptions[] = 'C', 'Compile the language files (generate the final .mo files');
			self::ShowEntry($validOptions[] = 'X', 'Back to main menu');
			switch(self::AskOption($validOptions)) {
				case 'T':
					try {
						POTFile::CreateNew($packageInfo);
					}
					catch(Exception $x) {
						Console::WriteLine();
						Console::WriteLine( $x->getMessage());
						Console::Write('Press [RETURN] to continue');
						Console::ReadLine();
					}
					break;
				case 'P':
					if(empty(ToolOptions::$Languages)) {
						Console::WriteLine('No languages to create/update .po files for!');
					}
					else {
						foreach(ToolOptions::$Languages as $language) {
							try {
								POFile::CreateNew($packageInfo, $language);
							}
							catch(Exception $x) {
								Console::WriteLine();
								Console::WriteLine( $x->getMessage());
								Console::Write('Press [RETURN] to continue');
								Console::ReadLine();
							}
						}
					}
					break;
				case 'C':
					if(empty(ToolOptions::$Languages)) {
						Console::WriteLine('No languages to create .mo files for!');
					}
					else {
						foreach(ToolOptions::$Languages as $language) {
							try {
								POFile::Compile($packageInfo, $language);
							}
							catch(Exception $x) {
								Console::WriteLine();
								Console::WriteLine( $x->getMessage());
								Console::Write('Press [RETURN] to continue');
								Console::ReadLine();
							}
						}
					}
					break;
				case 'X':
					return;
			}
		}
	}

	/** Show the languages-management menu. */
	private static function ShowLanguagesMenu() {
		for(;;) {
			$validOptions = array();
			self::ShowTitle('LANGUAGES MENU');
			self::ShowEntry($validOptions[] = '?', 'Show current language list');
			self::ShowEntry($validOptions[] = 'E', 'Empty current language list');
			self::ShowEntry($validOptions[] = 'A', 'Add a language to the list');
			self::ShowEntry($validOptions[] = 'R', 'Remove a language from list');
			self::ShowEntry($validOptions[] = 'L', 'Show all available languages');
			self::ShowEntry($validOptions[] = 'C', 'Show all available countries');
			self::ShowEntry($validOptions[] = 'X', 'back to main menu');
			switch(self::AskOption($validOptions)) {
				case '?':
					if(empty(ToolOptions::$Languages)) {
						Console::WriteLine('No current languages');
					}
					else {
						foreach(ToolOptions::$Languages as $code) {
							Console::WriteLine($code . "\t" . Language::DescribeCode($code));
						}
					}
					break;
				case 'E':
					ToolOptions::$Languages = array();
					Console::WriteLine('List cleared.');
					break;
				case 'A':
					Console::Write('Language code to add (LL or LL_CC): ');
					$code = trim(Console::ReadLine());
					if(!strlen($code)) {
						Console::WriteLine('Skipped.');
					}
					else {
						try {
							$code = Language::NormalizeCode($code);
						}
						catch(Exception $x) {
							Console::WriteLine('Invalid code. The language code format is LL or LL_CC, where LL is a language code and CC is a country code');
							$code = '';
						}
						if(strlen($code)) {
							$name = Language::DescribeCode($code);
							if(array_search($code, ToolOptions::$Languages) === false) {
								ToolOptions::$Languages[] = $code;
								sort(ToolOptions::$Languages);
								Console::WriteLine('Added language ' . $code . ' - ' . $name);
							}
							else {
								Console::WriteLine($name . ' is already in the current list of languages.');
							}
						}
					}
					break;
				case 'R':
					Console::Write('Language code to remove (LL or LL_CC): ');
					$code = trim(Console::ReadLine());
					if(!strlen($code)) {
						Console::WriteLine('Skipped.');
					}
					else {
						try {
							$code = Language::NormalizeCode($code);
						}
						catch(Exception $x) {
							Console::WriteLine('Invalid code. The language code format is LL or LL_CC, where LL is a language code and CC is a country code');
							$code = '';
						}
						if(strlen($code)) {
							$name = Language::DescribeCode($code);
							if(array_search($code, ToolOptions::$Languages) === false) {
								Console::WriteLine($name . ' is not in the current list of languages.');
							}
							else {
								Console::WriteLine('Removed language ' . $code . ' - ' . $name);
							}
						}
					}
					break;
				case 'L':
					foreach(Language::GetLanguages() as $code => $info) {
						Console::WriteLine($code . "\t" . $info['name']);
					}
					break;
				case 'C':
					foreach(Language::GetCountries() as $code => $info) {
						Console::WriteLine($code . "\t" . $info['name']);
					}
					break;
				case 'X':
					return;
			}
		}
	}
}

/** Holds the info about main concrete5 or about a package. */
class PackageInfo {

	/** The package name. Empty means we're going to potify concrete5 itself.
	* @var string
	*/
	public $Package;

	/** I'm for concrete5 (true) of for a package (false)?
	* @var bool
	*/
	public $IsConcrete5;

	/** The contact email address.
	* @var string
	*/
	public $PotContact;

	/** List of dirs to exclude.
	* @var string
	*/
	public $ExcludeDirsFromPot;

	/** True if we have to create the .pot file.
	* @var bool
	*/
	public $CreatePot;

	/** True if we have to create the .po files.
	* @var bool
	*/
	public $CreatePo;

	/** True if we have to compile the .po files into .mo files.
	* @var bool
	*/
	public $Compile;

	/** The name of the .pot file.
	* @var string
	*/
	public $PotName;

	/** The path of the directory to parse to create the .pot file (relative to the web root).
	* @var string
	*/
	public $DirectoryToPotify;

	/** The full name of the .pot file.
	* @var string
	*/
	public $PotFullname;

	/** The version of concrete5/package.
	* @var string
	*/
	public $Version;

	/** The relative path from .pot file to web root.
	* @var string
	*/
	public $Potfile2root;

	/** The relative path from .po files to web root.
	* @var string
	*/
	public $Pofile2root;

	/** Initializes the class instance.
	* @param string $package The package name (empty for concrete5).
	*/
	public function __construct($package) {
		$this->Package = is_string($package) ? trim($package) : '';
		if(strlen($this->Package)) {
			$this->IsConcrete5 = false;
			$this->PotContact = '';
		}
		else {
			$this->IsConcrete5 = true;
			$this->PotContact = ToolOptions::$PotContactConcrete5Default;
		}
		$this->ExcludeDirsFromPot = null;
		$this->CreatePot = null;
		$this->CreatePo = null;
		$this->Compile = null;
		$this->PotName = 'messages.pot';
	}

	/** Retrieves the version of a package given the concrete5 root folder and the package name.
	* @param string $webroot The path of the webroot containing concrete5.
	* @param string $package The package name.
	* @throws Exception Throws an Exception in case of errors.
	* @return string
	*/
	public static function GetVersionOfPackage($webroot, $package) {
		if(!is_file($fn = Enviro::MergePath($webroot, 'packages', $package, 'controller.php'))) {
			throw new Exception("'" . $package . "' is not a valid package name ('$fn' not found).");
		}
		$fc = "\n" . Enviro::GetEvaluableContent($fn);
		if(!preg_match('/[\r\n]\s*class[\r\n\s]+([^\s\r\n]+)[\r\n\s]+extends[\r\n\s]+Package\s*\{/i', $fc, $m)) {
			throw new Exception("'" . self::$package . "' can't be parsed for a version.");
		}
		$packageClassOriginal = $m[1];
		for($x = 0; ; $x++) {
			$packageClassRenamed = $packageClassOriginal . $x;
			if(!class_exists($packageClassRenamed)) {
				if(stripos($fc, $packageClassRenamed) === false) {
					break;
				}
			}
		}
		$fc = preg_replace('/\\b' . preg_quote($packageClassOriginal) . '\\b/i', $packageClassRenamed, $fc);
		if(!class_exists('Package')) {
			eval('class Package {}');
		}
		@ob_start();
		$evalued = eval($fc);
		@ob_end_clean();
		if($evalued === false) {
			throw new Exception("Unable to parse the version of package (file '$fn').");
		}
		if(!class_exists("VersionGetter_$packageClassRenamed")) {
			eval(<<<EOT
				class VersionGetter_$packageClassRenamed extends $packageClassRenamed {
					private function __construct() {
					}
					public static function GV() {
						\$me = new VersionGetter_$packageClassRenamed();
						return \$me->pkgVersion;
					}
				}
EOT
			);
		}
		$r = eval("return VersionGetter_$packageClassRenamed::GV();");
		if(empty($r) && ($r !== '0')) {
			throw new Exception("Unable to parse the version of package (file '$fn').");
		}
		return $r;
	}

	/** Fix the instance values once we've read all the command line arguments. */
	public function PostInitialize() {
		if(!defined('C5_EXECUTE')) {
			define('C5_EXECUTE', true);
		}
		if($this->IsConcrete5) {
			if(is_null($this->CreatePot)) {
				$this->CreatePot = (count(ToolOptions::$Packages) == 1) ? true : false;
			}
			if(is_null($this->CreatePo)) {
				$this->CreatePo = (count(ToolOptions::$Packages) == 1) ? true : false;
			}
			if(is_null($this->Compile)) {
				$this->Compile = (count(ToolOptions::$Packages) == 1) ? true : false;
			}
			if(is_null($this->ExcludeDirsFromPot)) {
				$this->ExcludeDirsFromPot = ToolOptions::$ExcludeDirsFromPotConcrete5Default;
			}
			$this->Version = Options::GetVersionOfConcrete5();
			$this->DirectoryToPotify = 'concrete';
			$this->PotFullname = Enviro::MergePath(Options::$WebrootFolder, 'languages/' . $this->PotName);
			$this->Potfile2root = '..';
			$this->Pofile2root = '../../..';
		}
		else {
			if(is_null($this->CreatePot)) {
				$this->CreatePot = true;
			}
			if(is_null($this->CreatePo)) {
				$this->CreatePo = true;
			}
			if(is_null($this->Compile)) {
				$this->Compile = true;
			}
			if(is_null($this->ExcludeDirsFromPot)) {
				$this->ExcludeDirsFromPot = ToolOptions::$ExcludeDirsFromPotPackageDefault;
			}
			$this->Version = self::GetVersionOfPackage(Options::$WebrootFolder, $this->Package);
			$this->DirectoryToPotify = 'packages/' . $this->Package;
			$this->PotFullname = Enviro::MergePath(Options::$WebrootFolder, 'packages', $this->Package, 'languages', $this->PotName);
			$this->Potfile2root = '../../..';
			$this->Pofile2root = '../../../../..';
		}
	}

	/** Retrieves the full name of the .po file for the specified language.
	* @param string $language The language (and country) code.
	* @return string
	*/
	public function GetPoFullname($language) {
		if($this->IsConcrete5) {
			$parent = Options::$WebrootFolder;
		}
		else {
			$parent = Enviro::MergePath(Options::$WebrootFolder, 'packages', $this->Package);
		}
		return Enviro::MergePath($parent, 'languages', Language::NormalizeCode($language), 'LC_MESSAGES', 'messages.po');
	}

	/** Retrieves the full name of the .mo file for the specified language.
	* @param string $language The language (and country) code.
	* @return string
	*/
	public function GetMoFullname($language) {
		if($this->IsConcrete5) {
			$parent = Options::$WebrootFolder;
		}
		else {
			$parent = Enviro::MergePath(Options::$WebrootFolder, 'packages', $this->Package);
		}
		return Enviro::MergePath($parent, 'languages', Language::NormalizeCode($language), 'LC_MESSAGES', 'messages.mo');
	}

}

/** Base class for POTFile and POFile. */
abstract class POxFile {

	/** The file header.
	* @var POEntrySingle|null
	*/
	public $Header;
	
	/** The entries in the file.
	* @var array[POEntry]
	*/
	public $Entries;

	/** Reads a .pot/.po from file.
	* @param string $filename The name of the file to read.
	* @throws Exception Throws an Exception in case of errors.
	*/
	public function __construct($filename) {
		if(($s = @realpath($filename)) === false) {
			throw new Exception("Error resolving $filename: does it exists?");
		}
		$filename = $s;
		if(!($lines = @file($filename, FILE_IGNORE_NEW_LINES))) {
			global $php_errormsg;
			throw new Exception("Error reading '$filename': $php_errormsg");
		}
		try {
			$this->Header = null;
			$this->Entries = array();
			$i = 0;
			$n = count($lines);
			while($i < $n) {
				if($entry = POEntry::GetNextFromLines($lines, $i, $nextStart)) {
					if((!$this->Header) && empty($this->Entries) && is_a($entry, 'POEntrySingle') && (!strlen($entry->GetMsgId()))) {
						$this->Header = $entry;
					}
					else {
						$hash = $entry->GetHash();
						if(array_key_exists($hash, $this->Entries) !== false) {
							throw new Exception("Duplicated entry:\n" . print_r($this->Entries[$hash]). "\n" . print_r($entry));
						}
						$this->Entries[$hash] = $entry;
					}
				}
				$i = $nextStart;
			}
		}
		catch(Exception $x) {
			throw new Exception("Error reading $filename: " . $x->getMessage());
		}
	}

	/** Fix the slash of path the entry loctaion comments (eg from concrete\dispatcher.php to concrete/dispatcher.php) */
	public function FixFilesSlash() {
		foreach($this->Entries as $entry) {
			$entry->FixFilesSlash();
		}
	}
	
	/** Remove the superfluous i18n at the beginning of comments. */
	public function FixI18NComments() {
		foreach($this->Entries as $entry) {
			$entry->FixI18NComments();
		}
	}
	
	/** Fix the cr/lf end-of-line terminator in every msgid (required when parsed source files under Windows). */
	public function Replace_CRLF_LF() {
		if($this->Header) {
			$this->Header->Replace_CRLF_LF();
		}
		foreach($this->Entries as $entry) {
			$entry->Replace_CRLF_LF();
		}
	}
	
	/** Add one/many entries to the entries of this instance.
	* @param POEntry|array[POEntry] $entries The entry/entries to be merged with this instance.
	*/
	public function MergeEntries($entries) {
		if(is_array($entries)) {
			foreach($entries as $entry) {
				$this->MergeEntries($entry);
			}
		}
		else {
			$hash = $entries->GetHash();
			if(array_key_exists($hash, $this->Entries)) {
				$this->Entries[$hash]->MergeWith($entries);
			}
			else {
				$this->Entries[$hash] = $entries;
			}
		}
	}

	/** Save the data to file.
	* @param string $filename The filename to save the data to (if existing it'll be overwritten).
	* @param bool $indent Set to true to indent data, false otherwise (default: false).
	* @throws Exception Throws an exception in case of errors.
	*/
	public function SaveAs($filename, $indent = false) {
		$tempFilename = Enviro::GetTemporaryFileName();
		try {
			if(!$hFile = @fopen($tempFilename, 'wb')) {
				global $php_errormsg;
				throw new Exception("Error opening '$tempFilename': $php_errormsg");
			}
			try {
				$isFirst = true;
				if($this->Header) {
					$this->Header->SaveTo($hFile, $indent);
					$isFirst = false;
				}
				foreach($this->Entries as $entry) {
					if($isFirst) {
						$isFirst = false;
					}
					else {
						fwrite($hFile, "\n");
					}
					$entry->SaveTo($hFile, $indent);
				}
				fflush($hFile);
			} catch(Exception $x) {
				@fclose($hFile);
				throw $x;
			}
			fclose($hFile);
			$dirname = dirname($filename);
			if(is_file($dirname)) {
				throw new Exception("we'd like to use the folder '$dirname', but it's a file!");
			}
			if(!is_dir($dirname)) {
				if(!@mkdir($dirname, 0777, true)) {
					throw new Exception("unable to create the folder '$dirname'!");
				}
			}
			if(!is_writable($dirname)) {
				throw new Exception("the folder '$dirname' is not writable!");
			}
			if(is_file($filename)) {
				if(!is_writable($filename)) {
					throw new Exception("the file $filename is not writable!");
				}
				if(@unlink($filename) === false) {
					global $php_errormsg;
					throw new Exception("error deleting $filename: $php_errormsg");
				}
			}
			if(!@rename($tempFilename, $filename)) {
				throw new Exception("error renaming from '$tempFilename' to '$filename'!");
			}
		} catch(Exception $x) {
			@unlink($tempFilename);
			throw $x;
		}
	}
}
/** Represents a .pot file. */
class POTFile extends POxFile {

	/** Fixes the content of the header.
	* @param PackageInfo $packageInfo The info about the package.
	* @param int|null $timestamp The timestamp of the creation of the .pot file (if null we'll use current server time).
	* @throws Exception Throws an exception in case of errors.
	*/
	public function FixHeader($packageInfo, $timestamp = null) {
		$this->Header = new POEntrySingle(
			array(),
			array(
				'Project-Id-Version: ' . ($packageInfo->IsConcrete5 ? 'concrete5' : $packageInfo->Package) . ' ' . $packageInfo->Version . '\\n',
				'Report-Msgid-Bugs-To: ' . $packageInfo->PotContact . '\\n',
				'POT-Creation-Date: ' . gmdate('Y-m-d H:i', $timestamp ? $timestamp : time()) . '+0000\\n',
				'MIME-Version: 1.0\\n',
				'X-Poedit-Basepath: ' . $packageInfo->Potfile2root . '\\n',
				'X-Poedit-SourceCharset: UTF-8\n',
				'Content-Type: text/plain; charset=UTF-8\\n',
				'Content-Transfer-Encoding: 8bit\\n',
				'Language: \\n'
			)
		);
	}

	/** Create a .pot file starting from sources.
	* @param PackageInfo $packageInfo The info about the .pot file to be created (it'll be overwritten if already existing).
	* @throws Exception Throws an Exception in case of errors.
	*/
	public static function CreateNew($packageInfo) {
		Console::WriteLine('* CREATING .POT FILE ' . $packageInfo->PotName . ' FOR ' . ($packageInfo->IsConcrete5 ? 'concrete5 core' : $packageInfo->Package) . ' v' . $packageInfo->Version);
		Console::Write('  Listing .php files... ');
		$phpFiles = array();
		self::GetFiles($packageInfo->DirectoryToPotify, 'php', $phpFiles, $packageInfo->ExcludeDirsFromPot);
		if(!count($phpFiles)) {
			throw new Exception('No source .php files found.');
		}
		Console::WriteLine(count($phpFiles) . ' files found.');
		$xmlFiles = array();
		if($packageInfo->IsConcrete5) {
			Console::Write('  Listing .xml files... ');
			self::GetFiles($packageInfo->DirectoryToPotify, 'xml', $xmlFiles, $packageInfo->ExcludeDirsFromPot);
			if(!count($xmlFiles)) {
				throw new Exception('No .xml files found.');
			}
			Console::WriteLine(count($xmlFiles) . ' files found.');
			if(version_compare($packageInfo->Version, '5.6.1', '<')) {
				$xmlContexts = false;
			}
			else {
				$xmlContexts = true;
			}
		}
		Console::Write('  Extracting strings from .php files... ');
		$tempList = Enviro::GetTemporaryFileName();
		try {
			if(!@file_put_contents($tempList, implode("\n", $phpFiles))) {
				global $php_errormsg;
				throw new Exception("Error writing to '$tempFile': $php_errormsg");
			}
			$tempPot = Enviro::GetTemporaryFileName();
			try {
				@chdir(Options::$WebrootFolder);
				$args = array();
				$args[] = '--default-domain=messages'; // Domain
				$args[] = '--output=' . escapeshellarg(basename($tempPot)); // Output .pot file name
				$args[] = '--output-dir=' . escapeshellarg(dirname($tempPot)); // Output .pot folder name
				$args[] = '--language=PHP'; // Source files are in php
				$args[] = '--from-code=UTF-8'; // Source files are in utf-8
				$args[] = '--add-comments=i18n'; // Place comment blocks preceding keyword lines in output file if they start with '// i18n: '
				$args[] = '--keyword'; // Don't use default keywords
				$args[] = '--keyword=t:1'; // Look for the first argument of the "t" function for extracting translatable text in singular form
				$args[] = '--keyword=_:1'; // Look for the first argument of the "_" function for extracting translatable text in singular form
				$args[] = '--keyword=t2:1,2'; // Look for the first and second arguments of the "t2" function for extracting both the singular and plural forms
				$args[] = '--keyword=tc:1c,2'; // Look for the first argument of the "tc" function for extracting translation context, and the second argument is the translatable text in singular form.
				$args[] = '--no-escape'; // Do not use C escapes in output
				$args[] = '--indent'; // Write using indented style
				$args[] = '--add-location'; // Generate '#: filename:line' lines
				$args[] = '--no-wrap'; // Do not break long message lines, longer than the output page width, into several lines
				$args[] = '--files-from=' . escapeshellarg($tempList); // Get list of input files from file
				Enviro::RunTool('xgettext', $args);
				Console::WriteLine('done.');
				if(!empty($xmlFiles)) {
					Console::Write('  Extracting strings from .xml files... ');
					$xmlEntries = POEntry::FromXmlFile($xmlFiles, $xmlContexts);
					Console::WriteLine('done.');
				}
				Console::Write('  Loading .pot file... ');
				$pot = new POTFile($tempPot);
				Console::WriteLine('done.');
				Console::Write('  Fixing .pot file... ');
				$pot->FixHeader($packageInfo);
				$pot->FixFilesSlash();
				$pot->FixI18NComments();
				if(Enviro::GetOS() !== Enviro::OS_WIN) {
					$pot->Replace_CRLF_LF();
				}
				Console::WriteLine('done.');
				if(!empty($xmlFiles)) {
					Console::Write('  Merging strings from xml... ');
					$pot->MergeEntries($xmlEntries);
					Console::WriteLine('done.');
				}
				Console::Write('  Saving .pot file... ');
				$pot->SaveAs($packageInfo->PotFullname, ToolOptions::$Indent);
				Console::WriteLine('done.');
				Console::WriteLine('  .pot file created: ' . $packageInfo->PotFullname);
				@unlink($tempPot);
				@unlink($tempList);
			}
			catch(Exception $x) {
				@unlink($tempPot);
				throw $x;
			}
		} catch(Exception $x) {
			@unlink($tempList);
			throw $x;
		}
	}

	/** Sub-function called by POTFile::CreateNew to parse a sub-folder.
	* @param string $relPath The relative path of the sub-folder to be analyzed.
	* @param string $extension The lower-case extension of the files to retrieve (without initial dot).
	* @param ref array $items Found files will be appended to this array.
	* @throws Exception Throws an exception in case of errors.
	*/
	private static function GetFiles($relPath, $extension, &$items, $excludedDirs = array(), $_callback = false) {
		global $options;
		if(!$_callback) {
			if(is_array($excludedDirs)) {
				if(DIRECTORY_SEPARATOR != '/') {
					foreach(array_keys($excludedDirs) as $i) {
						$excludedDirs[$i] = str_replace('/', DIRECTORY_SEPARATOR, $excludedDirs[$i]);
					}
				}
			}
			else {
				$excludedDirs = array();
			}
		}
		$absPath = Enviro::MergePath(Options::$WebrootFolder, $relPath);
		if(!($hDir = @opendir($absPath))) {
			global $php_errormsg;
			throw new Exception("Error opening '$absPath': $php_errormsg");
		}
		try {
			while(($entry = @readdir($hDir)) !== false) {
				$relPathSub = Enviro::MergePath($relPath, $entry);
				$absPathSub = Enviro::MergePath($absPath, $entry);
				if(is_dir($absPathSub)) {
					switch($entry) {
						case '.':
						case '..':
							break;
						default:
							if(array_search($relPathSub, $excludedDirs) === false) {
								self::GetFiles($relPathSub, $extension, $items, $excludedDirs, true);
							}
							break;
					}
				}
				else {
					switch(strtolower(pathinfo($absPathSub, PATHINFO_EXTENSION))) {
						case $extension:
							$items[] = $relPathSub;
							break;
					}
				}
			}
			closedir($hDir);
		}
		catch(Exception $x) {
			@closedir($hDir);
			throw $x;
		}
	}
}

/** Represents a .po file (and exposes .po-related functions). */
class POFile extends POxFile {

	/** Reads a .po from file.
	* @param string $filename The name of the file to read.
	* @throws Exception Throws an Exception in case of errors.
	*/
	public function __construct($filename) {
		parent::__construct($filename);
	}

	/** Retrieves a value from the header.
	* @param string $key The name of the header value (with or without ending colon).
	* @param string $default What to return if the value is missing or empty.
	* @return string
	*/
	private function GetHeaderValue($key, $default = '') {
		if($this->Header == null) {
			return $default;
		}
		$key = rtrim($key, ':') . ':';
		$value = '';
		foreach(explode("\n", str_replace("\\n", "\n", $this->Header->GetMsgStr())) as $line) {
			$line = trim($line);
			if(stripos($line, $key) === 0) {
				$value = trim(substr($line, strlen($key)));
				break;
			}
		}
		return strlen($value) ? $value : $default;
	}

	/** Retrieves all the headers.
	* @param bool $removeEmptyValues Set to true to remove empty values, false (default) to keep them.
	* @return array
	* @throws Exception Throws an Exception in case of errors.
	*/
	private function GetHeaders($removeEmptyValues = false) {
		$headers = array();
		if(!is_null($this->Header)) {
			foreach(explode("\n", str_replace("\\n", "\n", $this->Header->GetMsgStr())) as $line) {
				$line = trim($line);
				if(strlen($line)) {
					$i = strpos($line, ':');
					if(($i === false) || ($i === 0)) {
						throw new Exception('Invalid header line: \'' . $line . '\'');
					}
					$key = substr($line, 0, $i);
					if(array_key_exists($key, $headers)) {
						throw new Exception('Duplicated header: \'' . $key . '\'');
					}
					$headers[$key] = trim(substr($line, $i + 1));
				}
			}
		}
		if($removeEmptyValues) {
			foreach(array_keys($headers) as $key) {
				if(!strlen($headers[$key])) {
					unset($headers[$key]);
				}
			}
		}
		return $headers;
	}

	/** Fixes the content of the header.
	* @param PackageInfo $packageInfo The info about the package.
	* @param string $language The language (and country) code (eg it or it_IT).
	* @throws Exception Throws an exception in case of errors.
	*/
	public function FixHeader($packageInfo, $language, $timestamp = null) {
		$language = Language::NormalizeCode($language);
		$lc = Language::SplitCode($language);
		$languages = Language::GetLanguages();
		$languageInfo = $languages[$lc['language']];
		if(strlen($lc['country'])) {
			$countries = Language::GetCountries();
			$countryInfo = $countries[$lc['country']];
		}
		else {
			$countryInfo = null;
		}
		$oldHeaders = $this->GetHeaders(true);
		$newHeaders = array(
				'Project-Id-Version' => ($packageInfo->IsConcrete5 ? 'concrete5' : $packageInfo->Package) . ' ' . $packageInfo->Version,
				'Report-Msgid-Bugs-To' => (isset($oldHeaders['Report-Msgid-Bugs-To']) ? $oldHeaders['Report-Msgid-Bugs-To'] : $packageInfo->PotContact),
				'POT-Creation-Date' => (isset($oldHeaders['POT-Creation-Date']) ? $oldHeaders['POT-Creation-Date'] : (gmdate('Y-m-d H:i', $timestamp ? $timestamp : time()) . '+0000')),
				'PO-Revision-Date' => (isset($oldHeaders['PO-Revision-Date']) ? $oldHeaders['PO-Revision-Date'] : (gmdate('Y-m-d H:i', $timestamp ? $timestamp : time()) . '+0000')),
				'Last-Translator' => (isset($oldHeaders['Last-Translator']) ? $oldHeaders['Last-Translator'] : ''),
				'Language-Team' => (isset($oldHeaders['Language-Team']) ? $oldHeaders['Language-Team'] : ''),
				'MIME-Version' => '1.0',
				'Content-Type' => 'text/plain; charset=UTF-8',
				'Content-Transfer-Encoding' => '8bit',
				'Language' => $language,
				'Plural-Forms' => $languageInfo['plural'],
				'X-Poedit-Basepath' => $packageInfo->Pofile2root,
				'X-Poedit-SourceCharset' => 'UTF-8',
				'X-Language' => $language,
				'X-Poedit-Language' => $languageInfo['name'],
				'X-Poedit-Country' => ($countryInfo ? $countryInfo['name'] : '')
		);
		foreach($oldHeaders as $oldKey => $oldValue) {
			if(!array_key_exists($oldKey, $newHeaders)) {
				$newHeaders[$oldKey] = $oldValue;
			}
		}
		$finalHeaders = array();
		foreach($newHeaders as $key => $value) {
			$finalHeaders[] = "$key: $value\\n";
		}
		$this->Header = new POEntrySingle(
				array(),
				$finalHeaders
		);
	}

	/** Create a language .po file starting from .pot file.
	* @param PackageInfo $packageInfo The info about the .po file to be created (it'll be overwritten if already existing).
	* @param string $language The language (and country) code (eg it or it_IT).
	* @throws Exception Throws an Exception in case of errors.
	*/
	public static function CreateNew($packageInfo, $language) {
		Console::WriteLine('* CREATING/UPDATING .PO FILE FOR ' . ($packageInfo->IsConcrete5 ? 'concrete5 core' : $packageInfo->Package) . ' v' . $packageInfo->Version . ', LANGUAGE ' . Language::DescribeCode($language));
		if(!is_file($packageInfo->PotFullname)) {
			throw new Exception('The .pot file name \'' . $packageInfo->PotFullname . '\' does not exist.');
		}
		$poFullFilename = $packageInfo->GetPoFullname($language);
		$poFolder = dirname($poFullFilename);
		if(!is_dir($poFolder)) {
			if(!@mkdir($poFolder, 0777, true)) {
				throw new Exception('Error creating the directory \'' . $poFolder . '\'');
			}
		}
		if(!is_writable($poFolder)) {
			throw new Exception('The directory \'' . $poFolder . '\' is not writable.');
		}
		$tempPoSrc = null;
		$tempPoDst = null;
		try {
			if(is_file($poFullFilename)) {
				$src = $poFullFilename;
				$isNew = false;
			}
			else {
				Console::Write('  Creating empty .po file... ');
				$src = $tempPoSrc = Enviro::GetTemporaryFileName();
				POFile::CreateEmpty($packageInfo, $language, $tempPoSrc);
				Console::WriteLine('done.');
				$isNew = true;
			}
			Console::Write('  Merging .pot and .po files... ');
			$tempPoDst = Enviro::GetTemporaryFileName();
			$args = array();
			$args[] = '--no-fuzzy-matching'; // Do not use fuzzy matching when an exact match is not found.
			$args[] = '--previous'; // Keep the previous msgids of translated messages, marked with '#|', when adding the fuzzy marker to such messages.
			$args[] = '--lang=' . $language; // Specify the 'Language' field to be used in the header entry
			$args[] = '--force-po'; // Always write an output file even if it contains no message.
			$args[] = '--indent'; // Write the .po file using indented style.
			$args[] = '--add-location'; // Generate '#: filename:line' lines.
			$args[] = '--no-wrap'; // Do not break long message lines
			$args[] = '--output-file=' . escapeshellarg($tempPoDst); // Write output to specified file.
			$args[] = escapeshellarg($src);
			$args[] = escapeshellarg($packageInfo->PotFullname);
			Enviro::RunTool('msgmerge', $args);
			Console::WriteLine('done.');
			Console::Write('  Fixing .po header... ');
			$poFile = new POFile($tempPoDst);
			$poFile->FixHeader($packageInfo, $language);
			Console::WriteLine('done.');
			Console::Write('  Saving final .po file... ');
			$poFile->SaveAs($poFullFilename, ToolOptions::$Indent);
			Console::WriteLine('done.');
			@unlink($tempPoDst);
			$tempPoDst = null;
			if(!is_null($tempPoSrc)) {
				@unlink($tempPoSrc);
				$tempPoSrc = null;
			}
			Console::WriteLine('  .po file ' . ($isNew ? 'created' : 'updated') . ': ' . $packageInfo->GetPoFullname($language));
		}
		catch(Exception $x) {
			if(!is_null($tempPoDst)) {
				@unlink($tempPoDst);
			}
			if(!is_null($tempPoSrc)) {
				@unlink($tempPoSrc);
			}
			throw $x;
		}
	}


	/** Compile a language .po into a .mo file (which will be overwritten if existing).
	* @param PackageInfo $packageInfo The info about the .po file to be compiled.
	* @param string $language The language (and country) code (eg it or it_IT).
	* @throws Exception Throws an Exception in case of errors.
	*/
	public static function Compile($packageInfo, $language) {
		Console::WriteLine('* CREATING .MO FILE FOR ' . ($packageInfo->IsConcrete5 ? 'concrete5 core' : $packageInfo->Package) . ' v' . $packageInfo->Version . ', LANGUAGE ' . Language::DescribeCode($language));
		$poFullFilename = $packageInfo->GetPoFullname($language);
		if(!is_file($poFullFilename)) {
			throw new Exception('The .po file name \'' . $poFullFilename . '\' does not exist.');
		}
		$moFullFilename = $packageInfo->GetMoFullname($language);
		$tempMo = Enviro::GetTemporaryFileName();
		try {
			Console::Write('  Compiling .po into temporary .mo file... ');
			$args = array();
			$args[] = '--output-file=' . escapeshellarg($tempMo);
			$args[] = '--check-format';
			$args[] = '--check-header';
			$args[] = '--check-domain';
			$args[] = escapeshellarg($poFullFilename);
			Enviro::RunTool('msgfmt', $args);
			Console::WriteLine('done.');
			Console::Write('  Moving .mo file to final location... ');
			if(is_file($moFullFilename)) {
				@unlink($moFullFilename);
			}
			if(!@rename($tempMo, $moFullFilename)) {
				throw new Exception("error renaming from '$tempMo' to '$moFullFilename'!");
			}
			$tempMo = '';
			Console::WriteLine('done.');
			Console::WriteLine('  .mo file created: ' . $moFullFilename);
		}
		catch(Exception $x) {
			if(strlen($tempMo)) {
				@unlink($tempMo);
			}
			throw $x;
		}
	}

	/** Create an epty .po file.
	* @param PackageInfo $packageInfo The info about the .po file to be created.
	* @param string $language The language (and country) code (eg it or it_IT).
	* @param string $filename The name of the file to be created (it'll be overwritten if existing).
	* @throws Exception Throws an Exception in case of errors.
	*/
	private static function CreateEmpty($packageInfo, $language, $filename) {
		if(!($hFile = @fopen($filename, 'wb'))) {
			throw new Exception("Error creating '$filename'.");
		}
		try {
			fwrite($hFile, str_replace(array("\r\n", "\r"), "\n", <<<EOT
msgid ""
msgstr ""
	"Project-Id-Version: \\n"
	"MIME-Version: 1.0\\n"
	"Content-Type: text/plain; charset=utf-8\\n"
	"Content-Transfer-Encoding: 8bit\\n"
	"X-Poedit-SourceCharset: utf-8\\n"

EOT
			));
			@fflush($hFile);
			fclose($hFile);
		}
		catch(Exception $x) {
			@fclose($hFile);
			throw $x;
		}
	}
}

/** Holds a single translation entry.
* @abstract
*/
class POEntry {

	/** All the comments associated to this entry.
	* @var array[string]
	*/
	public $Comments;

	/** All the lines associated to the entry context.
	* @var array[string]
	*/
	public $MsgCtxt;

	/** All the lines associated to the entry msgid.
	* @var array[string]
	*/
	public $MsgId;

	/** Is the entry deleted?
	* @var bool
	*/
	public $IsDeleted;

	/** Gets the entry context text.
	* @return string
	*/
	public function GetMsgCtx() {
		return self::ArrayToString($this->MsgCtxt);
	}

	/** Gets the entry msgid text.
	* @return string
	*/
	public function GetMsgId() {
		return self::ArrayToString($this->MsgId);
	}

	/** Builds a string from an array of strings.
	* @param array[string] $array The strings to be merged.
	* @return string
	*/
	protected static function ArrayToString($array) {
		if(empty($array)) {
			return '';
		}
		return implode($array);
	}

	/** Fix CR/LF in the given array of strings.
	* @param ref array[string] $array The array of strings to be fixed.
	*/
	protected static function Replace_CRLF_LF_in(&$array) {
		if(is_array($array)) {
			foreach(array_keys($array) as $key) {
				$array[$key] = str_replace('\\r\\n', '\\n', $array[$key]);
			}
		}
	}

	/** Fix CR/LF in the msgid array of strings. */
	protected function Replace_CRLF_LF_msgid() {
		self::Replace_CRLF_LF_in($this->MsgId);
	}

	/** Save an array of strings to file.
	* @param resource $hFile The file to save the data to.
	 @param string $prefix The prefix for each line (eg '#~ ' for obsolete items).
	* @param string $name The name of the data to be saved.
	* @param array[string] $array The data to be saved.
	* @param bool $indent Should we indent data [default: false].
	* @param bool $skipIfEmptyArray Should we skip data writing if the data to write is empty.
	*/
	protected static function ArrayToFile($hFile, $prefix, $name, $array, $indent = false, $skipIfEmptyArray = false) {
		if($indent) {
			if(strlen($name) < 7) {
				$name .= str_repeat(' ', 7 - strlen($name));
			}
			$pre = str_repeat(' ', strlen($name) + 1);
		}
		else {
			$pre = '';
		}
		if(empty($array)) {
			if(!$skipIfEmptyArray) {
				fwrite($hFile, "$name \"\"\n");
			}
		}
		else {
			$first = true;
			foreach($array as $line) {
				if($first) {
					fwrite($hFile, "$prefix$name \"$line\"\n");
					$first = false;
				}
				else {
					fwrite($hFile, "$prefix$pre\"$line\"\n");
				}
			}
		}
	}

	/** Fix slash from back to forward in location comments. */
	public function FixFilesSlash() {
		$n = count($this->Comments);
		for($i = 0; $i < $n; $i++) {
			if(strpos($this->Comments[$i], '#: ') === 0) {
				$this->Comments[$i] = str_replace('\\', '/', $this->Comments[$i]);
			}
		}
	}

	/** Remove the superfluous i18n at the beginning of comments. */
	public function FixI18NComments() {
		$n = count($this->Comments);
		for($i = 0; $i < $n; $i++) {
			if(preg_match('/^#\\.\s/', $this->Comments[$i])) {
				if(preg_match('/^#\\.\s+i18n\s*(:\s*)(.+)$/', $this->Comments[$i], $m)) {
					$this->Comments[$i] = '#. ' . $m[2];
				}
				break;
			}
		}
	}

	/** Initializes the data instance.
	* @param array[string] $comments The string array of comments.
	* @param array[string] $msgctxt The string array of msgctx.
	* @param array[string] $msgid The string array of msgid.
	* @param bool $isDeleted Is the entry marked as deleted [default: false].
	*/
	protected function __construct($comments, $msgctxt, $msgid, $isDeleted = false) {
		$this->Comments = is_array($comments) ? $comments : (strlen($comments) ? array($comments) : array());
		$this->MsgCtxt = is_array($msgctxt) ? $msgctxt : (strlen($msgctxt) ? array($msgctxt) : array());
		$this->MsgId = is_array($msgid) ? $msgid : (strlen($msgid) ? array($msgid) : array());
		$this->IsDeleted = $isDeleted ? true : false;
	}

	/** Callback function to sort comments.
	* @param string $a The first comment to compare.
	* @param string $b The second comment to compare.
	* @return int
	*/
	private static function CommentsSorter($a, $b) {
		if((strlen($a) < 2) || (strlen($b) < 2)) {
			return 0;
		}
		/* ORDER:
		#  translator-comments
		#. extracted-comments
		#: reference...
		#, flag...
		#| msgid previous-untranslated-string
		*/
		$order = ' .:,|';
		$delta = strpos($order, $a[1]) - strpos($order, $b[1]);
		if($delta) {
			return $delta;
		}
		switch($a[1]) {
			case ':':
				if(preg_match('/^#:[ \t]+(.*):([\d]+)$/', $a, $cA) && preg_match('/^#:[ \t]+(.*):([\d]+)$/', $b, $cB)) {
					$fd = strcasecmp($cA[1], $cB[1]);
					if(!$fd) {
						$fd = intval($cA[2]) - intval($cB[2]);
					}
					return $fd;
				}
				else {
					return strcasecmp($a, $b);
				}
			default:
				return 0;
		}
	}

	/** Save the instance data to file (comments and context).
	* @param resource $hFile The file to save the data to.
	* @param bool $indent Set to true to indent data, false otherwise (default: false).
	*/
	protected function _saveTo($hFile, $indent = false) {
		usort($this->Comments, array(__CLASS__, 'CommentsSorter'));
		foreach($this->Comments as $comment) {
			fwrite($hFile, $comment);
			fwrite($hFile, "\n");
		}
		self::ArrayToFile($hFile, $this->IsDeleted ? '#~ ' : '', 'msgctxt', $this->MsgCtxt, $indent, true);
	}

	/** Retrieves POEntry instances from one/many .xml files.
	* @param string|array[string] $xmlFiles The .xml file(s) to read.
	* @param bool $xmlContexts [default: false] Write contexts for the extracted strings?
	* @return array[POEntry]
	* @throws Exception Throws an Exception in case of errors.
	*/
	public static function FromXmlFile($xmlFiles, $xmlContexts = false) {
		if(is_array($xmlFiles)) {
			$result = array();
			foreach($xmlFiles as $xmlFile) {
				foreach(self::FromXmlFile($xmlFile, $xmlContexts) as $poEntry) {
					$hash = $poEntry->GetHash();
					if(array_key_exists($hash, $result)) {
						$result[$hash]->MergeWith($poEntry);
					}
					else {
						$result[$hash] = $poEntry;
					}
				}
			}
			return array_values($result);
		}
		else {
			global $options;
			$filenameRel = $xmlFiles;
			$filenameAbs = Enviro::MergePath(Options::$WebrootFolder, $xmlFiles);
			$xml = new DOMDocument();
			if(!@$xml->load($filenameAbs)) {
				global $php_errormsg;
				throw new Exception("Error loading '$filename': $php_errormsg");
			}
			$entries = array();
			switch($xml->documentElement->tagName) {
				case 'concrete5-cif':
					self::ParseXmlNode($filenameRel, '', $xml->documentElement, $entries, $xmlContexts);
					break;
				case 'schema':
				case 'access':
					break;
				default:
					throw new Exception('Unknown root node: ' . $xml->documentElement->tagName . ' in ' . $filenameRel);
			}
			return $entries;
		}
	}

	/** Parse an xml node and retrieves any associated POEntry.
	* @param string $filenameRel The relative file name of the xml file being read.
	* @param string $prePath The path of the node containing the current node.
	* @param DOMNode $node The current node.
	* @param ref array[POEntry] $entries Will be populated with found entries.
	* @param bool $xmlContexts [default: false] Write contexts for the extracted strings?
	* @throws Exception Throws an Exception in case of errors.
	*/
	private static function ParseXmlNode($filenameRel, $prePath, $node, &$entries, $xmlContexts = false) {
		switch(get_class($node)) {
			case 'DOMElement':
				break;
			case 'DOMText':
			case 'DOMCdataSection':
			case 'DOMComment':
				return;
			default:
				throw new Exception(get_class($node) . ' in ' . $filenameRel);
		}
		$path = $prePath . '/' . $node->tagName;
		$childnodesLimit = null;
		switch($path) {
			case '/concrete5-cif':
			case '/concrete5-cif/attributecategories':
			case '/concrete5-cif/attributecategories/category':
			case '/concrete5-cif/attributetypes':
			case '/concrete5-cif/attributetypes/attributetype/categories':
			case '/concrete5-cif/attributetypes/attributetype/categories/category':
			case '/concrete5-cif/attributekeys':
			case '/concrete5-cif/attributekeys/attributekey/type':
			case '/concrete5-cif/attributekeys/attributekey/type/options';
			case '/concrete5-cif/attributekeys/attributekey/type/options/option';
			case '/concrete5-cif/attributesets':
			case '/concrete5-cif/attributesets/attributeset/attributekey':
			case '/concrete5-cif/blocktypes':
			case '/concrete5-cif/blocktypes/blocktype':
			case '/concrete5-cif/singlepages':
			case '/concrete5-cif/singlepages/page/attributes':
			case '/concrete5-cif/singlepages/page/attributes/attributekey':
			case '/concrete5-cif/pagetypes':
			case '/concrete5-cif/pages':
			case '/concrete5-cif/pages/page/attributes':
			case '/concrete5-cif/pages/page/attributes/attributekey':
			case '/concrete5-cif/pages/page/attributes/attributekey/value':
			case '/concrete5-cif/pages/page/attributes/attributekey/value/option':
			case '/concrete5-cif/pages/page/area/block/data':
			case '/concrete5-cif/pages/page/area/block/stack':
			case '/concrete5-cif/jobs':
			case '/concrete5-cif/jobs/job':
			case '/concrete5-cif/jobsets':
			case '/concrete5-cif/jobsets/jobset/job':
			case '/concrete5-cif/singlepages/page/area':
			case '/concrete5-cif/permissioncategories':
			case '/concrete5-cif/permissioncategories/category':
			case '/concrete5-cif/permissionkeys':
			case '/concrete5-cif/permissionkeys/permissionkey/access':
			case '/concrete5-cif/permissionkeys/permissionkey/access/group':
			case '/concrete5-cif/systemcaptcha':
			case '/concrete5-cif/workflowtypes':
			case '/concrete5-cif/permissionaccessentitytypes':
			case '/concrete5-cif/permissionaccessentitytypes/permissionaccessentitytype/categories':
			case '/concrete5-cif/permissionaccessentitytypes/permissionaccessentitytype/categories/category':
			case '/concrete5-cif/workflowprogresscategories':
			case '/concrete5-cif/workflowprogresscategories/category':
			case '/concrete5-cif/themes':
			case '/concrete5-cif/themes/theme':
			case '/concrete5-cif/taskpermissions':
			case '/concrete5-cif/taskpermissions/taskpermission/access':
			case '/concrete5-cif/taskpermissions/taskpermission/access/group':
			case '/concrete5-cif/stacks':
			case '/concrete5-cif/stacks/stack/area/block/data';
			case '/concrete5-cif/pagetypes/pagetype/page/area/block/data':
			case '/concrete5-cif/pagetypes/pagetype/composer':
			case '/concrete5-cif/pagetypes/pagetype/composer/items':
			case '/concrete5-cif/pagetypes/pagetype/composer/items/attributekey':
			case '/concrete5-cif/pagetypes/pagetype/page/attributes':
			case '/concrete5-cif/pagetypes/pagetype/page/attributes/attribute':
			case '/concrete5-cif/pagetypes/pagetype/page/attributes/attribute':
			case '/concrete5-cif/pagetypes/pagetype/page/attributes/attributekey';
				// Skip this node
				break;
			case '/concrete5-cif/config':
			case '/concrete5-cif/stacks/stack/area/block/data/record';
			case '/concrete5-cif/pagetypes/pagetype/page/area/block/data/record':
				// Skip this node and its children
				return;
			case '/concrete5-cif/pagetypes/pagetype':
			case '/concrete5-cif/pages/page/area':
			case '/concrete5-cif/pages/page/area/block':
			case '/concrete5-cif/systemcaptcha/library':
			case '/concrete5-cif/workflowtypes/workflowtype':
			case '/concrete5-cif/stacks/stack';
			case '/concrete5-cif/stacks/stack/area';
			case '/concrete5-cif/stacks/stack/area/block';
			case '/concrete5-cif/pagetypes/pagetype/page/area':
			case '/concrete5-cif/pagetypes/pagetype/page/area/block':
			case '/concrete5-cif/pagetypes/pagetype/composer/items/block':
				// Translatable text: name attribute
				self::ReadNodeAttribute($filenameRel, $node, 'name', $entries);
				break;
			case '/concrete5-cif/attributekeys/attributekey':
				// Translatable text: name attribute (it's a concrete5 attribute key name)
				self::ReadNodeAttribute($filenameRel, $node, 'name', $entries, $xmlContexts ? 'AttributeKeyName' : '');
				break;
			case '/concrete5-cif/attributesets/attributeset':
				// Translatable text: name attribute (it's a concrete5 attribute set name)
				self::ReadNodeAttribute($filenameRel, $node, 'name', $entries, $xmlContexts ? 'AttributeSetName' : '');
				break;
			case '/concrete5-cif/attributetypes/attributetype':
				// Translatable text: name attribute (it's a concrete5 attribute type name)
				self::ReadNodeAttribute($filenameRel, $node, 'name', $entries, $xmlContexts ? 'AttributeTypeName' : '');
				break;
			case '/concrete5-cif/permissionaccessentitytypes/permissionaccessentitytype':
				// Translatable text: name attribute (it's a concrete5 access entity type name)
				self::ReadNodeAttribute($filenameRel, $node, 'name', $entries, $xmlContexts ? 'PermissionAccessEntityTypeName' : '');
				break;
			case '/concrete5-cif/singlepages/page':
			case '/concrete5-cif/pages/page':
			case '/concrete5-cif/singlepages/page':
			case '/concrete5-cif/taskpermissions/taskpermission':
			case '/concrete5-cif/pagetypes/pagetype/page':
				// Translatable text: name attribute, description attribute
				self::ReadNodeAttribute($filenameRel, $node, 'name', $entries);
				self::ReadNodeAttribute($filenameRel, $node, 'description', $entries);
				break;
			case '/concrete5-cif/permissionkeys/permissionkey':
				// Translatable text: name attribute (it's a concrete5 permission key name), description attribute (it's a concrete5 permission key description)
				self::ReadNodeAttribute($filenameRel, $node, 'name', $entries, $xmlContexts ? 'PermissionKeyName' : '');
				self::ReadNodeAttribute($filenameRel, $node, 'description', $entries, $xmlContexts ? 'PermissionKeyDescription' : '');
				break;
			case '/concrete5-cif/pages/page/area/block/data/record':
				// Skip this node and *almost* all its children
				$childnodesLimit = array('title');
				break;
			case '/concrete5-cif/pages/page/area/block/data/record/title':
				// Translatable text: node value
				self::ReadNodeValue($filenameRel, $node, $entries);
				break;
			case '/concrete5-cif/singlepages/page/attributes/attributekey/value':
				switch($node->parentNode->getAttribute('handle')) {
					case 'meta_keywords':
						self::ReadPageKeywords($filenameRel, $node->parentNode->parentNode->parentNode->getAttribute('path'), $node, $entries);
						break;
				}
				break;
			case '/concrete5-cif/jobsets/jobset':
				// Translatable text: name attribute (it's a concrete5 job set name)
				self::ReadNodeAttribute($filenameRel, $node, 'name', $entries, $xmlContexts ? 'JobSetName' : '');				
				break;				
			default:
				throw new Exception('Unknown tag name ' . $path . ' in ' . $filenameRel);
		}
		if($node->hasChildNodes()) {
			foreach($node->childNodes as $child) {
				if((!$childnodesLimit) || (is_a($child, 'DOMElement') && array_search($child->tagName, $childnodesLimit) !== false)) {
					self::ParseXmlNode($filenameRel, $path, $child, $entries, $xmlContexts);
				}
			}
		}
	}

	/** Parse a node attribute and create a POEntry item if it has a value.
	* @param string $filenameRel The relative file name of the xml file being read.
	* @param DOMNode $node The current node.
	* @param string $attributeName The name of the attribute.
	* @param ref array[POEntry] $entries Will be populated with found entries.
	* @param string $context [default: ''] The translation context
	*/
	private static function ReadNodeAttribute($filenameRel, $node, $attributeName, &$entries, $context = '') {
		$value = $node->getAttribute($attributeName);
		if(strlen($value)) {
			$key = strlen($context) ? "$context\x04$value" : $value;
			if(!array_key_exists($key, $entries)) {
				$entries[$key] = new POEntrySingle($value, array(), array(), $context);
			}
			$entries[$key]->Comments[] = '#: ' . str_replace('\\', '/', $filenameRel) . ':' . $node->getLineNo();
		}
	}

	/** Parse a node value and create a POEntry item if it has a value.
	* @param string $filenameRel The relative file name of the xml file being read.
	* @param DOMNode $node The current node.
	* @param ref array[POEntry] $entries Will be populated with found entries.
	*/
	private static function ReadNodeValue($filenameRel, $node, &$entries) {
		$value = $node->nodeValue;
		if(strlen($value)) {
			if(!array_key_exists($value, $entries)) {
				$entries[$value] = new POEntrySingle($value);
			}
			$entries[$value]->Comments[] = '#: ' . str_replace('\\', '/', $filenameRel) . ':' . $node->getLineNo();
		}
	}

	/** Parse a node attribute which contains the keywords for a page.
	* @param string $filenameRel The relative file name of the xml file being read.
	* @param string $pageUrl The url of the page for which the keywords are for.
	* @param DOMNode $node The current node.
	* @param string $attributeName The name of the attribute.
	* @param ref array[POEntry] $entries Will be populated with found entries.
	*/
	private static function ReadPageKeywords($filenameRel, $pageUrl, $node, &$entries) {
		$keywords = $node->nodeValue;
		if(strlen($keywords)) {
			$comment = "#. Keywords for page $pageUrl";
			if(array_key_exists($keywords, $entries)) {
				$entries[$keywords]->Comments[] = $comment;
			}
			else {
				$entries[$keywords] = new POEntrySingle($keywords, array(), array($comment));
			}
			$entries[$keywords]->Comments[] = '#: ' . str_replace('\\', '/', $filenameRel) . ':' . $node->getLineNo();
		}
	}

	/** Merge an instance of a POEntry with this (they must be equal, only comments may differ).
	* @param POEntry $poEntry The entry to be merged.
	*/
	public function MergeWith($poEntry) {
		$this->Comments = array_merge($this->Comments, $poEntry->Comments);
	}

	/** Read the next POEntry from the lines of the .po file.
	* @param array[string] $lines The lines of the file.
	* @param int $start The current line position.
	* @param out int $nextStart The next line position.
	* @throws Exception Throws an Exception in case of errors.
	* @return POEntry|null Returns null if no POEntry has been found, a POEntry if found.
	*/
	public static function GetNextFromLines($lines, $start, &$nextStart) {
		$n = count($lines);
		$nextStart = -1;
		$comments = array();
		$msgctxtIndex = -1;
		$msgidIndex = -1;
		$msgid_pluralIndex = -1;
		$msgstrIndex = -1;
		$msgstr_pluralIndexes = array();
		$isDeleted = null;
		for($i = $start; ($i < $n) && ($nextStart < 0); $i++) {
			$trimmedLine = trim($lines[$i]);
			if(strlen($trimmedLine)) {
				$dataReady = (($msgstrIndex >= 0) || count($msgstr_pluralIndexes));
				if(preg_match('/^#~[ \t]+msgctxt($|\s|")/', $trimmedLine)) {
					if($dataReady) {
						$lineType = 'next';
					}
					else {
						if(!is_null($isDeleted)) {
							throw new Exception('Misplaced deleted msgctxt');
						}
						$isDeleted = true;
						$lineType = 'msgctxt';
					}
				}
				elseif(preg_match('/^#~[ \t]+msgid($|\s|")/', $trimmedLine)) {
					if($dataReady) {
						$lineType = 'next';
					}
					else {
						if(is_null($isDeleted) || ($isDeleted)) {
							$isDeleted = true;
						}
						else {
							throw new Exception('Misplaced deleted msgid');
						}
						$lineType = 'msgid';
					}
				}
				elseif(preg_match('/^#~[ \t]+msgid_plural($|\s|")/', $trimmedLine)) {
					if(!$isDeleted) {
						throw new Exception('Misplaced deleted msgid_plural');
					}
					$lineType = 'msgid_plural';
				}
				elseif(preg_match('/^#~[ \t]+msgstr($|\s|")/', $trimmedLine)) {
					if(!$isDeleted) {
						throw new Exception('Misplaced deleted msgstr');
					}
					$lineType = 'msgstr';
				}
				elseif(preg_match('/^#~[ \t]+msgstr\[[0-9]+\]/', $trimmedLine)) {
					if(!$isDeleted) {
						throw new Exception('Misplaced deleted msgstr[]');
					}
					$lineType = 'msgstr_plural';
				}
				elseif(strpos($trimmedLine, '#~') === 0) {
					if($isDeleted === true) {
						$lineType = 'text';
					}
					else {
						throw new Exception('Misplaced deleted marked (#~)');
					}
				}
				elseif($trimmedLine[0] == '#') {
					if($dataReady) {
						$lineType = 'next';
					}
					else {
						$lineType = 'comment';
					}
				}
				elseif(preg_match('/^msgctxt($|\s|")/', $trimmedLine)) {
					if($dataReady) {
						$lineType = 'next';
					}
					else {
						if(!is_null($isDeleted)) {
							throw new Exception('Misplaced non-deleted msgctxt');
						}
						$isDeleted = false;
						$lineType = 'msgctxt';
					}
				}
				elseif(preg_match('/^msgid($|\s|")/', $trimmedLine)) {
					if($dataReady) {
						$lineType = 'next';
					}
					else {
						if(is_null($isDeleted) || (!$isDeleted)) {
							$isDeleted = false;
						}
						else {
							throw new Exception('Misplaced non-deleted msgid');
						}
						$lineType = 'msgid';
					}
				}
				elseif(preg_match('/^msgid_plural($|\s|")/', $trimmedLine)) {
					if($isDeleted) {
						throw new Exception('Misplaced non-deleted msgid_plural');
					}
					$lineType = 'msgid_plural';
				}
				elseif(preg_match('/^msgstr($|\s|")/', $trimmedLine)) {
					if($isDeleted) {
						throw new Exception('Misplaced non-deleted msgstr');
					}
					$lineType = 'msgstr';
				}
				elseif(preg_match('/^msgstr\[[0-9]+\]/', $trimmedLine)) {
					if($isDeleted) {
						throw new Exception('Misplaced non-deleted msgstr[]');
					}
					$lineType = 'msgstr_plural';
				}
				elseif(preg_match('/^".*"$/', $trimmedLine)) {
					$lineType = 'text';
				}
				else {
					throw new Exception("Invalid content '$trimmedLine' at line " . ($i + 1) . ".");
				}
				switch($lineType) {
					case 'next';
						$nextStart = $i;
						break;
					case 'comment':
						if($msgidIndex < 0) {
							while(preg_match('/^(#:[ \t].*?:\d+)[ \t]+([^ \t].*:\d.*)$/', $trimmedLine, $chunks)) {
								$comments[] = $chunks[1];
								$trimmedLine = '#: ' . trim($chunks[2]);
							}
							$comments[] = $trimmedLine;
						}
						else {
							throw new Exception("Misplaced '$trimmedLine' (linetype: $lineType) at line " . ($i + 1) . ".");
						}
						break;
					case 'msgctxt':
						if(($msgctxtIndex < 0) && ($msgidIndex < 0)) {
							$msgctxtIndex = $i;
						}
						else {
							throw new Exception("Misplaced '$trimmedLine' (linetype: $lineType) at line " . ($i + 1) . ".");
						}
						break;
					case 'msgid':
						if($msgidIndex < 0) {
							$msgidIndex = $i;
						}
						else {
							throw new Exception("Misplaced '$trimmedLine' (linetype: $lineType) at line " . ($i + 1) . ".");
						}
						break;
					case 'msgid_plural':
						if(($msgidIndex >= 0) && ($msgid_pluralIndex < 0) && ($msgstrIndex < 0) && empty($msgstr_pluralIndexes)) {
							$msgid_pluralIndex = $i;
						}
						else {
							throw new Exception("Misplaced '$trimmedLine' (linetype: $lineType) at line " . ($i + 1) . ".");
						}
						break;
					case 'msgstr':
						if(($msgidIndex >= 0) && ($msgstrIndex < 0) && empty($msgstr_pluralIndexes)) {
							$msgstrIndex = $i;
						}
						else {
							throw new Exception("Misplaced '$trimmedLine' (linetype: $lineType) at line " . ($i + 1) . ".");
						}
						break;
					case 'msgstr_plural':
						if(($msgidIndex >= 0) && ($msgid_pluralIndex >= 0) && ($msgstrIndex < 0)) {
							$msgstr_pluralIndexes[] = $i;
						}
						else {
							throw new Exception("Misplaced '$trimmedLine' (linetype: $lineType) at line " . ($i + 1) . ".");
						}
						break;
					case 'text':
						if($msgidIndex < 0) {
							throw new Exception("Misplaced '$trimmedLine' (linetype: $lineType) at line " . ($i + 1) . ".");
						}
						break;
					default:
						throw new Exception("Not implemented line type: $lineType");
				}
			}
		}
		if($nextStart < 0) {
			$nextStart = $n;
		}
		if((!empty($comments)) || ($msgctxtIndex >= 0) || ($msgidIndex >= 0) || ($msgid_pluralIndex >= 0) || ($msgstrIndex >= 0) || count($msgstr_pluralIndexes)) {
			if($isDeleted) {
				$isDeleted = true;
			}
			if($msgidIndex < 0) {
				throw new Exception("Missing msgid for entry starting at line " . ($i + 1) . ".");
			}
			if($msgid_pluralIndex >= 0) {
				if(($msgstrIndex >= 0) || empty($msgstr_pluralIndexes)) {
					throw new Exception("Expected plural msgstr for entry starting at line " . ($i + 1) . ".");
				}
				return POEntryPlural::FromLines($lines, $comments, $msgctxtIndex, $msgidIndex, $msgid_pluralIndex, $msgstr_pluralIndexes, $isDeleted);
			}
			else {
				if(($msgstrIndex < 0) || count($msgstr_pluralIndexes)) {
					throw new Exception("Expected singular msgstr for entry starting at line " . ($i + 1) . ".");
				}
				return POEntrySingle::FromLines($lines, $comments, $msgctxtIndex, $msgidIndex, $msgstrIndex, $isDeleted);
			}
		}
		else {
			return null;
		}
	}
}

/** Holds a single translation entry (non-plural). */
class POEntrySingle extends POEntry {

	/** All the lines associated to the entry msgstr.
	* @var array[string]
	*/
	public $MsgStr;


	/** Gets the entry msgstr text.
	* @return string
	*/
	public function GetMsgStr() {
		return self::ArrayToString($this->MsgStr);
	}

	/** Initializes the data instance.
	* @param array[string] $msgid The string array of msgid.
	* @param array[string] $msgstr The string array of msgstr.
	* @param array[string] $comments The string array of comments.
	* @param array[string] $msgctxt The string array of msgctx.
	* @param bool $isDeleted Is the entry marked as deleted [default: false].
	*/
	public function __construct($msgid, $msgstr = array(), $comments = array(), $msgctxt = array(), $isDeleted = false) {
		parent::__construct($comments, $msgctxt, $msgid, $isDeleted);
		$this->MsgStr = is_array($msgstr) ? $msgstr : (strlen($msgstr) ? array($msgstr) : array());
	}

	/** Create a POEntrySingle from .po/.pot file lines.
	* @param array[string] $lines All the lines of the file.
	* @param array[string] $comments The comments lines.
	* @param int $msgctxtIndex The index of the first line of msgctxt (-1 if not present).
	* @param int $msgidIndex The index of the first line of msgid.
	* @param int $msgstrIndex The index of the first line of msgstr.
	* @param bool $isDeleted True if the entry is marked as deleted.
	* @return POEntrySingle
	*/
	public static function FromLines($lines, $comments, $msgctxtIndex, $msgidIndex, $msgstrIndex, $isDeleted) {
		$values = array(array(), array(), array());
		$n = count($lines);
		foreach(array($msgctxtIndex, $msgidIndex, $msgstrIndex) as $valueIndex => $i) {
			if($i < 0) {
				continue;
			}
			for($j = $i; $j < $n; $j++) {
				if(preg_match(($j == $i) ? '/"(.*)"[ \t]*$/' : ('/^' . ($isDeleted ? '#~' : '') . '[ \t]*"(.*)"[ \t]*$/'), $lines[$j], $matches)) {
					$values[$valueIndex][] = $matches[1];
				}
				else {
					break;
				}
			}
		}
		$className = __CLASS__;
		return new $className($values[1], $values[2], $comments, $values[0], $isDeleted);
	}

	/** Fix the cr/lf end-of-line terminator in every msgid (required when parsed source files under Windows). */
	public function Replace_CRLF_LF() {
		parent::Replace_CRLF_LF_msgid();
	}

	/** Save the instance data to file.
	* @param resource $hFile The file to save the data to.
	* @param bool $indent Set to true to indent data, false otherwise (default: false).
	*/
	public function SaveTo($hFile, $indent = false) {
		parent::_saveTo($hFile, $indent);
		self::ArrayToFile($hFile, $this->IsDeleted ? '#~ ' : '', 'msgid', $this->MsgId, $indent);
		self::ArrayToFile($hFile, $this->IsDeleted ? '#~ ' : '', 'msgstr', $this->MsgStr, $indent);
	}

	/** Retrieves a string that uniquely identifies the entry.
	* @return string
	*/
	public function GetHash() {
		return $this->GetMsgCtx() . chr(1) . $this->GetMsgId() . chr(3) . ($this->IsDeleted ? 0 : 1);
	}
}

/** Holds a single translation entry (plural). */
class POEntryPlural extends POEntry {

	/** All the lines associated to the entry msgstr.
	* @var array[string]
	*/
	public $MsgIdPlural;

	/** All the lines associated to the all msgstr[] entries.
	* @var array[array[string]]
	*/
	public $MsgStr;


	/** Initializes the data instance.
	* @param array[string] $msgid The string array of msgid.
	* @param array[string] $msgid_plural The string array of msgid_plural.
	* @param array[array[string]] $msgstr The string array of every msgstr[].
	* @param array[string] $comments The string array of comments.
	* @param array[string] $msgctxt The string array of msgctx.
	* @param bool $isDeleted Is the entry marked as deleted [default: false].
	*/
	public function __construct($msgid, $msgid_plural, $msgstr = array(), $comments = array(), $msgctxt = array(), $isDeleted = false) {
		parent::__construct($comments, $msgctxt, $msgid, $isDeleted);
		$this->MsgIdPlural = is_array($msgid_plural) ? $msgid_plural : (strlen($msgid_plural) ? array($msgid_plural) : array());
		$this->MsgStr = is_array($msgstr) ? $msgstr : array();
	}

	/** Gets the entry msgid_plural text.
	* @return string
	*/
	public function GetMsgId_Plural() {
		return self::ArrayToString($this->MsgId);
	}

	/** Create a POEntryPlural from .po/.pot file lines.
	* @param array[string] $lines All the lines of the file.
	* @param array[string] $comments The comments lines.
	* @param int $msgctxtIndex The index of the first line of msgctxt (-1 if not present).
	* @param int $msgidIndex The index of the first line of msgid.
	* @param int $msgid_pluralIndex The index of the first line of msgid_plural.
	* @param array[int] $msgstr_pluralIndexes The indexes of the first lines of each msgstr[].
	* @param bool $isDeleted True if the entry is marked as deleted.
	* @throws Exception Throws an Exception in case of errors.
	* @return POEntryPlural
	*/
	public static function FromLines($lines, $comments, $msgctxtIndex, $msgidIndex, $msgid_pluralIndex, $msgstr_pluralIndexes, $isDeleted) {
		$n = count($lines);
		$multiValues = array();
		$pluralCount = 0;
		for($k = 0; $k < 2; $k++) {
			switch($k) {
				case 0:
					$indexes = array($msgctxtIndex, $msgidIndex, $msgid_pluralIndex);
					$multiValues[$k] = array(array(), array(), array());
					break;
				case 1:
					$indexes = $msgstr_pluralIndexes;
					$multiValues[$k] = array();
					for($j = 0; $j < count($msgstr_pluralIndexes); $j++) {
						$multiValues[$k][$j] = array();
					}
					break;
			}
			foreach($indexes as $valueIndex => $i) {
				if($i < 0) {
					continue;
				}
				for($j = $i; $j < $n; $j++) {
					if(preg_match(($j == $i) ? '/"(.*)"[ \t]*$/' : '/^[ \t]*"(.*)"[ \t]*$/', $lines[$j], $matches)) {
						$multiValues[$k][$valueIndex][] = $matches[1];
						if(($k == 1) && ($j = $i)) {
							if(!preg_match('/^[ \t]*msgstr\[(\d)+\]/', $lines[$j], $matches)) {
								throw new Exception("Bad msgstr '{$lines[$j]}' at line " . ($j + 1));
							}
							$msgstr_index = @intval($matches[1]);
							if($pluralCount !== $msgstr_index) {
								throw new Exception("Bad msgstr '{$lines[$j]}' at line " . ($j + 1) . " (expected index $pluralCount).");
							}
							$pluralCount++;
						}
					}
					else {
						break;
					}
				}
			}
		}
		$className = __CLASS__;
		return new $className($multiValues[0][1], $multiValues[0][2], $multiValues[1], $comments, $multiValues[0][0], $isDeleted);
	}

	/** Fix the cr/lf end-of-line terminator in msgid and msgid_plural (required when parsed source files under Windows). */
	public function Replace_CRLF_LF() {
		parent::Replace_CRLF_LF_msgid();
		self::Replace_CRLF_LF_in($this->MsgIdPlural);
	}

	/** Save the instance data to file.
	* @param resource $hFile The file to save the data to.
	* @param bool $indent Set to true to indent data, false otherwise (default: false).
	*/
	public function SaveTo($hFile, $indent = false) {
		parent::_saveTo($hFile, $indent = false);
		self::ArrayToFile($hFile, $this->IsDeleted ? '#~ ' : '', 'msgid', $this->MsgId, $indent);
		self::ArrayToFile($hFile, $this->IsDeleted ? '#~ ' : '', 'msgid_plural', $this->MsgIdPlural, $indent);
		for($i = 0; $i < count($this->MsgStr); $i++) {
			self::ArrayToFile($hFile, $this->IsDeleted ? '#~ ' : '', "msgstr[$i]", $this->MsgStr[$i], $indent);
		}
	}

	/** Retrieves a string that uniquely identifies the entry.
	* @return string
	*/
	public function GetHash() {
		return $this->GetMsgCtx() . chr(1) . $this->GetMsgId() . chr(2) . $this->GetMsgId_Plural() . chr(3) . ($this->IsDeleted ? 0 : 1);
	}
}

/** Static class with language-related functions. */
class Language {

	/** Get the code of the language of the original strings.
	* @return string
	*/
	public static function GetSourceCode() {
		return 'en';
	}

	/** Get the predefined language codes.
	* @return array[string]
	*/
	public static function GetStandardCodes() {
		return array(
				'ar',
				'bg',
				'ca',
				'cs_CZ',
				'da_DK',
				'de_DE',
				'es',
				'es_AR',
				'es_ES',
				'es_PE',
				'et',
				'fa_IR',
				'fi_FI',
				'fr_FR',
				'he_IL',
				'hi',
				'hr',
				'hu_HU',
				'id_ID',
				'it_IT',
				'ja_JP',
				'ku',
				'lt_LT',
				'nl_NL',
				'no_NO',
				'pl_PL',
				'pt_BR',
				'pt_PT',
				'ro_RO',
				'ru_RU',
				'sv_SE',
				'th_TH',
				'tr',
				'zh',
				'zh_CN',
		);
	}

	/** Split a code into language (required) and country (optional) codes (eg: from 'xx_XX' to array with <b>language</b> and <b>country</b>).
	* @param string $code The code to be splitted.
	* @throws Exception Throws an Exception if $code is malformed or if contains invalid values.
	* @return array
	*/
	public static function SplitCode($code) {
		if(!preg_match('/^([a-z]{2,3})([_\\-]([a-z]{2,3}))?$/i', $code, $m)) {
			throw new Exception("'$code' is not a valid language/country code (malfolmed code)");
		}
		$language = $m[1];
		if(!array_key_exists($language, self::GetLanguages())) {
			throw new Exception("'$code' is not a valid language/country code ('$language' is not a valid language code)");
		}
		$country = (count($m) > 3) ? strtoupper($m[3]) : '';
		if(strlen($country) && (!array_key_exists($country, self::GetCountries()))) {
			throw new Exception("'$code' is not a valid language/country code ('$country' is not a valid country code)");
		}
		return array('language' => $language, 'country' => $country);
	}

	/** Check and normalize a code (eg from 'Xx-xX' to 'xx_XX').
	* @param string $code The code to be normalized.
	* @return string
	*/
	public static function NormalizeCode($code) {
		$a = self::SplitCode($code);
		return $a['language'] . (strlen($a['country']) ? ('_' . $a['country']) : '');
	}

	/** Describe a code, eg: from 'it_IT' to 'Italian (Italy)', 'en' to 'English'.
	* @param string|array $code A code or an already splitted code.
	* @throws Exception Throws an Exception if $code is malformed or if contains invalid values.
	* @return string
	*/
	public static function DescribeCode($code) {
		if(is_array($code)) {
			$a = $code;
			$s = implode('-', $code);
			if(!isset($a['language'])) {
				throw new Exception('Missing \'language\' key.');
			}
		}
		else {
			$a = self::SplitCode($code);
			$s = $code;
		}
		$languages = self::GetLanguages();
		if(!array_key_exists($a['language'], $languages)) {
			throw new Exception("'$s' is not a valid language/country code ('{$a['language']}' is not a valid language code)");
		}
		$result = $languages[$a['language']]['name'];
		if(isset($a['country']) && strlen($a['country'])) {
			$a['country'] = strtoupper($a['country']);
			$countries = self::GetCountries();
			if(!array_key_exists($a['country'], $countries)) {
				throw new Exception("'$s' is not a valid language/country code ('{$a['country']}' is not a valid country code)");
			}
			$result .= ' (' . $countries[$a['country']]['name'] . ')';
		}
		return $result;
	}

	/** Cache of the result of Language::GetLanguages().
	* @var null|array
	*/
	private static $_languages = null;

	/** Return the list of all languages.
	* @return array Returns an array with: string <b>key</b> = language identifier, values = an array with:
	* <ul>
	*	<li>string <b>name</b> The language name.</li>
	*	<li>string <b>plural</b> The plural formula.</li>
	* </ul>
	*/
	public static function GetLanguages() {
		if(empty(self::$_languages)) {
			// From http://translate.sourceforge.net/wiki/l10n/pluralforms (there's there a bug in 'lt': there's an 'or' instead of a '||')
			self::$_languages = array(
					'ach' => array('name' => 'Acholi', 'plural' => 'nplurals=2; plural=(n > 1)'),
					'af' => array('name' => 'Afrikaans', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'ak' => array('name' => 'Akan', 'plural' => 'nplurals=2; plural=(n > 1)'),
					'am' => array('name' => 'Amharic', 'plural' => 'nplurals=2; plural=(n > 1)'),
					'an' => array('name' => 'Aragonese', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'ar' => array('name' => 'Arabic', 'plural' => 'nplurals=6; plural= n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 ? 4 : 5;'),
					'arn' => array('name' => 'Mapudungun', 'plural' => 'nplurals=2; plural=(n > 1)'),
					'ast' => array('name' => 'Asturian', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'ay' => array('name' => 'Aymará', 'plural' => 'nplurals=1; plural=0'),
					'az' => array('name' => 'Azerbaijani', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'be' => array('name' => 'Belarusian', 'plural' => 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2)'),
					'bg' => array('name' => 'Bulgarian', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'bn' => array('name' => 'Bengali', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'bo' => array('name' => 'Tibetan', 'plural' => 'nplurals=1; plural=0'),
					'br' => array('name' => 'Breton', 'plural' => 'nplurals=2; plural=(n > 1)'),
					'brx' => array('name' => 'Bodo', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'bs' => array('name' => 'Bosnian', 'plural' => 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2)'),
					'ca' => array('name' => 'Catalan', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'cgg' => array('name' => 'Chiga', 'plural' => 'nplurals=1; plural=0'),
					'cs' => array('name' => 'Czech', 'plural' => 'nplurals=3; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 2'),
					'csb' => array('name' => 'Kashubian', 'plural' => 'nplurals=3; n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2'),
					'cy' => array('name' => 'Welsh', 'plural' => 'nplurals=4; plural= (n==1) ? 0 : (n==2) ? 1 : (n != 8 && n != 11) ? 2 : 3'),
					'da' => array('name' => 'Danish', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'de' => array('name' => 'German', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'doi' => array('name' => 'Dogri', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'dz' => array('name' => 'Dzongkha', 'plural' => 'nplurals=1; plural=0'),
					'el' => array('name' => 'Greek', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'en' => array('name' => 'English', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'eo' => array('name' => 'Esperanto', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'es' => array('name' => 'Spanish', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'et' => array('name' => 'Estonian', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'eu' => array('name' => 'Basque', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'fa' => array('name' => 'Persian', 'plural' => 'nplurals=1; plural=0'),
					'ff' => array('name' => 'Fulah', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'fi' => array('name' => 'Finnish', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'fil' => array('name' => 'Filipino', 'plural' => 'nplurals=2; plural=n > 1'),
					'fo' => array('name' => 'Faroese', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'fr' => array('name' => 'French', 'plural' => 'nplurals=2; plural=(n > 1)'),
					'fur' => array('name' => 'Friulian', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'fy' => array('name' => 'Frisian', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'ga' => array('name' => 'Irish', 'plural' => 'nplurals=5; plural=n==1 ? 0 : n==2 ? 1 : n<7 ? 2 : n<11 ? 3 : 4'),
					'gd' => array('name' => 'Scottish Gaelic', 'plural' => 'nplurals=4; plural=(n==1 || n==11) ? 0 : (n==2 || n==12) ? 1 : (n > 2 && n < 20) ? 2 : 3'),
					'gl' => array('name' => 'Galician', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'gu' => array('name' => 'Gujarati', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'gun' => array('name' => 'Gun', 'plural' => 'nplurals=2; plural = (n > 1)'),
					'ha' => array('name' => 'Hausa', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'he' => array('name' => 'Hebrew', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'hi' => array('name' => 'Hindi', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'hne' => array('name' => 'Chhattisgarhi', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'hy' => array('name' => 'Armenian', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'hr' => array('name' => 'Croatian', 'plural' => 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2)'),
					'hu' => array('name' => 'Hungarian', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'ia' => array('name' => 'Interlingua', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'id' => array('name' => 'Indonesian', 'plural' => 'nplurals=1; plural=0'),
					'is' => array('name' => 'Icelandic', 'plural' => 'nplurals=2; plural=(n%10!=1 || n%100==11)'),
					'it' => array('name' => 'Italian', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'ja' => array('name' => 'Japanese', 'plural' => 'nplurals=1; plural=0'),
					'jbo' => array('name' => 'Lojban', 'plural' => 'nplurals=1; plural=0'),
					'jv' => array('name' => 'Javanese', 'plural' => 'nplurals=2; plural=n!=0'),
					'ka' => array('name' => 'Georgian', 'plural' => 'nplurals=1; plural=0'),
					'kk' => array('name' => 'Kazakh', 'plural' => 'nplurals=1; plural=0'),
					'km' => array('name' => 'Khmer', 'plural' => 'nplurals=1; plural=0'),
					'kn' => array('name' => 'Kannada', 'plural' => 'nplurals=2; plural=(n!=1)'),
					'ko' => array('name' => 'Korean', 'plural' => 'nplurals=1; plural=0'),
					'ku' => array('name' => 'Kurdish', 'plural' => 'nplurals=2; plural=(n!= 1)'),
					'kw' => array('name' => 'Cornish', 'plural' => 'nplurals=4; plural= (n==1) ? 0 : (n==2) ? 1 : (n == 3) ? 2 : 3'),
					'ky' => array('name' => 'Kyrgyz', 'plural' => 'nplurals=1; plural=0'),
					'lb' => array('name' => 'Letzeburgesch', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'ln' => array('name' => 'Lingala', 'plural' => 'nplurals=2; plural=n>1;'),
					'lo' => array('name' => 'Lao', 'plural' => 'nplurals=1; plural=0'),
					'lt' => array('name' => 'Lithuanian', 'plural' => 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && (n%100<10 || n%100>=20) ? 1 : 2)'),
					'lv' => array('name' => 'Latvian', 'plural' => 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n != 0 ? 1 : 2)'),
					'mai' => array('name' => 'Maithili', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'mfe' => array('name' => 'Mauritian Creole', 'plural' => 'nplurals=2; plural=(n > 1)'),
					'mg' => array('name' => 'Malagasy', 'plural' => 'nplurals=2; plural=(n > 1)'),
					'mi' => array('name' => 'Maori', 'plural' => 'nplurals=2; plural=(n > 1)'),
					'mk' => array('name' => 'Macedonian', 'plural' => 'nplurals=2; plural= n==1 || n%10==1 ? 0 : 1'),
					'ml' => array('name' => 'Malayalam', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'mn' => array('name' => 'Mongolian', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'mni' => array('name' => 'Manipuri', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'mnk' => array('name' => 'Mandinka', 'plural' => 'nplurals=3; plural=(n==0 ? 0 : n==1 ? 1 : 2'),
					'mr' => array('name' => 'Marathi', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'ms' => array('name' => 'Malay', 'plural' => 'nplurals=1; plural=0'),
					'mt' => array('name' => 'Maltese', 'plural' => 'nplurals=4; plural=(n==1 ? 0 : n==0 || ( n%100>1 && n%100<11) ? 1 : (n%100>10 && n%100<20 ) ? 2 : 3)'),
					'my' => array('name' => 'Burmese', 'plural' => 'nplurals=1; plural=0'),
					'nah' => array('name' => 'Nahuatl', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'nap' => array('name' => 'Neapolitan', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'nb' => array('name' => 'Norwegian Bokmal', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'ne' => array('name' => 'Nepali', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'nl' => array('name' => 'Dutch', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'se' => array('name' => 'Northern Sami', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'nn' => array('name' => 'Norwegian Nynorsk', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'no' => array('name' => 'Norwegian', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'nso' => array('name' => 'Northern Sotho', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'oc' => array('name' => 'Occitan', 'plural' => 'nplurals=2; plural=(n > 1)'),
					'or' => array('name' => 'Oriya', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'ps' => array('name' => 'Pashto', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'pa' => array('name' => 'Punjabi', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'pap' => array('name' => 'Papiamento', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'pl' => array('name' => 'Polish', 'plural' => 'nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2)'),
					'pms' => array('name' => 'Piemontese', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'pt' => array('name' => 'Portuguese', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'rm' => array('name' => 'Romansh', 'plural' => 'nplurals=2; plural=(n!=1);'),
					'ro' => array('name' => 'Romanian', 'plural' => 'nplurals=3; plural=(n==1 ? 0 : (n==0 || (n%100 > 0 && n%100 < 20)) ? 1 : 2);'),
					'ru' => array('name' => 'Russian', 'plural' => 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2)'),
					'rw' => array('name' => 'Kinyarwanda', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'sah' => array('name' => 'Yakut', 'plural' => 'nplurals=1; plural=0'),
					'sat' => array('name' => 'Santali', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'sco' => array('name' => 'Scots', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'sd' => array('name' => 'Sindhi', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'si' => array('name' => 'Sinhala', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'sk' => array('name' => 'Slovak', 'plural' => 'nplurals=3; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 2'),
					'sl' => array('name' => 'Slovenian', 'plural' => 'nplurals=4; plural=(n%100==1 ? 1 : n%100==2 ? 2 : n%100==3 || n%100==4 ? 3 : 0)'),
					'so' => array('name' => 'Somali', 'plural' => 'nplurals=2; plural=n != 1'),
					'son' => array('name' => 'Songhay', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'sq' => array('name' => 'Albanian', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'sr' => array('name' => 'Serbian', 'plural' => 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2)'),
					'su' => array('name' => 'Sundanese', 'plural' => 'nplurals=1; plural=0'),
					'sw' => array('name' => 'Swahili', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'sv' => array('name' => 'Swedish', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'ta' => array('name' => 'Tamil', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'te' => array('name' => 'Telugu', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'tg' => array('name' => 'Tajik', 'plural' => 'nplurals=2; plural=(n > 1)'),
					'ti' => array('name' => 'Tigrinya', 'plural' => 'nplurals=2; plural=n > 1'),
					'th' => array('name' => 'Thai', 'plural' => 'nplurals=1; plural=0'),
					'tk' => array('name' => 'Turkmen', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'tr' => array('name' => 'Turkish', 'plural' => 'nplurals=2; plural=(n>1)'),
					'tt' => array('name' => 'Tatar', 'plural' => 'nplurals=1; plural=0'),
					'ug' => array('name' => 'Uyghur', 'plural' => 'nplurals=1; plural=0;'),
					'uk' => array('name' => 'Ukrainian', 'plural' => 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2)'),
					'ur' => array('name' => 'Urdu', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'uz' => array('name' => 'Uzbek', 'plural' => 'nplurals=2; plural=(n > 1)'),
					'vi' => array('name' => 'Vietnamese', 'plural' => 'nplurals=1; plural=0'),
					'wa' => array('name' => 'Walloon', 'plural' => 'nplurals=2; plural=(n > 1)'),
					'wo' => array('name' => 'Wolof', 'plural' => 'nplurals=1; plural=0'),
					'yo' => array('name' => 'Yoruba', 'plural' => 'nplurals=2; plural=(n != 1)'),
					'zh' => array('name' => 'Chinese', 'plural' => 'nplurals=1; plural=0')
			);
		}
		return self::$_languages;
	}

	/** Cache of the result of Language::GetCountries().
	* @var null|array
	*/
	private static $_countries = null;

	/** Return the list of all countries.
	* @return array Returns an array with: string <b>key</b> = country identifier, values = an array with:
	* <ul>
	*	<li>string <b>name</b> The country name.</li>
	* </ul>
	*/
	public static function GetCountries() {
		if(empty(self::$_countries)) {
			// From http://en.wikipedia.org/wiki/ISO_3166-2
			self::$_countries = array(
					'AD' => array('name' => 'Andorra'),
					'AE' => array('name' => 'United Arab Emirates'),
					'AF' => array('name' => 'Afghanistan'),
					'AG' => array('name' => 'Antigua and Barbuda'),
					'AI' => array('name' => 'Anguilla'),
					'AL' => array('name' => 'Albania'),
					'AM' => array('name' => 'Armenia'),
					'AN' => array('name' => 'Netherlands Antilles'),
					'AO' => array('name' => 'Angola'),
					'AQ' => array('name' => 'Antarctica'),
					'AR' => array('name' => 'Argentina'),
					'AS' => array('name' => 'American Samoa'),
					'AT' => array('name' => 'Austria'),
					'AU' => array('name' => 'Australia'),
					'AW' => array('name' => 'Aruba'),
					'AX' => array('name' => 'Åland Islands'),
					'AZ' => array('name' => 'Azerbaijan'),
					'BA' => array('name' => 'Bosnia and Herzegovina'),
					'BB' => array('name' => 'Barbados'),
					'BD' => array('name' => 'Bangladesh'),
					'BE' => array('name' => 'Belgium'),
					'BF' => array('name' => 'Burkina Faso'),
					'BG' => array('name' => 'Bulgaria'),
					'BH' => array('name' => 'Bahrain'),
					'BI' => array('name' => 'Burundi'),
					'BJ' => array('name' => 'Benin'),
					'BL' => array('name' => 'Saint Barthélemy'),
					'BM' => array('name' => 'Bermuda'),
					'BN' => array('name' => 'Brunei'),
					'BO' => array('name' => 'Bolivia'),
					'BR' => array('name' => 'Brazil'),
					'BS' => array('name' => 'Bahamas'),
					'BT' => array('name' => 'Bhutan'),
					'BV' => array('name' => 'Bouvet Island'),
					'BW' => array('name' => 'Botswana'),
					'BY' => array('name' => 'Belarus'),
					'BZ' => array('name' => 'Belize'),
					'CA' => array('name' => 'Canada'),
					'CC' => array('name' => 'Cocos Islands'),
					'CD' => array('name' => 'Congo, Democratic Republic of the'),
					'CF' => array('name' => 'Central African Republic'),
					'CG' => array('name' => 'Congo, Republic of the'),
					'CH' => array('name' => 'Switzerland'),
					'CI' => array('name' => 'Ivory Coast'),
					'CK' => array('name' => 'Cook Islands'),
					'CL' => array('name' => 'Chile'),
					'CM' => array('name' => 'Cameroon'),
					'CN' => array('name' => 'China'),
					'CO' => array('name' => 'Colombia'),
					'CR' => array('name' => 'Costa Rica'),
					'CU' => array('name' => 'Cuba'),
					'CV' => array('name' => 'Cape Verde'),
					'CX' => array('name' => 'Christmas Island'),
					'CY' => array('name' => 'Cyprus'),
					'CZ' => array('name' => 'Czech Republic'),
					'DE' => array('name' => 'Germany'),
					'DJ' => array('name' => 'Djibouti'),
					'DK' => array('name' => 'Denmark'),
					'DM' => array('name' => 'Dominica'),
					'DO' => array('name' => 'Dominican Republic'),
					'DZ' => array('name' => 'Algeria'),
					'EC' => array('name' => 'Ecuador'),
					'EE' => array('name' => 'Estonia'),
					'EG' => array('name' => 'Egypt'),
					'EH' => array('name' => 'Western Sahara'),
					'ER' => array('name' => 'Eritrea'),
					'ES' => array('name' => 'Spain'),
					'ET' => array('name' => 'Ethiopia'),
					'FI' => array('name' => 'Finland'),
					'FJ' => array('name' => 'Fiji'),
					'FK' => array('name' => 'Falkland Islands'),
					'FM' => array('name' => 'Micronesia'),
					'FO' => array('name' => 'Faroe Islands'),
					'FR' => array('name' => 'France'),
					'GA' => array('name' => 'Gabon'),
					'GB' => array('name' => 'United Kingdom'),
					'GD' => array('name' => 'Grenada'),
					'GE' => array('name' => 'Georgia'),
					'GF' => array('name' => 'French Guiana'),
					'GG' => array('name' => 'Guernsey'),
					'GH' => array('name' => 'Ghana'),
					'GI' => array('name' => 'Gibraltar'),
					'GL' => array('name' => 'Greenland'),
					'GM' => array('name' => 'Gambia'),
					'GN' => array('name' => 'Guinea'),
					'GP' => array('name' => 'Guadeloupe'),
					'GQ' => array('name' => 'Equatorial Guinea'),
					'GR' => array('name' => 'Greece'),
					'GS' => array('name' => 'South Georgia'),
					'GT' => array('name' => 'Guatemala'),
					'GU' => array('name' => 'Guam'),
					'GW' => array('name' => 'Guinea-Bissau'),
					'GY' => array('name' => 'Guyana'),
					'HK' => array('name' => 'Hong Kong'),
					'HM' => array('name' => 'Heard Island and McDonald Islands'),
					'HN' => array('name' => 'Honduras'),
					'HR' => array('name' => 'Croatia'),
					'HT' => array('name' => 'Haiti'),
					'HU' => array('name' => 'Hungary'),
					'ID' => array('name' => 'Indonesia'),
					'IE' => array('name' => 'Ireland'),
					'IL' => array('name' => 'Israel'),
					'IM' => array('name' => 'Isle of Man'),
					'IN' => array('name' => 'India'),
					'IO' => array('name' => 'British Indian Ocean Territory'),
					'IQ' => array('name' => 'Iraq'),
					'IR' => array('name' => 'Iran'),
					'IS' => array('name' => 'Iceland'),
					'IT' => array('name' => 'Italy'),
					'JE' => array('name' => 'Jersey'),
					'JM' => array('name' => 'Jamaica'),
					'JO' => array('name' => 'Jordan'),
					'JP' => array('name' => 'Japan'),
					'KE' => array('name' => 'Kenya'),
					'KG' => array('name' => 'Kyrgyzstan'),
					'KH' => array('name' => 'Cambodia'),
					'KI' => array('name' => 'Kiribati'),
					'KM' => array('name' => 'Comoros'),
					'KN' => array('name' => 'Saint Kitts and Nevis'),
					'KP' => array('name' => 'North Korea'),
					'KR' => array('name' => 'South Korea'),
					'KW' => array('name' => 'Kuwait'),
					'KY' => array('name' => 'Cayman Islands'),
					'KZ' => array('name' => 'Kazakhstan'),
					'LA' => array('name' => 'Laos'),
					'LB' => array('name' => 'Lebanon'),
					'LC' => array('name' => 'Saint Lucia'),
					'LI' => array('name' => 'Liechtenstein'),
					'LK' => array('name' => 'Sri Lanka'),
					'LR' => array('name' => 'Liberia'),
					'LS' => array('name' => 'Lesotho'),
					'LT' => array('name' => 'Lithuania'),
					'LU' => array('name' => 'Luxembourg'),
					'LV' => array('name' => 'Latvia'),
					'LY' => array('name' => 'Libya'),
					'MA' => array('name' => 'Morocco'),
					'MC' => array('name' => 'Monaco'),
					'MD' => array('name' => 'Moldova'),
					'ME' => array('name' => 'Montenegro'),
					'MF' => array('name' => 'Saint Martin'),
					'MG' => array('name' => 'Madagascar'),
					'MH' => array('name' => 'Marshall Islands'),
					'MK' => array('name' => 'Macedonia'),
					'ML' => array('name' => 'Mali'),
					'MM' => array('name' => 'Myanmar'),
					'MN' => array('name' => 'Mongolia'),
					'MO' => array('name' => 'Macau'),
					'MP' => array('name' => 'Northern Mariana Islands'),
					'MQ' => array('name' => 'Martinique'),
					'MR' => array('name' => 'Mauritania'),
					'MS' => array('name' => 'Montserrat'),
					'MT' => array('name' => 'Malta'),
					'MU' => array('name' => 'Mauritius'),
					'MV' => array('name' => 'Maldives'),
					'MW' => array('name' => 'Malawi'),
					'MX' => array('name' => 'Mexico'),
					'MY' => array('name' => 'Malaysia'),
					'MZ' => array('name' => 'Mozambique'),
					'NA' => array('name' => 'Namibia'),
					'NC' => array('name' => 'New Caledonia'),
					'NE' => array('name' => 'Niger'),
					'NF' => array('name' => 'Norfolk Island'),
					'NG' => array('name' => 'Nigeria'),
					'NI' => array('name' => 'Nicaragua'),
					'NL' => array('name' => 'Netherlands'),
					'NO' => array('name' => 'Norway'),
					'NP' => array('name' => 'Nepal'),
					'NR' => array('name' => 'Nauru'),
					'NU' => array('name' => 'Niue'),
					'NZ' => array('name' => 'New Zealand'),
					'OM' => array('name' => 'Oman'),
					'PA' => array('name' => 'Panama'),
					'PE' => array('name' => 'Peru'),
					'PF' => array('name' => 'French Polynesia'),
					'PG' => array('name' => 'Papua New Guinea'),
					'PH' => array('name' => 'Philippines'),
					'PK' => array('name' => 'Pakistan'),
					'PL' => array('name' => 'Poland'),
					'PM' => array('name' => 'Saint Pierre and Miquelon'),
					'PN' => array('name' => 'Pitcairn Islands'),
					'PR' => array('name' => 'Puerto Rico'),
					'PS' => array('name' => 'Palestine'),
					'PT' => array('name' => 'Portugal'),
					'PW' => array('name' => 'Palau'),
					'PY' => array('name' => 'Paraguay'),
					'QA' => array('name' => 'Qatar'),
					'RE' => array('name' => 'Réunion'),
					'RO' => array('name' => 'Romania'),
					'RS' => array('name' => 'Serbia'),
					'RU' => array('name' => 'Russia'),
					'RW' => array('name' => 'Rwanda'),
					'SA' => array('name' => 'Saudi Arabia'),
					'SB' => array('name' => 'Solomon Islands'),
					'SC' => array('name' => 'Seychelles'),
					'SD' => array('name' => 'Sudan'),
					'SE' => array('name' => 'Sweden'),
					'SG' => array('name' => 'Singapore'),
					'SH' => array('name' => 'Saint Helena'),
					'SI' => array('name' => 'Slovenia'),
					'SJ' => array('name' => 'Svalbard and Jan Mayen Island'),
					'SK' => array('name' => 'Slovakia'),
					'SL' => array('name' => 'Sierra Leone'),
					'SM' => array('name' => 'San Marino'),
					'SN' => array('name' => 'Senegal'),
					'SO' => array('name' => 'Somalia'),
					'SR' => array('name' => 'Suriname'),
					'ST' => array('name' => 'Saint Tome and Principe'),
					'SV' => array('name' => 'El Salvador'),
					'SX' => array('name' => 'Sint Maarten'),
					'SY' => array('name' => 'Syria'),
					'SZ' => array('name' => 'Swaziland'),
					'TC' => array('name' => 'Turks and Caicos Islands'),
					'TD' => array('name' => 'Chad'),
					'TF' => array('name' => 'French Southern Lands'),
					'TG' => array('name' => 'Togo'),
					'TH' => array('name' => 'Thailand'),
					'TJ' => array('name' => 'Tajikistan'),
					'TK' => array('name' => 'Tokelau'),
					'TL' => array('name' => 'East Timor'),
					'TM' => array('name' => 'Turkmenistan'),
					'TN' => array('name' => 'Tunisia'),
					'TO' => array('name' => 'Tonga'),
					'TR' => array('name' => 'Turkey'),
					'TT' => array('name' => 'Trinidad and Tobago'),
					'TV' => array('name' => 'Tuvalu'),
					'TW' => array('name' => 'Taiwan'),
					'TZ' => array('name' => 'Tanzania'),
					'UA' => array('name' => 'Ukraine'),
					'UG' => array('name' => 'Uganda'),
					'UM' => array('name' => 'United States Minor Outlying Islands'),
					'US' => array('name' => 'United States of America'),
					'UY' => array('name' => 'Uruguay'),
					'UZ' => array('name' => 'Uzbekistan'),
					'VA' => array('name' => 'Vatican City'),
					'VC' => array('name' => 'Saint Vincent and the Grenadines'),
					'VE' => array('name' => 'Venezuela'),
					'VG' => array('name' => 'British Virgin Islands'),
					'VI' => array('name' => 'United States Virgin Islands'),
					'VN' => array('name' => 'Vietnam'),
					'VU' => array('name' => 'Vanuatu'),
					'WF' => array('name' => 'Wallis and Futuna'),
					'WS' => array('name' => 'Samoa'),
					'YE' => array('name' => 'Yemen'),
					'YT' => array('name' => 'Mayotte'),
					'ZA' => array('name' => 'South Africa'),
					'ZM' => array('name' => 'Zambia'),
					'ZW' => array('name' => 'Zimbabwe')
			);
		}
		return self::$_countries;
	}
}

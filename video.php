<?php
define('C5_BUILD', true);
require_once dirname(__FILE__) . '/base.php';

$tempFile = '';
try {
	$videoFormats = ToolOptions::GetVideoFormatsToConvert();
	if(count($videoFormats)) {
		if(ToolOptions::$Speed != 1) {
			Console::Write('Changing video speed... ');
			$workOn = $tempFile = Enviro::GetTemporaryFileName();
			$args = array();
			$args[] = '-ovc copy';
			$args[] = '-oac copy';
			$args[] = '-speed ' . ToolOptions::$Speed;
			$args[] = escapeshellarg(ToolOptions::$SourceFile);
			$args[] = '-o ' . escapeshellarg($tempFile);
			Enviro::RunTool('mencoder', $args);
			Console::WriteLine('ok.');
		}
		else {
			$tempFile = '';
			$workOn = ToolOptions::$SourceFile;
		}
		foreach($videoFormats as $format) {
			Converter::Convert($workOn, $format);
		}
		if(strlen($tempFile)) {
			@unlink($tempFile);
			$tempFile = '';
		}
	}
	if(!is_null(ToolOptions::$PosterAt)) {
		Converter::Convert(ToolOptions::$SourceFile, Converter::FORMAT_POSTER);
	}
	die(0);
}
catch(Exception $x) {
	if(strlen($tempFile)) {
		@unlink($tempFile);
		$tempFile = '';
	}
	DieForException($x);
}

class Converter {
	const FORMAT_H264 = 'H.264';
	const FORMAT_WEBM = 'WebM';
	const FORMAT_THEORA = 'Theora';
	const FORMAT_POSTER = 'Poster';
	
	public static function Convert($workOn, $format) {
		Console::Write("Creating $format... ");
		$baseName = basename(ToolOptions::$SourceFile);
		$p = strrpos($baseName, '.');
		if(($p !== false) && ($p > 0)) {
			$baseName = substr($baseName, 0, $p);
		}
		$destFile = Enviro::MergePath(ToolOptions::$DestinationFolder, $baseName . '.' . self::GetExtension($format));
		$args = array();
		$args[] = '-i ' . escapeshellarg($workOn);
		switch($format) {
			case self::FORMAT_H264:
				$args[] = '-f mp4 -vcodec libx264 -vprofile baseline';
				if(!ToolOptions::$NoAudio) {
					$args[] = '-acodec libvo_aacenc';
				}
				break;
			case self::FORMAT_WEBM:
				$args[] = '-f webm -vcodec libvpx';
				if(!ToolOptions::$NoAudio) {
					$args[] = '-acodec libvorbis';
				}
				break;
			case self::FORMAT_THEORA:
				$args[] = '-f ogg -vcodec libtheora';
				if(!ToolOptions::$NoAudio) {
					$args[] = '-acodec libvorbis';
				}
				break;
			case self::FORMAT_POSTER:
				$args[] = '-f image2 -vframes 1 -r 1 -ss ' . ToolOptions::$PosterAt;
				break;
		}
		if(file_exists($destFile)) {
			if(!ToolOptions::$Overwrite) {
				throw new Exception("The file $destFile already exists.");
			}
			$args[] = '-y';
		}
		switch($format) {
			case self::FORMAT_POSTER:
				break;
			default:
				if(ToolOptions::$NoAudio) {
					$args[] = '-an';
				}
				break;
		}
		$args[] = escapeshellarg($destFile);
		Enviro::RunTool('ffmpeg', $args);
		Console::WriteLine("ok.");
	}
	private static function GetExtension($format) {
		switch($format) {
			case self::FORMAT_WEBM:
				return 'webm';
			case self::FORMAT_THEORA:
				return 'ogv';
			case self::FORMAT_H264:
				return 'mp4';
			case self::FORMAT_POSTER:
				return ToolOptions::$PosterFormat;
		}
		throw new Exception("Which extension for $format?");
	}
}
class ToolOptions {
	public static $NeedWebRoot;
	public static $SourceFile;
	public static $DestinationFolder;
	public static $Speed;
	public static $NoAudio;
	public static $Overwrite;
	public static $PosterAt;
	public static $PosterFormat;
	public static $PosterFormatDefault;
	public static $Skip_H264;
	public static $Skip_WebM;
	public static $Skip_Theora;

	public static function ShowIntro() {
		global $argv;
		Console::WriteLine($argv[0] . ' is a tool that helps building video files suitable for html5 videos.');
	}

	public static function InitializeDefaults() {
		self::$NeedWebRoot = false;
		self::$SourceFile = '';
		self::$Speed = 1;
		self::$NoAudio = false;
		self::$Overwrite = false;
		self::$PosterAt = null;
		self::$PosterFormat = '';
		self::$PosterFormatDefault = 'png';
		self::$Skip_H264 = false;
		self::$Skip_WebM = false;
		self::$Skip_Theora = false;
	}

	public static function GetOptions(&$options) {
		$options['--source'] = array('helpValue' => '<file>', 'description' => 'the file name to convert.');
		$options['--destination'] = array('helpValue' => '<folder>', 'description' => 'the destination folder there the converted files will be saved.');
		$options['--speed'] = array('helpValue' => '<num>', 'description' => 'change speed (eg: a value of 0.5 makes the video slower and doubles its duration.');
		$options['--noaudio'] = array('helpValue' => '<yes|no>', 'description' => 'destination files won\'t have audio tracks.');
		$options['--overwrite'] = array('helpValue' => '<yes|no>', 'description' => 'overwrite existing files?');
		$options['--posterat'] = array('helpValue' => '<time>', 'description' => 'the time position where to take the poster (seconds or hh:mm:ss[.xxx])');
		$options['--posterformat'] = array('helpValue' => '<png|jpg>', 'description' => sprintf('the file format of the poster file (default: %s)', self::$PosterFormatDefault));
		$options['--skip-h264'] = array('helpValue' => '<yes|no>', 'description' => 'skip the creation of the H264 video file');
		$options['--skip-webm'] = array('helpValue' => '<yes|no>', 'description' => 'skip the creation of the WebM video file');
		$options['--skip-theora'] = array('helpValue' => '<yes|no>', 'description' => 'skip the creation of the OGG Theora video file');
	}

	public static function ParseArgument($argument, $value) {
		switch($argument) {
			case '--source':
				if(!strlen($value)) {
					throw new Exception("Argument '$argument' requires a value (a valid file name).");
				}
				self::$SourceFile = $value;
				return true;
			case '--destination':
				if(!strlen($value)) {
					throw new Exception("Argument '$argument' requires a value (a valid file name).");
				}
				self::$DestinationFolder = $value;
				return true;
			case '--speed':
				if(!strlen($value)) {
					throw new Exception("Argument '$argument' requires a value (a valid speed change value).");
				}
				if(!is_numeric($value)) {
					throw new Exception("Argument '$argument' needs a numeric value.");
				}
				$num = @floatval($value);
				if($num <= 0) {
					throw new Exception("Argument '$argument' needs a numeric value greater than 0.");
				}
				self::$Speed = $num;
				return true;
			case '--noaudio':
				self::$NoAudio = Options::ArgumentToBool($argument, $value);
				return true;
			case '--overwrite':
				self::$Overwrite = Options::ArgumentToBool($argument, $value);
				return true;
			case '--posterat':
				if(!strlen($value)) {
					throw new Exception("Argument '$argument' requires a value (the poster time position).");
				}
				if(preg_match('/^[0-9]+$/', $value)) {
					self::$PosterAt = @intval($value);
				}
				elseif(preg_match('/^[0-9]+:[0-9][0-9]:[0-9][0-9](\.[0-9]+)?$/', $value)) {
					self::$PosterAt = $value;
				}
				else {
					throw new Exception("Invalid value for '$argument': $value");
				}
				return true;
			case '--posterformat':
				if(!strlen($value)) {
					throw new Exception("Argument '$argument' requires a value (the poster format).");
				}
				switch($s = strtolower($value)) {
					case 'png':
					case 'jpg':
						self::$PosterFormat = $s;
						break;
					default:
						throw new Exception("Invalid poster format: $value");
				}
				return true;
			case '--skip-h264':
				self::$Skip_H264 = Options::ArgumentToBool($argument, $value);
				return true;
			case '--skip-webm':
				self::$Skip_WebM = Options::ArgumentToBool($argument, $value);
				return true;
			case '--skip-theora':
				self::$Skip_Theora = Options::ArgumentToBool($argument, $value);
				return true;
		}
		return false;
	}

	public static function ArgumentsRead() {
		if(!strlen(self::$SourceFile)) {
			throw new Exception("Missing source file.");
		}
		$file = @realpath(self::$SourceFile);
		if(($file === false) || (!is_file($file))) {
			throw new Exception("Source file does not exist: " . self::$SourceFile);
		}
		self::$SourceFile = $file;
		if(!strlen(self::$DestinationFolder)) {
			throw new Exception("Missing destination folder.");
		}
		if(!Enviro::PathIsAbsolute(self::$DestinationFolder)) {
			self::$DestinationFolder = Enviro::MergePath(Options::$InitialFolder, self::$DestinationFolder);
		}
		try {
			Enviro::RunTool('ffmpeg', '-h');
		}
		catch(Exception $x) {
			Console::WriteLine('This tool requires ffmpeg.');
			switch(Enviro::GetOS()) {
				case Enviro::OS_LINUX:
					Console::WriteLine('Depending on your Linux distro, you could install it with the following command:', true);
					Console::WriteLine('sudo apt-get install ffmpeg', true);
					die(1);
				case Enviro::OS_WIN:
					Console::WriteLine('Please download it from http://ffmpeg.org and copy the ffmpeg.exe in the folder ' . Options::$Win32ToolsFolder, true);
					die(1);
				default:
					Console::WriteLine('Please download it from http://ffmpeg.org', true);
					die(1);
			}
		}
		if((ToolOptions::$Speed != 1) && count(self::GetVideoFormatsToConvert())) {
			try {
				Enviro::RunTool('mencoder', '', array(0, 1));
			}
			catch(Exception $x) {
				Console::WriteLine('To change the video speed we need mencoder.');
				switch(Enviro::GetOS()) {
					case Enviro::OS_LINUX:
						Console::WriteLine('Depending on your Linux distro, you could install it with the following command:', true);
						Console::WriteLine('sudo apt-get install mencoder', true);
						die(1);
					case Enviro::OS_WIN:
						Console::WriteLine('Please download it from http://code.google.com/p/mplayer-for-windows/downloads/list and copy all the files in the folder ' . Options::$Win32ToolsFolder, true);
						Console::WriteLine('(Required files are mencoder.exe and the folder codecs)', true);
						die(1);
					default:
						Console::WriteLine('Please download it from http://www.mplayerhq.hu', true);
						die(1);
				}
			}
		}
		if(!is_dir(self::$DestinationFolder)) {
			if(!@mkdir(self::$DestinationFolder, 0777, true)) {
				throw new Exception('Unable to create the folder ' . self::$DestinationFolder);
			}
		}
		$s = realpath(self::$DestinationFolder);
		if($s === false) {
			throw new Exception('Unable determine the real path of the folder ' . self::$DestinationFolder);
		}
		self::$DestinationFolder = $s;
		if(!is_writable(self::$DestinationFolder)) {
			throw new Exception('The destination folder is not writable.');
		}
		if(!strlen(self::$PosterFormat)) {
			self::$PosterFormat = self::$PosterFormatDefault;
		}
	}
	public static function GetVideoFormatsToConvert() {
		$formats = array();
			if(!self::$Skip_H264) {
			$formats[] = Converter::FORMAT_H264;
		}
		if(!self::$Skip_WebM) {
			$formats[] = Converter::FORMAT_WEBM;
		}
		if(!self::$Skip_Theora) {
			$formats[] = Converter::FORMAT_THEORA;
		}
		return $formats;
	}
}

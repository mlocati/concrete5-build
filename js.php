<?php
define('C5_BUILD', true);
require_once dirname(__FILE__) . '/base.php';

try {
	Options::CheckWebRoot();
	if(ToolOptions::$Compress && (!Enviro::CheckNodeJS('uglifyjs'))) {
		die(1);
	}
	$coreVersion = Options::GetVersionOfConcrete5();
	if(version_compare($coreVersion, '5.6.1', '<')) {
		MergeJavascript(
			array(
				'concrete/js/bootstrap/bootstrap.tooltip.js',
				'concrete/js/bootstrap/bootstrap.popover.js',
				'concrete/js/bootstrap/bootstrap.dropdown.js',
				'concrete/js/bootstrap/bootstrap.transitions.js',
				'concrete/js/bootstrap/bootstrap.alert.js'
			),
			'concrete/js/bootstrap.js'
		);
		MergeJavascript(
			'concrete/js/ccm_app/jquery.cookie.js',
			'concrete/js/jquery.cookie.js'
		);
		MergeJavascript(
			array(
				'concrete/js/ccm_app/jquery.colorpicker.js',
				'concrete/js/ccm_app/jquery.hoverIntent.js',
				'concrete/js/ccm_app/jquery.liveupdate.js',
				'concrete/js/ccm_app/jquery.metadata.js',
				'concrete/js/ccm_app/chosen.jquery.js',
				'concrete/js/ccm_app/dashboard.js',
				'concrete/js/ccm_app/filemanager.js',
				'concrete/js/ccm_app/jquery.cookie.js',
				'concrete/js/ccm_app/layouts.js',
				'concrete/js/ccm_app/legacy_dialog.js',
				'concrete/js/ccm_app/newsflow.js',
				'concrete/js/ccm_app/page_reindexing.js',
				'concrete/js/ccm_app/quicksilver.js',
				'concrete/js/ccm_app/remote_marketplace.js',
				'concrete/js/ccm_app/search.js',
				'concrete/js/ccm_app/sitemap.js',
				'concrete/js/ccm_app/status_bar.js',
				'concrete/js/ccm_app/tabs.js',
				'concrete/js/ccm_app/tinymce_integration.js',
				'concrete/js/ccm_app/ui.js',
				'concrete/js/ccm_app/toolbar.js',
				'concrete/js/ccm_app/themes.js'
			),
			'concrete/js/ccm.app.js',
			'--no-seqs'
		);
	}
	elseif(version_compare($coreVersion, '5.7', '<')) {
		MergeJavascript(
			array(
				'concrete/js/bootstrap/bootstrap.tooltip.js',
				'concrete/js/bootstrap/bootstrap.popover.js',
				'concrete/js/bootstrap/bootstrap.dropdown.js',
				'concrete/js/bootstrap/bootstrap.transitions.js',
				'concrete/js/bootstrap/bootstrap.alert.js'
			),
			'concrete/js/bootstrap.js'
		);
		MergeJavascript(
			'concrete/js/ccm_app/jquery.cookie.js',
			'concrete/js/jquery.cookie.js'
		);
		MergeJavascript(
			'concrete/js/ccm_app/dashboard.js',
			'concrete/js/ccm.dashboard.js',
			'--no-seqs'
		);
		MergeJavascript(
			array(
				'concrete/js/ccm_app/jquery.colorpicker.js',
				'concrete/js/ccm_app/jquery.hoverIntent.js',
				'concrete/js/ccm_app/jquery.liveupdate.js',
				'concrete/js/ccm_app/jquery.metadata.js',
				'concrete/js/ccm_app/chosen.jquery.js',
				'concrete/js/ccm_app/filemanager.js',
				'concrete/js/ccm_app/jquery.cookie.js',
				'concrete/js/ccm_app/layouts.js',
				'concrete/js/ccm_app/legacy_dialog.js',
				'concrete/js/ccm_app/newsflow.js',
				'concrete/js/ccm_app/page_reindexing.js',
				'concrete/js/ccm_app/quicksilver.js',
				'concrete/js/ccm_app/remote_marketplace.js',
				'concrete/js/ccm_app/search.js',
				'concrete/js/ccm_app/sitemap.js',
				'concrete/js/ccm_app/status_bar.js',
				'concrete/js/ccm_app/tabs.js',
				'concrete/js/ccm_app/tinymce_integration.js',
				'concrete/js/ccm_app/ui.js',
				'concrete/js/ccm_app/toolbar.js',
				'concrete/js/ccm_app/themes.js',
				'concrete/js/ccm_app/composer.js'
			),
			'concrete/js/ccm.app.js',
			'--no-seqs'
		);
	}
	else {
		MergeJavascript(
			'concrete/js/ccm_app/jquery.cookie.js',
			'concrete/js/jquery.cookie.js'
		);
		MergeJavascript(
			'concrete/js/ccm_profile/base.js',
			'concrete/js/ccm.profile.js'
		);
		MergeJavascript(
			array(
				'concrete/js/redactor/redactor.js',
				'concrete/js/redactor/redactor.concrete5.js'
			),
			'concrete/js/redactor.js'
		);
		MergeJavascript(
			'concrete/js/ccm_app/dashboard.js',
			'concrete/js/ccm.dashboard.js',
			'--no-seqs'
		);
		MergeJavascript(
			'concrete/js/composer/composer.js',
			'concrete/js/ccm.composer.js',
			'--no-seqs'
		);
		MergeJavascript(
			array(
				'concrete/js/ccm_app/conversations/conversations.js',
				'concrete/js/ccm_app/conversations/attachments.js'
			),
			'concrete/js/ccm.conversations.js',
			'--no-seqs'
		);
		MergeJavascript(
			array(
				'concrete/js/gathering/packery.js',
				'concrete/js/gathering/gathering.js'
			),
			'concrete/js/ccm.gathering.js',
			'--no-seqs'
		);
		MergeJavascript(
			'concrete/js/ccm_app/pubsub.js',
			'concrete/js/ccm.pubsub.js',
			'--no-seqs'
		);
		MergeJavascript(
			array(
				'concrete/js/bootstrap/bootstrap-alert.js',
				'concrete/js/bootstrap/bootstrap-tooltip.js',
				'concrete/js/bootstrap/bootstrap-dropdown.js',
				'concrete/js/bootstrap/bootstrap-popover.js',
				'concrete/js/bootstrap/bootstrap-transition.js'
			),
			'concrete/js/bootstrap.js',
			'--no-seqs'
		);
		MergeJavascript(
			array(
				'concrete/js/ccm_app/jquery.colorpicker.js',
				'concrete/js/ccm_app/jquery.hoverIntent.js',
				'concrete/js/ccm_app/jquery.liveupdate.js',
				'concrete/js/ccm_app/jquery.metadata.js',
				'concrete/js/ccm_app/chosen.jquery.js',
				'concrete/js/ccm_app/base.js',
				'concrete/js/ccm_app/ui.js',
				'concrete/js/ccm_app/edit_page.js',
				'concrete/js/ccm_app/filemanager.js',
				'concrete/js/ccm_app/jquery.cookie.js',
				'concrete/js/ccm_app/dialog.js',
				'concrete/js/ccm_app/alert.js',
				'concrete/js/ccm_app/newsflow.js',
				'concrete/js/ccm_app/page_reindexing.js',
				'concrete/js/ccm_app/in_context_menu.js',
				'concrete/js/ccm_app/quicksilver.js',
				'concrete/js/ccm_app/remote_marketplace.js',
				'concrete/js/ccm_app/progressive_operations.js',
				'concrete/js/ccm_app/inline_edit.js',
				'concrete/js/ccm_app/search.js',
				'concrete/js/ccm_app/dynatree.js',
				'concrete/js/ccm_app/sitemap.js',
				'concrete/js/ccm_app/page_search.js',
				'concrete/js/ccm_app/custom_style.js',
				'concrete/js/ccm_app/tabs.js',
				'concrete/js/ccm_app/toolbar.js',
				'concrete/js/ccm_app/themes.js'
			),
			'concrete/js/ccm.app.js',
			'--no-seqs'
		);
		MergeJavascript(
			'concrete/js/layouts/layouts.js',
			'concrete/js/ccm.layouts.js'
		);
		MergeJavascript(
			'concrete/js/ccm_app/backstretch.js',
			'concrete/js/jquery.backstretch.js'
		);
		MergeJavascript(
			array(
				'concrete/js/image_editor/build/kinetic.prototype.js',
				'concrete/js/image_editor/build/imageeditor.js',
				'concrete/js/image_editor/build/history.js',
				'concrete/js/image_editor/build/events.js',
				'concrete/js/image_editor/build/elements.js',
				'concrete/js/image_editor/build/controls.js',
				'concrete/js/image_editor/build/save.js',
				'concrete/js/image_editor/build/extend.js',
				'concrete/js/image_editor/build/background.js',
				'concrete/js/image_editor/build/imagestage.js',
				'concrete/js/image_editor/build/image.js',
				'concrete/js/image_editor/build/actions.js',
				'concrete/js/image_editor/build/slideOut.js',
				'concrete/js/image_editor/build/jquerybinding.js',
				'concrete/js/image_editor/build/filters.js'
			),
			'concrete/js/image_editor/image_editor.js',
			'--no-seqs --no-squeeze --beautify'
		);
		MergeJavascript(
			array(
				'concrete/js/image_editor/build/kinetic.js',
				'concrete/js/image_editor/image_editor.js'
			),
			'concrete/js/image_editor.min.js',
			'--no-seqs'
		);
	}
	if(is_string(Options::$InitialFolder)) {
		@chdir(Options::$InitialFolder);
	}
	die(0);
}
catch(Exception $x) {
	DieForException($x);
}

class ToolOptions {
	public static $Compress;
	public static $OmitComments;
	public static function InitializeDefaults() {
		self::$Compress = true;
		self::$OmitComments = true;
	}
	public static function ShowIntro() {
		global $argv;
		Console::WriteLine($argv[0] . ' is a tool that generates an optimized version of JavaScripts used by the concrete5 core.');
	}
	public static function GetOptions(&$options) {
		$options['--compress'] = array('helpValue' => '<yes|no>', 'description' => 'if yes (default) the output files will be compressed; use no for debugging');
		$options['--omit-comments'] = array('helpValue' => '<yes|no>', 'description' => 'if no the output files will contain the initial comment of the first file compressed, if yes (default) it will be omitted');
	}
	public static function ParseArgument($name, $value) {
		switch($name) {
			case '--compress':
				self::$Compress = Options::ArgumentToBool($name, $value);
				return true;
			case '--omit-comments':
				self::$OmitComments = Options::ArgumentToBool($name, $value);
				return true;

		}
		return false;
	}
}

/** Compress one or more javascript files.
* @param string|array[string] $srcFiles The file(s) to compress.
* @param string $dstFile The file where to save the compressed scripts.
* @param array|string $options An optional list of options for the compiler.
* @param bool $pathsAreRelativeToRoot Set to true (default) if all the input/output files are relative to Options::$WebrootFolder, false otherwise.
* @throws Exception Throws an exception in case of errors.
*/
function MergeJavascript($srcFiles, $dstFile, $options = '', $pathsAreRelativeToRoot = true) {
	Console::Write("Generating $dstFile... ");
	if(!is_array($srcFiles)) {
		$srcFiles = array($srcFiles);
	}
	$srcFilesFull = array();
	foreach($srcFiles as $srcFile) {
		$srcFilesFull[] = $pathsAreRelativeToRoot ? Enviro::MergePath(Options::$WebrootFolder, $srcFile) : $srcFile;
	}
	$dstFileFull = $pathsAreRelativeToRoot ? Enviro::MergePath(Options::$WebrootFolder, $dstFile) : $srcFile;
	$tempFileSrc = '';
	$tempFileDst = '';
	try {
		switch(count($srcFilesFull)) {
			case 0:
				throw new Exception('No Javascript file to compress!');
			case 1:
				if(!is_file($srcFilesFull[0])) {
					throw new Exception("Unable to find the file '" . $srcFilesFull[0] . "'");
				}
				$srcLength = @filesize($srcFilesFull[0]);
				if($srcLength === false) {
					throw new Exception("Unable to check the size of the file '" . $srcFilesFull[0] . "'");
				}
				$compressMe = $srcFilesFull[0];
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
					$s .= "\n";
					$srcLength += strlen($s);
					$numSrc++;
					if(!@file_put_contents($tempFileSrc, $s, FILE_APPEND)) {
						throw new Exception("Unable to write data to the temporary file");
					}
				}
				$compressMe = $tempFileSrc;
				break;
		}
		if(ToolOptions::$Compress) {
			$tempFileDst = Enviro::GetTemporaryFileName();
			if(!is_array($options)) {
				if((!is_string($options)) || ($options === '')) {
					$options = array();
				}
				else {
					$options = array($options);
				}
			}
			if(ToolOptions::$OmitComments) {
				$options[] = '--no-copyright';
			}
			$options[] = '-o ' . escapeshellarg($tempFileDst);
			$options[] = escapeshellarg($compressMe);
			Enviro::RunNodeJS('uglifyjs', $options);
			$dstLength = @filesize($tempFileDst);
			if($dstLength === false) {
				throw new Exception("Unable to check the size of the file '" . $tempFileDst . "'");
			}
		}
		else {
			$dstLength = @filesize($compressMe);
			if($dstLength === false) {
				throw new Exception("Unable to check the size of the file '" . $compressMe . "'");
			}
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
		if(ToolOptions::$Compress) {
			if(!@rename($tempFileDst, $dstFileFull)) {
				throw new Exception("Unable to save the result to '$dstFileFull'");
			}
			$tempFileDst = '';
		}
		else {
			if(!@copy($compressMe, $dstFileFull)) {
				throw new Exception("Unable to save the result to '$dstFileFull'");
			}
		}
		if(strlen($tempFileSrc)) {
			@unlink($tempFileSrc);
			$tempFileSrc = '';
		}
		$gain = round(100 * (1 - $dstLength/$srcLength), 1);
		Console::WriteLine('ok.');
		Console::WriteLine("   Number of source files: $numSrc");
		Console::WriteLine("   Original file size(s) : " . number_format($srcLength) . " B");
		Console::WriteLine("   Final file size       : " . number_format($dstLength) . " B");
		Console::WriteLine("   Gain                  : $gain%");
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
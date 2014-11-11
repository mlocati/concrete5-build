# Building Assets Required for concrete5

## i18n.php
##### Requirements: php command line 5.3, gettext tools (namely `xgettext`, `msgmerge` and `msgfmt`) installed and in your $PATH (but the script verifies all the dependencies and suggests how to proceed).
This shell php script manage localization files (.pot templates, .po translations and .mo compiled translations), for both the concrete5 core and for custom packages.
Launch it with `php i18n.php --help` for help & options, or with `php i18n.php --interactive` for an interactive session.

## packager.php
##### Requirements: php command line 5.2.2
Tool to create zip file from a package, ready to be submitted to the PRB.
It's quite a nice tool: allows you to directly integrate translations taken from Transifex.

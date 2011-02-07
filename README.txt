Nette Translator (c) Patrik Votoček (Vrtak-CZ), 2010 (http://patrik.votocek.cz)

Requirements
------------
Nette Framework 2.0-dev or higher. (PHP 5.3 edition)

Documentation and Examples
--------------------------
This is Gettext translator with editor. Editor is specia Nette Debug Bar panel.
Load languages from .mo file(s) and save changes with generates .mo & .po files.

Enable Translator
-----------------
Add this line to your config.ini / config.neon.
service.Nette-ITranslator.factory = "NetteTranslator\Gettext::getTranslator"

Add Files
---------
Add files in bootstrap.php or other file where you configurate application.
Environment::getService('Nette\ITranslator')->addFile('%appDir%/AdminModule/lang', 'admin');

There must be at least one file added, otherwise please don't use NetteTranslator.

Enable Editor (panel)
---------------------
To enable add NetteTranslator\Panel::register(); to your bootstrap.php or to the
file where you register your Gettext files (AFTER files registration!).
According to modules, if a translation file exists with the name of current module,
if will be automatically selected as default dictionary in Editor.

Translate String
----------------
Nette\Environment::getService('Nette\ITranslator')->translate('This is translation text');
or plural version
Nette\Environment::getService('Nette\ITranslator')
	->translate('This is translation text', array('This is transtaltion texts', 2));
or use shortcuts
__('This is translation text');
or plural version shortcuts
_n('This is translation text', 'This is transtaltion texts', 2);
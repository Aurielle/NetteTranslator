Nette Translator (c) Patrik Votoček (Vrtak-CZ), 2010 (http://patrik.votocek.cz)

Requirements
------------
Nette Framework 1.x or higher. (PHP 5.3 edition)

Documentation and Examples
--------------------------
This is Gettext translator with editor. Editor is specia Nette Debug Bar panel.
Load languages from .mo file(s) and save changes with generates .mo & .po files.

Enable Translator
-----------------
Add this lines to your config.ini.
service.Nette-ITranslator.option.file = %appDir%/lang/en.mo
service.Nette-ITranslator.factory = "NetteTranslator\Gettext::getTranslator"

Enable Editor (panel)
---------------------
For enable add NetteTranslator\Panel::register(); to your bootstrap.php.

Translate String
----------------
Nette\Environment::getService('Nette\ITranslator')->translate('This is translation text');

<?php
/*
 * Copyright (c) 2009-2010 Roman Sklenář
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.

 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 *
 */
namespace NetteTranslator;

use Nette\Environment;

/**
 * Gettext translator.
 * This solution is partitionaly based on Zend_Translate_Adapter_Gettext (c) Zend Technologies USA Inc. (http://www.zend.com), new BSD license
 *
 * @author     Roman Sklenář
 * @author	   Miroslav Smetana
 * @author	   Patrik Votoček <patrik@votocek.cz>
 * @copyright  Copyright (c) 2009 Roman Sklenář (http://romansklenar.cz)
 * @license    New BSD License
 * @example    http://addons.nettephp.com/gettext-translator
 * @package    NetteTranslator\Gettext
 * @version    0.5
 */
class Gettext extends \Nette\Object implements IEditable
{
	/** @var string */
	public $locale;
	/** @var bool */
	private $endian = FALSE;
	/** @var string|stream  MO gettext file */
	protected $file = FALSE;
	/** @var array  translation table */
	protected $dictionary = array();
	/** @var array */
	protected $meta;
	/** @var array */
	protected $space;
	/** @var string */
	protected $filename;

	/**
	 * Translator contructor.
	 * @param string
	 * @param string
	 * @return void
	 */
	public function __construct($filename, $locale = NULL)
	{
		$this->locale = $locale;
		if (!empty($filename))
			$this->buildDictionary($filename);
		$this->filename = $filename;
		$this->space = \Nette\Environment::getSession('NetteTranslator-Gettext');
		if (!isset($this->space->untranslated))
			$this->space->untranslated = array();
	}

	/**
	 * Translates the given string.
	 * @param  string	translation string
	 * @param  int		count (positive number)
	 * @return string
	 */
	public function translate($message, $count = 1)
	{
		$message = (string) $message;
		if (!empty($message) && isset($this->dictionary[$message])) {
			$word = $this->dictionary[$message];
			if ($count === NULL)
				$count = 1;
			if (is_array($count)) {
				$tcount = 1;
				foreach ($count as $value) {
					if (is_int($value)) {
						$tcount = $value;
					}
				}
				$count = $tcount;
			}
			if (!is_int($count)) {
				$count = 1;
			}
			$s = preg_replace('/([a-z]+)/', '$$1', "n=$count;".$this->meta['Plural-Forms']);
			eval($s);
			$message = $word->translate($plural);
		} else {
			$this->space->untranslated[] = $message;
		}

		$args = func_get_args();
		if (count($args) > 1) {
			array_shift($args);
			$tempargs = $args;
			if (is_array(array_pop($tempargs))) {
				$args = array_pop($args);
			}
			$message = vsprintf($message, $args);
		}
		return $message;
	}

	/**
	 * Load translation data (MO file reader) and builds the dictionary.
	 * @param  string  $filename  MO file to add, full path must be given for access
	 * @throws InvalidArgumentException
	 * @return void
	 */
	private function buildDictionary($filename)
	{
		$this->endian = FALSE;
		$this->file = @fopen($filename, 'rb');
		if (!$this->file) {
			throw new \InvalidArgumentException("Error opening translation file '$filename'.");
		}
		if (@filesize($filename) < 10) {
			\InvalidArgumentException("'$filename' is not a gettext file.");
		}

		// get endian
		$input = $this->readMoData(1);
		if (strtolower(substr(dechex($input[1]), -8)) == "950412de") {
			$this->endian = FALSE;
		} else if (strtolower(substr(dechex($input[1]), -8)) == "de120495") {
			$this->endian = TRUE;
		} else {
			\InvalidArgumentException("'$filename' is not a gettext file.");
		}
		// read revision - not supported for now
		$input = $this->readMoData(1);

		// number of bytes
		$input = $this->readMoData(1);
		$total = $input[1];

		// number of original strings
		$input = $this->readMoData(1);
		$originalOffset = $input[1];

		// number of translation strings
		$input = $this->readMoData(1);
		$translationOffset = $input[1];

		// fill the original table
		fseek($this->file, $originalOffset);
		$origtemp = $this->readMoData(2 * $total);
		fseek($this->file, $translationOffset);
		$transtemp = $this->readMoData(2 * $total);

		for ($count = 0; $count < $total; ++$count) {
			if ($origtemp[$count * 2 + 1] != 0) {
				fseek($this->file, $origtemp[$count * 2 + 2]);
				$original = @fread($this->file, $origtemp[$count * 2 + 1]);
			} else {
				$original = '';
			}

			if ($transtemp[$count * 2 + 1] != 0) {
				fseek($this->file, $transtemp[$count * 2 + 2]);
				$tr = fread($this->file, $transtemp[$count * 2 + 1]);
				if ($original === '') {
					$this->generateMeta($tr);
					continue;
				}

				$word = new Word(explode(\Nette\String::chr(0x00), $original), explode(\Nette\String::chr(0x00), $tr));
				$this->dictionary[$word->message] = $word;
			}
		}
		return $this->dictionary;
	}

	/**
	 * Read values from the MO file.
	 * @param  string
	 */
	private function readMoData($bytes)
	{
		$data = fread($this->file, 4 * $bytes);
		return $this->endian === FALSE ? unpack('V'.$bytes, $data) : unpack('N'.$bytes, $data);
	}

	/**
	 * Generates meta information about distionary.
	 * @return void
	 */
	private function generateMeta($s)
	{
		$s = trim($s);

		$s = preg_split('/[\n,]+/', $s);
		foreach ($s as $meta) {
			$pattern = ': ';
			$tmp = preg_split("($pattern)", $meta);
			$this->meta[trim($tmp[0])] = count($tmp) > 2 ? ltrim(strstr($meta, $pattern), $pattern) : $tmp[1];
		}
	}

	public function getVariantsCount()
	{
		if (isset($this->meta)) {
			$s = preg_replace('/([a-z]+)/', '$$1', "n=2;".$this->meta['Plural-Forms']);
			eval($s);

			return $nplurals;
		}
		return 1;
	}

	public function getStrings()
	{
		$result = array();
		foreach ($this->dictionary as $value) {
			if (trim($value->message) != "") {
				$result[$value->message] = $value->getTranslation(NULL);
			}
		}

		foreach ($this->space->untranslated as $value) {
			if (trim($value) != "" && !isset($result[$value])) {
				$result[$value] = false;
			}
		}


		return $result;
	}

	public function setTranslation($message, $string)
	{
		$word = new Word($message, $string);
		$this->dictionary[$word->message] = $word;
	}

	public function save()
	{
		$filename = explode('/', $this->filename);
		$filename = $filename[count($filename) - 1];

		$newPoFilename = str_replace($filename, '', $this->filename).'TPanel.po';
		//$this->gettext_gen_mo($newFilename, $this->getStrings());
		$fp = fopen($newPoFilename, 'w');
		fwrite($fp, $this->getPoHeader());
		fwrite($fp, $this->getPoStrings());
		fwrite($fp, $dump);
		fclose($fp);
		echo exec('msgfmt -o '.$this->filename.' '.$newPoFilename);
		$this->space->untranslated = array();
	}

	private function getPoHeader()
	{
		$time = new \DateTime();
		$header = '# Gettext keys exported by GettextTranslator and Translation Panel
# Created: '.$time->format('Y-m-d H:i:s').'
msgid ""
msgstr ""
"Project-Id-Version: \n"
"POT-Creation-Date: \n"
"PO-Revision-Date: \n"
"Last-Translator: TranslationPanel\n"
"Language-Team: \n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"Plural-Forms: '.$this->meta['Plural-Forms'].'\n"
"X-Poedit-SourceCharset: utf-8\n"

';

		return $header;
	}

	private function getPoStrings()
	{
		$result = '';
		$strings = $this->getStrings();
		foreach ($strings as $key => $value) {
			$result .= 'msgid "'.$key.'"
';
			if (!is_array($value)) {
				$result .= 'msgstr "'.$value.'"

';
			} else {
				if (count($value) == 1) {
					$result .= 'msgstr "'.$value[0].'"

';
				} else {
					$counter = 0;
					$result .= 'msgid_plural "'.$key.'"
';
					foreach ($value as $val) {
						$result .= 'msgstr['.$counter.'] "'.$val.'"
';
						$counter++;
					}
					$result .= '
';
				}
			}
		}

		return $result;
	}

	/**
	 * Get translator
	 *
	 * @param array $options
	 * @return NetteTranslator\Gettext
	 */
	public static function getTranslator($options)
	{
		return new static(isset($options['file']) ? $options['file'] : NULL, \Nette\Environment::getVariable('lang', 'en'));
	}
}

/**
 * Class that represents translatable word.
 * 
 * @author     Roman Sklenář
 * @copyright  Copyright (c) 2009 Roman Sklenář (http://romansklenar.cz)
 * @license    New BSD License
 * @example    http://addons.nettephp.com/gettext-translator
 * @package    NetteTranslator\Gettext
 * @version    0.5
 */
class Word extends \Nette\Object
{
	/** @var string|array */
	protected $message;
	/** @var string|array */
	protected $translation;

	/**
	 * Word constructor.
	 * @param string|array
	 * @param string|array
	 * @return void
	 */
	public function __construct($message, $translation)
	{
		$this->message = $message;
		$this->translation = $translation;
	}

	/**
	 * @return string
	 */
	public function getTranslation($form = 0)
	{
		return (is_array($this->translation) && $form !== NULL) ? $this->translation[$form] : $this->translation;
	}

	/**
	 * @return string
	 */
	public function getMessage($form = 0)
	{
		return is_array($this->message) ? $this->message[$form] : $this->message;
	}

	/**
	 * Translates a word.
	 * @param  string  translation string
	 * @param  int     form of translation
	 * @return string
	 */
	public function translate($form = 0)
	{
		$msg = $this->getTranslation($form);
		return!empty($msg) ? $msg : $this->getMessage($form);
	}
}
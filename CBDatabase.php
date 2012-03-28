<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2011 Leo Feyer
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  Winans Creative 2011, Helmut Schottm�ller 2009
 * @author     Blair Winans <blair@winanscreative.com>
 * @author     Fred Bliss <fred.bliss@intelligentspark.com>
 * @author     Adam Fisher <adam@winanscreative.com>
 * @author     Includes code from survey_ce module from Helmut Schottm�ller <typolight@aurealis.de>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */


class CBDatabase
{

	/**
	 * Current object instance (Singleton)
	 * @var object
	 */
	protected static $objInstance;

	/**
	 * Files object
	 * @var object
	 */
	protected $Files;

	/**
	 * Top content
	 * @var string
	 */
	protected $strTop = '';

	/**
	 * Bottom content
	 * @var string
	 */
	protected $strBottom = '';

	/**
	 * Modified
	 * @var boolean
	 */
	protected $blnIsModified = false;

	/**
	 * Data array
	 * @var array
	 */
	protected $arrData = array();

	/**
	 * Cache array
	 * @var array
	 */
	protected $arrCache = array();


	/**
	 * Load all configuration files
	 */
	protected function __construct()
	{
		// Read the local configuration file
		$strMode = 'top';
		$resFile = fopen(TL_ROOT . '/system/modules/course_builder/config/database.sql', 'rb');

		while (!feof($resFile))
		{
			$strLine = fgets($resFile);
			$strTrim = trim($strLine);

			if ($strTrim == '?>')
			{
				continue;
			}

			if ($strTrim == '-- LESSON SEGMENT START --')
			{
				$strMode = 'data';
				continue;
			}

			if ($strTrim == '-- LESSON SEGMENT STOP --')
			{
				$strMode = 'bottom';
				continue;
			}

			if ($strMode == 'top')
			{
				$this->strTop .= $strLine;
			}
			elseif ($strMode == 'bottom')
			{
				$this->strBottom .= $strLine;
			}
			elseif ($strTrim != '')
			{
				if (preg_match('@^[ ]*`([^`]+)`([^,]*)@i', $strLine, $arrMatch))
				{
					$this->arrData[$arrMatch[1]] = trim($arrMatch[2]);
				}
			}
		}

		fclose($resFile);

		// Do not use __destruct, because Database object might be destructed first (see http://dev.contao.org/issues/2236)
		register_shutdown_function(array($this, 'storeFile'));
	}
	

	/**
	 * Return the current data as associative array
	 * @return array
	 */
	public function getData()
	{
		return $this->arrData;
	}



	/**
	 * Save the local configuration
	 */
	public function storeFile()
	{
		if (!$this->blnIsModified)
		{
			return;
		}

		$strFile  = trim($this->strTop) . "\n\n";
		$strFile .= "-- LESSON SEGMENT START --\nCREATE TABLE `tl_cb_lessonsegment` (\n";

		foreach ($this->arrData as $k=>$v)
		{
			$strFile .= "  `$k` $v,\n";
		}

		$strFile .= ") ENGINE=MyISAM DEFAULT CHARSET=utf8;\n-- LESSON SEGMENT STOP --\n\n";

		if ($this->strBottom != '')
		{
			$strFile .= trim($this->strBottom) . "\n\n";
		}

		$objFile = new File('system/modules/course_builder/config/database.sql');
		$objFile->write($strFile);
		$objFile->close();
	}


	/**
	 * Prevent cloning of the object (Singleton)
	 */
	final private function __clone() {}


	/**
	 * Return the current object instance (Singleton)
	 * @return object
	 */
	public static function getInstance()
	{
		if (!is_object(self::$objInstance))
		{
			self::$objInstance = new CBDatabase();
		}

		return self::$objInstance;
	}


	/**
	 * Add a configuration variable to the local configuration file
	 * @param string
	 * @param mixed
	 */
	public function add($strKey, $varValue)
	{
		$this->blnIsModified = true;
		$this->arrData[$strKey] = $varValue;
	}


	/**
	 * Alias for Config::add()
	 * @param string
	 * @param mixed
	 */
	public function update($strKey, $varValue)
	{
		$this->add($strKey, $varValue);
	}


	/**
	 * Delete a configuration variable from the local configuration file
	 * @param string
	 * @param mixed
	 */
	public function delete($strKey)
	{
		$this->blnIsModified = true;
		unset($this->arrData[$strKey]);
	}


	/**
	 * Escape a parameter depending on its type and return it
	 * @param mixed
	 * @return mixed
	 */
	protected function escape($varValue)
	{
		if (is_numeric($varValue))
		{
			return $varValue;
		}

		if (is_bool($varValue))
		{
			return $varValue ? 'true' : 'false';
		}

		if ($varValue == 'true')
		{
			return 'true';
		}

		if ($varValue == 'false')
		{
			return 'false';
		}

		$varValue = preg_replace('/[\n\r\t]+/i', ' ', str_replace("'", "\\'", $varValue));
		$varValue = "'" . preg_replace('/ {2,}/i', ' ', $varValue) . "'";

		return $varValue;
	}
}

?>
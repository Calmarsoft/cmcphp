<?php
/**
 -------------------------------------------------------------------------
    CMC for PHP is a web framework for PHP.                              
    More information can be seen here: <http://cmc.calmarsoft.com/about>
 -------------------------------------------------------------------------

    Copyright (c) 2014 by Calmarsoft company <http://calmarsoft.com> (FRANCE). All rights reserved.
     
    This file is part of CMC for PHP.

    CMC for PHP is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    CMC for PHP is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with CMC for PHP.  If not, see <http://www.gnu.org/licenses/>.
**/

namespace cmc;
/**
 * This class contains the global variables and methods of the framework
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
class cmc {
    /**
     * The microtime value for starting the whole process
     * @var integer
     */
    static $startTime;

    /**
     * the microtime value of ending the library parse
     * @var integer
     */
    static $endCMCTime;

    /**
     * the microtime value when user process starts
     * @var integer
     */
    static $startProcessTime;

    /**
     * the microtime value when process is finished
     * @var integer
     */
    static $endProcessTime;

    /**
     * the default mark value
     */
    const CMC_gloMark = 999;

    static private $CMC_mark;

    /**
     * returns the 'mark' of the library.<br>The 'mark' changes each time the library is generated.
     * @return string
     */
    static public function mark() {
        if (!self::$CMC_mark) {
            self::$CMC_mark = self::CMC_gloMark;
            if (defined('cmcPharMark'))
                self::$CMC_mark = cmcPharMark;
        }
        return self::$CMC_mark;
    }

    /**
     * tests if a string begins with $pat
     * @param string the string being tested
     * @param string the prefix to test
     * @return boolean
     */
    static public function str_beginsby($str, $pat) {
        return strncmp($pat, $str, strlen($pat)) === 0;
    }

    /**
     * removes a prefix if exists
     * @param string the input/output string
     * @param string the prefix value
     * @return boolean true if succeeded
     */
    static public function str_truncprefix(&$str, $pat) {
        $l = strlen($pat);
        if (strncmp($pat, $str, $l) === 0) {
            $str = substr($str, $l);                            
            if ($str===false)
                $str = '';
            return true;
        }
        return false;
    }

    /**
     * gets the class part of an object (without namespace)
     * 
     * @param string $var
     * @return string
     */
    static public function className($var) {
        $c = get_class($var);
        $p = strrpos($c, '\\');
        return $p ? substr($c, $p + 1) : $c;
    }
    
    /**
     * @ignore
     */
    static public function getPoweredByBanner($path) {
        $banner = config::PoweredBy_Banner($path);
        if ($banner === true)
            $banner = '<style>.cmc-poweredby{float:right;margin-right:20px;margin-top:20px;font-size:small;text-align:right}</style><div class="cmc-poweredby">Powered by CMC (c) <a href="http://www.calmarsoft.com/">CalmarSoft</a></div>';
        return $banner;
    }

}

cmc::$startTime = microtime(true);

require_once('error/runtimeErrors.php');
require_once('error/fatalErrors.php');
require_once('dftconfig.php');
require_once('core/request.php');
require_once('core/translation.php');
require_once('config.php');             // this one is external from the lib
require_once('core/IClonable.php');
require_once('core/ISerializable.php');
require_once('core/cache.php');
require_once('core/ui/material.php');
require_once('ui/dynframe.php');
require_once('app.php');
require_once('sess.php');
require_once('ui/view.php');
require_once('ui/dynview.php');

require_once('db/dataenv.php');


cmc::$endCMCTime = microtime(true);

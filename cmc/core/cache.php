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
namespace cmc\core;

use cmc\config, cmc\core\request;
/**
 * global cache management
 *
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
class cache {
    /**
     * calculates a storepath for the given key
     * 
     * @param string key value
     * @return string path
     */
    static function store_path($key) {
         if (!config::CACHE_path)
            return sys_get_temp_dir() . '/' . $key . '.tmp';
        else {
            if (substr(config::CACHE_path, 0, 1)==='/')
                return \realpath(config::CACHE_path) . '/'. $key . '.tmp';
            else
                return \realpath(request::rootphyspath() . '/' . config::CACHE_path) . '/' . $key . '.tmp';
        }
    }
    /**
     * stores a variable in the global cache
     * 
     * @param string key value
     * @param binary value
     */
    static function global_store($key, $var)
    {
        $key = urlencode(strtr($key,'/','.'));
        $storepath = self::store_path($key);
        touch($storepath);
        chmod($storepath, 0660); // a little security
        file_put_contents($storepath, bzcompress(serialize($var), 2) );
    }
    /**
     * tests if a variable is present in the cache 
     * 
     * @param string key value
     * @return boolean
     */
    static function global_exists($key)
    {
        $key = urlencode(strtr($key,'/','.'));
        $storepath = self::store_path($key);
        return file_exists(realpath($storepath));
    }
    /**
     * removes a cache entry
     * 
     * @param string key value
     */
    static function global_remove($key)
    {
        $key = urlencode(strtr($key,'/','.'));
        $storepath = self::store_path($key);
        if (file_exists(realpath($storepath)))
            unlink(realpath($storepath));
    }
    /**
     * retrieves a variable from the cache
     * 
     * @param string key value
     * @return binary|boolean
     */
    static function global_get($key)
    {
        $key = urlencode(strtr($key,'/','.'));
        $storepath = realpath(self::store_path($key));        
        if (!$storepath)
            return false;
        return unserialize(bzdecompress(file_get_contents($storepath)));
    }
}

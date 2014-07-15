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
namespace cmc\db;
use cmc\config;

/**
 * factors a database instance
 */
abstract class databasefactory {
    static public function createdatabase($database, $server) {
        return null;
    }
}
/**
 * the database abstract model
 *
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
abstract class database {
    protected $_de, $_user, $_passwd, $_server, $_database, $_port;
    protected $_lasterror;   
    /**     
     * @param string database name if any
     * @param string server name, IP address, ...
     */
    public function __construct($dataenv, $database, $server) {   
        $this->_de = $dataenv;
        $this->_database = $database;
        $this->_server = $server;
    }
    
    /**
     * is the database connected?
     * @return boolean
     */
    abstract public function is_connected();
    /**
     * last error string
     * @return string
     */
    public function lastError() {
        return $this->_lasterror;
    }
    /**
     * user/password authentication
     * @param string user
     * @param string password
     */
    public function setLogin($user, $passwd=null) {
        $this->_user = $user;
        $this->_passwd = $passwd;
    }
    /**
     * sets a port number
     * @param integer $port
     */
    public function setPort($port) {
        $this->_port = $port;
    }
    
    public function dataenv() {
        return $this->_de;        
    }
    
    /**
     * opens connection
     * @return boolean
     */
    abstract public function connect();
    /**
     * closes connection
     */
    abstract public function disconnect();
    /**
     * gets the last native errortext
     */
    abstract public function getLastError();
    /**
     * creates a datasource object from sql Text
     * may be checked and/or prepared depending on implementation
     * @return dataquery
     * @throws DatabaseException_mysqli
     */
    abstract public function getdataSource($sqlData);
    /**
     * creates an object for 'table' acccess
     * @return \cmc\db\datatable
     */
    abstract public function gettable($tablename, $sort=null);
    /**
     * direct execution of multiple queries, with a semicolon separation
     * typical use is SQL scripts
     * in case of failure, it stops execution and throws exception
     * 
     * @param string $text
     * @return boolean
     * @throws DatabaseException
     */
    public function executeQueries($text) {
        $list = preg_split('/(;)|([^;.]+\'.+\'[^;.]+)|([^;.]+`.+`[^;.]+)/', $text,0, PREG_SPLIT_NO_EMPTY  | PREG_SPLIT_DELIM_CAPTURE);
        foreach ($list as $i=>$q) {
            if ($q===';' || rtrim($q)==='') continue;
            $ds = $this->getdataSource($q);
            if (!$ds)
                throw new DatabaseException('UNEX', 'unexpected error');    // unexpected because getdatasource throws an exception itself
            if (!$ds->execute())
                throw new DatabaseException('EXEC', 'execution failed'); 
        }
        return true;
                       
    }
}

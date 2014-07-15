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
 * */

namespace cmc\db;

require_once('DatabaseException.php');
require_once('database.php');
require_once('datasource.php');
require_once('dataquery.php');
require_once('datatable.php');

require_once('mysqli/database_mysqli.php');

use cmc\config;
use cmc\db\mysqli\database_mysqli_factory;

/**
 * abstract data environment
 * 
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
abstract class dataenv {

    private $_ourDS, $_ourDB;
    private $_benchmarking;
    private $_benchBegin;
    private $_execLog;

    /**
     * defines the factory of this environment. It can be done by inheriting dataenv_xxx or putting a 'type' entry in config['db']
     * 
     * @return string databasefactory derived classname
     */
    public function getDatabaseFactory() {
        if (array_key_exists('type', config::val('db'))) {
            switch (config::val('db')['type']) {
                case 'mysqli':
                    return database_mysqli_factory::className;
            }
        }
    }

    /**
     * gets the query definition map: name=>sqltext
     * 
     * @return array key=>query string queries inventory
     */
    public function getQueryMap() {
        return array();
    }

    /**
     * associative array of envirnment direct tables
     * key is the tablename, and value is an associative array of options (sort, filter, ...)
     * @return array
     */
    public function getTables() {
        return array();
    }

    /**
     * retrieves the database value
     * 
     * default implementation takes value from config::val('db')['database']
     * @return string
     */
    public function getDatabaseName() {
        if (array_key_exists('database', config::val('db')))
            return config::val('db')['database'];
        return null;
    }

    /**
     * retrieves login name
     * 
     * default implementation takes from config::val('db')['login']
     * @return string
     */
    public function getLogin() {
        if (array_key_exists('login', config::val('db')))
            return config::val('db')['login'];
        return null;
    }

    /**
     * retrieves password
     * 
     * default implementation takes from config::val('db')['password']
     */
    public function getPassword() {
        if (array_key_exists('password', config::val('db')))
            return config::val('db')['password'];
        return null;
    }

    /**
     * retrieves port number
     * 
     * default implementation takes from config::val('db')['port']
     */
    public function getPort() {
        if (array_key_exists('port', config::val('db')))
            return config::val('db')['port'];
        return null;
    }

    /**
     * retrieves server name
     * 
     * default implementation takes from config::val('db')['server']
     */
    public function getServerName() {
        if (array_key_exists('server', config::val('db')))
            return config::val('db')['server'];
    }

    public function __construct() {
        $this->_ourDS = array();
        $this->_ourDB = null;
    }

    /**
     * instantiates or retrieves a connected database according to current settings
     * @return database
     */
    public function getConnectedDB() {

        if (!$this->_ourDB) {
            $factory = $this->getDatabaseFactory();
            $this->_ourDB = $factory::createdatabase($this, $this->getDatabaseName(), $this->getServerName());
            if ($this->_ourDB) {
                $this->_ourDB->setLogin($this->getLogin(), $this->getPassword());
                $this->_ourDB->setPort($this->getPort());
                if (!$this->_ourDB->connect()) {
                    unset($this->_ourDB);
                    $this->_ourDB = null;
                }
            }
        }
        return $this->_ourDB;
    }

    /**
     * instantiates or retrieves a datasource from its name in the inventory
     * @param string query name
     * @return \cmc\db\datasource
     */
    public function getQueryDS($objectName) {
        if (array_key_exists($objectName, $this->_ourDS))
            return $this->_ourDS[$objectName];

        $db = $this->getConnectedDB();
        if (!$db)
            return false;
        $qmap = $this->getQueryMap();
        $tmap = $this->getTables();
        if (array_key_exists($objectName, $tmap)) {
            $sort = null;
            if (array_key_exists('sort', $tmap[$objectName]))
                $sort = $tmap[$objectName]['sort'];
            $ds = $db->gettable($objectName, $sort);
            if (!$ds) {
                return false;
            }            
        }
        else if (array_key_exists($objectName, $qmap)) {
            $ds = $db->getdataSource($qmap[$objectName]);
            if (!$ds) {
                return false;
            }
        }
        else
            return false;
        
        $this->_ourDS[$objectName] = $ds;

        return $ds;
    }

    /**
     * instantiates or retrieves a datasource from its name in the inventory, 
     * then executes it with parameters, and finally fetches the first record
     * 
     * the result is an associative array of values
     * @param string query name
     * @param array parameter values
     * @return array
     */
    public function getQueryFirst($qryName, $params = false) {
        $result = false;
        $qr = $this->getQueryDS($qryName);
        if ($qr) {
            if ($params)
                $qr->setParams($params);
            $result = $qr->first();
            $qr->close();
        }
        return $result;
    }
    
    public function enableBenchMarking() {
        $this->_benchmarking = true;
    }

    public function benchMarking() {
        return $this->_benchmarking;
    }
    
    /**
     * used for debugging information: called by objects just before executing SQL statement
     */
    public function executionBegin() {
        if (!$this->_benchmarking)
            return;
        $this->_benchBegin = microtime(true);
    }
    /**
     * used for debugging information: called by objects just after executing SQL statement
     * 
     * @param type $sql statement text
     * @param type $rows affected rows or null
     */
    public function executionLog($sql, $rows) {
        if (!$this->_benchmarking)
            return;
        $duration = microtime(true) - $this->_benchBegin;
        if (!is_array($this->_execLog))
            $this->_execLog = array();
        array_push($this->_execLog, array('sql'=>$sql, 'time'=>$duration, 'rows'=>$rows));
    }
    
    public function getExecutionText() {
        if (!$this->_benchmarking || !is_array($this->_execLog))
            return '';
        echo '<table class="cmc-timings"><tbody>';
        foreach ($this->_execLog as $item) {
            echo '<tr><td>'.$item['sql'].'</td><td>'
                           .\number_format($item['time']*1000, 3).' ms</td><td>'
                           .$item['rows'].'</td></tr>';
        }
        echo '</tbody></table>';
    }
    
    /**
     * instantiates or retrieves a datasource from its name in the inventory, 
     * then executes it with parameters
     * 
     * the result is the executed datasource
     * @param string query name
     * @param array parameter values
     * @return datasource
     */
    public function queryExecute($qryName, $params = false) {
        $result = false;
        $qr = $this->getQueryDS($qryName);
        if ($qr) {
            if ($params)
                $qr->setParams($params);
            $result = $qr->execute();
            $qr->close();
        }
        return $result;
    }

    public function OnSerialize() {
        $this->_ourDS = array();
        $this->_ourDB = null;
        $this->_execLog = null;
        $this->_benchBegin = null;
        $this->_benchmarking = null;
    }

}

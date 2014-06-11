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
namespace cmc\db\mysqli;

/**
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
ini_set('mysqli.allow_persistent', '1');
ini_set('mysqli.max_persistent', '0');
ini_set('mysqli.max_links', '0');

require_once("DatabaseException_mysqli.php");
require_once("dataquery_mysqli.php");
require_once("datatable_mysqli.php");

use cmc\error\runtimeErrors, cmc\error\fatalErrors;
use cmc\db\databasefactory, cmc\db\database;
use cmc\config, cmc\app;
use cmc\db\mysqli\DatabaseException_mysqli;

/**
 * mysqli databasefactory implementation
 */
class database_mysqli_factory extends databasefactory {
    const className=__CLASS__;
    
    static public function createdatabase($database, $server='localhost') {
        return new database_mysqli($database, $server);
    }

}

/**
 * mysqli database implementation
 *
 * @author Benoit@calmarsoft.com
 */
class database_mysqli extends database {
    private $_activestmt;
    private $_mysqli;
    private $_prvFlag='p:';

    public function __construct($database, $server) {
        parent::__construct($database, $server);
        $this->_port  = 3306;
        $this->_mysqli = null;
    }

    /**
     * opens the connection
     * 
     * @return boolean
     */
    public function connect() {
        $bOneTry = false;
        $this->_mysqli = new \mysqli();
        $this->_mysqli->init();        

        runtimeErrors::setMask(E_WARNING|E_NOTICE);
        do {
            $bRetry = false;
            $bSuccess = $this->_mysqli->real_connect($this->_prvFlag.$this->_server, $this->_user, $this->_passwd, 
                                $this->_database, $this->_port);
            if (!$bSuccess && $this->mysqli->errno==32 && !$bOneTry) {
                $this->_mysqli->close();
                $bOneTry = true;
                $bRetry = true;
            }
        } while (!$bSuccess && $bRetry);
            
        if (!$bSuccess) {
            runtimeErrors::setMask(0);
            if (config::databaseErrFatal) {
                fatalErrors::trigger(app::current()->getSession(), 'mysql_connect', 1, $this->_mysqli->connect_error);
            }
            unset($this->_mysqli);
            $this->_mysqli = null;
            
            return false;
        }
        runtimeErrors::setMask(0);
        $this->_mysqli->set_charset('UTF8');
        /*
        var_dump($this->_mysqli->stat);
        var_dump($this->_mysqli);
        var_dump($this->_mysqli->host_info);
        var_dump($this->_mysqli->client_info);
        var_dump($this->_mysqli->server_info);*/
        return true;
    }
    /**
     * is connected?
     * @return boolean
     */
    public function is_connected() {
        return $this->_mysqli != null;
    }
    /**
     * closes connection
     */
    public function disconnect() {
        if ($this->_mysqli) {
            $this->_mysqli->close();
            unset($this->_mysqli);
            $this->_mysqli = null;
        }
    }
    /**
     * retrieves the database late error text
     * 
     * @return string|null
     */
    public function getLastError() {
        if ($this->_mysqli)
            return $this->_mysqli->error;
        return null;
    }
    public function getLastErrno() {
        if ($this->_mysqli)
            return $this->_mysqli->errno;
        return null;        
    }
    /**
     * creates a new datasource from sql text
     * 
     * @param string sql statement text
     * @return boolean
     * @throws DatabaseException_mysqli
     */
    public function getdataSource($sqlData) {
        if (!$this->_mysqli)
            return;
        $stmt = $this->_mysqli->prepare($sqlData);
        if ($stmt)
            return dataquery_mysqli::create($this, $stmt);
        else {
            throw new DatabaseException_mysqli($this, 'PREP', 'prepare failed');
        }
    }
    
    public function gettable($tablename, $sort=false) {
        return datatable_mysqli::create($this, $tablename, $sort);
    }
    
    public function validstmt($stmt, $bInvalidate=false) {
        $valid = ($stmt && $stmt===$this->_activestmt);
        if ($bInvalidate && $valid)
            $this->_activestmt = null;
        return $valid;
    }
    
    public function prepareNativeStatement($sql) {
        if (!$this->_mysqli)
            return;        
        if ($this->_activestmt) {
            $this->_activestmt->close();
            $this->_activestmt = null;
        }
        
        $stmt = $this->_mysqli->prepare($sql);
        if ($stmt) {
            $this->_activestmt = $stmt;
            return $stmt;
        } else {
            throw new DatabaseException_mysqli($this, 'PREP', 'prepare failed');
        }        
    }
    
    public function __destroy() {
        unset($this->_mysqli);
    }



}

/** usage sample: 

    $db = \cmc\database_mysqli_factory::createdatabase('idtp');
    $db->setLogin('idtp');
    if (!$db->connect())
        var_dump($db->lastError ());
    if ($db)
        $ds = $db->createdataSource('select * from materials order by matpath, lang');
    if (!$ds)
        var_dump($db->lastError ());

    if ($ds) {
        $ds->execute();

        while ($row = $ds->fetch_assoc()) {
            var_dump($row);
        }
    }

 */

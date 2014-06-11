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

use cmc\db\datasource;

require_once('database.php');
require_once('datasource.php');

/**
 * abstract datasource
 *
 * need to implement the Iterator methods
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
abstract class dataquery implements datasource {
    const className=__CLASS__;
    protected $_currentrow;
    protected $_position;    
    
    /**
     * underlying database
     * @var database
     */
    protected $_db;
 
    public function __construct() {
    }

    /**
     * executes the datasource
     */
    abstract public function execute();
    /**
     * fetch a record, and returns an associative array of values
     * @return array
     */
    abstract public function fetch_assoc();
    /**
     * updates parameter bindings
     * 
     * @param type $params
     * @return type
     */
    abstract public function setparams($params);        
    
     /**
     * returns the last fetched row
     * @return array|null
     */
    public function current() {
       return $this->_currentrow;
    }
    /**
     * Iterator: returns the position
     * @return integer
     */
    public function key() {
        return $this->_position;
    }
    /**
     * Iterator: returns the next record
     * @return type
     */
    public function next() {
        return $this->fetch_assoc();
    }
    /**
     * Iterator: rewind and returns the first record
     * @return boolean
     */
    public function first() {
        $this->rewind();
        if ($this->_currentrow)
            return $this->_currentrow;
        return false;
    }
    /**
     * refresh query (in this implementation: re-execute)
     */
    public function rewind() {
        $this->close();
        $this->_currentrow = null;
        $this->fetch_assoc();
    }
    /**
     * checks if current record is available
     */
    public function valid() {
        return ($this->_currentrow!=null);
    }
}


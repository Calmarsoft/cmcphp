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

require_once('database.php');

interface IBrowsable extends \Iterator {

}

interface ICreatable {
    /**
     * creates the underlying data object if not exists
     */
    function createIfNotExists();    
}



interface IUpdatable {
    /**
     * inserts new data row, using the provided associative array of data
     */
    function insertData($data);
    
    /**
     * updates data row using $key and $data associative arrays
     */
    function updateData($data);
    /**
     * removes a data entry
     */
    function deleteData($key);    
}
/**
 * primarykey capability
 */
interface IPrimaryKey {
    /**
     * gets the value of the primary key
     * Returns an array or a scalar
     * @returns mixed
     */
    function getPrimaryKey();
    /**
     * seeks using a primary key value
     * @param $key primary key value (scalar or array)
     * @returns array|null
     */
    function seekPrimaryKey($key);
}
/**
 * filtering capability
 */
interface IFilter {
    /**
     * sets a filter with the sepcifier vector criteria
     */
    function setFilter($data);
    /**
     * uses back the whole result
     */
    function cancelFilter();
}

interface IMetaInfo {
    /**
     * returns a traversable object of items
     * @returns array
     */
    function getItems();
    /**
     * returns a traversable object of items of the unique key
     * @returns array
     */
    function getKeyItems();
}

/**
 * abstract datasource
 *
 * need to implement the Iterator methods
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
interface datasource extends IBrowsable {

    /**
     * closes the datasource
     */
    function close();
}

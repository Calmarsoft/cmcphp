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

namespace cmc\db\mysqli;

require_once('database_mysqli.php');

use cmc\db\datatable;
use cmc\db\DatabaseException;

/**
 * mysqli datasource implementation
 *
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
class datatable_mysqli extends datatable {

    // metadata flags. Information source is include/mysql_com.h in MySQL distribution
    /** @ignore * */
    const MYSQL_NOT_NULL_FLAG = 1;               /* Field can't be NULL */

    /** @ignore * */
    const MYSQL_PRI_KEY_FLAG = 2;               /* Field is part of a primary key */

    /** @ignore * */
    const MYSQL_UNIQUE_KEY_FLAG = 4;               /* Field is part of a unique key */

    /** @ignore * */
    const MYSQL_MULTIPLE_KEY_FLAG = 8;             /* Field is part of a key */

    /** @ignore * */
    const MYSQL_BLOB_FLAG = 16;              /* Field is a blob */

    /** @ignore * */
    const MYSQL_UNSIGNED_FLAG = 32;              /* Field is unsigned */

    /** @ignore * */
    const MYSQL_ZEROFILL_FLAG = 64;              /* Field is zerofill */

    /** @ignore * */
    const MYSQL_BINARY_FLAG = 128;             /* Field is binary   */

    /** @ignore * */
    const MYSQL_ENUM_FLAG = 256;             /* field is an enum */

    /** @ignore * */
    const MYSQL_AUTO_INCREMENT_FLAG = 512;         /* field is a autoincrement field */

    /** @ignore * */
    const MYSQL_TIMESTAMP_FLAG = 1024;            /* Field is a timestamp */

    /** @ignore * */
    const MYSQL_SET_FLAG = 2048;            /* field is a set */

    /** @ignore * */
    const MYSQL_NO_DEFAULT_VALUE_FLAG = 4096;      /* Field doesn't have default value */

    /** @ignore * */
    const MYSQL_ON_UPDATE_NOW_FLAG = 8192;        /* Field is set to NOW on UPDATE */

    /** @ignore * */
    const MYSQL_NUM_FLAG = 32768;          /* Field is num (for clients) */

    private $_table;
    private $_md;
    private $_select, $_select_text;
    private $_update, $_update_text;
    private $_insert, $_insert_text;
    private $_delete;
    private $_seek, $_seek_text;
    private $_sort;
    private $_filter, $_filterchanged;

    /**
     * creates a new table instance
     * 
     * @param \cmc\db\mysqli\database_mysqli $db
     * @param string $tablename
     * @param array $sort the simple array of sort fields
     * @return \cmc\db\mysqli\datatable_mysqli
     */
    static public function create(database_mysqli $db, $tablename, $sort) {
        $new_obj = new datatable_mysqli();
        $new_obj->_db = $db;
        $new_obj->_table = $tablename;
        $new_obj->_sort = $sort;
        $new_obj->_position = -1;
        return $new_obj;
    }

    /**
     * set parameter values for a MYSQLi statement
     * @param \mysqli_stmt $stmt
     * @param array $values
     * @return boolean
     */
    private function setStmtParms($stmt, $values) {
        $ar = array();
        $ar[0] = '';
        $aridx = 0;
        $t = '';
        foreach ($values as $idx => $val) {
            $ar[$aridx + 1] = &$values[$idx];
            if (is_string($ar[$aridx + 1]))
                $t .= 's';
            else if (is_long($ar[$aridx + 1]))
                $t .= 'i';
            else if (is_numeric($ar[$aridx + 1]))
                $t .= 'd';
            else
                $t .= 'b';
            $aridx++;
        }
        $ar[0] = $t;
        $result = call_user_func_array(array($stmt, 'bind_param'), $ar);
        return $result;
    }

    /**
     * deletes a record designated by the associative array
     * the key paramter must be complete
     * @param tarray $key
     * @throws DatabaseException
     */
    public function deleteData($key) {
        $uk = $this->getUniqueKey();
        if (count($key) !== count($uk)) {
            throw new cmc\db\DataBaseException('INVK', 'invalid unique key');
        }

        if (!$this->_db->validstmt($this->_delete)) {
            $w = '';
            foreach ($uk as $f) {
                if ($w === '')
                    $w = '`' . $f['name'] . '`=?';
                else
                    $w .= ' AND `' . $f['name'] . '`=?';
            }
            $text = 'delete from ' . $this->_table . ' where ' . $w;

            $stmt = $this->_db->prepareNativeStatement($text);
            if ($stmt) {
                $this->_delete = $stmt;
            } else
                throw new DatabaseException('UNEX');
        }

        if ($this->_delete) {
            $idx = 0;
            $assoc = (array_keys($key) !== range(0, count($key) - 1));
            if ($assoc) {
                $pvals = array();
                foreach ($uk as $f) {
                    if (!array_key_exists($f['name'], $key))
                        throw new DatabaseException('INVK', 'Invalid unique key components (' . $f['name'] . ' not found)');
                    $pvals[$idx] = $key[$f['name']];
                }
            } else {
                $pvals = $key;
            }
            $result = $this->setStmtParms($this->_delete, $pvals);
            $this->_delete->execute();
        }
    }

    /**
     * inserts new data using the provided associative array
     * @param array $data
     * @throws DatabaseException
     */
    public function insertData($data) {
        $uk = $this->getUniqueKey(false);

        foreach ($uk as $f) {
            if (!array_key_exists($f['name'], $data))
                throw new DatabaseException('INVK', 'insert data incomplete; primary key is needed');
        }

        $qf = '';
        $qv = '?';
        foreach (array_keys($data) as $f) {
            if ($qf === '') {
                $qf = '`' . $f . '`';
                $qv = '?';
            } else {
                $qf .= ', `' . $f . '`';
                $qv .= ', ?';
            }
            if ($this->_md[$f]['type'] === 10) {
                if ($data[$f]) {
                    $data[$f] = \cmc\config::dateToISO($data[$f]);
                }
            }
        }

        $text = 'insert into ' . $this->_table . ' (' . $qf . ') values(' . $qv . ')';

        if ($text != $this->_insert_text || !$this->_db->validstmt($this->_insert)) {
            $stmt = $this->_db->prepareNativeStatement($text);
            if ($stmt) {
                $this->_insert = $stmt;
                $this->_insert_text = $text;
            } else {
                throw new DatabaseException('UNEX');
            }
        }
        if ($this->_insert) {
            $result = $this->setStmtParms($this->_insert, array_values($data));
            if (!$this->_insert->execute())
                throw new DatabaseException('INSERT', 'error inserting data '.implode(',',$data));
        }
        return true;
    }

    /**
     * updates a row with the provided data
     * the modified data is an associative array
     * 
     * the values for each primary key elements must be provided
     * the primary key value cannot be altered using this function
     * @param array $data
     * @throws DatabaseException
     */
    public function updateData($data) {
        $klist = array();
        $parms = array();
        $uk = $this->getUniqueKey();
        foreach ($uk as $f) {
            if (!array_key_exists($f['name'], $data))
                throw new DatabaseException('INVK', 'update data incomplete; primary key is needed');
            $klist[] = $f['name'];
        }

        $qset = '';
        foreach ($data as $f => $v) {
            if (in_array($f, $klist))
                continue;

            if ($qset === '') {
                $qset = '`' . $f . '`=?';
            } else {
                $qset .= ', `' . $f . '`=?';
            }
            if ($this->_md[$f]['type'] === 10) {
                if ($v) {
                    $v = \cmc\config::dateToISO($v);
                }
            }
            $parms[] = $v;
        }
        $w = '';
        foreach ($uk as $f) {
            if ($w === '')
                $w = '`' . $f['name'] . '`=?';
            else
                $w .= ' AND `' . $f['name'] . '`=?';
        }
        $text = 'update ' . $this->_table . ' set ' . $qset . ' where ' . $w;

        if ($text != $this->_update_text || !$this->_db->validstmt($this->_update)) {
            $stmt = $this->_db->prepareNativeStatement($text);
            if ($stmt) {
                $this->_update = $stmt;
                $this->_update_text = $text;
            } else {
                throw new DatabaseException('UNEX');
            }
        }
        if ($this->_update) {
            foreach ($uk as $f) {
                if (!array_key_exists($f['name'], $data))
                    throw new DatabaseException('INVK', 'invalid unique key components (' . $f['name'] . ' not found)');
                $parms[] = $data[$f['name']];
            }
            $result = $this->setStmtParms($this->_update, $parms);
            if (!$this->_update->execute())
                throw new DatabaseException('UPDATE', 'error updating data');
            if ($this->_update->affected_rows !== 1) {
                $n = $this->_update->affected_rows;
                throw new DatabaseException($n==0?'UPDATE0':'UPDATE', 'row not found for update', $this->_update->affected_rows);
            }
        }
    }

    /**
     * retrives the primary or unique key composition
     * returns an associative array with 'name', 'flags', etc. fields.
     * @param boolean $bAll if false it ignores auto increment fields (for insert)
     * @return array
     * @throws DatabaseException
     */
    public function getUniqueKey($bAll = true) {
        $this->prepmeta();
        if (!$this->_md)
            throw new DatabaseException('UNEX', 'Metadata not available');
        // first searh PK
        $result = array();
        foreach ($this->_md as $f) {
            if (($f['flags'] & self::MYSQL_PRI_KEY_FLAG) === self::MYSQL_PRI_KEY_FLAG && (($bAll) || (($f['flags'] & self::MYSQL_AUTO_INCREMENT_FLAG) === 0)))
                $result[] = $f;
        }
        if (!$result) {
            foreach ($this->_md as $f) {
                if (($f['flags'] & self::MYSQL_PRI_KEY_FLAG) === self::MYSQL_UNIQUE_KEY_FLAG && (($bAll) || (($f['flags'] & self::MYSQL_AUTO_INCREMENT_FLAG) === 0)))
                    $result[] = $f;
            }
        }
        return $result;
    }

    /**
     * closes the table
     */
    public function close() {        
        if ($this->_db->validstmt($this->_select, true))
            $this->_select->close();
        $this->_select = null;
    }

    /**
     * ensuses the private metadata is available
     */
    private function prepmeta() {
        if ($this->_md)
            return;
        $this->prepselect();
    }

    /**
     * execute part of a select prepared statement
     * - gets the metadata (if first execute() success)
     * - rebinds the fields to $this->currentrow
     * @param \mysqli_stmt $stmt
     * @return boolean
     */
    private function select_execute($stmt) {
        if ($stmt->execute()) {
            $this->_currentrow = array();
            if (!$this->_md) {
                $this->_md = array();
                $md = $stmt->result_metadata();
                while ($field = $md->fetch_field()) {
                    $this->_md[$field->name] = array('name' => $field->name,
                        'type' => $field->type, 'length' => $field->length,
                        'decimals' => $field->decimals, 'max_length' => $field->max_length,
                        'flags' => $field->flags);
                }
            }
            $bind = array();
            foreach ($this->_md as $field) {
                $bind[] = &$this->_currentrow[$field['name']];
            }
            if (call_user_func_array(array($stmt, 'bind_result'), $bind)) {
                return true;
            }
        }
    }

    /**
     * prepares the 'select' statement with ordering and filtering status
     * @return mixed
     */
    private function prepselect() {
        $cnt = 0;
        $filter = array();

        $text = "select * from `$this->_table`";
        if ($this->_filter) {
            $text .= ' where ';
            $w = '';
            foreach ($this->_filter as $f => $v) {
                if ($w == '')
                    $w = '`' . $f . '` = ?';
                else
                    $w .= ' and `' . $f . '` = ?';
                $filter[] = $v;
            }
            $text .= $w;
        }
        if ($this->_sort) {
            $text.=' order by ';
            if (is_string($this->_sort))
                $text .= '`' . $this->_sort . '`';
            else {
                foreach ($this->_sort as $f) {
                    if ($cnt == 0)
                        $text .= "`$f`";
                    else
                        $text .= ", `$f`";
                    $cnt++;
                }
            }
        }

        if ($this->_select_text == $text && $this->_db->validstmt($this->_select) && !$this->_filterchanged)
            return $this->_select;

        if ($this->_select_text != $text || !$this->_db->validstmt($this->_select)) {
            try {
                $stmt = $this->_db->prepareNativeStatement($text);
                $this->_select_text = $text;
                $this->_select = null;
                $this->_filterchanged = false;
                if ($this->_filter)
                    $this->setStmtParms($stmt, $filter);
                if ($this->select_execute($stmt))
                    $this->_select = $stmt;
                if ($this->_position != -1) {
                    $this->_select->store_result();
                    $this->_select->data_seek($this->_position + 1);
                }                
            } catch (\cmc\db\DatabaseException $e) {
                if ($e->getNatCode() == 1146) // table not found
                    throw new \cmc\db\DatabaseException('NTAB', $this->_table);
                else
                    throw $e;
            }
        } else
            $stmt = $this->_select;

        return $this->_select;
    }

    /**
     * returns the current record data
     * @return null|array
     */
    public function current() {
        if (!$this->prepselect())
            return;
        if ($this->_position == -1)
            return null;
        return $this->_currentrow;
    }

    /**
     * returns a value representing the current position. 
     * Warning: this is not a consistent value, if underlying data changes or if the filtering/ordering options change
     * @return integer
     */
    public function key() {
        return $this->_position;
    }

    /**
     * changes the format of fields after fetch (dates,...)
     */
    private function afterfetch() {
        if (!$this->_md || !$this->_currentrow)
            return;
        foreach ($this->_md as $f) {
            $name = $f['name'];
            if ($f['type'] === 10 && array_key_exists($name, $this->_currentrow)) {
                $this->_currentrow[$name] = \cmc\config::dateFromISO($this->_currentrow[$name]);
            }              
        }
    }

    /**
     * reads next records and returns the data in an associative array
     * @return array
     */
    public function next() {
        if (!$this->prepselect())
            return;
        if (!$this->_select->fetch()) {
            $this->_currentrow = null;
        } else
            $this->afterfetch();
        if ($this->_currentrow)
            $this->_position ++;
        return $this->_currentrow;
    }

    /**
     * reset position before first record
     */
    public function rewind() {
        if ($this->_position==-1)
            return $this->next();
        
        if (!$this->prepselect())
            return;
        $this->_position = -1;
        $this->_currentrow = null;
        if ($this->select_execute($this->_select)) {
            if (!$this->_select->fetch()) {
                $this->_currentrow = null;
            } else
                $this->afterfetch();
            if ($this->_currentrow)
                $this->_position ++;
            return $this->_currentrow;
        }
    }

    /**
     * 
     */
    public function getRowCopy() {
        if (!$this->_currentrow || $this->_position==-1)
            return null;
        $result = array();
        foreach ($this->_currentrow as $k=>$v) {
            $result[$k] = $v;
        }    
        return $result;
    }
    
    /**
     * returns if there is a valid current record
     * @return boolean
     */
    public function valid() {
        return ($this->_position !== -1) && ($this->_currentrow != null);
    }

    /**
     * defines a filter 'equal' criteria for the table
     * @param array $data
     */
    public function setFilter($data) {
        $this->_filter = $data;
        $this->_filterchanged = true;
        $this->_position = -1;
    }

    /**
     * cancel a previously defined filter
     */
    public function cancelFilter() {
        $this->_filter = null;
        $this->_filterchanged = true;
        $this->_position = -1;
    }

    /**
     * gets the value of the primary key
     * Returns an array or a scalar
     * @returns mixed
     */
    function getPrimaryKey() {
        if ($this->_currentrow == null)
            return null;
        $result = array();
        $uk = $this->getUniqueKey();
        foreach ($uk as $f) {
            if (!array_key_exists($f['name'], $this->_currentrow))
                throw new DatabaseException('UNEX', 'inconsistent data');
            $result[$f['name']] = $this->_currentrow[$f['name']];
        }
        if (count($result) > 1)
            return $result;
        else
            return array_values($result)[0];
    }

    /**
     * seeks using a primary key value
     * @param $key primary key value (scalar or array)
     * @returns array|null
     */
    function seekPrimaryKey($key) {
        $parms = array();
        $uk = $this->getUniqueKey();
        if (is_array($key)) {
            foreach ($uk as $f) {
                if (!array_key_exists($f['name'], $key))
                    throw new DatabaseException('INVK', 'seek data incomplete; primary key is needed');
            }
        } else {
            if (count($uk) !== 1)
                throw new DatabaseException('INVK', 'seek data incomplete; primary key is needed');
            $key = array($uk[0]['name'] => $key);
        }

        $w = '';
        foreach ($key as $f => $v) {
            if ($w === '') {
                $w = '`' . $f . '`=?';
            } else {
                $w .= ' and `' . $f . '`=?';
            }
            $parms[] = $v;
        }
        $text = 'select * from ' . $this->_table . ' where ' . $w;

        if ($text != $this->_seek_text || !$this->_db->validstmt($this->_seek)) {
            $stmt = $this->_db->prepareNativeStatement($text);
            if ($stmt) {
                $this->_seek = $stmt;
                $this->_seek_text = $text;
            } else {
                throw new DatabaseException('UNEX');
            }
        }
        $this->_currentrow = null;
        $this->_position = -1;
        if ($this->_seek) {
            if ($this->setStmtParms($this->_seek, $parms)) {
                if ($this->select_execute($this->_seek)) {
                    if (!$this->_seek->fetch()) {
                        $this->_currentrow = null;
                    } else
                        $this->afterfetch();
                }
            }
        }
        return $this->_currentrow;
    }

    /**
     * 
     */
    public function getItems() {
        $this->prepmeta();

        return $this->_md;
    }

    public function getKeyItems() {
        return $this->getUniqueKey();
    }

}

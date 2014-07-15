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

require_once('database_mysqli.php');

use cmc\db\dataquery;

/**
 * mysqli datasource implementation
 *
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
class dataquery_mysqli extends dataquery  {
    private $_stmt;private $_sqlText;
    /**
     *
     * @var boolean true if execute is needed
     */
    protected $_bNeedExec;
   

   
    /**
     * creates a new datasource
     * 
     * @param \cmc\db\mysqli\database_mysqli $db
     * @param type $stmt
     * @return \cmc\db\mysqli\datasource_mysqli
     */
    static public function create(database_mysqli $db, $stmt) {
        $new_obj = new dataquery_mysqli();
        $new_obj->_db = $db;
        $new_obj->_stmt = $stmt;        
        $new_obj->_bNeedExec = true;
        return $new_obj;
    }
    /**
     * closes current statement
     */
    public function close()    {
        $this->_stmt->reset();
        $this->_bNeedExec = true;
    }
    /**
     * ensures executed state
     * 
     * @param boolean re-executes the statement even if already active
     * @return boolean
     * @throws DatabaseException_mysqli
     */
    public function execute($bForce=false) {
        $result = true;
        if (!$this->_stmt)
            throw new DatabaseException_mysqli($this, 'BADCTX', 'wrong context: statement not valid');      
        if ($this->_bNeedExec || $bForce) {
            $this->executionBegin();
            $result = $this->_stmt->execute();
            $this->executionLog();
            $this->_bNeedExec = false;
        }
        return $result;
    }
    /**
     * updates parameter bindings
     * 
     * @param type $params
     * @return type
     */
    public function setparams($params) {
        $this->_bNeedExec = true;        
                
        $ar = array();
        $t = '';$ar[0]='';
        for($i=0;$i<count($params);$i++){
            $p = $params[$i];
            if (is_string($p)) $t .= 's';
                else if (is_long($p)) $t .= 'i';
                else if (is_numeric($p)) $t .= 'd';
                else $t .= 'b';
            $ar[$i+1] = &$params[$i];
        }
        $ar[0] = $t;
        
        $result = call_user_func_array(array($this->_stmt, 'bind_param'),$ar);   
        return $result;
    }
    /**
     * fetches first or next record
     * 
     * executes the statement if needed
     * returns false in case of error or end of data
     * @return array|boolean
     */
    public function fetch_assoc() {
        if (!$this->_stmt)
            return false;
        if (!$this->_currentrow) {
           $this->_position = -1; 
           if (!$this->execute())
               return false;
            // we are using databinding
            $this->_currentrow = array();            
            $md = $this->_stmt->result_metadata();            
            $bind = array();
            while($field = $md->fetch_field()) {                
               $bind[] = &$this->_currentrow[$field->name];
            }
            if (!call_user_func_array(array($this->_stmt, 'bind_result'), $bind)) {
                $this->_currentrow = null;
                return false;
            }
        }
        if (!$this->_stmt->fetch()) {
            $this->_currentrow = null;
            return false;
        }
        if ($this->_currentrow)
            $this->_position ++;
        return $this->_currentrow;
    }

    /**
     * information only: statement text
     * @param string $text
     */
    public function setSqlText($text) {
        $this->_sqlText = $text;
    }
    
    private function executionBegin() {
        if (!$this->dataenv()->benchMarking())
            return;
        $this->dataenv()->executionBegin();
    }
            
    private function executionLog() {
        if (!$this->dataenv()->benchMarking())
            return;
        $this->dataenv()->executionLog($this->_sqlText, $this->_stmt->affected_rows);
    }

}


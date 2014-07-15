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
namespace cmc\error;

use cmc\core\translation;
use cmc\core\request;

/**
 * tools for handling fatal errors detected in the framework
 * 
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
class fatalErrors {
    static $_continue = false;
    
    /**
     * Configuration error: no valid view found for /view/ Hint: check default view in config.php
     */
    const noValidView=1;
    
    /**
     * special translation method, in case of session object is not ready
     * @param type $sess
     * @param type $text
     * @return type
     */
    private static function translate($sess, $text, $args=null) {
        $trans = null;
        if ($sess != null) {
            $trans = $sess->getTranslation();
        } 
        if ($trans===null) {
            // no session: get language from client
            $lang=request::getAcceptPrimaryLang();
            $trans = new translation($lang);
        }
        return $trans->fmtText($text, $args);
    }
    static function callstack($e, $popcnt=1) {
            $trace = $e->getTraceAsString();
            $exp = preg_split('/[\n]/', $trace);
            $result = '';
            $popcnt =0;
            // renumbers and remove root path
            foreach($exp as $line) {
                if (preg_match('/^#([0-9]+) (.*)$/', $line, $items)===0) {
                    $result.=$line."\n";
                } else {
                    if ($items[1]<$popcnt)
                        continue;
                    $items[2] = \str_replace(request::rootphyspath().'/', '', $items[2]);
                    $result.='#'.($items[1]-$popcnt).' '.$items[2]."\n";
                }
            }
            
            return $result;
    }    
    /**
     * triggers a fatal error
     * 
     * the error is expected to be present in translation strings with
     * fatal$errorId key. The error parameters are referenced by %1, %2 and so on
     * in the error text
     * @param \cmc\sess $sess
     * @param string|integer $errorId
     * @param $stack if >0 the call stack is shown, and pops the specified number at top
     * @param string ... optional parameters
     */
    static function trigger($sess, $errorId, $stack=1) {                
        $e = new \Exception();
        $args = func_get_args();
        array_splice($args, 2, 0, array($e));
        return call_user_func_array(array(__CLASS__, 'int_trigger'), $args);
    }
    
    static private function int_trigger($sess, $errorId, $e, $stack=-1) {
        $args = func_get_args();                
        array_shift($args);array_shift($args);array_shift($args);array_shift($args); // strip 4 regular args
        $body = self::translate($sess, 'fatalbody');

        $text = self::translate($sess, 'fatal'.$errorId, $args);
     
        if ($stack!==-1) $text .= str_replace('%1', self::callstack ($e, $stack), self::translate($sess, 'fatalcontext'));
        //ob_clean();
        //TODO: check ob status
        echo "<!DOCTYPE html>".str_replace('--error--', $text,  $body);
        
        //var_dump($repl, $repl_str);
        http_response_code(500);
        //if (session_status()==PHP_SESSION_ACTIVE) session_destroy();
        if (!self::$_continue)
            exit();
    }
    
    /**
     * Exception as fatal error...
     */
    static function triggerException($sess, $e) {
        $cls = implode(array_slice(explode('\\', get_class($e)), -1));
       
        // try to get localized message
        if (is_a($e, \cmc\db\DatabaseException::className)) {
            $cls = 'DatabaseException';
            $code = $e->getErrCode();
            $natcode = $e->getNatCode();
            $natmsg = $e->getNatMsg();
            
            // we have a message code "$code" error
            if (self::translate($sess, 'fatal'.$cls.$code) !== 'fatal'.$cls.$code)
                    $cls = $cls.$code;
            
            self::int_trigger($sess, $cls, $e, 0, $e->getMessage(), $code, $natcode, $natmsg);
        }
        else
            self::int_trigger($sess, $cls, $e);
    }
    /**
     * defines whether to continue or exit() when a fatal error is trigered 
     * useful when exit() is already in motion
     */
    static function setContinue() {
        self::$_continue = true;
    }
}


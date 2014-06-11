<?php
namespace cmc\db;

class DatabaseException extends \Exception{
    const className = __CLASS__;    
    protected $_errcode;
    protected $_natcode;
    protected $_natmsg;
    
    public function getErrCode() {
        return $this->_errcode;
    }
    public function getNatCode() {
        return $this->_natcode;
    }
    public function getNatMsg() {
        return $this->_natmsg;
    }
    /**
     * 
     * @param string $errcode code that corresponds to the message
     * @param type $natcode native error code
     * @param type $natmsg native error message
     * @param type $message message from source
     * @param type $code error location
     * @param type $previous
     */
    public function __construct($errcode, $message=null, $natcode=null, $natmsg=null, $code=null, $previous=null) {        
        $this->_natcode = $natcode;
        $this->_natmsg = $natmsg;
        $this->_errcode = $errcode;
        
        if ($natmsg) {
            if ($message) 
                $message .=': '.$natmsg;
            else
                $message = $natmsg;
        }
        parent::__construct($message, $code, $previous);
    }    
    
}

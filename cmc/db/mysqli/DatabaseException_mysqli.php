<?php
namespace cmc\db\mysqli;

use cmc\db\DatabaseException;


class DatabaseException_mysqli extends DatabaseException {

    public function __construct($db, $errcode, $message=null, $code=null, $previous=null) {
        $natmsg = null;
        $natcode = null;
        if ($db) {
            $natmsg = $db->getLastError();
            $natcode = $db->getLastErrno();
        }
        parent::__construct($errcode, $message, $natcode, $natmsg, $code, $previous);
    }
    
}

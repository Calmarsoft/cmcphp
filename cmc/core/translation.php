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
namespace cmc\core;

use cmc\config;

require_once('php/config.php');

/**
 * Handles direct translation data
 *
 * @copyright     Copyright (c) Calmarsoft company (FRANCE) (http://calmarsoft.com)
 * @link          http://cmc.calmarsoft.com CMC Project
 * @license       http://www.gnu.org/licenses/ GNU General Public License version 3
 * @version       0.9
 */
class translation {
    private $_lang;
    /**
     *
     * @var array some vital static internal translations
     */
    private $_translation = array( 
        'en' => array('Language' => 'Language', '.lc' => array('en.UTF-8', 'en_US.UTF-8'),
                      'fatal1' => 'Configuration error: no valid view found for <b>%1</b><br>Hint: check default view in config.php',
                      'fatalmysql_connect' => 'MySQL database connection error: %1',
                      'fatalnoview' => 'Unable to find a valid display for request %1',
                      'fatalruntime' => 'Execution error<br>Error code:%1<br>Error text:%2<br>In %3:%4',
                      'fatalnoscriptname1' => 'Neither $_SERVER["SCRIPT_FILENAME"] or $_SERVER["SCRIPT_FILENAME_ORIG"]  is available',
                      'fatalnoscriptname2' => 'Neither $_SERVER["SCRIPT_NAME"] or $_SERVER["SCRIPT_NAME_ORIG"]  is available',
                      'fatalcallback_wrongstate' => 'A widget/frame server callback cannot be defined in this context. Try moving the callbackInitialization in dynframe::viewInitialUpdate',
                      'fatalcontext' => '<br><small>Context:<br>%1</small>',            
                      'fatalDatabaseException' => '%1<br>uncaught database exception with code %2<br>Database layer returned error code %3, with message "%4"',
                      'fatalDatabaseExceptionPREP' => 'prepare failed<br>A database statement prepare failed with error code %3 and message "%4"',
                      'fatalDatabaseExceptionNTAB' => 'the table `%1` was not found in the database',
                      'fatalDatabaseExceptionUPDATE' => 'the update did not alter the expected number of rows (%3 updated)',
                      'fatalDatabaseExceptionUPDATE0' => 'the update altered no rows',
                      'fatalbody' => <<<EOT
                        <html><head><title>CMC Framework - Fatal error!</title>
                            <style>.cmc-logo { position: relative;left:25px;top:15px;} h1 { position:relative;left:200px;top:-50px;}
                                   .cmc-errorbody { position:relative;top:-20px;left:20px;}
                                   .cmc-error { margin-left:15px;} </style>
                            </head>
                            <body>
                                <img src="http://www.calmarsoft.com/img/LogoCalmarSoft-s.png" class="cmc-logo">
                                <h1>CMC Framework - Fatal error!</h1>
                                <div class="cmc-errorbody">
                                <p><b>A fatal error just occured...</b><br>
                                   This is probably due to a wrong installation or configuration</p>
                                <p>Error information:<br>
                                <div class="cmc-error">
                                --error--
                                </div>
                                </p>
                                </div>
                            </body>
                        </html>
EOT
                     ),
        'fr' => array('Language' => 'Langue', '.lc' => array('fr.UTF-8', 'fr_FR.UTF-8'),
                      'fatal1'=> 'Erreur de configuration: aucune vue valide pour <b>%1</b><br>Indice: vérifier la vue par défaut dans config.php',
                      'fatalmysql_connect' => 'Erreur de connexion à la base de données MySQL: %1',
                      'fatalnoview' => 'Echec de résolution de l\'affichage pour la requête %1',
                      'fatalruntime' => 'Erreur d\'exécution<br>Code d\'erreur:%1<br>Texte d\'erreur: %2<br>Emplacement: %3:%4',
                      'fatalnoscriptname1' => 'Variable $_SERVER["SCRIPT_FILENAME"] ou $_SERVER["SCRIPT_FILENAME_ORIG"]  non disponible',
                      'fatalnoscriptname2' => 'Variable $_SERVER["SCRIPT_NAME"] ou $_SERVER["SCRIPT_NAME_ORIG"]  non disponible',
                      'fatalcallback_wrongstate' => 'Un callback serveur ne peut être défini à ce stade. Essayer de déplacer le code d\'appel au niveau de dynframe::viewInitialUpdate',
                      'fatalcontext' => '<br><small>Contexte:<br><pre>%1</pre></small>',
                      'fatalDatabaseException' => '%1<br>exception de base de donnée non gérée de code %2<br>La base de donnée a retourné le code d\'erreur %3, et le message "%4"',
                      'fatalDatabaseExceptionPREP' => 'échec de requête failed<br>Une requête a échoué avec le code d\'erreur %3 et le message "%4"',
                      'fatalDatabaseExceptionNTAB' => 'la table `%1` n\'a pas été trouvée dans la base de données',
                      'fatalDatabaseExceptionUPDATE' => 'la mise à jour n\'a pas affecté le nombre attendu d\'enregistrements (%3 enregistrements modifiés)',
                      'fatalDatabaseExceptionUPDATE0' => 'la mise à jour n\'a pas affecté aucun enregistrement',
                      'fatalbody' => <<<EOT
                        <html><head><title>CMC Framework - Erreur fatale!</title>
                            <style>.cmc-logo { position: relative;left:25px;top:15px;} h1 { position:relative;left:200px;top:-50px;}
                                   .cmc-errorbody { position:relative;top:-20px;left:20px;}
                                   .cmc-error { margin-left:15px;} </style>
                            </head>
                            <body>
                                <img src="http://www.calmarsoft.com/img/LogoCalmarSoft-s.png" class="cmc-logo">
                                <h1>CMC Framework - Erreur fatale!</h1>
                                <div class="cmc-errorbody">
                                <p><b>Une erreur fatale vient de se produire...</b><br>
                                   Cela est probablement dû à une mauvaise installation ou configuration</p>
                                <p>Informations sur l'erreur:<br>
                                <div class="cmc-error">
                                --error--
                                </div>
                                </p>
                                </div>
                            </body>
                        </html>
EOT
        ,)
    );
    private $_localization;
    
    function addData($data) {
        $this->_translation = array_replace_recursive($this->_translation, $data);
        $this->updateLocalization();
    }
    /**
     * finds a localized string from a key
     * 
     * returns the input key if no entry was found 
     * @param string the item key
     * @return string the localized string
     */
    function getText($key)
    {
       if (array_key_exists($key, $this->_localization))
            $result = $this->_localization[$key];
       else if (array_key_exists($key, $this->_translation[config::DFT_translation]))
            $result = $this->_translation[config::DFT_translation][$key];
       else
            $result = $key;
       return $result;
    }
    /**
     * returns the current language
     * 
     * @return string the current language
     */
    function getLangName()
    {
        return $this->_lang;
    }
    /**
     * updates the PHP locale from the current language
     */
    function updateLocale() {
        if (array_key_exists('.lc', $this->_localization)) {
            setlocale(LC_ALL, $this->_localization['.lc']);
        }        
    }
    /**
     * @ignore
     */
    private function updateLocalization()
    {
        if (array_key_exists($this->_lang, $this->_translation))
            $this->_localization = $this->_translation[$this->_lang];
        else
            $this->_localization = $this->_translation[config::DFT_translation];
    }
    /**
     * initializes language and locale object from the givent language string
     * @param string language code
     */
    function __construct($lang)
    {
        $this->_lang = $lang;
        $this->updateLocalization();
    }
}

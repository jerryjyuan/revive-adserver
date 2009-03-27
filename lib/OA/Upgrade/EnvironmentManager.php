<?php

/*
+---------------------------------------------------------------------------+
| OpenX v${RELEASE_MAJOR_MINOR}                                                                |
| =======${RELEASE_MAJOR_MINOR_DOUBLE_UNDERLINE}                                                                |
|                                                                           |
| Copyright (c) 2003-2009 OpenX Limited                                     |
| For contact details, see: http://www.openx.org/                           |
|                                                                           |
| This program is free software; you can redistribute it and/or modify      |
| it under the terms of the GNU General Public License as published by      |
| the Free Software Foundation; either version 2 of the License, or         |
| (at your option) any later version.                                       |
|                                                                           |
| This program is distributed in the hope that it will be useful,           |
| but WITHOUT ANY WARRANTY; without even the implied warranty of            |
| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             |
| GNU General Public License for more details.                              |
|                                                                           |
| You should have received a copy of the GNU General Public License         |
| along with this program; if not, write to the Free Software               |
| Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA |
+---------------------------------------------------------------------------+
$Id$
*/

/**
 * OpenXUpgrade Class
 *
 * @author     Monique Szpak <monique.szpak@openx.org>
 *
 */
define('OA_ENV_ERROR_PHP_NOERROR',                    1);
define('OA_ENV_ERROR_PHP_VERSION',                   -1);
define('OA_ENV_ERROR_PHP_MEMORY',                    -2);
define('OA_ENV_ERROR_PHP_SAFEMODE',                  -3);
define('OA_ENV_ERROR_PHP_MAGICQ',                    -4);
define('OA_ENV_ERROR_PHP_TIMEZONE',                  -5);
define('OA_ENV_ERROR_PHP_UPLOADS',                   -6);
define('OA_ENV_ERROR_PHP_ARGC',                      -7);
define('OA_ENV_ERROR_PHP_XML',                       -8);
define('OA_ENV_ERROR_PHP_PCRE',                      -9);
define('OA_ENV_ERROR_PHP_ZLIB',                     -10);
define('OA_ENV_ERROR_PHP_MYSQL',                    -11);
define('OA_ENV_ERROR_PHP_TIMEOUT',                  -12);
define('OA_ENV_WARNING_MEMORY',                     -13);

require_once MAX_PATH.'/lib/OA/DB.php';
require_once MAX_PATH . '/lib/OA/Admin/Settings.php';

define('OA_MEMORY_UNLIMITED', 'Unlimited');

class OA_Environment_Manager
{

    var $aInfo = array();

    function OA_Environment_Manager()
    {
        $conf = $GLOBALS['_MAX']['CONF'];
        global $installing;
        if (!$installing)
        {
            $this->aInfo['PERMS']['expected'][] = $this->buildFilePermArrayItem(MAX_PATH.'/var');
        }
        else
        {
            $this->aInfo['PERMS']['expected'][] = $this->buildFilePermArrayItem(MAX_PATH.'/var',true);
        }
        $this->aInfo['PERMS']['expected'][] = $this->buildFilePermArrayItem(MAX_PATH.'/var/cache', true);
        $this->aInfo['PERMS']['expected'][] = $this->buildFilePermArrayItem(MAX_PATH.'/var/plugins', true);
        $this->aInfo['PERMS']['expected'][] = $this->buildFilePermArrayItem(MAX_PATH.'/var/templates_compiled', true);
        $this->aInfo['PERMS']['expected'][] = $this->buildFilePermArrayItem(MAX_PATH.'/plugins', true);
        $this->aInfo['PERMS']['expected'][] = $this->buildFilePermArrayItem(MAX_PATH.'/www/admin/plugins', true);

        // if CONF file hasn't been created yet, use the default images folder
        if (!empty($conf['store']['webDir']))
        {
            $this->aInfo['PERMS']['expected'][] = $this->buildFilePermArrayItem($conf['store']['webDir']);
        }
        else
        {
            $this->aInfo['PERMS']['expected'][] = $this->buildFilePermArrayItem(MAX_PATH.'/www/images');
        }

        if (!empty($conf['delivery']['cachePath']))
        {
            $this->aInfo['PERMS']['expected'][] = $this->buildFilePermArrayItem($conf['delivery']['cachePath']);
        }

        // Fix directory separator
        if (DIRECTORY_SEPARATOR != '/')
        {
            foreach ($this->aInfo['PERMS']['expected'] as $idx => $aValue)
            {
                $this->aInfo['PERMS']['expected'][$idx]['file'] = str_replace('/', DIRECTORY_SEPARATOR, $aValue['file']);
            }
        }

        $this->aInfo['PHP']['actual']     = array();
        $this->aInfo['PERMS']['actual']   = array();
        $this->aInfo['FILES']['actual']   = array();

        $this->aInfo['PHP']['expected']['version']              = '5.1.4';
        $this->aInfo['PHP']['expected']['magic_quotes_runtime'] = '0';
        $this->aInfo['PHP']['expected']['safe_mode']            = '0';
        $this->aInfo['PHP']['expected']['file_uploads']         = '1';
        $this->aInfo['PHP']['expected']['register_argc_argv']   = '1';
        $this->aInfo['PHP']['expected']['pcre']                 = true;
        $this->aInfo['PHP']['expected']['xml']                  = true;
        $this->aInfo['PHP']['expected']['zlib']                 = true;
        $this->aInfo['PHP']['expected']['mysql']                = true;
        $this->aInfo['PHP']['expected']['timeout']              = false;
        $this->aInfo['COOKIES']['expected']['enabled']          = true;

        $this->aInfo['FILES']['expected'] = array();
    }

    function checkSystem()
    {
        $this->getAllInfo();
        $this->checkCritical();
        return $this->aInfo;
    }

    function getAllInfo()
    {
        $this->aInfo['PHP']['actual']     = $this->getPHPInfo();
        $this->aInfo['PERMS']['actual']   = $this->getFilePermissionErrors();
        $this->aInfo['FILES']['actual']   = $this->getFileIntegInfo();
        $this->aInfo['COOKIES']['actual'] = $this->getCookieInfo();
        return $this->aInfo;
    }

    function getCookieInfo()
    {
        $aResult['enabled'] = false;
        $this->aInfo['COOKIES']['error']['enabled'] = $GLOBALS['strEnableCookies'];

        if (isset($_COOKIE['sessionID']) || isset($_COOKIE['oat']))
        {
            $aResult['enabled'] = true;
            unset($this->aInfo['COOKIES']['error']['enabled']);
        }
        return $aResult;
    }

    function getPHPInfo()
    {
        $aResult['version'] = phpversion();

        $aResult['memory_limit'] = getMemorySizeInBytes();
        if ($aResult['memory_limit'] == -1) {
            $aResult['memory_limit'] = OA_MEMORY_UNLIMITED;
        }
        $aResult['magic_quotes_runtime'] = get_magic_quotes_runtime();
        $aResult['safe_mode']            = ini_get('safe_mode');
        $aResult['date.timezone']        = (ini_get('date.timezone') ? ini_get('date.timezone') : getenv('TZ'));
        $aResult['register_argc_argv']   = ini_get('register_argc_argv');
        $aResult['file_uploads']         = ini_get('file_uploads');
        $aResult['xml']                  = extension_loaded('xml');
        $aResult['pcre']                 = extension_loaded('pcre');
        $aResult['zlib']                 = extension_loaded('zlib');
        // some users have the mysqli extension and not the mysql, some have both
        // only a problem if they don't have mysql extension (until we handle mysqli)
        $aResult['mysql']                = extension_loaded('mysql');
        $aResult['pgsql']                = extension_loaded('pgsql');

        // set_time_limit is used throughout maintenance to increase the timeout for scripts
        // if user has disabled the set_time_limit function
        // their scripts will run in ini_get('max_execution_time')
        // if ini_get('max_execution_time') > 0 or < 300 they may have a problem
        $aResult['timeout']              = false;
        $aDisabled = explode(',',ini_get('disable_functions'));
        $timeout = ini_get('max_execution_time');
        if (in_array('set_time_limit',$aDisabled) && (($timeout >0) && ($timeout <300)) )
        {
            $aResult['timeout']          = $timeout;
        }

        return $aResult;
    }

    function getFileIntegInfo()
    {
        return false;
    }

    function buildFilePermArrayItem($file, $recurse=false, $result='OK', $error = false, $string='')
    {
        return array(
                    'file'      => $file,
                    'recurse'   => $recurse,
                    'result'    => $result,
                    'error'     => $error,
                    'string'    => $string,
                    );
    }

    function checkFilePermission($file, $recurse)
    {
        if ( (!file_exists($file)) || (!$this->isWritable($file)) )
        {
            return false;
        }
        if ($recurse)
        {
            $dh = @opendir($file);
            if ($dh)
            {
                while (false !== ($f = readdir($dh)))
                {
                    if ( ($f == '.') || ($f == '..') || ($f == '.svn') )
                    {
                        continue;
                    }
                    if (!$this->checkFilePermission($file.'/'.$f, $recurse))
                    {
                        return false;
                    }
                }
                closedir($dh);
            }
        }
        return true;
    }

    /**
     * Check access to an array of required files/folders
     *
     * @return array of error messages
     */
    function getFilePermissionErrors()
    {
        $aErrors = array();

        // Test that all of the required files/directories can
        // be written to by the webserver
        foreach ($this->aInfo['PERMS']['expected'] as $idx => $aFile)
        {
            if (empty($aFile['file']))
            {
                continue;
            }
            if (!$this->checkFilePermission($aFile['file'], $aFile['recurse']))
            {
                $aFile['result'] = 'NOT writeable';
                $aFile['error']  = true;
                $aFile['string'] = ($aFile['recurse'] ? 'strErrorFixPermissionsRCommand' : 'strErrorFixPermissionsRCommand');
            }
            $aErrors[] = $aFile;
        }

        // If upgrading, must also be able to write to:
        //  - The configuration file(s) (if the web hosts is the same as
        //    it was, the user cannot have the config file locked, as
        //    new items might need to be merged into the config file(s)).
        //  - The default configuration file, if it exists, needs to
        //    be able to be written to by the web server also.
        //  - The INSTALLED file needs to be able to be "touched",
        //    as this is done for all upgrades/installs.


        // IS ANY OF THIS NECESSARY NOW THAT WE EXPECT VAR RECURSIVELY WRITEABLE?
        /*if (OA_INSTALLATION_STATUS != OA_INSTALLATION_STATUS_INSTALLED) {
            $configFile = MAX_PATH . '/var/' . OX_getHostName() . '.conf.php';
            if (file_exists($configFile)) {
                // Test if *this* config file can be written to, as the
                // installer might need to do this later
                if (!OA_Admin_Settings::isConfigWritable($configFile)) {
                    $aErrors[] = $this->buildFilePermArrayItem($configFile, false, 'NOT writeable', true, 'strErrorFixPermissionsCommand');
                } else {
                    $this->aInfo['PERMS']['expected'][] = $this->buildFilePermArrayItem($configFile);
                }
                // Test if this configuration file is the real one or not
                // by looking for a realConfig value
                $aUpgradeConfig = @parse_ini_file($configFile, true);
                if (!empty($aUpgradeConfig['realConfig'])) {
                    // This is not the real configuration file! Use
                    // the one suggested instead
                    $configFile = MAX_PATH . '/var/' . $aUpgradeConfig['realConfig'] . '.conf.php';
                    $aUpgradeConfig = @parse_ini_file($configFile, true);
                }
                // Now inspect the possible configuration file(s) that
                // may exist, based on the webpaths in use
                $aPossibleConfigFiles = array();
                if (!empty($aUpgradeConfig['webpath']['admin'])) {
                    $url = @parse_url('http://' . $aUpgradeConfig['webpath']['admin']);
                    $aPossibleConfigFiles[] = MAX_PATH . '/var/' . $url['host']  . '.conf.php';
                }
                if (!empty($aUpgradeConfig['webpath']['delivery'])) {
                    $url = @parse_url('http://' . $aUpgradeConfig['webpath']['delivery']);
                    $aPossibleConfigFiles[] = MAX_PATH . '/var/' . $url['host']  . '.conf.php';
                }
                if (!empty($aUpgradeConfig['webpath']['deliverySSL'])) {
                    $url = @parse_url('http://' . $aUpgradeConfig['webpath']['deliverySSL']);
                    $aPossibleConfigFiles[] = MAX_PATH . '/var/' . $url['host']  . '.conf.php';
                }
                $aPossibleConfigFiles = array_unique($aPossibleConfigFiles);
                if (!empty($aPossibleConfigFiles)) {
                    foreach ($aPossibleConfigFiles as $configFile) {
                        if (!OA_Admin_Settings::isConfigWritable($configFile)) {
                            $aErrors[] = $this->buildFilePermArrayItem($configFile, false, 'NOT writeable', true, 'strErrorFixPermissionsCommand');
                        } else {
                            $this->aInfo['PERMS']['expected'][] = $this->buildFilePermArrayItem($configFile);
                        }
                    }
                }
            }
            // Test the default.conf.php file
            $configFile = MAX_PATH . '/var/default.conf.php';
            if (file_exists($configFile)) {
                if (!OA_Admin_Settings::isConfigWritable($configFile)) {
                    $aErrors[] = $this->buildFilePermArrayItem($configFile, false, 'NOT writeable', true, 'strErrorFixPermissionsCommand');
                } else {
                    $this->aInfo['PERMS']['expected'][] = $this->buildFilePermArrayItem($configFile);
                }
            }
            $installerFile = MAX_PATH . '/var/INSTALLED';
            if (file_exists($installerFile)) {
                if (!$this->isWritable($installerFile)) {
                    $aErrors[] = $this->buildFilePermArrayItem($installerFile, false, 'NOT writeable', true, 'strErrorFixPermissionsCommand');
                }
            }
        }*/

        return $aErrors;
    }

    function isWritable($file)
    {
        if (DIRECTORY_SEPARATOR == '\\')
        {
            // Windows hack - is_writable returns bogus results
            // see http://bugs.php.net/bug.php?id=27609
            if (@is_dir($file))
            {
                $file = preg_replace('/\\\\$/', '', $file).DIRECTORY_SEPARATOR.md5(uniqid('', true));
                $unlink = true;
            }
            else
            {
                $unlink = !file_exists($file);
            }
            if ($fp = @fopen($file, 'ab'))
            {
                @fclose($fp);
                if ($unlink)
                {
                    @unlink($file);
                }
                return true;
            }
            else
            {
                return false;
            }
        }
        return is_writable($file);
    }

    function checkCritical()
    {
        $this->_checkCriticalPHP();
        $this->_checkCriticalFilePermissions();
        $this->_checkCriticalFiles();
        return $this->aInfo;
    }

    /**
     * Check if amount of memory is enough for our application
     *
     * @return boolean  True if amount of memory is enough, else false
     */
    function checkMemory()
    {
        $memlim = $this->aInfo['PHP']['actual']['memory_limit'];
        $expected = getMinimumRequiredMemory();
        // Warn (not error) if the memory limit can't be increased
        if  (!$this->_checkMemoryCanBeSet())
        {
            $this->aInfo['PHP']['warning'][OA_ENV_WARNING_MEMORY] = "The <a href='http://php.net/ini.core#ini.memory-limit' target='_blank'>memory_limit</a> cannot be set by PHP, some parts of the product may not function correctly";
        }
        if ($memlim != OA_MEMORY_UNLIMITED && ($memlim > 0) && ($memlim < $expected))
        {
            return false;
        }
        return true;
    }

    /**
     * A private method to test the configuration of the user's PHP environment.#
     *
     * Tests the following values, and in the event of a fatal error or a
     * warning, the value set is listed below:
     *
     *  - The PHP version
     *      Sets: $this->aInfo['PHP']['warning'][OA_ENV_ERROR_PHP_VERSION]
     *
     *  - The PHP configuration's memory_limit value
     *      Sets: $this->aInfo['PHP']['error'][OA_ENV_ERROR_PHP_MEMORY]
     *
     *  - The PHP configuration's safe_mode value
     *      Sets: $this->aInfo['PHP']['error'][OA_ENV_ERROR_PHP_SAFEMODE]
     *
     *  - The PHP configuration's magic_quotes_runtime value
     *      Sets: $this->aInfo['PHP']['error'][OA_ENV_ERROR_PHP_MAGICQ]
     *
     *  - The PHP configuration's file_uploads value
     *      Sets: $this->aInfo['PHP']['error'][OA_ENV_ERROR_PHP_UPLOADS]
     *
     * Otherwise, if there are no errors or warnings, then $this->aInfo['PHP']['error']
     * is set to "false".
     *
     * @access private
     * @return void
     */
    function _checkCriticalPHP()
    {
        // Test the PHP version
        if (function_exists('version_compare'))
        {
            $result = version_compare(
                $this->aInfo['PHP']['actual']['version'],
                $this->aInfo['PHP']['expected']['version'],
                "<"
            );
            if ($result) {
                $result = OA_ENV_ERROR_PHP_VERSION;
            } else {
                $result = OA_ENV_ERROR_PHP_NOERROR;
            }
        }
        else
        {
            // The user's PHP version is well old - it doesn't
            // even have the version_compare() function!
            $result = OA_ENV_ERROR_PHP_VERSION;
        }
        if ($result == OA_ENV_ERROR_PHP_VERSION)
        {
            $this->aInfo['PHP']['warning'][OA_ENV_ERROR_PHP_VERSION] =
                "Version {$this->aInfo['PHP']['actual']['version']} is below the minimum supported version of {$this->aInfo['PHP']['expected']['version']}." .
                "<br />Although you can install OpenX, this is not a supported version, and it is not possible to guarantee that everything will work correctly. " .
                "Please see the <a href='" . OX_PRODUCT_DOCSURL . "/faq/php-unsupported'>FAQ</a> for more information.";
        }
        else
        {
            $this->aInfo['PHP']['error'] = false;
        }

        // Test the PHP configuration's memory_limit value
        if (!$this->checkMemory())
        {
            $result = OA_ENV_ERROR_PHP_MEMORY;
            $this->aInfo['PHP']['error'][OA_ENV_ERROR_PHP_MEMORY] = 'The memory_limit value needs to be increased';
        }

        // Test the PHP configuration's safe_mode value
        if ($this->aInfo['PHP']['actual']['safe_mode'])
        {
            $result = OA_ENV_ERROR_PHP_SAFEMODE;
            $this->aInfo['PHP']['error'][OA_ENV_ERROR_PHP_SAFEMODE] = 'The safe_mode option must be OFF';
        }

        // Test the PHP configuration's magic_quotes_runtime value
        if ($this->aInfo['PHP']['actual']['magic_quotes_runtime'])
        {
            $result = OA_ENV_ERROR_PHP_MAGICQ;
            $this->aInfo['PHP']['error'][OA_ENV_ERROR_PHP_MAGICQ] = 'The magic_quotes_runtime option must be OFF';
        }

        // Test the PHP configuration's file_uploads value
        if (!$this->aInfo['PHP']['actual']['file_uploads']) {
            $this->aInfo['PHP']['error'][OA_ENV_ERROR_PHP_UPLOADS] = 'The file_uploads option must be ON';
        }

        // Test the required PHP extensions are loaded
        if (!$this->aInfo['PHP']['actual']['pcre']) {
            $this->aInfo['PHP']['error'][OA_ENV_ERROR_PHP_PCRE] = 'The pcre extension must be loaded';
        }
        if (!$this->aInfo['PHP']['actual']['xml']) {
            $this->aInfo['PHP']['error'][OA_ENV_ERROR_PHP_XML] = 'The xml extension must be loaded';
        }
        if (!$this->aInfo['PHP']['actual']['zlib']) {
            $this->aInfo['PHP']['error'][OA_ENV_ERROR_PHP_ZLIB] = 'The zlib extension must be loaded';
        }
        if (!($this->aInfo['PHP']['actual']['mysql'] || $this->aInfo['PHP']['actual']['pgsql'])) {
            $this->aInfo['PHP']['error'][OA_ENV_ERROR_PHP_MYSQL] = 'Either the mysql or the pgsql extension must be loaded';
        }
        if ($this->aInfo['PHP']['actual']['timeout']) {
            $this->aInfo['PHP']['error'][OA_ENV_ERROR_PHP_TIMEOUT] = 'The PHP function set_time_limit() has been disabled and ';
            $this->aInfo['PHP']['error'][OA_ENV_ERROR_PHP_TIMEOUT].= 'max_execution_time is set to '.$this->aInfo['PHP']['actual']['timeout'].' which may cause problems with functionality such as maintenance';
        }

        return $result;
    }

    /**
     * A private method to test for any critial errors resulting from "bad"
     * file or directory permissions.
     *
     * Sets $this->aInfo['PERMS']['error'] to the boolean false if all
     * permissions are acceptable, otherwise, it is set to a string containing
     * an appropriate error message to show to the user on the system check
     * page.
     *
     * @return boolean True when all permissions are okay, false otherwise.
     */
    function _checkCriticalFilePermissions()
    {
        // Test to see if there were any file/directory permission errors
        unset($this->aInfo['PERMS']['error']['filePerms']);
        foreach ($this->aInfo['PERMS']['actual'] AS $idx => $aFile)
        {
            if ($aFile['error'])
            {
                if (is_null($this->aInfo['PERMS']['error']['filePerms']))
                {
                    if (DIRECTORY_SEPARATOR == '\\') {
                        $this->aInfo['PERMS']['error']['filePerms'] = $GLOBALS['strErrorWritePermissionsWin'];
                    } else {
                        $this->aInfo['PERMS']['error']['filePerms'] = $GLOBALS['strErrorWritePermissions'];
                    }
                }
                if (DIRECTORY_SEPARATOR != '\\') {
                    $this->aInfo['PERMS']['error']['filePerms'] .= "<br />" . sprintf($GLOBALS[$aFile['string']], $aFile['file']);
                }
            }
        }
        if (!is_null($this->aInfo['PERMS']['error']['filePerms']))
        {
            $this->aInfo['PERMS']['error']['filePerms'] .= "<br />" . $GLOBALS['strCheckDocumentation'];
            return false;
        }
        $this->aInfo['PERMS']['error'] = false;
        return true;
    }

    function _checkCriticalFiles()
    {
        $this->aInfo['FILES']['error'] = false;
        return true;
    }

    function _checkMemoryCanBeSet()
    {
        $memoryLimit = getMemorySizeInBytes();
        // Unlimited memory, no need to check if it can be set
        if ($memoryLimit == -1) {
            return true;
        }
        increaseMemoryLimit($memoryLimit + 1);
        $newMemoryLimit = getMemorySizeInBytes();
        $memoryCanBeSet = ($memoryLimit != $newMemoryLimit);

        // Restore previous limit
        @ini_set('memory_limit', $memoryLimit);
        return $memoryCanBeSet;
    }
}

?>

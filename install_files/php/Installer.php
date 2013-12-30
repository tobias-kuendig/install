<?php

class Installer
{
    /**
     * Constructor/Router
     */
    public function __construct()
    {
        if ($handler = $this->post('handler')) {
            try {
                if (!preg_match('/^on[A-Z]{1}[\w+]*$/', $handler))
                    throw new Exception(sprintf('Invalid handler: %s', $handler));

                if (method_exists($this, $handler) && ($result = $this->$handler()) !== null) {
                    header('Content-Type: application/json');
                    die(json_encode($result));
                }
            }
            catch (Exception $ex) {
                header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
                die($ex->getMessage());
            }
        }
    }

    protected function onCheckRequirement()
    {
        $checkCode = $this->post('code');
        $result = false;
        switch ($checkCode) {
            case 'liveConnection':
                $result = ($this->requestServerData('ping') !== null);
                break;
            case 'writePermission':
                $result = is_writable(PATH_INSTALL);
                break;
            case 'phpVersion':
                // $result = version_compare(PHP_VERSION , "5.4", ">=");
                $result = true; // Debug
                break;
            case 'safeMode':
                $result = !ini_get('safe_mode');
                break;
            case 'pdoLibrary':
                $result = defined('PDO::ATTR_DRIVER_NAME');
                break;
            case 'curlLibrary':
                $result = function_exists('curl_init');
                break;
            case 'mcryptLibrary':
                $result = function_exists('mcrypt_encrypt');
                break;
            case 'zipLibrary':
                // $result = class_exists('ZipArchive');
                $result = true; // Debug
                break;
        }

        return array('result' => $result);
    }

    protected function onValidateDatabase()
    {
        if ($this->post('db_type') != 'sqlite' && !strlen($this->post('db_host')))
            throw new InstallerException('Please specify a database host', 'db_host');

        if (!strlen($this->post('db_name')))
            throw new InstallerException('Please specify the database name', 'db_name');

        $config = array_merge(array(
            'type' => null,
            'host' => null,
            'name' => null,
            'port' => null,
            'user' => null,
            'pass' => null,
        ), array(
            'type' => $this->post('db_type'),
            'host' => $this->post('db_host'),
            'name' => $this->post('db_name'),
            'user' => $this->post('db_user'),
            'pass' => $this->post('db_pass'),
        ));

        extract($config);

        switch ($type) {
            case 'mysql':
                $dsn = 'mysql:host='.$host.';dbname='.$name;
                if ($port) $dsn .= ";port=".$port;
                break;

            case 'pgsql':
                $_host = ($host) ? 'host='.$host.';' : '';
                $dsn = 'pgsql:'.$_host.'dbname='.$name;
                if ($port) $dsn .= ";port=".$port;
                break;

            case 'sqlite':
                $dsn = 'sqlite:'.$name;
                break;

            case 'sqlsrv':
                $availableDrivers = PDO::getAvailableDrivers();
                $_port = $port ? ','.$port : '';
                if (in_array('dblib', $availableDrivers))
                    $dsn = 'dblib:host='.$host.$_port.';dbname='.$name;
                else {
                    $_name = ($name != '') ? ';Database='.$name : '';
                    $dsn = 'dblib:host='.$host.$_port.$_name;
                }
            break;
        }
        try {
            $db = new PDO($dsn, $user, $pass, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        }
        catch (PDOException $ex) {
            throw new Exception('Connection failed: ' . $ex->getMessage());
        }

        /*
         * Check the database is empty
         */
        $fetch = $db->query('show tables', PDO::FETCH_NUM);
        $tables = 0;
        while ($result = $fetch->fetch()) $tables++;

        if ($tables > 0)
            throw new Exception(sprintf('Database "%s" is not empty. Please empty the database or specify another database.', $name));
    }

    protected function onValidateAdminAccount()
    {
        if (!strlen($this->post('admin_first_name')))
            throw new InstallerException('Please specify the administrator first name', 'admin_first_name');

        if (!strlen($this->post('admin_last_name')))
            throw new InstallerException('Please specify the administrator first name', 'admin_last_name');

        if (!strlen($this->post('admin_email')))
            throw new InstallerException('Please specify administrator email address', 'admin_email');

        if (!preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/", $this->post('admin_email')))
            throw new InstallerException('Please specify valid email address', 'admin_email');

        if (!strlen($this->post('admin_password')))
            throw new InstallerException('Please specify password', 'admin_password');
    }

    protected function onValidateAdvancedConfig()
    {
        if (!strlen($this->post('encryption_code')))
            throw new InstallerException('Please specify encryption key', 'encryption_code');

        if (strlen($this->post('encryption_code')) < 6)
            throw new InstallerException('The encryption key should be at least 6 characters in length.', 'encryption_code');

        if (!strlen($this->post('folder_mask')))
            throw new InstallerException('Please specify folder permission mask', 'folder_mask');

        if (!strlen($this->post('file_mask')))
            throw new InstallerException('Please specify file permission mask', 'file_mask');

        if (!preg_match("/^[0-9]{3}$/", $this->post('folder_mask')) || $this->post('folder_mask') > 777)
            throw new InstallerException('Please specify a valid folder permission mask', 'folder_mask');

        if (!preg_match("/^[0-9]{3}$/", $this->post('file_mask')) || $this->post('file_mask') > 777)
            throw new InstallerException('Please specify a valid file permission mask', 'file_mask');
    }

    protected function onGetPopularPackages()
    {
        return $this->requestServerData('packages/popular');
    }

    protected function onSearchPackages()
    {
        return $this->requestServerData('packages/search', array('query' => $this->post('query')));
    }

    protected function onInstallStep()
    {
        $installStep = $this->post('step');
        $result = false;

sleep(2); //Debug

        switch ($installStep) {
            case 'getMetaData':
                // Download file hashes
                $result = $this->requestServerData('install/public', array(
                    'plugins' => $this->post('plugins', array())
                ));
                break;

            case 'downloadCore':
                // Download the core archive
                break;

            case 'downloadPlugin':
                // Download each plugin
                break;

            case 'extractCore':
                // Extract core
                break;

            case 'extractPlugin':
                // Extract each plugin
                break;

            case 'setupConfig':
                // Set up config files
                break;

            case 'createAdmin':
                // Create admin account
                break;
        }

        return array('result' => $result);
    }

    //
    // Helpers
    //

    private function requestServerData($uri = null, $params = array())
    {
        $result = null;
        $error = null;
        try {
            $postData = http_build_query($params, '', '&');
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, OCTOBER_GATEWAY.'/'.$uri);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3600);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION , true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode == 500) {
                $error = $result;
                $result = '';
            }
        }
        catch (Exception $ex) {}

        if ($error !== null)
            throw new Exception('Server responded with error: ' . $error);

        if (!$result || !strlen($result))
            throw new Exception('Unable to make an outgoing connection to the update server.');

        try {
            $resultData = @json_decode($result);
        }
        catch (Exception $ex) {
            $resultData = $result;
        }

        return $resultData;
    }

    private function post($var, $default = null)
    {
        if (array_key_exists($var, $_REQUEST)) {
            $result = $_REQUEST[$var];
            if (is_string($result)) $result = trim($result);
            return $result;
        }

        return $default;
    }
}

$installer = new Installer;
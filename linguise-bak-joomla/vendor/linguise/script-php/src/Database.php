<?php

namespace Linguise\Script\Core;

use Linguise\Script\Core\Databases\Mysql;
use Linguise\Script\Core\Databases\Sqlite;

defined('LINGUISE_SCRIPT_TRANSLATION') or die();

class Database
{
    /**
     * @var null|Database
     */
    private static $_instance = null;

    /**
     * @var null|Mysql
     */
    protected $_database;

    private $_configuration = null;

    private function __construct()
    {
        $cms = 'none';
        if (empty(Configuration::getInstance()->get('cms')) || Configuration::getInstance()->get('cms') === 'auto') {
            $base_dir = Configuration::getInstance()->get('base_dir');
            if (file_exists($base_dir . 'wp-config.php')) {
                $cms = 'wordpress';
            } elseif (file_exists($base_dir . 'configuration.php')) {
                $config_content = file_get_contents($base_dir . 'configuration.php');
                if ($config_content && strpos($config_content, 'JConfig') !== false) {
                    $cms = 'joomla';
                }
            }
        } elseif (strtolower(Configuration::getInstance()->get('cms')) === 'joomla') {
            $cms = 'joomla';
        } elseif (strtolower(Configuration::getInstance()->get('cms')) === 'wordpress') {
            $cms = 'wordpress';
        }

        if ($cms === 'joomla') {
            $this->_configuration = $this->retrieveJoomlaConfiguration();
            $this->_database = Mysql::getInstance();
            $connection_result = $this->_database->connect($this->_configuration);
        } elseif ($cms === 'wordpress') {
            $this->_configuration = $this->retrieveWordPressConfiguration();
            $this->_database = Mysql::getInstance();
            $connection_result = $this->_database->connect($this->_configuration);
        } elseif (Configuration::getInstance()->get('db_host')) {
            $this->_database = Mysql::getInstance();
            $this->_configuration = $this->retrieveMysqlConfiguration();
            $connection_result = $this->_database->connect($this->_configuration);
        } else {
            $this->_database = Sqlite::getInstance();
            $connection_result = $this->_database->connect();
        }

        if (!$connection_result) {
            //fixme: redirect to non translated page
        }
    }

    /**
     * Retrieve singleton instance
     *
     * @return Database|null
     */
    public static function getInstance() {

        if(is_null(self::$_instance)) {
            self::$_instance = new Database();
        }

        return self::$_instance;
    }

    /**
     * Retrieve Joomla database credentials and tries to connect
     *
     * @return bool
     */
    protected function retrieveJoomlaConfiguration()
    {
        $configuration_file = Configuration::getInstance()->get('base_dir') . DIRECTORY_SEPARATOR . 'configuration.php';
        if (!file_exists($configuration_file)) {
            return false;
        }

        include_once($configuration_file);
        if (!class_exists('JConfig')) {
            return false;
        }

        return new \JConfig();
    }

    /**
     * Retrieve Wordpress database credentials and tries to connect
     *
     * @return bool|\stdClass
     */
    protected function retrieveWordPressConfiguration()
    {
        $config = new \stdClass();

        global $wpdb;
        if (!empty($wpdb) && !empty($wpdb->db_version())) {
            // We have already mysql connected

            $config->db = $wpdb->__get('dbname');
            $config->user = $wpdb->__get('dbuser');
            $config->password = $wpdb->__get('dbpassword');
            $config->host = $wpdb->__get('dbhost');
            $config->dbprefix = $wpdb->base_prefix;
            $config->multisite = is_multisite();
            if (defined('DOMAIN_CURRENT_SITE')) {
                $config->domain_current_site = DOMAIN_CURRENT_SITE;
            }

        } else {
            // Fallback to loading configuration file
            $configuration_file = Configuration::getInstance()->get('base_dir') . DIRECTORY_SEPARATOR . 'wp-config.php';
            if (!file_exists($configuration_file)) {
                return false;
            }

            $config_content = file_get_contents($configuration_file);

            preg_match_all('/define\( *[\'"](.*.)[\'"] *, *(?:[\'"](.*?)[\'"]|([0-9]+)|(true)|(TRUE)) *\)/m', $config_content, $matches, PREG_SET_ORDER, 0);

            foreach ($matches as $config_line) {
                switch ($config_line[1]) {
                    case 'DB_NAME':
                        $config->db = $config_line[2];
                        break;
                    case 'DB_USER':
                        $config->user = $config_line[2];
                        break;
                    case 'DB_PASSWORD':
                        $config->password = $config_line[2];
                        break;
                    case 'DB_HOST':
                        $config->host = $config_line[2];
                        break;
                    case 'MULTISITE':
                        if ((!empty($config_line[3]) && (int)$config_line[3] > 0) || empty($config_line[4]) || empty($config_line[5])) {
                            $config->multisite = true;
                        } else {
                            $config->multisite = false;
                        }
                        break;
                    case 'DOMAIN_CURRENT_SITE':
                        $config->domain_current_site = $config_line[2];
                        break;
                }
            }

            preg_match('/\$table_prefix *= *[\'"](.*?)[\'"]/', $config_content, $matches);
            $config->dbprefix = $matches[1];
        }

        return $config;
    }

    /**
     * Retrieve credentials from Configuration.php
     *
     * @return bool|\stdClass
     */
    protected function retrieveMysqlConfiguration()
    {
        $config = new \stdClass();
        $config->db = Configuration::getInstance()->get('db_name');
        $config->user = Configuration::getInstance()->get('db_user');
        $config->password = Configuration::getInstance()->get('db_password');
        $config->host = Configuration::getInstance()->get('db_host');
        $config->dbprefix = Configuration::getInstance()->get('db_prefix');
        $config->dbport = Configuration::getInstance()->get('db_port');

        return $config;
    }

    public function getSourceUrl($url) {
        return $this->_database->getSourceUrl($url);
    }

    public function getTranslatedUrl($url) {
        return $this->_database->getTranslatedUrl($url);
    }

    public function saveUrls($urls) {
        if (empty($urls)) {
            return;
        }
        return $this->_database->saveUrls($urls);
    }

    public function removeUrls($urls) {
        if (empty($urls)) {
            return;
        }
        return $this->_database->removeUrls($urls);
    }

    public function retrieveWordpressOption($option_name, $host = null) {
        if (function_exists('get_option')) {
            $options = get_option('linguise_options');

            if (empty($options[$option_name])) {
                return false;
            }

            return $options[$option_name];
        }

        if (!empty($this->_configuration->multisite) && $host !== $this->_configuration->domain_current_site) {
            return $this->_database->retrieveWordpressMultisiteOption($option_name, $host);
        } else {
            return $this->_database->retrieveWordpressOption($option_name);
        }
    }

    public function retrieveJoomlaParam($option_name) {
        return $this->_database->retrieveJoomlaParam($option_name);
    }

    public function close() {
        $this->_database->close();
    }
}

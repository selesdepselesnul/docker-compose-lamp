<?php
namespace Linguise\Script\Core;

defined('LINGUISE_SCRIPT_TRANSLATION') or die();

class Helper {

    public static function getIpAddress()
    {
        if (isset($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = (string)trim(current(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])));
            if ($ip) {
                return $ip;
            }
        }

        if (isset( $_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }

        return '';
    }

    public static function getClassStaticVars($classname)
    {
        $class = new \ReflectionClass($classname);
        return $class->getStaticProperties();
    }

    public static function prepareDataDir()
    {
        if (Configuration::getInstance()->get('data_dir') === null) {
            $data_folder = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..') . DIRECTORY_SEPARATOR . md5('data' . Configuration::getInstance()->get('token'));
            if (!file_exists($data_folder)) {
                mkdir($data_folder);
                mkdir($data_folder . DIRECTORY_SEPARATOR . 'database');
                mkdir($data_folder . DIRECTORY_SEPARATOR . 'cache');
                mkdir($data_folder . DIRECTORY_SEPARATOR . 'tmp');
                file_put_contents($data_folder . DIRECTORY_SEPARATOR . '.htaccess', 'deny from all');
            }
            Configuration::getInstance()->set('data_dir', $data_folder);
        }
    }
}
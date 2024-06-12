<?php

namespace Linguise\Script;

defined('LINGUISE_SCRIPT_TRANSLATION') || die('');

require 'Configuration.php';

class ConfigurationLocal extends Configuration
{
    public static $host = "127.0.0.1"; // Put your "Core" server host
    public static $port = "12100"; // Put your "Core" server port
}
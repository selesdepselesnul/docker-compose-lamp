<?php
/**
 * Linguise
 *
 * We developed this code with our hearts and passion.
 * We hope you found it useful, easy to understand and to customize.
 * Otherwise, please feel free to contact us at contact@linguise.com
 *
 * @package   Linguise
 * @copyright Copyright (C) 2021 Linguise (http://www.linguise.com). All rights reserved.
 * @license   GNU General Public License version 2 or later; http://www.gnu.org/licenses/gpl-2.0.html
 */

use Linguise\Script\Core\Processor;

if (!defined('LINGUISE_SCRIPT_TRANSLATION')) {
    define('LINGUISE_SCRIPT_TRANSLATION', true);
}
if (!defined('LINGUISE_SCRIPT_TRANSLATION_VERSION')) {
    define('LINGUISE_SCRIPT_TRANSLATION_VERSION', 'joomla_plugin/2.0.4');
}

ini_set('display_errors', false);

require_once( __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php');

LinguiseHelper::loadConfigurationFile();

$processor = new Processor();
if (isset($_GET['linguise_language']) && $_GET['linguise_language'] === 'zz-zz' &&  isset($_GET['linguise_action'])) {
    switch ($_GET['linguise_action']) {
        case 'clear-cache':
            $processor->clearCache();
            break;
        case 'update-certificates':
            $processor->updateCertificates();
            break;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['linguise_language']) && $_GET['linguise_language'] === 'zz-zz') {
    $processor->editor();
} else {
    $processor->run();
}

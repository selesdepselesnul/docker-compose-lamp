<?php
namespace Linguise\Script;

use Linguise\Script\Core\Request;
use Linguise\Script\Core\Response;

if (!defined('LINGUISE_SCRIPT_TRANSLATION')) die();

class Configuration {

    public static function onAfterMakeRequest() {
        // Do not translate SPPageBuilder ajax requests
        if (!empty($_POST['option']) && !empty($_POST['task']) && $_POST['option']==='com_sppagebuilder' && $_POST['task'] === 'ajax') {
            \Linguise\Script\Core\Response::getInstance()->end();
        }
    }

    public static function onAfterTranslation()
    {
        $response = Response::getInstance();
        $content = $response->getContent();

        // List of scripts we need to make changes to
        $scripts = ['com_faqbookpro'];
        $content = preg_replace_callback('/<script type="application\/json" class="joomla-script-options (loaded|new)"( nonce=".*?")?>(.*?(?:' . implode('|', $scripts) . ')".*?)<\/script>/', function ($matches) {
            $decoded = json_decode($matches[3]);

            if (empty($decoded)) {
                return $matches[0];
            }

            $changed = false;

            if (!empty($decoded->com_faqbookpro) && !empty($decoded->com_faqbookpro->site_path)) {
                $decoded->com_faqbookpro->site_path = $decoded->com_faqbookpro->site_path . Request::getInstance()->getLanguage() . '/';
                $changed = true;
            }

            if (!$changed) {
                return $matches[0];
            }

            $script = '<script type="application/json" class="joomla-script-options ' . $matches[1];
            if ($matches[2]) {
                $script .= ' nonce="' . $matches[2] . '">';
            }
            $script .= json_encode($decoded) . '</script>';

            return $script;
        }, $content);


        $response->setContent($content);
    }

}

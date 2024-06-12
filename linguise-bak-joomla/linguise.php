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

use Linguise\Script\Core\Configuration;
use Linguise\Script\Core\Database;
use Linguise\Script\Core\Cache;

defined('_JEXEC') or die;


class PlgSystemLinguise extends JPlugin
{
    /**
     * Hook begore anything happen to translate this request if we have to
     *
     * @param $subject
     * @param array $config
     */
    public function __construct(&$subject, $config = array())
    {
        if (JFactory::getApplication()->isClient('administrator')) {
            return parent::__construct($subject, $config);
        }

        JLoader::register('LinguiseHelper', JPATH_ADMINISTRATOR . '/components/com_linguise/helpers/linguise.php');

        if (!LinguiseHelper::getOption('linguise_field_token')) {
            return parent::__construct($subject, $config);
        }

        if (!empty(LinguiseHelper::getOption('joomla_languages'))) {
            // We have a joomla multilingual, we need to unset the session to avoid Joomla language redirection
            JFactory::getSession()->set('plg_system_languagefilter.language', JFactory::getLanguage()->getDefault());
        }

        include_once('vendor' . DIRECTORY_SEPARATOR . 'autoload.php');

        $language = LinguiseHelper::getLanguageFromUrl();

        if (!$language) {
            return parent::__construct($subject, $config);
        }

        $use_joomla_language = (bool) LinguiseHelper::getOption('use_joomla_language', false);
        if (!$use_joomla_language && !empty($_SERVER['HTTP_LINGUISE_ORIGINAL_LANGUAGE']) && $language !== LinguiseHelper::getOption('language_default') && in_array($language, LinguiseHelper::getOption('joomla_languages'))) {
            throw new Exception(JText::_('JERROR_LAYOUT_PAGE_NOT_FOUND'), 404);
        }

        // Disable Linguise when Use Joomla Language Option enabled and Current Language in Native Joomla Language
        if (
            (
                $use_joomla_language && // Option use joomla language Enabled
                (
                    in_array($language, LinguiseHelper::getOption('joomla_languages')) || // Current Language in Native Joomla Languages OR (fr in [fr, en])
                    !in_array($language, array_merge(LinguiseHelper::getOption('languages_enabled'), array('zz-zz'))) // Current Language NOT in Linguise languages list (fr in [fr, vi])
                )
            ) ||
            (
                !$use_joomla_language && // Option use joomla language Disabled
                // Stop using Linguise translator when:
                !in_array($language, array_merge(LinguiseHelper::getOption('languages_enabled'), array('zz-zz'))) // Current language not in Linguise enabled language
            )
        ) {
            return parent::__construct($subject, $config);
        }

        $_GET['linguise_language'] = $language;

        include_once('script.php');
    }


    /**
     * J4 Disable languagefilter plugin when use_joomla_language option enabled
     *
     * @param $context
     * @param $type
     * @param $extension
     * @param $container
     * @return void
     * @throws ReflectionException
     */
    public function onAfterExtensionBoot($context, $type, $extension, $container) {
        if (!JFactory::getApplication()->isClient('site')) {
            return;
        }

        if((bool) LinguiseHelper::getOption('use_joomla_language', false)) {
            return;
        }

        $dispatcher = JFactory::getApplication()->getDispatcher();
        $listeners = $dispatcher->getListeners('onAfterInitialise');

        foreach ($listeners as $listener) {
            if ($listener instanceof Closure) {
                $r = new \ReflectionFunction($listener);
                if($r->getClosureThis()->_name === 'languagefilter') {
                    $dispatcher->removeListener('onAfterInitialise', $listener);
                }
            }
        }
    }

    public function onAfterInitialise()
    {
        if (!JFactory::getApplication()->isClient('site')) {
            return;
        }

        // Check translate option
        JLoader::register('LinguiseHelper', JPATH_ADMINISTRATOR . '/components/com_linguise/helpers/linguise.php');

        if (!LinguiseHelper::getOption('translate_search', 0)) {
            return;
        }

        $jversion = new JVersion();
        if (version_compare($jversion->getShortVersion(), '4.0', 'gt')) {
            $search = JFactory::getApplication()->input->get('q', '', 'string');
        } else {
            $search = JFactory::getApplication()->input->get('searchword', '', 'string');
        }

        if ($search === '') {
            return;
        }

        //todo: Check if setting is enabled

        $linguise_original_language = false;
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) === 'HTTP_') {
                $key = str_replace(' ', '-', strtolower(str_replace('_', ' ', substr($name, 5))));
                if ($key === 'linguise-original-language') {
                    $linguise_original_language = $value;
                    break;
                }
            }
        }

        if (!$linguise_original_language) {
            return;
        }

        if (!defined('LINGUISE_SCRIPT_TRANSLATION')) {
            define('LINGUISE_SCRIPT_TRANSLATION', true);
        }
        if (!defined('LINGUISE_SCRIPT_TRANSLATION_VERSION')) {
            define('LINGUISE_SCRIPT_TRANSLATION_VERSION', 'joomla_plugin/2.0.4');
        }

        include_once(JPATH_PLUGINS . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'linguise' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php');

        JLoader::register('LinguiseHelper', JPATH_ADMINISTRATOR . '/components/com_linguise/helpers/linguise.php');

        LinguiseHelper::loadConfigurationFile();

        $translation = \Linguise\Script\Core\Translation::getInstance()->translateJson(['search' => $search], rtrim(JURI::base(), '/'), $linguise_original_language);

        if (empty($translation->search)) {
            return;
        }

        // Set request back to joomla search
        if (version_compare($jversion->getShortVersion(), '4.0', 'gt')) {
            JFactory::getApplication()->input->request->set('q', $translation->search);
        } else {
            JFactory::getApplication()->input->set('searchword', $translation->search);
        }
    }

    /**
     * Languages have been initialized, we can fill the current language option
     *
     * @return void
     */
    public function onAfterRoute() {

        if (!JFactory::getApplication()->isClient('site')) {
            return;
        }

        JLoader::register('LinguiseHelper', JPATH_ADMINISTRATOR . '/components/com_linguise/helpers/linguise.php');

        $joomla_current_language = LinguiseHelper::getOption('joomla_default_language');
        if (!empty($joomla_current_language)) {
            $current_language = substr(JFactory::getLanguage()->getTag(), 0, 2);
        } else {
            $current_language = LinguiseHelper::getOption('language_default');
        }

        $linguise_original_language = null;
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) === 'HTTP_') {
                $key = str_replace(' ', '-', strtolower(str_replace('_', ' ', substr($name, 5))));
                if ($key === 'linguise-original-language') {
                    $linguise_original_language = $value;
                    break;
                }
            }
        }

        LinguiseHelper::setOption('current_language', $linguise_original_language ?: $current_language);

        LinguiseHelper::browserRedirect();
    }

    /**
     * We need to add flag to search input to translate the input value too
     * @return void
     */
    public function onAfterDispatch()
    {
        if (!JFactory::getApplication()->isClient('site')) {
            return;
        }

        // Check translate option
        JLoader::register('LinguiseHelper', JPATH_ADMINISTRATOR . '/components/com_linguise/helpers/linguise.php');

        if (!LinguiseHelper::getOption('translate_search', 0)) {
            return;
        }

        if (JFactory::getApplication()->input->request->exists('q') || JFactory::getApplication()->input->request->exists('searchword')) {
            $doc = JFactory::getApplication()->getDocument();
            $html = $doc->getBuffer('component');
            // Add translation flag to the search input and form
            if (preg_match('/<input.*?(name="(q|searchword)").*?>/', $html, $match)) {
                $searchInputRaw = $match[0];
                $searchInput = str_replace('name="q"', 'name="q" data-linguise-translate-attributes="value"', $searchInputRaw);
                $searchInput = str_replace('name="searchword"', 'name="searchword" data-linguise-translate-attributes="value"', $searchInput);
                $html = str_replace($searchInputRaw, $searchInput, $html);
            }

            $doc->setBuffer($html, 'component');
        }
    }

    static private $_before_rendered = false;

    /**
     * Before Render
     *
     * @return void
     * @throws \Exception Throw when application can not start
     * @since  version
     */
    public function onBeforeLinguiseRender()
    {
        if (self::$_before_rendered) {
            return;
        }
        self::$_before_rendered = true;

        $app = JFactory::getApplication();
        // get the router
        if (!$app->isClient('site')) {
            return;
        }

        $options = LinguiseHelper::getOptions();

        $uri = JUri::getInstance();

        $languages_content = file_get_contents(JPATH_ROOT. '/modules/mod_linguise/assets/languages.json');
        $languages_names = json_decode($languages_content);

        // Get from module parameters the enable languages
        $languages_enabled_param = LinguiseHelper::getOption('languages_enabled', []);
        // Get the default language
        $default_language = LinguiseHelper::getOption('language_default', 'en');
        $language_name_display = LinguiseHelper::getOption('language_name_display', 'en');

        // Generate language list with default language as first item
        if ($language_name_display === 'en') {
            $language_list = array($default_language => $languages_names->{$default_language}->name);
        } else {
            $language_list = array($default_language => $languages_names->{$default_language}->original_name);
        }

        foreach ($languages_enabled_param as $language) {
            if ($language === $default_language) {
                continue;
            }

            if (!isset($languages_names->{$language})) {
                continue;
            }

            if ($language_name_display === 'en') {
                $language_list[$language] = $languages_names->{$language}->name;
            } else {
                $language_list[$language] = $languages_names->{$language}->original_name;
            }
        }

        if (preg_match('@(\/+)$@', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), $matches) && !empty($matches[1])) {
            $trailing_slashes = $matches[1];
        } else {
            $trailing_slashes = '';
        }

        $base = rtrim($uri->base(true),'/');
        LinguiseHelper::setOption('languages', $language_list);
        LinguiseHelper::setOption('base', $base);
        $original_path = rtrim(substr(rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'), strlen($base)), '/');
        LinguiseHelper::setOption('original_path', $original_path);
        LinguiseHelper::setOption('trailing_slashes', $trailing_slashes);

        if ($options['alternate_link']) {

            $joomla_languages = [];
            if (!empty($options['joomla_languages'])) {
                $params = new \Joomla\Registry\Registry();

                $jVersion = new JVersion();
                if (version_compare($jVersion->getShortVersion(), '4', '>=')) {
                    $joomla_language_list = Joomla\Module\Languages\Site\Helper\LanguagesHelper::getList($params);
                } else {
                    JLoader::register('ModLanguagesHelper', JPATH_ROOT . '/modules/mod_languages/helper.php');
                    $joomla_language_list = ModLanguagesHelper::getList($params);
                }

                foreach ($joomla_language_list as $joomla_language) {
                    $jlang = substr($joomla_language->lang_code, 0, 2);
                    if (!in_array($jlang, $options['joomla_languages'])) {
                        continue;
                    }

                    if ($jlang === $options['joomla_default_language']) {
                        // Remove the language tag from the link
                        $link = preg_replace('/^' . str_replace('/', '\/', $base) . '\/' . $jlang . '/', '', $joomla_language->link);
                    } else {
                        $link = $joomla_language->link;
                    }

                    $joomla_languages[$jlang] = $link;
                }
            }

            $scheme = $uri->getScheme();
            $host = $uri->getHost();
            $path = $original_path;
            $query = $uri->getQuery();
            $alternates = $language_list;
            $alternates['x-default'] = 'x-default';

            if (!empty($joomla_languages) && $options['current_language'] !== $options['language_default'] && in_array($options['current_language'], array_keys($joomla_languages))) {
                // We are in a Joomla managed language but not the default one. Let's replace the path by the untranslated path we get from Joomla
                $path = $joomla_languages[$options['language_default']] ?? $path;
            }

            $dbo = JFactory::getDbo();
            foreach ($alternates as $language_code => $language) {
                if (!empty($joomla_languages[$language_code]) && $language_code !== $options['joomla_default_language']) {
                    // This language is managed by joomla, get the url from joomla
                    $url = $scheme . '://' . $host . $joomla_languages[$language_code];
                } else {

                    if ($language_code === $options['joomla_default_language']) {
                        // This is the default Joomla language
                        $url = $scheme . '://' . $host . $base . $path . (!empty($query) ? '?' . $query : '');
                    } else {
                        $db_query = 'SELECT * FROM #__linguise_urls WHERE hash_source=' . $dbo->quote(md5($path)) . ' AND language=' . $dbo->quote($language_code);
                        try {
                            // Wrap in a try catch in case databse has not been already created by linguise script
                            $url_translation = $dbo->setQuery($db_query)->loadAssoc();
                        } catch (\Exception $e) {}

                        if (!empty($url_translation)) {
                            $url = $scheme . '://' . $host . $base  . htmlentities($url_translation['translation'], ENT_COMPAT) . (!empty($query) ? '?' . $query : '');
                        } else {
                            $url = $scheme . '://' . $host . $base . (in_array($language_code, array($default_language, 'x-default')) ? '' : '/' . $language_code) . $path . $trailing_slashes . (!empty($query) ? '?' . $query : '');
                        }
                    }
                }
                JFactory::getDocument()->addCustomTag(
                    '<link
                    rel="alternate"
                    hreflang="' . $language_code . '"
                    href="' . $url . '"
                />'
                );
            }
        }
    }

    public function onAfterRenderModule($module, $attribs)
    {
        if (JFactory::getApplication()->isClient('administrator')) {
            return;
        }

        if (!in_array($module->module, ['mod_menu', 'mod_maximenuck'])) {
            return;
        }

        $params = new \Joomla\Registry\Registry($module->params);
        if (version_compare(JVERSION, '4.0.0') >= 0) {
            $list = Joomla\Module\Menu\Site\Helper\MenuHelper::getList($params);
        } else {
            JLoader::register('ModMenuHelper', JPATH_BASE . '/modules/mod_menu/helper.php');
            $list = ModMenuHelper::getList($params);
        }



        foreach ($list as $item) {
            if ($item->type === 'component' && $item->component === 'com_linguise') {
                JFactory::getApplication()->triggerEvent('onBeforeLinguiseRender');

                JLoader::register('LinguiseHelper', JPATH_ADMINISTRATOR . '/components/com_linguise/helpers/linguise.php');

                LinguiseHelper::addAssets();
            }
        }
    }

    public function onExtensionAfterSave($context, $table, $isNew)
    {
        if ($context !== 'com_modules.module' || $table->module !== 'mod_linguise') {
            return;
        }
    }

    public function onContentAfterSave($context, $table, $isNew)
    {
        if ($context !== 'com_advancedmodules.module' || $table->module !== 'mod_linguise') {
            return;
        }
    }

    /**
     * Add the right default class to the menu anchor attribute
     *
     * @param $form
     * @param $data
     * @return boolean
     */
    public function onContentPrepareForm($form, $data) {
        if (JFactory::getApplication()->isClient('site')) {
            return true;
        }

        if ((!empty($data->params['option']) && is_array($data->params) && ($data->params['option'] !== 'com_linguise' || $data->params['view'] !== 'linguise')) ||
            $form->getName() !== 'com_menus.item' || !$form->getField('menu-anchor_css', 'params')) {
            return true;
        }

        $form->setFieldAttribute('menu-anchor_css', 'default', 'linguise_switcher', 'params');
        return true;
    }

    /**
     * Make sure we have the right class in the menu item after form submission
     *
     * @param $context
     * @param $item
     * @param $isNew
     * @return boolean
     * @throws Exception
     */
    public function onContentBeforeSave($context, $item, $isNew) {
        if (JFactory::getApplication()->isClient('site')) {
            return true;
        }

        if ($context !== 'com_menus.item'  || JFactory::getApplication()->isClient('site') || $item->getTableName() !== '#__menu') {
            return true;
        }

        $params = json_decode($item->get('params'));
        if (!$params) {
            return true;
        }

        if ($item->get('link') !== 'index.php?option=com_linguise&view=linguise') {
            if (preg_match('/(^| )linguise_switcher($| )/', $params->{'menu-anchor_css'})) {
                $params->{'menu-anchor_css'} = str_replace('linguise_switcher', '', $params->{'menu-anchor_css'});
                $item->set('params', json_encode($params));
                return true;
            }
            return true;
        }

        // Check if we have the right class
        if (preg_match('/(^| )linguise_switcher($| )/', $params->{'menu-anchor_css'}) && preg_match('/(^| )noindex, nofollow($| )/', $params->{'robots'})) {
            return true;
        }

        if ($params->{'menu-anchor_css'} !== '') {
            $params->{'menu-anchor_css'} .= ' ';
        }

        $params->{'menu-anchor_css'} = str_replace('linguise_switcher', '', $params->{'menu-anchor_css'});
        $params->{'menu-anchor_css'}.= 'linguise_switcher';
        $params->{'robots'} = 'noindex, nofollow';
        $item->set('params', json_encode($params));

        return true;
    }

    public function onAjaxLinguiseDownloadDebug()
    {
        if (JFactory::getApplication()->isClient('site')) {
            return false;
        }

        if (!JSession::checkToken('get')) {
            header('Content-Type: application/json; charset=UTF-8;');
            echo json_encode(['success' => false, 'data' => 'Invalid Token']);
            return false;
        }

        $debug_file = JPATH_PLUGINS . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'linguise' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'linguise' . DIRECTORY_SEPARATOR . 'script-php' . DIRECTORY_SEPARATOR . 'debug.php';
        if (!file_exists($debug_file)) {
            $lang = JFactory::getLanguage();
            $lang->load('plg_system_linguise');
            header('Content-Type: application/json; charset=UTF-8;');
            echo json_encode(['success' => false, 'data' => JText::_('PLG_LINGUISE_DEBUG_NO_DEBUG_FILE')]);
            return false;
        }

        if (file_exists($debug_file)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="debug.txt"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($debug_file));
            ob_clean();
            ob_end_flush();
            $handle = fopen($debug_file, 'rb');
            while (! feof($handle)) {
                echo fread($handle, 1000);
            }
            return true;
        }
    }

    public function onAjaxLinguiseTruncateDebug()
    {
        if (JFactory::getApplication()->isClient('site')) {
            return false;
        }

        if (!JSession::checkToken('get')) {
            header('Content-Type: application/json; charset=UTF-8;');
            echo json_encode(['success' => false, 'data' => 'Invalid Token']);
            return false;
        }

        $log_path = JPATH_PLUGINS . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'linguise' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'linguise' . DIRECTORY_SEPARATOR . 'script-php' . DIRECTORY_SEPARATOR;
        $full_debug_file =  $log_path . 'debug.php';
        $last_errors_file = $log_path . 'errors.php';

        if (file_exists($full_debug_file)) {
            file_put_contents($full_debug_file, '<?php die(); ?>' . PHP_EOL);
        }

        if (file_exists($last_errors_file)) {
            file_put_contents($last_errors_file, '<?php die(); ?>' . PHP_EOL);
        }
        $lang = JFactory::getLanguage();
        $lang->load('plg_system_linguise');
        header('Content-Type: application/json; charset=UTF-8;');
        echo json_encode(['success' => true, 'data' => JText::_('PLG_LINGUISE_DEBUG_TRUNCATED')]);
        die();
    }

    public function onAjaxLinguiseClearCache()
    {
        if (JFactory::getApplication()->isClient('site')) {
            header('Content-Type: application/json; charset=UTF-8;');
            echo json_encode(['success' => false]);
            die();
        }

        if (!JSession::checkToken('get')) {
            header('Content-Type: application/json; charset=UTF-8;');
            echo json_encode(['success' => false, 'data' => 'Invalid Token']);
            return false;
        }

        @ini_set('display_errors', false);

        if (!defined('LINGUISE_SCRIPT_TRANSLATION')) {
            define('LINGUISE_SCRIPT_TRANSLATION', true);
        }

        require_once( __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php');
        Configuration::getInstance()->set('cms', 'joomla');
        Configuration::getInstance()->set('base_dir', realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR  . '..' . DIRECTORY_SEPARATOR  . '..') . DIRECTORY_SEPARATOR);

        $token = Database::getInstance()->retrieveJoomlaParam('linguise_field_token');
        Configuration::getInstance()->set('token', $token);
        Configuration::getInstance()->set('data_dir', JPATH_ROOT . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'plg_linguise' . DIRECTORY_SEPARATOR . md5('data', $token));

        Cache::getInstance()->clearAll();
    }
}

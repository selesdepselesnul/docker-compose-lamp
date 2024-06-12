<?php

// autoload_real.php @generated by Composer

class ComposerAutoloaderInited8b2bb021fe968d74dd59af1ebaebb2
{
    private static $loader;

    public static function loadClassLoader($class)
    {
        if ('Composer\Autoload\ClassLoader' === $class) {
            require __DIR__ . '/ClassLoader.php';
        }
    }

    /**
     * @return \Composer\Autoload\ClassLoader
     */
    public static function getLoader()
    {
        if (null !== self::$loader) {
            return self::$loader;
        }

        spl_autoload_register(array('ComposerAutoloaderInited8b2bb021fe968d74dd59af1ebaebb2', 'loadClassLoader'), true, true);
        self::$loader = $loader = new \Composer\Autoload\ClassLoader(\dirname(__DIR__));
        spl_autoload_unregister(array('ComposerAutoloaderInited8b2bb021fe968d74dd59af1ebaebb2', 'loadClassLoader'));

        require __DIR__ . '/autoload_static.php';
        call_user_func(\Composer\Autoload\ComposerStaticInited8b2bb021fe968d74dd59af1ebaebb2::getInitializer($loader));

        $loader->register(true);

        return $loader;
    }
}
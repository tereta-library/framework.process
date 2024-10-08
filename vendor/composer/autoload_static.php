<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit825108b8e917543b1d4bfa0caaf1452d
{
    public static $prefixLengthsPsr4 = array (
        'T' => 
        array (
            'Tereta\\FrameworkProcess\\' => 24,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Tereta\\FrameworkProcess\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit825108b8e917543b1d4bfa0caaf1452d::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit825108b8e917543b1d4bfa0caaf1452d::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit825108b8e917543b1d4bfa0caaf1452d::$classMap;

        }, null, ClassLoader::class);
    }
}

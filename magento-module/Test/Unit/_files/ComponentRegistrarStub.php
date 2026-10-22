<?php
declare(strict_types=1);

namespace Magento\Framework\Component;

/**
 * Inert stand-in so registration.php can load in a standalone checkout.
 *
 * Composer's autoloader runs registration.php, which calls ComponentRegistrar, and a
 * standalone checkout of this module has no Magento framework. This class is mapped
 * only through autoload-dev, which Composer ignores when the module is installed as a
 * dependency of a Magento project, so the real class is always used there.
 */
class ComponentRegistrar
{
    public const MODULE = 'module';

    /**
     * Accept the registration and do nothing.
     *
     * @param string $type
     * @param string $componentName
     * @param string $path
     * @return void
     */
    public static function register($type, $componentName, $path): void
    {
    }
}

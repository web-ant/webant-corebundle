<?php

/**
 * This file is part of the WebAnt CoreBundle package.
 * It modifies the default Symfony GenerateBundle.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 * reworked Yuri Kovalev <u@webant.ru>
 *
 */

namespace WebAnt\CoreBundle\Generator;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\Container;

/**
 * Generates a bundle.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * reworked Yuri Kovalev <u@webant.ru>
 */
class BundleGenerator extends Generator
{
    private $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function generate($namespace, $bundle, $dir, $format, $structure)
    {
        $dir .= '/'.strtr($namespace, '\\', '/');
        if (file_exists($dir)) {
            if (!is_dir($dir)) {
                throw new \RuntimeException(sprintf('Unable to generate the bundle as the target directory "%s" exists but is a file.', realpath($dir)));
            }
            $files = scandir($dir);
            if ($files != array('.', '..')) {
                throw new \RuntimeException(sprintf('Unable to generate the bundle as the target directory "%s" is not empty.', realpath($dir)));
            }
            if (!is_writable($dir)) {
                throw new \RuntimeException(sprintf('Unable to generate the bundle as the target directory "%s" is not writable.', realpath($dir)));
            }
        }

        $basename = substr($bundle, 0, -6);
        $parameters = array(
            'namespace' => $namespace,
            'bundle'    => $bundle,
            'format'    => $format,
            'bundle_basename' => $basename,
            'extension_alias' => Container::underscore($basename),
        );

        $this->renderFile('bundle/Bundle.php.twig', $dir.'/'.$bundle.'.php', $parameters);
        $this->renderFile('bundle/Controller.php.twig', $dir.'/Controller/' . $basename . 'Controller.php', $parameters);
        $this->renderFile('bundle/Entity.php.twig', $dir.'/Entity/' . $basename . '.php', $parameters);
        $this->renderFile('bundle/ControllerTest.php.twig', $dir.'/Tests/Controller/' . $basename . 'ControllerTest.php', $parameters);

        if ('xml' === $format || 'annotation' === $format) {
            $this->renderFile('bundle/services.xml.twig', $dir.'/Resources/config/services.xml', $parameters);
        } else {
            $this->renderFile('bundle/services.'.$format.'.twig', $dir.'/Resources/config/services.'.$format, $parameters);
        }

        if ('annotation' != $format) {
            $this->renderFile('bundle/routing.'.$format.'.twig', $dir.'/Resources/config/routing.'.$format, $parameters);
        }
    }
}

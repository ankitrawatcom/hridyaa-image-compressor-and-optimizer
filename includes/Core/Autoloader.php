<?php
/**
 * Zero-dependency PSR-4 Autoloader for NextGen Image Optimizer.
 *
 * @package NextGen\Core
 */

namespace NextGen\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Autoloader {

    /**
     * Namespace prefix.
     *
     * @var string
     */
    private const PREFIX = 'NextGen\\';

    /**
     * Base directory path.
     *
     * @var string
     */
    private string $baseDir;

    /**
     * Autoloader constructor.
     *
     * @param string $baseDir Base directory path for includes.
     */
    public function __construct(string $baseDir) {
        $this->baseDir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Register autoloader with SPL stack.
     *
     * @param string $baseDir Base directory path.
     * @return self
     */
    public static function register(string $baseDir): self {
        $loader = new self($baseDir);
        spl_autoload_register([$loader, 'loadClass']);
        return $loader;
    }

    /**
     * Load class file if it matches prefix.
     *
     * @param string $class Fully qualified class name.
     * @return bool True if loaded, false otherwise.
     */
    public function loadClass(string $class): bool {
        if (strpos($class, self::PREFIX) !== 0) {
            return false;
        }

        // Strip prefix.
        $relativeClass = substr($class, strlen(self::PREFIX));

        // Replace namespace separators with directory separators and append .php.
        $file = $this->baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
            return true;
        }

        return false;
    }
}

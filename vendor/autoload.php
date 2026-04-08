<?php
/**
 * Custom autoloader for manually installed Dompdf and dependencies.
 * Used when Composer cannot run (e.g. SSL issues). Replace with Composer autoload when possible.
 */
$vendorDir = __DIR__;

spl_autoload_register(function ($class) use ($vendorDir) {
    $prefixes = array(
        'Dompdf\\' => $vendorDir . '/dompdf/dompdf/src/',
        'FontLib\\' => $vendorDir . '/phenx/php-font-lib/src/FontLib/',
        'Svg\\' => $vendorDir . '/phenx/php-svg-lib/src/Svg/',
        'Masterminds\\' => $vendorDir . '/masterminds/html5/src/',
        'Sabberworm\\CSS\\' => $vendorDir . '/sabberworm/php-css-parser/src/',
    );

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        $relative = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($file)) {
            require $file;
            return true;
        }
    }

    // Dompdf\Cpdf is in lib/, not src/
    if ($class === 'Dompdf\\Cpdf') {
        $file = $vendorDir . '/dompdf/dompdf/lib/Cpdf.php';
        if (file_exists($file)) {
            require $file;
            return true;
        }
    }

    return false;
});

<?php
/**
 * update phar file timestamp to the last commit in the repository for binary reproduceable build
 * of phar-files.
 *
 * @author Tom Klingenberg <https://github.com/ktomk>
 */

use Seld\PharUtils\Timestamps;

echo "reset phar file timestamps to latest commit timestamp (reproduceable build)\n";

# seld/phar-utils via build requirements
require __DIR__ . '/../vendor/seld/phar-utils/src/Timestamps.php';

$projectDir = __DIR__ . '/../..';

$build = new SimpleXMLElement($projectDir . '/build.xml', 0, true);

$file = $projectDir . '/' . $build['name'] . '.phar';

list($signature) = $build->xpath('//patched-pharpackage/@signature') + array(null);

echo "Signature: ", $signature, " (from build.xml) \n";
$const = "Phar::" . strtoupper($signature);

$sig = constant($const);
echo "Phar Algo: ", var_export($sig, true), " ($const)\n";

if (!is_file($file) || !is_readable($file)) {
    throw new RuntimeException(sprintf('Is not a file or not readable: %s', var_export($file, true)));
}

$timestamp = (int) `git log --format=format:%ct HEAD -1`;
$threshold = 1343826993;
if ($timestamp < $threshold) {
    $message = sprintf(
        'Timestamp %d (%s) below threshold %d (%s).', $timestamp, date(DATE_RFC3339, $timestamp), $threshold,
        date(DATE_RFC3339, $threshold)
    );
    throw new RuntimeException($message);
}

$tmp = $file . '.tmp';

if ($tmp !== $file && file_exists($tmp)) {
    unlink($tmp);
}

if (!rename($file, $tmp)) {
    throw new RuntimeException(
        sprintf('Failed to move file %s to %s', var_export($file, true), var_export($tmp, true))
    );
}

if (!is_file($tmp)) {
    throw new RuntimeException('No tempfile %s for reading', var_export($tmp, true));
}

$timestamps = new Timestamps($tmp);
$timestamps->updateTimestamps($timestamp);
$timestamps->save($file, $sig);

printf("Timestamp: %d (%s)\n", $timestamp, date(DATE_RFC3339, $timestamp));
echo "SHA1.....: ", sha1_file($file), "\nMD5......: ", md5_file($file), "\n";

if (!unlink($tmp)) {
    throw new RuntimeException('Error deleting tempfile %s', var_export($tmp, true));
}

#!/usr/bin/env php
<?php
/**
 * This file is part of Swow
 *
 * @link    https://github.com/swow/swow
 * @contact twosee <twosee@php.net>
 *
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code
 */

declare(strict_types=1);

(static function () use ($argv): void {
    $args = array_slice($argv, 1);
    $proc = proc_open(
        [PHP_BINARY, '-r', sprintf('echo (int) extension_loaded(\'%s\');', Swow::class)],
        [0 => STDIN, 1 => ['pipe', 'w'], 2 => ['redirect', 1]],
        $pipes, null, null
    );
    $output = '';
    do {
        $output .= fread($pipes[1], 8192);
    } while (!feof($pipes[1]));
    $exitCode = proc_get_status($proc)['exitcode'];
    proc_close($proc);
    $extensionLoaded = $exitCode === 0 && $output === '1';
    $proc = proc_open(
        [PHP_BINARY, ...($extensionLoaded ? [] : ['-d', 'extension=' . strtolower(Swow::class)]), ...$args],
        [0 => STDIN, 1 => [PHP_OS_FAMILY === 'Windows' ? 'pipe' : 'pty', 'w'], 2 => STDERR],
        $pipes, null, null
    );
    do {
        echo @fread($pipes[1], 8192);
    } while (!feof($pipes[1]));
    /* FIXME: workaround for some platforms */
    if (function_exists('pcntl_waitpid')) {
        $status = proc_get_status($proc);
        if ($status['running']) {
            pcntl_waitpid($status['pid'], $wStatus);
            $exitCode = pcntl_wexitstatus($wStatus);
        } else {
            $exitCode = $status['exitcode'];
        }
    } else {
        while (true) {
            $status = proc_get_status($proc);
            if (!$status['running']) {
                $exitCode = $status['exitcode'];
                break;
            }
            usleep(1000);
        }
    }
    proc_close($proc);
    exit($exitCode);
})();

<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         1.2.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Cache\Engine;

use Cake\Cache\CacheEngine;
use CallbackFilterIterator;
use DateInterval;
use Exception;
use FilesystemIterator;
use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use SplFileObject;

/**
 * File Storage engine for cache. Filestorage is the slowest cache storage
 * to read and write. However, it is good for servers that don't have other storage
 * engine available, or have content which is not performance sensitive.
 *
 * You can configure a FileEngine cache, using Cache::config()
 */
class FileEngine extends CacheEngine
{
    /**
     * Instance of SplFileObject class
     *
     * @var \SplFileObject
     */
    protected SplFileObject $_File;

    /**
     * The default config used unless overridden by runtime configuration
     *
     * - `duration` Specify how long items in this cache configuration last.
     * - `groups` List of groups or 'tags' associated to every key stored in this config.
     *    handy for deleting a complete group from cache.
     * - `lock` Used by FileCache. Should files be locked before writing to them?
     * - `mask` The mask used for created files
     * - `dirMask` The mask used for created folders
     * - `path` Path to where cache files should be saved. Defaults to system's temp dir.
     * - `prefix` Prepended to all entries. Good for when you need to share a keyspace
     *    with either another cache config or another application.
     * - `serialize` Should cache objects be serialized first.
     *
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'duration' => 3600,
        'groups' => [],
        'lock' => true,
        'mask' => 0664,
        'dirMask' => 0770,
        'path' => null,
        'prefix' => 'cake_',
        'serialize' => true,
    ];

    /**
     * True unless FileEngine::__active(); fails
     *
     * @var bool
     */
    protected bool $_init = true;

    /**
     * Initialize File Cache Engine
     *
     * Called automatically by the cache frontend.
     *
     * @param array<string, mixed> $config array of setting for the engine
     * @return bool True if the engine has been successfully initialized, false if not
     */
    public function init(array $config = []): bool
    {
        parent::init($config);

        $this->_config['path'] ??= sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cake_cache' . DIRECTORY_SEPARATOR;
        if (substr($this->_config['path'], -1) !== DIRECTORY_SEPARATOR) {
            $this->_config['path'] .= DIRECTORY_SEPARATOR;
        }
        if ($this->_groupPrefix) {
            $this->_groupPrefix = str_replace('_', DIRECTORY_SEPARATOR, $this->_groupPrefix);
        }

        return $this->_active();
    }

    /**
     * Write data for key into cache
     *
     * @param string $key Identifier for the data
     * @param mixed $value Data to be cached
     * @param \DateInterval|int|null $ttl Optional. The TTL value of this item. If no value is sent and
     *   the driver supports TTL then the library may set a default value
     *   for it or let the driver take care of that.
     * @return bool True on success and false on failure.
     */
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        if ($value === '' || !$this->_init) {
            return false;
        }

        $key = $this->_key($key);

        if ($this->_setKey($key, true) === false) {
            return false;
        }

        if (!empty($this->_config['serialize'])) {
            $value = serialize($value);
        }

        $expires = time() + $this->duration($ttl);
        $contents = implode('', [$expires, PHP_EOL, $value, PHP_EOL]);

        if ($this->_config['lock']) {
            $this->_File->flock(LOCK_EX);
        }

        $this->_File->rewind();
        $success = $this->_File->ftruncate(0) &&
            $this->_File->fwrite($contents) &&
            $this->_File->fflush();

        if ($this->_config['lock']) {
            $this->_File->flock(LOCK_UN);
        }
        unset($this->_File);

        return $success;
    }

    /**
     * Read a key from the cache
     *
     * @param string $key Identifier for the data
     * @param mixed $default Default value to return if the key does not exist.
     * @return mixed The cached data, or default value if the data doesn't exist, has
     *   expired, or if there was an error fetching it
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $key = $this->_key($key);

        if (!$this->_init || $this->_setKey($key) === false) {
            return $default;
        }

        if ($this->_config['lock']) {
            $this->_File->flock(LOCK_SH);
        }

        $this->_File->rewind();
        $time = time();
        $cachetime = (int)$this->_File->current();

        if ($cachetime < $time) {
            if ($this->_config['lock']) {
                $this->_File->flock(LOCK_UN);
            }

            return $default;
        }

        $data = '';
        $this->_File->next();
        while ($this->_File->valid()) {
            $data .= $this->_File->current();
            $this->_File->next();
        }

        if ($this->_config['lock']) {
            $this->_File->flock(LOCK_UN);
        }

        $data = trim($data);

        if ($data !== '' && !empty($this->_config['serialize'])) {
            return unserialize($data);
        }

        return $data;
    }

    /**
     * Delete a key from the cache
     *
     * @param string $key Identifier for the data
     * @return bool True if the value was successfully deleted, false if it didn't
     *   exist or couldn't be removed
     */
    public function delete(string $key): bool
    {
        $key = $this->_key($key);

        if ($this->_setKey($key) === false || !$this->_init) {
            return false;
        }

        $path = $this->_File->getRealPath();
        unset($this->_File);

        if ($path === false) {
            return false;
        }

        // phpcs:disable
        return @unlink($path);
        // phpcs:enable
    }

    /**
     * Delete all values from the cache
     *
     * @return bool True if the cache was successfully cleared, false otherwise
     */
    public function clear(): bool
    {
        if (!$this->_init) {
            return false;
        }
        unset($this->_File);

        $this->_clearDirectory($this->_config['path']);

        $directory = new RecursiveDirectoryIterator(
            $this->_config['path'],
            FilesystemIterator::SKIP_DOTS,
        );
        $iterator = new RecursiveIteratorIterator(
            $directory,
            RecursiveIteratorIterator::SELF_FIRST,
        );
        $cleared = [];
        /** @var \SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile()) {
                unset($fileInfo);
                continue;
            }

            $realPath = $fileInfo->getRealPath();
            if (!$realPath) {
                unset($fileInfo);
                continue;
            }

            $path = $realPath . DIRECTORY_SEPARATOR;
            if (!in_array($path, $cleared, true)) {
                $this->_clearDirectory($path);
                $cleared[] = $path;
            }

            // possible inner iterators need to be unset too in order for locks on parents to be released
            unset($fileInfo);
        }

        // unsetting iterators helps releasing possible locks in certain environments,
        // which could otherwise make `rmdir()` fail
        unset($directory, $iterator);

        return true;
    }

    /**
     * Used to clear a directory of matching files.
     *
     * @param string $path The path to search.
     * @return void
     */
    protected function _clearDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $dir = dir($path);
        if (!$dir) {
            return;
        }

        $prefixLength = strlen($this->_config['prefix']);

        while (($entry = $dir->read()) !== false) {
            if (substr($entry, 0, $prefixLength) !== $this->_config['prefix']) {
                continue;
            }

            try {
                $file = new SplFileObject($path . $entry, 'r');
            } catch (Exception) {
                continue;
            }

            if ($file->isFile()) {
                $filePath = $file->getRealPath();
                unset($file);

                // phpcs:disable
                @unlink($filePath);
                // phpcs:enable
            }
        }

        $dir->close();
    }

    /**
     * Not implemented
     *
     * @param string $key The key to decrement
     * @param int $offset The number to offset
     * @return int|false
     * @throws \LogicException
     */
    public function decrement(string $key, int $offset = 1): int|false
    {
        throw new LogicException('Files cannot be atomically decremented.');
    }

    /**
     * Not implemented
     *
     * @param string $key The key to increment
     * @param int $offset The number to offset
     * @return int|false
     * @throws \LogicException
     */
    public function increment(string $key, int $offset = 1): int|false
    {
        throw new LogicException('Files cannot be atomically incremented.');
    }

    /**
     * Sets the current cache key this class is managing, and creates a writable SplFileObject
     * for the cache file the key is referring to.
     *
     * @param string $key The key
     * @param bool $createKey Whether the key should be created if it doesn't exists, or not
     * @return bool true if the cache key could be set, false otherwise
     */
    protected function _setKey(string $key, bool $createKey = false): bool
    {
        $groups = null;
        if ($this->_groupPrefix) {
            $groups = vsprintf($this->_groupPrefix, $this->groups());
        }
        $dir = $this->_config['path'] . $groups;

        if (!is_dir($dir)) {
            mkdir($dir, $this->_config['dirMask'], true);
        }

        $path = new SplFileInfo($dir . $key);

        if (!$createKey && !$path->isFile()) {
            return false;
        }
        if (
            !isset($this->_File) ||
            $this->_File->getBasename() !== $key ||
            $this->_File->valid() === false
        ) {
            $exists = is_file($path->getPathname());
            try {
                $this->_File = $path->openFile('c+');
            } catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_WARNING);

                return false;
            }
            unset($path);

            if (!$exists && !chmod($this->_File->getPathname(), (int)$this->_config['mask'])) {
                trigger_error(sprintf(
                    'Could not apply permission mask `%s` on cache file `%s`',
                    $this->_File->getPathname(),
                    $this->_config['mask'],
                ), E_USER_WARNING);
            }
        }

        return true;
    }

    /**
     * Determine if cache directory is writable
     *
     * @return bool
     */
    protected function _active(): bool
    {
        $dir = new SplFileInfo($this->_config['path']);
        $path = $dir->getPathname();
        $success = true;
        if (!is_dir($path)) {
            // phpcs:disable
            $success = @mkdir($path, $this->_config['dirMask'], true);
            // phpcs:enable
        }

        $isWritableDir = ($dir->isDir() && $dir->isWritable());
        if (!$success || ($this->_init && !$isWritableDir)) {
            $this->_init = false;
            trigger_error(sprintf(
                '%s is not writable',
                $this->_config['path'],
            ), E_USER_WARNING);
        }

        return $success;
    }

    /**
     * @inheritDoc
     */
    protected function _key(string $key): string
    {
        $key = parent::_key($key);

        return rawurlencode($key);
    }

    /**
     * Recursively deletes all files under any directory named as $group
     *
     * @param string $group The group to clear.
     * @return bool success
     */
    public function clearGroup(string $group): bool
    {
        unset($this->_File);

        $prefix = (string)$this->_config['prefix'];

        $directoryIterator = new RecursiveDirectoryIterator($this->_config['path']);
        $contents = new RecursiveIteratorIterator(
            $directoryIterator,
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        $filtered = new CallbackFilterIterator(
            $contents,
            function (SplFileInfo $current) use ($group, $prefix) {
                if (!$current->isFile()) {
                    return false;
                }

                $hasPrefix = $prefix === '' || str_starts_with($current->getBasename(), $prefix);
                if ($hasPrefix === false) {
                    return false;
                }

                return str_contains(
                    $current->getPathname(),
                    DIRECTORY_SEPARATOR . $group . DIRECTORY_SEPARATOR,
                );
            },
        );
        /** @var \SplFileInfo $object */
        foreach ($filtered as $object) {
            $path = $object->getPathname();
            unset($object);
            // phpcs:ignore
            @unlink($path);
        }

        // unsetting iterators helps releasing possible locks in certain environments,
        // which could otherwise make `rmdir()` fail
        unset($directoryIterator, $contents, $filtered);

        return true;
    }
}

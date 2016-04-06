<?php

namespace Hypweb\elFinderFlysystemDriverExt;

use Hypweb\elFinderFlysystemDriverExt\Plugin\HasDir;

/**
 * Extended version of elFinder driver for Flysytem (https://github.com/barryvdh/elfinder-flysystem-driver)
 *
 *- Supported itemID based system (such as flysystem-google-drive)
 *- Added hsaDir plugin for Flysystem
 *
 * @author Naoki Sawada
 * */
class Driver extends \Barryvdh\elFinderFlysystemDriver\Driver
{
    /**
     * @inheritdoc
     */
    public function clearstatcache()
    {
        parent::clearstatcache();

        // clear cached adapter cache
        if ($this->fscache) {
            $this->fscache->flush();
        }
    }

    /**
     * Prepare driver before mount volume.
     * Return true if volume is ready.
     *
     * @return bool
     **/
    protected function init()
    {
        parent::init();

        $this->fs->addPlugin(new HasDir());
        if ($this->fs->hasDir()) {
            $this->options['checkSubfolders'] = true;
        }

        return true;
    }

    /**
     * Get item path from FS method result, It supports item ID based file system
     *
     * @param boolean|array $result
     * @param string $requestPath
     *
     * @return string|false
     */
    protected function _resultPath($result, $requestPath)
    {
        if ($result === false) {
            return false;
        }
        if (! is_array($result)) {
            if ($this->fscache) {
                $this->fscache->flush();
            }
            $result = $this->fs->getMetaData($requestPath);
        }
        if ($result && isset($result['path'])) {
            $path = $result['path'];
            if ($this->fscache && $path !== $requestPath) {
                $this->fscache->storeMiss($requestPath);
            }
        } else {
            $path = ($result === false)? false : $requestPath;
        }
        return $path;
    }

    /**
     * Return true if path is dir and has at least one childs directory
     *
     * @param  string  $path  dir path
     * @return bool
     **/
    protected function _subdirs($path)
    {
        if ($this->fs->hasDir()) {
            $ret = $this->fs->hasDir($path);
        } else {
            $ret = parent::_subdirs($path);
        }
        return $ret;
    }

    /**
     * Create dir and return created dir path or false on failed
     *
     * @param  string  $path  parent dir path
     * @param  string  $name  new directory name
     * @return string|bool
     **/
    protected function _mkdir($path, $name)
    {
        $path = $this->_joinPath($path, $name);

        return $this->_resultPath($this->fs->createDir($path), $path);
    }

    /**
     * Create file and return it's path or false on failed
     *
     * @param  string  $path  parent dir path
     * @param string  $name  new file name
     * @return string|bool
     **/
    protected function _mkfile($path, $name)
    {
        $path = $this->_joinPath($path, $name);

        return $this->_resultPath($this->fs->write($path, ''), $path);
    }

    /**
     * Copy file into another file
     *
     * @param  string  $source     source file path
     * @param  string  $target  target directory path
     * @param  string  $name       new file name
     * @return string|bool
     **/
    protected function _copy($source, $target, $name)
    {
        $path = $this->_joinPath($target, $name);

        return $this->_resultPath($this->fs->copy($source, $path), $path);
    }

    /**
     * Move file into another parent dir.
     * Return new file path or false.
     *
     * @param  string  $source  source file path
     * @param  string  $target  target dir path
     * @param  string  $name    file name
     * @return string|bool
     **/
    protected function _move($source, $target, $name)
    {
        $path = $this->_joinPath($target, $name);

        return $this->_resultPath($this->fs->rename($source, $path), $path);
    }

    /**
     * Create new file and write into it from file pointer.
     * Return new file path or false on error.
     *
     * @param  resource  $fp   file pointer
     * @param  string    $dir  target dir path
     * @param  string    $name file name
     * @param  array     $stat file stat (required by some virtual fs)
     * @return bool|string
     **/
    protected function _save($fp, $dir, $name, $stat)
    {
        $path = $this->_joinPath($dir, $name);
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $config = [];
        if (isset(self::$mimetypes[$ext])) {
            $config['mimetype'] = self::$mimetypes[$ext];
        }

        return $this->_resultPath($this->fs->putStream($path, $fp, $config), $path);
    }
}

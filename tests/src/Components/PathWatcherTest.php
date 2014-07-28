<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace DreamFactory\Platform\Components;

use DreamFactory\Platform\Enums\INotify;

/**
 * Path/File watching object
 */
class PathWatcherTest extends \PHPUnit_Framework_TestCase
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var string
     */
    protected static $_testPath;
    /**
     * @var string
     */
    protected static $_testFile;
    /**
     * @var PathWatcher
     */
    protected $_watcher;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param string $dir
     *
     * @return bool
     */
    public static function rmdir_recursive( $dir )
    {
        $_files = array_diff( scandir( $dir ), array('.', '..') );

        foreach ( $_files as $_file )
        {
            $_filePath = $dir . '/' . $_file;

            if ( is_dir( $_filePath ) )
            {
                static::rmdir_recursive( $_filePath );
            }
            else
            {
                unlink( $_filePath );
            }
        }

        return rmdir( $dir );
    }

    /**
     * @covers PathWatcher::available
     */
    public static function setUpBeforeClass()
    {
        PathWatcher::available();

        static::$_testPath = getcwd() . '/path.watcher.test.dir';
        static::$_testFile = static::$_testPath . '/test.file.txt';

        if ( static::$_testPath && is_dir( static::$_testPath ) )
        {
            static::rmdir_recursive( static::$_testPath );
        }

        mkdir( static::$_testPath );

        file_put_contents( static::$_testFile, time() );

        parent::setUpBeforeClass();
    }

    public static function tearDownAfterClass()
    {
        if ( is_dir( static::$_testPath ) )
        {
            static::rmdir_recursive( static::$_testPath );
        }

        parent::tearDownAfterClass();
    }

    protected function setUp()
    {
        parent::setUp();

        $this->_watcher = new PathWatcher();
    }

    /**
     * Watch a file/path
     * @covers PathWatcher::watch
     * @covers PathWatcher::unwatch
     * @covers PathWatcher::checkForEvents
     *
     * @return int The ID of this watch
     */
    public function testPathWatch()
    {
        $this->_watchTest( static::$_testPath );
    }

    /**
     * Watch a file/path
     * @covers PathWatcher::watch
     * @covers PathWatcher::unwatch
     * @covers PathWatcher::checkForEvents
     *
     * @return int The ID of this watch
     */
    public function testFileWatch()
    {
        $this->_watchTest( static::$_testFile );
    }

    /**
     * Watch a file/path
     * @covers PathWatcher::watch
     * @covers PathWatcher::unwatch
     * @covers PathWatcher::checkForEvents
     *
     * @param string $path /path/to/dir/or/file to watch
     *
     * @return int The ID of this watch
     */
    protected function _watchTest( $path )
    {
        $_id = $this->_watcher->watch( $path, INotify::IN_MODIFY | INotify::IN_ATTRIB, true );

        $this->assertTrue( false !== $_id );

        //  Make a change
        if ( is_dir( $path ) )
        {
            $path .= '/watch.test.path';
            mkdir( $path, 0777, true );

            $path .= '/test.file.txt';
        }

        //  change the file
        file_put_contents( $path, time() );

        //  Check for a change...
        $_changes = $this->_watcher->checkForEvents( false );

        $this->assertTrue( !empty( $_changes ) );
        echo count( $_changes ) . ' change(s) detected.' . PHP_EOL;

        //  Unwatch the file
        $this->assertTrue( $this->_watcher->unwatch( $path ) );
    }
}

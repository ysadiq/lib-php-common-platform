<?php
namespace DreamFactory\Platform\Components;

use Doctrine\Common\Cache\PhpFileCache;
use Kisma\Core\Utility\Storage;

/**
 * PlatformFileCache
 */
class PlatformFileCache extends PhpFileCache
{
    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * {@inheritdoc}
     */
    protected function doFetch( $id )
    {
        return parent::doFetch( $id );
//        $filename = $this->getFilename( $id );
//
//        if ( !is_file( $filename ) )
//        {
//            return false;
//        }
//
//        $_value = Storage::defrost( file_get_contents( $filename ) );
//
//        if ( !is_array( $_value ) || ( $_value['lifetime'] !== 0 && $_value['lifetime'] < time() ) )
//        {
//            return false;
//        }
//
//        return $_value['data'];
    }

//    /**
//     * {@inheritdoc}
//     */
//    protected function doContains( $id )
//    {
//        if ( false === ( $_value = $this->doFetch( $id ) ) )
//        {
//            return false;
//        }
//
//        if ( $_value['lifetime'] !== 0 && $_value['lifetime'] < time() )
//        {
//            return false;
//        }
//
//        return 0 === $_value['lifetime'] || $_value['lifetime'] > time();
//    }

    /**
     * {@inheritdoc}
     */
    protected function doSave( $id, $data, $lifeTime = 0 )
    {
        return parent::doSave( $id, $data, $lifeTime );
        
//        if ( $lifeTime > 0 )
//        {
//            $lifeTime = time() + $lifeTime;
//        }
//
//        $_fileName = $this->getFilename( $id );
//        $_path = dirname( $_fileName );
//
//        if ( !is_dir( $_path ) )
//        {
//            mkdir( $_path, 0777, true );
//        }
//
//        $_value = array(
//            'lifetime' => $lifeTime,
//            'data'     => $data
//        );
//
//        $_storeValue = Storage::freeze( $_value );
//
//        return false !== file_put_contents( $_fileName, $_storeValue );
    }
}

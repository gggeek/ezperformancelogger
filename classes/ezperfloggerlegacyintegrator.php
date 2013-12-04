<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2012-2013
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

class eZPerfLoggerLegacyIntegrator
{
    /// @var Symfony\Component\DependencyInjection\ContainerInterface
    protected static $kernel;

    /**
     * This function tells use if we're running in ez4 or ez5 context. IT IS A HACK!!!
     * For speed, since we still focus on ez4, there is a constant to define when you are not using a pure-legacy stack:
     * EZPERFLOGGER5ENABLED
     * you can put it f.e. in index_profiling.php
     *
     * @return bool
     */
    static function isLegacyContext()
    {
        /// maybe using ../../... is faster than dirname, but symlinks might break with it? to test...
        return( !defined( 'EZPERFLOGGER5ENABLED' ) || realpath( getcwd() ) == realpath( dirname( dirname( dirname( __DIR__ . '/../../..' ) ) ) ) );
    }

    /**
     * We do runtikme checking to avoid compilation problems in pure ez4 stacks
     * @param Symfony\Component\HttpKernel\HttpKernelInterface $kernel
     * @throws RuntimeException
     */
    static function init( $kernel )
    {
        if ( !is_a( $kernel, 'Symfony\Component\HttpKernel\HttpKernelInterface' ) )
        {
            throw new RuntimeException( 'kernel object does not implement desired interface' );
        }
        self::$kernel = $kernel;
    }

    /**
     * @return Symfony\Component\DependencyInjection\ContainerInterface
     */
    public static function getContainer()
    {
        if ( self::$kernel == null )
        {
            throw new RuntimeException( 'container not injected' );
        }
        return self::$kernel->getContainer();
    }
} 
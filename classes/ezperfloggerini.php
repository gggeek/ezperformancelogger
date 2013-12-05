<?php
/**
 * @author G. Giunta
 * @copyright (C) eZ Systems AS 2013
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

/**
 * A class which helps isolating us from ini/yaml config pains (read: allow code to run in ez5 context and do not
 * switch back to ez4 context just to read a single setting)
 *
 * It duplicates code already found in ez5 kernel, but it is supposed to work in pure-legacy mode as well
 *
 * NB: ez5 version (reads first the ez5 setting, then chained gthe ez4 setting):
 * $resolver = $this->getConfigResolver();
 * $robotUserId = $resolver->getParameter( 'ImportSettings.RobotUserID', 'sqliimport' );
 *
 * corresponding to
 *
 * parameters:
 *     # Namespace is sqliimport (formerly sqliimport.ini), scope is defined to ezdemo_site siteaccess
 *     sqliimport.ezdemo_site.ImportSettings.RobotUserID: 14
 */
class eZPerfLoggerINI
{
    protected static $configResolver;

    static function variable( $group, $var, $file='ezperformancelogger.ini' )
    {
        if ( eZPerfLoggerLegacyIntegrator::isLegacyContext() )
        {
            $ini = eZINI::instance( $file );
            return $ini->variable( $group, $var );
        }
        else
        {
            $resolver = self::getConfigResolver();
            return $resolver->getParameter( $group . '.' . $var, str_replace( '.ini', '', $file ) );
        }
    }

    static function hasVariable( $group, $var, $file='ezperformancelogger.ini' )
    {
        if ( eZPerfLoggerLegacyIntegrator::isLegacyContext() )
        {
            $ini = eZINI::instance( $file );
            return $ini->hasVariable( $group, $var );
        }
        else
        {
            $resolver = self::getConfigResolver();
            return $resolver->hasParameter( $group . '.' . $var, str_replace( '.ini', '', $file ) );
        }
    }

    static function variableMulti( $group, array $vars, $file='ezperformancelogger.ini' )
    {
        if ( eZPerfLoggerLegacyIntegrator::isLegacyContext() )
        {
            $ini = eZINI::instance( $file );
            return $ini->variableMulti( $group, $vars );
        }
        else
        {
            $resolver = self::getConfigResolver();
            $out = array();
            foreach( $vars as $key => $var )
            {
                $out[$key] = $resolver->getParameter( $group . '.' . $var, str_replace( '.ini', '', $file ) );
            }
            return $out;
        }
    }

    /**
     * A poor attempt at lazy-loading the config resolver.
     * Q: Should we avoid storing it in a static var and just ask it every time from the kernel?
     * @return mixed
     * @throws RuntimeException
     */
    protected static function getConfigResolver()
    {
        if ( self::$configResolver == null )
        {
            self::init();
        }
        if ( self::$configResolver == null )
        {
            throw new RuntimeException( 'resolver not injected' );
        }
        return self::$configResolver;
    }

    public static function init()
    {
        $container = eZPerfLoggerLegacyIntegrator::getContainer();
        self::setConfigResolver( $container->get( 'ezpublish.config.resolver' ) );
    }

    /**
     * Not too sure if it's gonna be needed... in which case do we want external code to allow to refresh the configResolver?
     * @param $resolver
     * @throws RuntimeException
     */
    public static function setConfigResolver( $resolver )
    {
        if ( !is_a( $resolver, 'eZ\Publish\Core\MVC\ConfigResolverInterface' ) )
        {
            throw new RuntimeException( 'resolver object does not implement desired interface' );
        }
        self::$configResolver = $resolver;
    }

}

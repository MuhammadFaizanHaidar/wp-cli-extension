<?php

defined( 'ABSPATH' ) OR exit;


if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) return;

/**
 * waashero_wp_cli Class adds external commands to wp cli.
 *
 * waashero_wp_cli adds external commands to wp-cli which overrides htaccess and wp-config.php.
 *
 * @version 1.0
 * @author  Faizan
 */

class Waashero_Wp_Cli {
    
    /**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string  $version    The current version of this plugin.
	 */
	private $version;
    /**
	 * Holds the class instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Class $instance    Holds the class instance.
	 */
    private static $instance = null;

	/**
	 * Get instance.
     * The object is created from within the class itself.
	 * only if the class has no instance.
     * 
	 * @since  1.0.0
	 * @return Class instance
	 */
	public static function getInstance( $plugin_name, $version ) {
		if ( self::$instance == null ) {
			self::$instance = new Waashero_Wp_Cli( $plugin_name, $version );
		}
	
		return self::$instance;
	}	
	/**
	 * Initialize the class and set its properties.
	 *
	 * @since  1.0.0
	 * @param  string  $plugin_name  The name of the plugin.
	 * @param  string  $version      The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
        $this->namespace   = $this->plugin_name . '/v' . intval( $this->version );
        
	}

    /**
     * Overrides Htaccess file.
     *
     * @since 1.0.0
     * @param string $token token to store for API validation.
     * @param string $domain domain to map.
     * @return mix void, bool
     */
    public function override_htaccess_file( $token, $domain ) {
        $insertion = array( '', '## Do not edit the contents of this block! ##' );
        array_push( $insertion, 'RewriteEngine On' );
        $whitelisted = get_site_option( 'waashero_dynamic_ip_whitelist' );
        if ( ! $whitelisted || ! is_array( $whitelisted ) ) {
            $whitelisted = array();
        }

        foreach ( $whitelisted as $key => $value ) {            
            array_push(
                $insertion,
                'SetEnvIfNoCase Remote_Addr ^' . $key . '$ MODSEC-OFF'
            );
            array_push(
                $insertion,
                'RewriteCond %{REMOTE_ADDR} ^' . str_replace( '.', '\.', $key ) . '$',
                'RewriteRule .* - [E=noconntimeout:1]'
            );
        }

        array_push( $insertion, '' );

        $marker   = 'WAASHERO';
        $uploads  = wp_upload_dir( null, false );
        $dir      = trim( ABSPATH, '/' );
        $filename = '';
        if ( file_exists( $dir . '\.htaccess' ) ) {
            $filename = $dir . '\.htaccess';
        } else {
            WP_CLI::error( __( 'Wrong path', 'waashero' ) );
        }
        

		if ( ! file_exists( $filename ) ) {
			if ( ! is_writable( dirname( $filename ) ) ) {
				return false ;
			}
		
			try {
				touch( $filename ) ;
			}
			catch ( ErrorException $ex ){
				return false ;
			}
			
		} elseif ( ! is_writeable( $filename ) ) {
			return false ;
		}

		if ( ! is_array( $insertion ) ) {
			$insertion = explode( "\n", $insertion ) ;
		}

		$start_marker = "# BEGIN {$marker}" ;
		$end_marker   = "# END {$marker}" ;

		$fp = fopen( $filename, 'r+' ) ;
		if ( ! $fp ) {
			return false ;
		}

		// Attempt to get a lock. If the filesystem supports locking, this will block until the lock is acquired.
		flock( $fp, LOCK_EX ) ;

		$lines = array() ;
		while ( ! feof( $fp ) ) {
			$lines[] = rtrim( fgets( $fp ), "\r\n" ) ;
		}

		// Split out the existing file into the preceding lines, and those that appear after the marker.
		$pre_lines = $post_lines = $existing_lines = array() ;
		$found_marker = $found_end_marker = false ;
		foreach ( $lines as $line ) {
			if ( ! $found_marker && false !== strpos( $line, $start_marker ) ) {
				$found_marker = true ;
				continue ;
			} elseif ( ! $found_end_marker && false !== strpos( $line, $end_marker ) ) {
				$found_end_marker = true ;
				continue ;
			}

			if ( ! $found_marker ) {
				$pre_lines[] = $line ;
			} elseif ( $found_marker && $found_end_marker ) {
				$post_lines[] = $line ;
			} else {
				$existing_lines[] = $line ;
			}
		}

		// Check to see if there was a change.
		if ( $existing_lines === $insertion ) {
			flock( $fp, LOCK_UN ) ;
			fclose( $fp ) ;

			return true ;
		}

		// Generate the new file data.
        $new_file_data = implode(
            "\n",
            array_merge(
                $pre_lines,
                array( $start_marker ),
                $insertion,
                array( $end_marker ),
                $post_lines
            )
        );


		// Write to the start of the file, and truncate it to that length.
		fseek( $fp, 0 ) ;
		$bytes = fwrite( $fp, $new_file_data ) ;
		if ( $bytes ) {
			ftruncate( $fp, ftell( $fp ) ) ;
		}
		fflush( $fp ) ;
		flock( $fp, LOCK_UN ) ;
		fclose( $fp ) ;
    }

    /**
     * Overrides WP Config File.
     * 
     * @since 1.0.0
     * @param string $token token to store for API validation.
     * @param string $domain domain to map.
     * @return void
     */
    public function override_wp_config_file( $token, $domain ) {
        if ( file_exists ( ABSPATH . 'wp-config.php' )
            && is_writable ( ABSPATH . 'wp-config.php' ) ) {
            $this->wp_config_put( '', $token, $domain );
        } elseif ( file_exists ( dirname ( ABSPATH ) . '/wp-config.php' )
            && is_writable ( dirname ( ABSPATH ) . '/wp-config.php' ) ) {
            $this->wp_config_put( '/', $token, $domain );
        } else { 
            WP_CLI::error( __( 'Wrong path', 'waashero' ) );
        }
    }

    /**
     * Puts token and domain detials to wp-config.php
     *
     * @since 1.0.0
     * @param string $slash holds slahs or ''
     * @param string $token token to store for API validation.
     * @param string $domain domain to map.
     * @return void
     */
    public function wp_config_put( $slash = '', $token, $domain ) {
        $config = file_get_contents( ABSPATH . 'wp-config.php' );
        $config = preg_replace( "/^([\r\n\t ]*)(\<\?)(php)?/i", "<?php define( 'WP_Token', $token );", $config );
        $config = preg_replace( "/^([\r\n\t ]*)(\<\?)(php)?/i", "<?php define( 'WH_Domain', $domain );", $config );
        file_put_contents( ABSPATH . $slash . 'wp-config.php', $config );
    }
   
   
}

$WP_Cli_Extension = Waashero_Wp_Cli::getInstance( 'wp-cli-extension', '1.0.0' );
// Register CLI cmd
if ( method_exists( 'WP_CLI', 'add_command' ) ) {
    WP_CLI::add_command( 'override_htaccess_file', $WP_Cli_Extension );
    WP_CLI::add_command( 'override_wp_config_file', $WP_Cli_Extension );
}
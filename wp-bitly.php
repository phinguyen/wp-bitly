<?php
/**
 * WP Bit.ly
 *
 * This plugin can be used to generate short links for your websites posts, pages,
 * and any other custom post type that you would like it to interact with. Extremely
 * compact and easy to set up, give it your Bit.ly oAuth token and you're set!
 *
 * ಠ_ಠ
 *
 * @package   wp-bitly
 * @author    Mark Waterous <mark@watero.us>
 * @author    Chip Bennett
 * @license   GPL-2.0+
 * @link      http://wordpress.org/plugins/wp-bitly
 * @copyright 2014 Mark Waterous
 *
 * @wordpress-plugin
 * Plugin Name:       WP Bit.ly
 * Plugin URI:        http://wordpress.org/plugins/wp-bitly
 * Description:       Whether you're sharing links on social media like Twitter, or sending someone a link from your smart phone, short links make life much easier. WP Bit.ly simplifies the process for you.
 * Version:           2.0-working
 * Author:            <a href="http://mark.watero.us/">Mark Waterous</a> & <a href="http://www.chipbennett.net/">Chip Bennett</a>
 * Text Domain:       wp-bitly
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/mwaterous/wp-bitly
 */


if ( ! defined( 'WPINC' ) )
    die;


define( 'WPBITLY_VERSION',  '2.0' );

define( 'WPBITLY_FILE', __FILE__ );
define( 'WPBITLY_DIR',  WP_PLUGIN_DIR.'/'.basename( dirname( __FILE__ ) ) );
define( 'WPBITLY_URL',  plugins_url().'/'.basename( dirname( __FILE__ ) ) );



/**
 * The primary controller class for everything wonderful that WP Bit.ly does.
 * We're not sure entirely what that means yet; if you figure it out, please
 * let us know and we'll say something snazzy about it here.
 *
 * @TODO: Update the class phpdoc description to say something snazzy.
 *
 * @package wp-bitly
 * @author  Mark Waterous <mark@watero.us>
 */
class wpbitly
{

    /**
     * @var $_instance An instance of ones own instance
     */
    private static $_instance;

    /**
     * @var string Static-ly accessible and globally similar
     */
    public static $slug = 'wp-bitly';

    /**
     * @var array Options, everybody has them.
     */
    public $options = array();


    /**
     * This creates and returns a single instance of wpbitly.
     *
     * If you haven't seen a singleton before, visit any Starbucks; they're the ones sitting on expensive laptops
     * in the corner drinking a macchiato and pretending to write a book. They'll always be singletons.
     *
     * @since 2.0
     * @static
     * @uses wpbitly::populate_options() To create our options array.
     * @uses wpbitly::check_for_upgrade() You run your updates, right?
     * @uses wpbitly::includes_files() To do something that sounds a lot like what it sounds like.
     * @uses wpbitly::action_filters() To set up any necessary WordPress hooks.
     * @return An|wpbitly
     */
    public static function get_in()
    {

        if ( !isset( self::$_instance ) && !( self::$_instance instanceof wpbitly ) )
        {
            self::$_instance = new self;
            self::$_instance->populate_options();
            self::$_instance->check_for_upgrade();
            self::$_instance->include_files();
            self::$_instance->action_filters();
        }

        return self::$_instance;

    }


    /**
     * This handles setting up some defaults and populating the local property
     * for use elsewhere around the plugin. Don't modify options without making sure
     * they're up to date.
     *
     * @since 2.0
     * @return void
     */
    public function populate_options()
    {

        $defaults = apply_filters( 'wpbitly_default_options', array(
            'version'       => WPBITLY_VERSION,
            'oauth_token'   => '',
            'post_types'    => array( 'post', 'page' ),
        ) );

        $this->options = wp_parse_args(
            get_option( 'wpbitly-options' ),
            $defaults );

    }


    /**
     * Simple wrapper for making sure everybody (who actually updates their plugins) is
     * current and that we don't just delete all their old data.
     *
     * @since 2.0
     * @return void
     */
    public function check_for_upgrade()
    {
        // We don't need to compare versions this time round, because we've moved the dash up a smidge.
        $upgrade_needed = get_option( 'wpbitly_options' );
        if ( $upgrade_needed !== false )
        {

            // If for some reason that array doesn't exist, then we don't have anything to work on.
            if ( array_key_exists( 'post_types', $upgrade_needed ) )
            {
                $post_types = apply_filters( 'wpbitly_allowed_post_types', get_post_types( array( 'public' => true ) ) );

                foreach ( $upgrade_needed['post_types'] as $key => $pt )
                {
                    if ( ! in_array( $pt, $post_types ) )
                        unset( $upgrade_needed['post_types'][$key] );
                }

                $this->options['post_types'] = $upgrade_needed['post_types'];
            }

            delete_option( 'wpbitly_options' ); // That's it. They've all changed.

        }

    }


    /**
     * WP Bitly is a pretty big plugin. Without this function, we'd probably include things
     * in the wrong order, or not at all, and cold wars would erupt all over the planet.
     *
     * @since 2.0
     * @return void
     */
    public function include_files()
    {

        if ( is_admin() )
            require_once( WPBITLY_DIR . '/includes/admin/class.wpbitly-admin.php' );

        require_once( WPBITLY_DIR . '/includes/functions.php' );

    }


    /**
     * Hook any necessary WordPress actions or filters that we'll be needing to communicate
     * with in order to make the plugin work its magic. This method also registers our
     * super amazing slice of shortcode.
     *
     * @since 2.0
     * @TODO Instead of arbitrarily and invisibly deactivating the Jetpack module, it seems polite to have this ask.
     * @TODO Until that gets added, move over Jetpack, WP Bit.ly owns this corner.
     * @return void
     */
    public function action_filters()
    {

        // Yada, yada (nothing to see here folks).
        add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

        $basename = plugin_basename( __FILE__ );
        add_filter( 'plugin_action_links_' . $basename, array( $this, 'add_action_links' ) );

        // Bingo, bango (ahhh, there's the stuff).
        if ( isset( $this->options['oauth_token'] ) && $this->options['oauth_token'] )
        {
            add_action( 'save_post', 'wpbitly_generate_shortlink' );
            add_filter( 'pre_get_shortlink', 'wpbitly_get_shortlink' );
        }

        add_shortcode( 'wpbitly', 'wpbitly_shortcode' );

        // @TODO This is where we're being impolite.
        if( class_exists( 'Jetpack' ) )
        {
            add_filter( 'jetpack_get_available_modules', '_sorry_thats_life_wpme' );
            function _sorry_thats_life_wpme( $modules )
            {
                unset( $modules['shortlinks'] );
                return $modules;
            }

        }

    }


    /**
     * Add a settings link to the plugins page so people can figure out where we are.
     *
     * @since 2.0
     * @param $links An array returned by WordPress with our plugin action links
     * @return array The slightly modified 'rray.
     */
    public function add_action_links( $links )
    {

        return array_merge(
            array(
                'settings' => '<a href="' . admin_url( 'options-writing.php' ) . '">' . __( 'Settings', 'wp-bitly' ) . '</a>'
            ),
            $links
        );

    }


    /**
     * This would be much easier if we all spoke Esperanto (or Old Norse).
     *
     * @since 2.0
     * @return void
     */
    public function load_plugin_textdomain()
    {

        $lang_dir = WPBITLY_DIR . '/languages/';
        $lang_dir = apply_filters( 'wpbitly_languages_directory', $lang_dir );

        $locale = apply_filters( 'plugin_locale', get_locale(), self::$slug );
        $mofile = $lang_dir . self::$slug . $locale . '.mo';

        if ( file_exists( $mofile ) )
            load_textdomain( self::$slug, $mofile );
        else
            load_plugin_textdomain( self::$slug, false, $lang_dir );

    }


}


/**
 * Call this in place of wpbitly::get_in()
 * It's shorthand.
 * Makes life easier.
 * In fact, the phpdoc block is bigger than the function itself.
 *
 * @return An|wpbitly
 */
function wp_bitly()
{
    return wpbitly::get_in();
}

wp_bitly();

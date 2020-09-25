<?php
/**
 * @package   wp-bitly
 * @author    Temerity Studios <info@temeritystudios.com>
 * @author    Chip Bennett
 * @license   GPL-2.0+
 * @link      http://wordpress.org/plugins/wp-bitly
 */

/**
 * Write to a WP Bitly debug log file
 *
 * @since 2.2.3
 * @param string $towrite The data to be written
 * @param string $message A string identifying this log entry
 * @param bool $bypass If true, will log regardless of user setting
 */
function wpbitly_debug_log($towrite, $message, $bypass = true)
{

    $wpbitly = wpbitly();

    if (!$wpbitly->getOption('debug') || !$bypass) {
        return;
    }

    $log = fopen(WPBITLY_LOG, 'a');

    fwrite($log, '# [ ' . date('F j, Y, g:i a') . " ]\n");
    fwrite($log, '# [ ' . $message . " ]\n\n");
    fwrite($log, (is_array($towrite) ? print_r($towrite, true) : var_export($towrite, 1)));
    fwrite($log, "\n\n\n");

    fclose($log);

}

/**
 * Retrieve the requested API endpoint.
 *
 * @since 2.6
 * @param   string $api_call Which endpoint do we need?
 * @return  string Returns the URL for our requested API endpoint
 */
function wpbitly_api($api_call)
{

    $api_links = array(
        'link/qr' => 'bitlinks/%1$s/qr',
        'link/clicks' => 'bitlinks/%1$s/clicks',
        'link/clicks/sum' => 'bitlinks/%1$s/clicks/summary',
        'link/refer' => 'bitlinks/%1$s/referrers',
    );

    if (!array_key_exists($api_call, $api_links)) {
        trigger_error(__('WP Bitly Error: No such API endpoint.', 'wp-bitly'));
    }

    return WPBITLY_BITLY_API . $api_links[ $api_call ];
}

/**
 * Retrieve the POST requested API endpoint.
 *
 * @since 2.6
 * @param   string $api_call Which endpoint do we need?
 * @param   string $group_guid Which group guid do we need?
 * @param   string $domain Default is bit.ly or custom domain?
 * @return  string Returns the body array for our requested API endpoint
 */
function wpbitly_api_v4($api_call, $group_guid, $domain)
{
    $api_links = array(
        'shorten' => array(
            'long_url' => '',
            'domain'  => $domain,
            'group_guid' => $group_guid
        ),
        'bitlinks' => array(
            'long_url' => '',
            'domain'  => $domain,
            'group_guid' => $group_guid,
            'title'  => ''
        ),
        'expand' => array(
            'bitlink_id' => '',
        ),
    );

    if (!array_key_exists($api_call, $api_links)) {
        trigger_error(__('WP Bitly Error: No such API endpoint.', 'wp-bitly'));
    }

    return $api_links[ $api_call ];
}

/**
 * WP Bitly wrapper for wp_remote_get that verifies a successful response.
 *
 * @since   2.6
 * @param   string $url The API endpoint we're contacting
 * @param   string $token API v4 require token in GET request
 * @return  bool|array False on failure, array on success
 */

function wpbitly_get($url,$token)
{

    $the = wp_remote_get($url, 
        array(
            'timeout' => '30',
            'headers' => array(
                'Authorization' => 'Bearer '.$token,
                'Content-Type'  => 'application/json'
            )
    ));

    if (is_array($the) && '200' == $the['response']['code']) {
        return json_decode($the['body'], true);
    }

    return false;
}

/**
 * WP Bitly wrapper for wp_remote_post that verifies a successful response.
 *
 * @since   2.6
 * @param   string $endpoint The API endpoint we're contacting
 * @param   string $token The API token
 * @param   string $args Body of request
 * @return  bool|array False on failure, array on success
 */

function wpbitly_post($endpoint,$token,$args = array())
{

    $body = wp_json_encode( $args , JSON_UNESCAPED_SLASHES);
    $options = [
        'body'        => $body,
        'headers'     => array(
            'Authorization' => 'Bearer '.$token,
            'Content-Type'  => 'application/json'
        ),
        'timeout'     => 30,
        'sslverify' => true,
    ];
    $the = wp_remote_post(WPBITLY_BITLY_API . $endpoint, $options);
    if (is_array($the) && '200' == $the['response']['code']) {
        return json_decode($the['body'], true);
    }

    return false;
}

/**
 * Generates the shortlink for the post specified by $post_id.
 *
 * @since   2.6
 * @param   int $post_id Identifies the post being shortened
 * @param   bool $bypass True bypasses the link expand API check
 * @return  bool|string  Returns the shortlink on success
 */

function wpbitly_generate_shortlink($post_id, $bypass = false)
{

    $wpbitly = wpbitly();

    // Token hasn't been verified, bail
    if (!$wpbitly->isAuthorized()) {
        return false;
    }

    // Verify this is a post we want to generate short links for
    if (!in_array(get_post_type($post_id), $wpbitly->getOption('post_types')) ||
        !in_array(get_post_status($post_id), array('publish', 'future', 'private'))) {
        return false;
    }

    // We made it this far? Let's get a shortlink
    $permalink = get_permalink($post_id);
    $shortlink = get_post_meta($post_id, '_wpbitly', true);
    $token = $wpbitly->getOption('access_token');
    $domain = $wpbitly->getOption('default_domain');
    $group_guid = $wpbitly->getOption('group_guid');

    if (!empty($shortlink) && !$bypass) {
        $body = array_replace(wpbitly_api_v4('expand', $group_guid, $domain), array("bitlink_id" => $shortlink ));
        $response = wpbitly_post('expand',$token,$body);

        wpbitly_debug_log($response, '/expand/');

        if (is_array($response) && $permalink == $response['long_url']) {
            update_post_meta($post_id, '_wpbitly', $shortlink);
            return $shortlink;
        }
    }
    $body = array_replace(wpbitly_api_v4('bitlinks', $group_guid, $domain), array("long_url" => $permalink, "title" => get_the_title($post_id)));
    
    $response = wpbitly_post('bitlinks',$token,$body);
    
    wpbitly_debug_log($response, '/bitlinks/');

    if (is_array($response)) {
        $shortlink = $response['id'];
        update_post_meta($post_id, '_wpbitly', $shortlink);
    }

    return ($shortlink) ? $shortlink : false;
}

/**
 * Short circuits the `pre_get_shortlink` filter.
 *
 * @since   0.1
 * @param   bool $original False if no shortlink generated
 * @param   int $post_id Current $post->ID, or 0 for the current post
 * @return  string|mixed A shortlink if generated, $original if not
 */
function wpbitly_get_shortlink($original, $post_id)
{

    $wpbitly = wpbitly();
    $shortlink = false;

    // Avoid creating shortlinks during an autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // or for revisions
    if (wp_is_post_revision($post_id)) {
        return;
    }

    if (0 == $post_id) {
        $post = get_post();
        if (is_object($post) && !empty($post->ID)) {
            $post_id = $post->ID;
        }
    }

    if ($post_id) {
        $shortlink = get_post_meta($post_id, '_wpbitly', true);

        if (!$shortlink) {
            $shortlink = wpbitly_generate_shortlink($post_id);
        }
    }
    return ($shortlink) ? $shortlink : $original;
}

/**
 * This can be used as a direct php call within a theme or another plugin. It also handles the [wpbitly] shortcode.
 *
 * @since   0.1
 * @param   array $atts Default shortcode attributes
 */
function wpbitly_shortlink($atts = array())
{

    $output = '';

    $post = get_post();
    $post_id = (is_object($post) && !empty($post->ID)) ? $post->ID : '';

    $defaults = array(
        'text' => '',
        'title' => '',
        'before' => '',
        'after' => '',
        'post_id' => $post_id
    );

    extract(shortcode_atts($defaults, $atts));
    if (!$post_id) {
        return $output;
    }

    $permalink = get_permalink($post_id);
    $shortlink = wpbitly_get_shortlink($permalink, $post_id);

    if (empty($text)) {
        $text = $shortlink;
    }

    if (empty($title)) {
        $title = the_title_attribute(array(
            'echo' => false
        ));
    }

    if (!empty($shortlink)) {
        $output = apply_filters('the_shortlink', sprintf('<a rel="shortlink" href="%s" title="%s">%s</a>', esc_url($shortlink), $title, $text), $shortlink, $text, $title);
        $output = $before . $output . $after;
    }

    return $output;
}

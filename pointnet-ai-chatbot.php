<?php
declare( strict_types=1 );
/**
 * Plugin Name: PointNet AI Chatbot – Virtual Assistant
 * Plugin URI:  https://www.pointnet.it/pointnet-ai-chatbot/
 * Description: AI-powered virtual assistant that responds to users using your WordPress site content.
 * Version:     1.8.5
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author:      PointNet
 * Author URI:  https://www.pointnet.it/
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pointnet-ai-chatbot
 * Domain Path: /languages
 * Update URI:  https://github.com/AlessioPellegrini/pointnet-ai-chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PN_AI_CHATBOT_VERSION', '1.8.5' );
define( 'PN_AI_CHATBOT_FILE', __FILE__ );
define( 'PN_AI_CHATBOT_DIR', plugin_dir_path( __FILE__ ) );
define( 'PN_AI_CHATBOT_URL', plugin_dir_url( __FILE__ ) );

function pn_ai_chatbot_activate_plugin(): void {
    flush_rewrite_rules();
    pn_ai_chatbot_create_logs_table();
    pn_ai_chatbot_create_fulltext_index();
    update_option( 'pn_ai_chatbot_ft_index_created', 1 );
}
register_activation_hook( __FILE__, 'pn_ai_chatbot_activate_plugin' );

/**
 * Crea la tabella personalizzata per i log chat e migra i dati esistenti.
 */
function pn_ai_chatbot_create_logs_table(): void {
    global $wpdb;

    $table_name = $wpdb->prefix . 'pn_ai_chatbot_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        timestamp DATETIME NOT NULL,
        provider VARCHAR(150) NOT NULL DEFAULT '',
        question TEXT NOT NULL,
        answer TEXT NOT NULL,
        duration DECIMAL(5,2) NOT NULL DEFAULT 0,
        tokens_in INT UNSIGNED NOT NULL DEFAULT 0,
        tokens_out INT UNSIGNED NOT NULL DEFAULT 0,
        tokens_total INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY idx_timestamp (timestamp)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    // Migrates existing logs from options to the new table
    $old_logs = get_option( 'pn_ai_chatbot_logs', array() );
    if ( ! empty( $old_logs ) && is_array( $old_logs ) ) {
        foreach ( $old_logs as $log ) {
            if ( is_array( $log ) && isset( $log['timestamp'] ) ) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'timestamp'    => $log['timestamp'],
                        'provider'     => $log['provider'] ?? '',
                        'question'     => $log['question'] ?? '',
                        'answer'       => $log['answer'] ?? '',
                        'duration'     => isset( $log['duration'] ) ? (float) $log['duration'] : 0,
                        'tokens_in'    => isset( $log['tokens_in'] ) ? (int) $log['tokens_in'] : 0,
                        'tokens_out'   => isset( $log['tokens_out'] ) ? (int) $log['tokens_out'] : 0,
                        'tokens_total' => isset( $log['tokens_total'] ) ? (int) $log['tokens_total'] : 0,
                    ),
                    array( '%s', '%s', '%s', '%s', '%f', '%d', '%d', '%d' )
                );
            }
        }
        delete_option( 'pn_ai_chatbot_logs' );
    }
}

/**
 * Creates the FULLTEXT index on wp_posts for MATCH...AGAINST search.
 * Called on plugin activation. Safe to call multiple times.
 */
function pn_ai_chatbot_create_fulltext_index(): void {
    global $wpdb;

    $index_name = 'pn_ai_chatbot_ft_idx';
    $table = $wpdb->posts;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Safe DDL operation, no caching required.
    $index_exists = $wpdb->get_results(
        "SHOW INDEX FROM {$table} WHERE Key_name = '{$index_name}'"
    );

    if ( ! empty( $index_exists ) ) {
        return;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fulltext index creation; table name is core wp_posts, not user input.
    $wpdb->query(
        "ALTER TABLE {$table} ADD FULLTEXT INDEX {$index_name} (post_title, post_content)"
    );
}

function pn_ai_chatbot_deactivate_plugin(): void {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'pn_ai_chatbot_deactivate_plugin' );

function pn_ai_chatbot_init(): void {

    if ( is_admin() ) {
        // Ensure FULLTEXT index exists (safe: idempotent check, runs once).
        if ( ! get_option( 'pn_ai_chatbot_ft_index_created' ) ) {
            pn_ai_chatbot_create_fulltext_index();
            update_option( 'pn_ai_chatbot_ft_index_created', 1 );
        }
        require_once PN_AI_CHATBOT_DIR . 'admin/settings-page.php';
        require_once PN_AI_CHATBOT_DIR . 'admin/flood-analyzer.php';
        new PointNet_AI_Chatbot_Admin_Settings();
    }
    
    // GitHub Release Updater (remove before WordPress.org submission).
    if ( file_exists( PN_AI_CHATBOT_DIR . 'includes/class-updater.php' ) && ( ! defined( 'PN_AI_CHATBOT_IS_WPORG' ) || ! PN_AI_CHATBOT_IS_WPORG ) ) {
        require_once PN_AI_CHATBOT_DIR . 'includes/class-updater.php';
        PointNet_AI_Chatbot_Updater::init();
    }

    require_once PN_AI_CHATBOT_DIR . 'frontend/chat-handler.php';
    new PointNet_AI_Chatbot_Frontend();
}
add_action( 'plugins_loaded', 'pn_ai_chatbot_init' );

function pn_ai_chatbot_add_settings_link( $links ): array {
    $settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=pointnet-ai-chatbot' ) ) . '">' . esc_html__( 'Settings', 'pointnet-ai-chatbot' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
$pn_ai_chatbot_plugin_file = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_{$pn_ai_chatbot_plugin_file}", 'pn_ai_chatbot_add_settings_link' );

function pn_ai_chatbot_plugin_row_meta( $links, $file ): array {
    if ( strpos( $file, 'pointnet-ai-chatbot.php' ) !== false ) {
        $website_link = '<a href="https://www.pointnet.it/pointnet-ai-chatbot/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Visit plugin site', 'pointnet-ai-chatbot' ) . '</a>';
        $support_link = '<a href="https://www.pointnet.it/contatta-la-web-agency-pointnet/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Support', 'pointnet-ai-chatbot' ) . '</a>';
        array_unshift( $links, $website_link );
        $links[] = $support_link;
    }
    return $links;
}
add_filter( 'plugin_row_meta', 'pn_ai_chatbot_plugin_row_meta', 10, 2 );

/**
 * Adds suggested privacy policy content for site administrators.
 */
function pn_ai_chatbot_add_privacy_policy_content(): void {
    if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
        return;
    }

    $content = sprintf(
        '<p>%s</p><h3>%s</h3><p>%s</p><h3>%s</h3><p>%s</p>',
        esc_html__( 'This site uses the PointNet AI Chatbot plugin to provide an interactive virtual assistant.', 'pointnet-ai-chatbot' ),
        esc_html__( 'What personal data we collect and why we collect it', 'pointnet-ai-chatbot' ),
        esc_html__( 'When visitors interact with the virtual assistant, their messages and questions are processed to generate replies and may be stored in the website database for customer support and quality assurance. IP addresses are hashed for rate limiting and are not stored in plain text.', 'pointnet-ai-chatbot' ),
        esc_html__( 'Where your data is sent', 'pointnet-ai-chatbot' ),
        esc_html__( 'User queries and relevant excerpted website content are securely transmitted to third-party AI service providers (such as OpenAI, Google Gemini, or OpenRouter) based on the configured settings, solely for the purpose of generating automated responses.', 'pointnet-ai-chatbot' )
    );

    wp_add_privacy_policy_content(
        __( 'PointNet AI Chatbot', 'pointnet-ai-chatbot' ),
        wp_kses_post( wpautop( $content, false ) )
    );
}
add_action( 'admin_init', 'pn_ai_chatbot_add_privacy_policy_content' );
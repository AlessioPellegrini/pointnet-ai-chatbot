<?php
declare( strict_types=1 );
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$pn_ai_chatbot_option_name = 'pn_ai_chatbot_settings';
$pn_ai_chatbot_options = get_option( $pn_ai_chatbot_option_name );

if ( is_array( $pn_ai_chatbot_options ) && isset( $pn_ai_chatbot_options['delete_on_uninstall'] ) && 1 == $pn_ai_chatbot_options['delete_on_uninstall'] ) {
    global $wpdb;

    delete_option( $pn_ai_chatbot_option_name );
    delete_option( 'pn_ai_chatbot_logs' );
    delete_option( 'pn_ai_chatbot_stop_words' );
    delete_option( 'pn_ai_chatbot_ft_index_created' );

    $pn_ai_chatbot_table_name = $wpdb->prefix . 'pn_ai_chatbot_logs';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query( "DROP TABLE IF EXISTS {$pn_ai_chatbot_table_name}" );

    // Drop FULLTEXT index if it was created
    $index_name = 'pn_ai_chatbot_ft_idx';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    $index_exists = $wpdb->get_results( "SHOW INDEX FROM {$wpdb->posts} WHERE Key_name = '{$index_name}'" );
    if ( ! empty( $index_exists ) ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query( "ALTER TABLE {$wpdb->posts} DROP INDEX {$index_name}" );
    }

    // Clean up all plugin transients
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_pn_ai_%' OR option_name LIKE '_transient_timeout_pn_ai_%'" );
}
<?php
declare( strict_types=1 );
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PointNet_AI_Chatbot_Flood_Analyzer {

    public function __construct() {
        add_action( 'wp_ajax_pn_ai_chatbot_analyze_flood_words', array( $this, 'ajax_analyze_flood_words' ) );
        add_action( 'wp_ajax_pn_ai_chatbot_save_manual_stops', array( $this, 'ajax_save_manual_stops' ) );
    }

    public function ajax_analyze_flood_words() {
        while ( ob_get_level() ) {
            ob_end_clean();
        }

        if ( ! check_ajax_referer( 'pn_ai_chatbot_analyze_flood_words_nonce', 'security', false ) ) {
            wp_send_json_error( __( 'Security token expired. Please reload the page.', 'pointnet-ai-chatbot' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized action.', 'pointnet-ai-chatbot' ) );
        }

        $all_public_pts = get_post_types( array( 'public' => true ), 'objects' );
        $valid_post_types = array();
        foreach ( $all_public_pts as $pt ) {
            if ( ! filter_var( $pt->exclude_from_search, FILTER_VALIDATE_BOOLEAN ) ) {
                $valid_post_types[] = $pt->name;
            }
        }
        $valid_post_types = array_diff( $valid_post_types, array( 'attachment' ) );

        $max_posts = apply_filters( 'pn_ai_chatbot_flood_analyzer_max_posts', 500 );
        $query = new WP_Query( array(
            'post_type'           => $valid_post_types,
            'post_status'         => 'publish',
            'has_password'        => false,
            'posts_per_page'      => $max_posts,
            'fields'              => 'ids',
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true
        ) );

        // Prevent cache bloat during large scans
        wp_suspend_cache_addition( true );

        $total_docs = count( $query->posts );
        if ( $total_docs === 0 ) {
            wp_send_json_error( __( 'No public content found to analyze.', 'pointnet-ai-chatbot' ) );
        }

        $tf = array();
        $df = array();

        $base_stop_words = array(
            'the', 'and', 'that', 'have', 'for', 'not', 'with', 'you', 'this', 'but', 'his', 'from', 'they', 'we', 'say', 'her', 'she', 'will', 'one', 'all', 'would', 'there', 'their', 'what', 'about', 'who', 'get', 'which',
            'il', 'lo', 'la', 'gli', 'le', 'un', 'una', 'uno', 'di', 'da', 'in', 'con', 'su', 'per', 'tra', 'fra', 
            'del', 'dello', 'della', 'dei', 'degli', 'delle', 'al', 'allo', 'alla', 'ai', 'agli', 'alle', 'dal', 'dallo', 'dalla', 'dai', 'dagli', 'dalle',
            'nel', 'nello', 'nella', 'nei', 'negli', 'nelle', 'col', 'coi', 'sul', 'sullo', 'sulla', 'sui', 'sugli', 'sulle',
            'che', 'chi', 'cui', 'quale', 'quali', 'si', 'ne', 'ce', 'me', 'te', 'se', 'ma', 'ed',
            'suggerisci', 'consiglia', 'vorrei', 'voglio', 'trova', 'cerca', 'dimmi', 'mostra', 'elenca', 'spiegami', 'parli', 'parla', 'sai', 'conosci', 'puoi', 'potresti', 'sapresti', 'aiutarmi', 'aiutami', 'fare', 'faccio', 'come', 'cosa', 'quando', 'dove', 'perche', 'perché',
            'alcuni', 'alcune', 'qualche', 'questo', 'quello', 'questi', 'quelle', 'altri', 'altre', 'tutti', 'tutte', 'tutto', 'tutta',
            'ciao', 'grazie', 'favore', 'buongiorno', 'buonasera', 'salve', 'ehi',
            'hai', 'avete', 'hanno', 'qualcuno', 'qualcuna', 'ripeti', 'ancora', 'più', 'meno', 'molto', 'poco', 'sono', 'siamo', 'siete', 'era', 'erano', 'stato', 'stata', 'stati', 'state', 'essere', 'avere', 'abbiamo', 'aveva', 'avevano',
            'anche', 'solo', 'sempre', 'mai', 'già', 'non', 'così', 'qui', 'lì', 'qua', 'là', 'sua', 'suo', 'sue', 'suoi', 'nostro', 'nostra', 'nostri', 'nostre', 'vostro', 'vostra', 'vostri', 'vostre', 'loro'
        );

        // Read stop words from the dedicated option
        $custom_stops_str = get_option('pn_ai_chatbot_stop_words', '');
        $custom_stops = array_filter(array_map('trim', explode(',', strtolower($custom_stops_str))));

        foreach ( $query->posts as $post_id ) {
            $text = get_post_field( 'post_title', $post_id ) . ' ' . get_post_field( 'post_content', $post_id );
            $text = wp_strip_all_tags( strip_shortcodes( $text ) );
            $text = mb_strtolower( $text );
            $text = preg_replace( '/[^\p{L}]/u', ' ', $text ); 
            
            $words = array_filter( explode( ' ', $text ) );
            $doc_words = array();

            foreach ( $words as $w ) {
                if ( mb_strlen( $w ) < 4 ) continue;
                if ( in_array( $w, $base_stop_words ) ) continue;

                if ( ! isset( $tf[$w] ) ) $tf[$w] = 0;
                $tf[$w]++;
                $doc_words[$w] = true;
            }

            foreach ( $doc_words as $w => $val ) {
                if ( ! isset( $df[$w] ) ) $df[$w] = 0;
                $df[$w]++;
            }
        }

        // Re-enable cache additions after scan
        wp_suspend_cache_addition( false );

        $results = array();
        foreach ( $tf as $w => $freq ) {
            $doc_freq = $df[$w];
            $coverage = $doc_freq / $total_docs;
            
            if ( $coverage >= 0.15 ) {
                $score = $coverage * $freq; 
                
                $suggestion = __( 'Possible redundant word', 'pointnet-ai-chatbot' );
                $color = '#d68500'; 
                if ( $coverage >= 0.40 ) {
                    $suggestion = __( 'Very redundant word', 'pointnet-ai-chatbot' );
                    $color = '#d63638'; 
                } elseif ( $coverage >= 0.25 ) {
                    $suggestion = __( 'Likely redundant word', 'pointnet-ai-chatbot' );
                    $color = '#d68500'; 
                }

                $results[] = array(
                    'word'       => sanitize_text_field( $w ),
                    'tf'         => $freq,
                    'df'         => $doc_freq,
                    'coverage_p' => round($coverage * 100) . '%',
                    'score'      => $score,
                    'suggestion' => $suggestion,
                    'color'      => $color,
                    'added'      => in_array( $w, $custom_stops )
                );
            }
        }

        usort( $results, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        $results = array_slice($results, 0, 30); 

        // Force-inject manual stop words
        $manual_additions = array();
        foreach ( $custom_stops as $cw ) {
            $found = false;
            foreach ( $results as $res ) {
                if ( $res['word'] === $cw ) {
                    $found = true;
                    break;
                }
            }
            if ( ! $found ) {
                $freq = isset($tf[$cw]) ? $tf[$cw] : '-';
                $doc_freq = isset($df[$cw]) ? $df[$cw] : '-';
                $coverage_p = (isset($df[$cw]) && $total_docs > 0) ? round(($df[$cw]/$total_docs)*100).'%' : '-';
                
                $manual_additions[] = array(
                    'word'       => sanitize_text_field( $cw ),
                    'tf'         => $freq,
                    'df'         => $doc_freq,
                    'coverage_p' => $coverage_p,
                    'score'      => 999999,
                    'suggestion' => __( 'Manual word', 'pointnet-ai-chatbot' ),
                    'color'      => '#0073aa',
                    'added'      => true
                );
            }
        }

        $results = array_merge( $manual_additions, $results );

        wp_send_json_success( array(
            'total_docs' => $total_docs,
            'words'      => $results
        ) );
    }

    public function ajax_save_manual_stops() {
        while ( ob_get_level() ) {
            ob_end_clean();
        }
        if ( ! check_ajax_referer( 'pn_ai_chatbot_save_manual_stops_nonce', 'security', false ) ) {
            wp_send_json_error( __( 'Security token expired. Please reload the page.', 'pointnet-ai-chatbot' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized action.', 'pointnet-ai-chatbot' ) );
        }

        $words_str = isset( $_POST['words_string'] ) ? sanitize_text_field( wp_unslash( $_POST['words_string'] ) ) : '';

        $arr = array_filter( array_map( 'trim', explode( ',', strtolower( $words_str ) ) ) );
        $new_str = implode( ', ', $arr );
        update_option( 'pn_ai_chatbot_stop_words', $new_str );
        wp_send_json_success( array( 'new_list' => $new_str ) );
    }
}
new PointNet_AI_Chatbot_Flood_Analyzer();
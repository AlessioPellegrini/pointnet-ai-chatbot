<?php
declare( strict_types=1 );
if ( ! defined( 'ABSPATH' ) ) exit;

class PointNet_AI_Chatbot_Frontend {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_footer', array( $this, 'render_chat_html' ) );
        add_action( 'wp_ajax_pn_ai_chatbot_send_message', array( $this, 'handle_ajax_request' ) );
        add_action( 'wp_ajax_nopriv_pn_ai_chatbot_send_message', array( $this, 'handle_ajax_request' ) );
        add_action( 'wp_ajax_pn_ai_chatbot_get_nonce', array( $this, 'ajax_get_fresh_nonce' ) );
        add_action( 'wp_ajax_nopriv_pn_ai_chatbot_get_nonce', array( $this, 'ajax_get_fresh_nonce' ) );
    }

    /**
     * Returns a fresh nonce via AJAX to avoid cached-page nonce expiration issues.
     */
    public function ajax_get_fresh_nonce() {
        wp_send_json_success( wp_create_nonce( 'pn_ai_chatbot_chat_nonce' ) );
    }

    public function enqueue_scripts() {
        $css_ver = file_exists( PN_AI_CHATBOT_DIR . 'frontend/chat.css' ) ? filemtime( PN_AI_CHATBOT_DIR . 'frontend/chat.css' ) : PN_AI_CHATBOT_VERSION;
        $js_ver  = file_exists( PN_AI_CHATBOT_DIR . 'frontend/chat.js' ) ? filemtime( PN_AI_CHATBOT_DIR . 'frontend/chat.js' ) : PN_AI_CHATBOT_VERSION;
        wp_enqueue_style( 'pn-ai-chatbot-css', PN_AI_CHATBOT_URL . 'frontend/chat.css', array(), $css_ver );
        wp_enqueue_script( 'pn-ai-chatbot-js', PN_AI_CHATBOT_URL . 'frontend/chat.js', array( 'jquery' ), $js_ver, true );
        $options     = get_option( 'pn_ai_chatbot_settings' );
        $contact_url = is_array( $options ) && ! empty( $options['contact_url'] ) ? $options['contact_url'] : '/contact/';
        wp_localize_script( 'pn-ai-chatbot-js', 'pnAiChatbotData', array(
            'ajax_url'     => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'pn_ai_chatbot_chat_nonce' ),
            'writing'      => __( 'The bot is writing...', 'pointnet-ai-chatbot' ),
            'ask_question' => __( 'Ask a question...', 'pointnet-ai-chatbot' ),
            'conn_error'   => __( 'A connection error occurred with the server.', 'pointnet-ai-chatbot' ),
            'contact_url'  => esc_url( $contact_url ),
        ));
    }

    private function hex_to_rgba( $hex, $opacity ) {
        $hex = ltrim( $hex, '#' );
        if ( strlen( $hex ) == 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if ( strlen( $hex ) !== 6 ) return 'rgba(0, 115, 170, 1)';
        
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
        
        $alpha = floatval( $opacity ) / 100;
        return "rgba($r, $g, $b, $alpha)";
    }

    public function render_chat_html() {
        $options = get_option( 'pn_ai_chatbot_settings' );
        
        $primary_color = ( !empty( $options['primary_color'] ) ) ? $options['primary_color'] : '#0073aa';
        $button_opacity = ( isset( $options['button_opacity'] ) ) ? absint( $options['button_opacity'] ) : 100;
        $welcome_msg = ( !empty( $options['welcome_message'] ) ) ? $options['welcome_message'] : __( 'Hello! How can I help you today?', 'pointnet-ai-chatbot' );

        $rgba_color = $this->hex_to_rgba( $primary_color, $button_opacity );
        $hover_opacity = min( 100, $button_opacity + 10 ); 
        $rgba_hover = $this->hex_to_rgba( $primary_color, $hover_opacity );

        $privacy_url = get_privacy_policy_url();
        if ( empty( $privacy_url ) ) $privacy_url = '/privacy-policy/';
        ?>
        <style>
            #pn-ai-chatbot-container {
                --pn-ai-chatbot-primary: <?php echo esc_attr( $rgba_color ); ?>;
                --pn-ai-chatbot-primary-hover: <?php echo esc_attr( $rgba_hover ); ?>;
                --pn-ai-chatbot-primary-solid: <?php echo esc_attr( $primary_color ); ?>;
            }
        </style>
        <div id="pn-ai-chatbot-container">
            <div id="pn-ai-chatbot-window" style="display: none;">
                <div class="pn-ai-chatbot-header">
                    <span><?php esc_html_e( 'Virtual Assistant', 'pointnet-ai-chatbot' ); ?></span>
                    <button id="pn-ai-chatbot-close-btn">&times;</button>
                </div>
                <div id="pn-ai-chatbot-messages"><div class="pn-ai-chatbot-msg bot-msg"><?php echo esc_html( $welcome_msg ); ?></div></div>
                <div class="pn-ai-chatbot-input-area">
                    <input type="text" id="pn-ai-chatbot-user-input" placeholder="<?php esc_attr_e( 'Ask a question...', 'pointnet-ai-chatbot' ); ?>" autocomplete="off" maxlength="1000" />
                    <button id="pn-ai-chatbot-send-btn"><?php esc_html_e( 'Send', 'pointnet-ai-chatbot' ); ?></button>
                </div>
                <div class="pn-ai-chatbot-disclaimer">
                    <?php esc_html_e( 'By sending a message, you agree to our', 'pointnet-ai-chatbot' ); ?> 
                    <a href="<?php echo esc_url( $privacy_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Privacy Policy', 'pointnet-ai-chatbot' ); ?></a>. 
                    <br>
                    <?php esc_html_e( 'AI may make mistakes; do not share sensitive data.', 'pointnet-ai-chatbot' ); ?>
                </div>
            </div>
            <button id="pn-ai-chatbot-toggle">
                <span class="pn-ai-chatbot-toggle-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 8V4H8"/>
                        <rect width="16" height="12" x="4" y="8" rx="2"/>
                        <path d="M2 14h2"/>
                        <path d="M20 14h2"/>
                        <path d="M15 13v2"/>
                        <path d="M9 13v2"/>
                    </svg>
                </span>
                <span class="pn-ai-chatbot-toggle-text"><?php esc_html_e( 'Chat', 'pointnet-ai-chatbot' ); ?></span>
            </button>
        </div>
        <?php
    }

    private function get_critical_rules(): string {
        $options = get_option( 'pn_ai_chatbot_settings' );
        $default = "\n\nCRITICAL RULES:\n1. Respond in User Language.\n2. Never show internal reasoning.\n3. Links: [Title](URL).\n4. ANTI-AMNESIA & CONVERSATION FOCUS: If the user asks a follow-up question with an implicit subject or pronoun (e.g. 'which is the strongest?', 'how do I make it?', 'how much?'), they are referring exclusively to the specific entity/topic discussed in the IMMEDIATE PREVIOUS message. You MUST analyze only the data of that previous entity from the Chat History, and completely ignore other unrelated search results in the SITE DATA.\n5. PARTIAL DATA WARNING: The SITE DATA contains page EXCERPTS (first N characters), NOT full content. If specific information (e.g. certifications, details, skills) is not found in the excerpt, do NOT assume or claim it is absent from the page. Instead respond: 'The excerpt available does not mention [topic]. See the full page: [Page Title](URL).' NEVER make definitive negative statements like 'the page does not contain', 'does not list', or 'is not indicated'.\n6. LINK INTEGRITY: Only use URLs that appear verbatim in the provided SITE DATA. NEVER guess, generate, or construct URLs for external websites based on institution names, file names, or any other contextual clues. If the SITE DATA contains text that resembles a filename or reference but does not contain a full clickable URL, the URL is absent — do NOT fabricate one. NEVER use a page URL as a generic 'download' link for individual sub-items such as certifications, courses, or files — the page URL belongs solely to the page itself, not to its items. NEVER repeat the same URL more than once in a list — if the page URL is the only available reference, link it once at the top and list the items without hyperlinks. If no specific URL (e.g. a PDF download from /wp-content/uploads/) is present for a given item, list it as plain text without any hyperlink. If the entire page is referenced, the page URL is acceptable for that purpose alone. (EXCEPTION: For contact and appointment inquiries, you are explicitly authorized and required to use the official Contact Page URL provided in the instructions).";
        // Only use custom rules if editing is enabled AND the field is not empty
        $editing_enabled = isset( $options['critical_rules_enabled'] ) ? (bool) $options['critical_rules_enabled'] : false;
        return $editing_enabled && isset( $options['critical_rules'] ) && ! empty( $options['critical_rules'] ) ? "\n\n" . trim( $options['critical_rules'] ) : $default;
    }

    public function handle_ajax_request() {
        while ( ob_get_level() ) {
            ob_end_clean();
        }

        if ( ! check_ajax_referer( 'pn_ai_chatbot_chat_nonce', 'security', false ) ) {
            wp_send_json_error( __( 'Session expired. Please reload the page.', 'pointnet-ai-chatbot' ) );
        }
        $time_start = microtime(true);
        $user_message = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
        if ( empty( $user_message ) ) wp_send_json_error( __( 'Message is empty.', 'pointnet-ai-chatbot' ) );
        $user_message = mb_substr( $user_message, 0, 1000 );

        $raw_history = isset( $_POST['history'] ) ? json_decode( sanitize_textarea_field( wp_unslash( $_POST['history'] ) ), true ) : [];
        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $raw_history ) ) {
            $raw_history = [];
        }
        $history = [];
        foreach ( array_slice( $raw_history, -6 ) as $h ) {
            if ( is_array( $h ) && isset( $h['role'], $h['content'] ) && is_string( $h['role'] ) && is_string( $h['content'] ) ) {
                $history[] = [
                    'role'    => ( sanitize_text_field( $h['role'] ) === 'assistant' ) ? 'assistant' : 'user',
                    'content' => mb_substr( sanitize_textarea_field( $h['content'] ), 0, 2000 )
                ];
            }
        }

        $options = get_option( 'pn_ai_chatbot_settings' );
        
        $user_ip = pn_ai_chatbot_get_real_ip();
        
        $limit_min = isset($options['limit_min']) ? absint($options['limit_min']) : 10;
        $limit_day_ip = isset($options['limit_day_ip']) ? absint($options['limit_day_ip']) : 100;
        $limit_day_global = isset($options['limit_day_global']) ? absint($options['limit_day_global']) : 2000;

        $ip_hash = wp_hash($user_ip);
        $minute_bucket = (int) floor( time() / 60 );
        $day_bucket = gmdate('Y-m-d');

        $ip_min_key = 'pn_ai_lmin_' . $ip_hash . '_' . $minute_bucket;
        $ip_day_key = 'pn_ai_lday_' . $ip_hash . '_' . $day_bucket;
        $global_day_key = 'pn_ai_lglob_' . $day_bucket;

        $count_min = (int) get_transient( $ip_min_key );
        $count_day_ip = (int) get_transient( $ip_day_key );
        $count_day_global = (int) get_transient( $global_day_key );

        if ( $count_min >= $limit_min ) wp_send_json_error( __( 'Too many requests in a minute. Please wait.', 'pointnet-ai-chatbot' ) );
        if ( $count_day_ip >= $limit_day_ip ) wp_send_json_error( __( 'Daily message limit reached for your IP.', 'pointnet-ai-chatbot' ) );
        if ( $count_day_global >= $limit_day_global ) wp_send_json_error( __( 'Global daily message limit reached. Try again tomorrow.', 'pointnet-ai-chatbot' ) );

        set_transient( $ip_min_key, $count_min + 1, 70 );
        set_transient( $ip_day_key, $count_day_ip + 1, DAY_IN_SECONDS + 3600 );
        set_transient( $global_day_key, $count_day_global + 1, DAY_IN_SECONDS + 3600 );

        $ai_provider = $options['ai_provider'] ?? 'openai';
        $search_depth = isset($options['rag_search_depth']) ? absint($options['rag_search_depth']) : 12;
        // Read stop words from the dedicated option
        $custom_stop_words_str = get_option('pn_ai_chatbot_stop_words', '');
        
        $context = $this->retrieve_wp_content( $user_message, $search_depth, $custom_stop_words_str, $user_ip );
        
        $contact_url = ! empty( $options['contact_url'] ) ? trim( $options['contact_url'] ) : '/contact/';
        $hallucination_guard = empty( $context ) ? "\n\nCRITICAL RULE: No site data found. Do not invent info. State that the information is not available and suggest contacting us: [Contatti]({$contact_url})." : "";
        $final_context = "GLOBAL KNOWLEDGE:\n" . ( $options['global_knowledge'] ?? '' ) . "\n\nEXTRACTED SITE DATA:\n" . $context;
        
        $system_prompt = $options['system_prompt'] ?? '';
        // Automatically substitute placeholders like [Contatti](URL), [Contacts](URL), {contact_url}
        $system_prompt = preg_replace( '/\[([^\]]+)\]\((URL|\{contact_url\}|\{url\})\)/i', '[$1](' . $contact_url . ')', $system_prompt );
        $system_prompt = str_ireplace( array( '{contact_url}', '{contact-url}' ), $contact_url, $system_prompt );
        $augmented_prompt = $system_prompt . $hallucination_guard;

        if ( $ai_provider === 'openrouter' ) {
            if ( empty( trim( $options['openrouter_api_key'] ?? '' ) ) ) {
                wp_send_json_error( __( 'OpenRouter API key is missing.', 'pointnet-ai-chatbot' ) );
            }
            $models = array_values( array_filter( array_map( 'trim', explode( ',', $options['openrouter_priority_models'] ?? '' ) ) ) );
            $response = $this->call_openrouter_api( $options['openrouter_api_key'] ?? '', $augmented_prompt, $final_context, $user_message, $contact_url, $models, $history );
        } elseif ( $ai_provider === 'gemini' ) {
            $response = $this->call_gemini_api( $options['gemini_api_key'] ?? '', $augmented_prompt, $final_context, $user_message, $contact_url, $options['gemini_model_name'] ?? 'gemini-3.5-flash', $history );
        } else {
            $openai_model = isset( $options['openai_model_name'] ) && ! empty( $options['openai_model_name'] ) ? $options['openai_model_name'] : 'gpt-4o-mini';
            $response = $this->call_openai_api( $options['api_key'] ?? '', $augmented_prompt, $final_context, $user_message, $contact_url, $openai_model, $history );
        }

        $duration = round(microtime(true) - $time_start, 2);

        if ( is_wp_error( $response ) ) {
            $this->log_chat_session( 'ERROR', $user_message, $response->get_error_message(), $duration );
            $client_error = current_user_can( 'manage_options' )
                ? $response->get_error_message()
                : __( 'The assistant is temporarily unavailable. Please try again shortly.', 'pointnet-ai-chatbot' );
            wp_send_json_error( $client_error );
        }
        
        $tokens_in = isset($response['usage']['prompt_tokens']) ? absint($response['usage']['prompt_tokens']) : 0;
        $tokens_out = isset($response['usage']['completion_tokens']) ? absint($response['usage']['completion_tokens']) : 0;
        $tokens_total = isset($response['usage']['total_tokens']) ? absint($response['usage']['total_tokens']) : 0;

        // Append truncation settings to model name for logging
        $trunc_mode = $options['content_truncation_mode'] ?? 'adaptive';
        if ( $trunc_mode === 'adaptive' ) {
            $adaptive_raw = isset($options['content_truncation_adaptive_limits']) ? $options['content_truncation_adaptive_limits'] : '4000,3000,1500';
            $trunc_label = ' | adaptive(' . $adaptive_raw . ')';
        } else {
            $trunc_limit = isset($options['content_truncation_limit']) ? absint($options['content_truncation_limit']) : 1500;
            $trunc_label = ' | uniform(' . $trunc_limit . ')';
        }
        $log_model = $response['model'] . $trunc_label . ' depth=' . $search_depth;

        $this->log_chat_session( $log_model, $user_message, $response['text'], $duration, $tokens_in, $tokens_out, $tokens_total );
        wp_send_json_success( $response['text'] );
    }

    private function retrieve_wp_content( $raw_query, $search_depth, $custom_stop_words_str, $user_ip ) {
        global $wpdb;

        $base_stop_words = array(
            'the', 'a', 'an', 'and', 'or', 'but', 'if', 'because', 'as', 'what', 'which', 'who', 'whom', 'this', 'that', 'these', 'those', 'am', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did',
            'il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'una', 'uno', 'di', 'a', 'da', 'in', 'con', 'su', 'per', 'tra', 'fra', 
            'del', 'dello', 'della', 'dei', 'degli', 'delle', 'al', 'allo', 'alla', 'ai', 'agli', 'alle', 'dal', 'dallo', 'dalla', 'dai', 'dagli', 'dalle',
            'nel', 'nello', 'nella', 'nei', 'negli', 'nelle', 'col', 'coi', 'sul', 'sullo', 'sulla', 'sui', 'sugli', 'sulle',
            'che', 'chi', 'cui', 'quale', 'quali', 'mi', 'ti', 'ci', 'vi', 'si', 'ne', 'ce', 'me', 'te', 'se', 'ma', 'o', 'e', 'ed',
            'suggerisci', 'suggeriscimi', 'consiglia', 'consigliami', 'vorrei', 'voglio', 'trova', 'cerca', 'dimmi', 'mostra', 'mostrami', 'elenca', 'spiegami', 'parli', 'parla', 'sai', 'conosci', 'puoi', 'potresti', 'sapresti', 'aiutarmi', 'aiutami', 'fare', 'fa', 'faccio', 'come', 'cosa', 'quando', 'dove', 'perche', 'perché',
            'alcuni', 'alcune', 'qualche', 'questo', 'quello', 'questi', 'quelle', 'altri', 'altre', 'tutti', 'tutte', 'tutto', 'tutta',
            'ciao', 'grazie', 'favore', 'buongiorno', 'buonasera', 'salve', 'ehi',
            'hai', 'avete', 'hanno', 'qualcuno', 'qualcuna', 'ripeti', 'ripetimi', 'ancora', 'più', 'meno', 'molto', 'poco'
        );

        $custom_stop_words = array_filter(array_map('trim', explode(',', strtolower($custom_stop_words_str))));
        $stop_words = array_merge($base_stop_words, $custom_stop_words);

        $words = array_values( array_filter( explode( ' ', strtolower( preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $raw_query ) ) ), function($w) use ($stop_words) {
            return !in_array($w, $stop_words) && strlen($w) > 2;
        }));

        $last_ctx_key = 'pn_ai_chatbot_last_ctx_' . wp_hash($user_ip);
        $last_post_ids = get_transient($last_ctx_key);
        if (!is_array($last_post_ids)) $last_post_ids = [];

        $all_public_pts = get_post_types(['public' => true], 'objects');
        $valid_post_types = [];
        foreach ($all_public_pts as $pt) {
            $is_excluded = filter_var($pt->exclude_from_search, FILTER_VALIDATE_BOOLEAN);
            if (!$is_excluded) $valid_post_types[] = $pt->name;
        }
        $valid_post_types = array_diff($valid_post_types, ['attachment']);

        $final_post_ids = [];

        if ( empty($words) ) {
            $final_post_ids = $last_post_ids;
        } else {
            $all_found_ids = [];

            foreach ( $words as $word ) {
                $term_search_like = '%' . $wpdb->esc_like($word) . '%';
                
                $results = $wpdb->get_col(
                    $wpdb->prepare("
                        SELECT tr.object_id 
                        FROM {$wpdb->term_relationships} tr
                        INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                        INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                        WHERE t.name LIKE %s OR t.slug LIKE %s
                    ", $term_search_like, $term_search_like)
                );
                if (!empty($results)) {
                    foreach ($results as $oid) {
                        for($i=0; $i<5; $i++) { $all_found_ids[] = $oid; }
                    }
                }
            }

            $pt_placeholders = implode( ',', array_fill( 0, count( $valid_post_types ), '%s' ) );

            // Fulltext search on wp_posts content and titles with relevance scoring
            $ft_words = implode(' ', $words);
            if (!empty($ft_words)) {
                $ft_args = array_merge(
                    array( $ft_words, $ft_words ),
                    $valid_post_types,
                    array( (int) ( $search_depth + 10 ) )
                );
                $wpdb->suppress_errors( true );
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom search query with relevance ranking.
                $ft_results = $wpdb->get_results(
                    $wpdb->prepare("
                        SELECT ID, MATCH(post_title, post_content) AGAINST(%s IN NATURAL LANGUAGE MODE) AS score
                        FROM {$wpdb->posts}
                        WHERE MATCH(post_title, post_content) AGAINST(%s IN NATURAL LANGUAGE MODE)
                          AND post_type IN ({$pt_placeholders})
                          AND post_status = 'publish'
                        ORDER BY score DESC
                        LIMIT %d
                    ", $ft_args),
                    ARRAY_A
                );
                $had_ft_error = ! empty( $wpdb->last_error );
                $wpdb->suppress_errors( false );

                if ( ! $had_ft_error && ! empty( $ft_results ) ) {
                    foreach ($ft_results as $row) {
                        $oid = (int) $row['ID'];
                        $score = (float) $row['score'];
                        // Weight: multiply by relevance score (max ~50 MIN, higher for good matches)
                        $weight = max(1, min(10, (int) round($score)));
                        for ($i = 0; $i < $weight; $i++) {
                            $all_found_ids[] = $oid;
                        }
                    }
                } elseif ( $had_ft_error ) {
                    // Fallback to LIKE if FULLTEXT index is unavailable
                    $like_term = '%' . $wpdb->esc_like( $ft_words ) . '%';
                    $fallback_args = array_merge(
                        array( $like_term, $like_term ),
                        $valid_post_types,
                        array( (int) ( $search_depth + 10 ) )
                    );
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                    $fallback_results = $wpdb->get_col(
                        $wpdb->prepare("
                            SELECT ID FROM {$wpdb->posts}
                            WHERE (post_title LIKE %s OR post_content LIKE %s)
                              AND post_type IN ({$pt_placeholders})
                              AND post_status = 'publish'
                            ORDER BY post_date DESC
                            LIMIT %d
                        ", $fallback_args)
                    );
                    if ( ! empty( $fallback_results ) ) {
                        foreach ( $fallback_results as $oid ) {
                            $all_found_ids[] = (int) $oid;
                        }
                    }
                }

                // Single-word FULLTEXT queries for partial matches (only if FULLTEXT succeeded)
                if ( ! $had_ft_error && count($words) > 1 ) {
                    foreach ($words as $w) {
                        if (strlen($w) < 4) continue;
                        $single_args = array_merge(
                            array( $w ),
                            $valid_post_types
                        );
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom query, not cacheable.
                        $single_results = $wpdb->get_col(
                            $wpdb->prepare("
                                SELECT ID FROM {$wpdb->posts}
                                WHERE MATCH(post_title, post_content) AGAINST(%s IN NATURAL LANGUAGE MODE)
                                  AND post_type IN ({$pt_placeholders})
                                  AND post_status = 'publish'
                                LIMIT 10
                            ", $single_args)
                        );
                        if (!empty($single_results)) {
                            foreach ($single_results as $oid) {
                                $all_found_ids[] = (int) $oid;
                            }
                        }
                    }
                }
            }

            $post_counts = array_count_values($all_found_ids);
            arsort($post_counts); 
            $final_post_ids = array_keys($post_counts);
            $final_post_ids = array_slice($final_post_ids, 0, $search_depth);
        }

        if (!empty($final_post_ids)) {
            set_transient($last_ctx_key, array_slice($final_post_ids, 0, 6), HOUR_IN_SECONDS);
        }

        $combined_ids = array_unique( array_merge( (array) $last_post_ids, (array) $final_post_ids ) );
        $combined_ids = array_slice($combined_ids, 0, $search_depth + 4);

        $options = get_option( 'pn_ai_chatbot_settings' );
        $truncation_mode = $options['content_truncation_mode'] ?? 'adaptive';
        $truncation_limit = isset($options['content_truncation_limit']) ? absint($options['content_truncation_limit']) : 1500;
        
        // Parse adaptive limits from option string (e.g. "4000,3000,1500")
        $adaptive_limits_raw = isset($options['content_truncation_adaptive_limits']) ? $options['content_truncation_adaptive_limits'] : '4000,3000,1500';
        $adaptive_limits = array_map('absint', explode(',', $adaptive_limits_raw));
        $adaptive_top = isset($adaptive_limits[0]) && $adaptive_limits[0] > 0 ? $adaptive_limits[0] : 4000;
        $adaptive_mid = isset($adaptive_limits[1]) && $adaptive_limits[1] > 0 ? $adaptive_limits[1] : 3000;
        $adaptive_rest = isset($adaptive_limits[2]) && $adaptive_limits[2] > 0 ? $adaptive_limits[2] : 1500;
        
        $context = "";
        if ( ! empty($combined_ids) ) {
            $query = new WP_Query([
                'post__in'       => $combined_ids,
                'post_type'      => $valid_post_types,
                'post_status'    => 'publish',
                'posts_per_page' => $search_depth + 4,
                'orderby'        => 'post__in',
                'ignore_sticky_posts' => true
            ]);
            
            $post_rank = 0;
            while ( $query->have_posts() ) {
                $query->the_post();
                $post_rank++;
                
                if ( post_password_required( get_the_ID() ) ) continue;
                if ( ! apply_filters( 'pn_ai_chatbot_can_index_post', true, get_the_ID() ) ) continue;
                
                $content = wp_strip_all_tags( apply_filters( 'the_content', get_the_content() ) );
                
                if ( $truncation_mode === 'adaptive' ) {
                    if ( $post_rank <= 3 ) {
                        $char_limit = $adaptive_top;
                    } elseif ( $post_rank <= 7 ) {
                        $char_limit = $adaptive_mid;
                    } else {
                        $char_limit = $adaptive_rest;
                    }
                } else {
                    $char_limit = $truncation_limit;
                }
                
                $content = mb_substr($content, 0, $char_limit);
                
                $terms_str = '';
                $taxonomies = get_post_taxonomies(get_the_ID());
                foreach ($taxonomies as $tax) {
                    $terms = wp_get_post_terms(get_the_ID(), $tax, ['fields' => 'names']);
                    if (!is_wp_error($terms) && !empty($terms)) {
                        $terms_str .= implode(', ', $terms) . ', ';
                    }
                }
                $terms_str = rtrim($terms_str, ', ');
                
                $meta_text = '';
                $all_meta = get_post_meta( get_the_ID() );
                if ( ! empty( $all_meta ) && is_array( $all_meta ) ) {
                    foreach ( $all_meta as $key => $values ) {
                        // Skip protected meta (keys starting with an underscore)
                        if ( is_protected_meta( $key, 'post' ) || strpos( $key, '_' ) === 0 ) {
                            continue;
                        }

                        // Block commonly sensitive meta keys (passwords, tokens, billing, emails, phones, auth)
                        $is_sensitive = (bool) preg_match( '/(pass(word)?|secret|token|api[_-]?key|auth|session|email|phone|billing|shipping|credit|card|license|cvv|ssn)/i', $key );
                        
                        // Allow developers to override or whitelist specific keys
                        $is_allowed = apply_filters( 'pn_ai_chatbot_allow_meta_key', ! $is_sensitive, $key, get_the_ID() );
                        if ( ! $is_allowed ) {
                            continue;
                        }

                        $raw_val = is_array( $values ) ? array_shift( $values ) : $values;
                        if ( is_serialized( $raw_val ) ) {
                            continue;
                        }
                        $val_str = is_array( $raw_val ) ? implode( ', ', $raw_val ) : (string) $raw_val;
                        $val_str = wp_strip_all_tags( $val_str );
                        if ( strlen( $val_str ) > 0 && strlen( $val_str ) < 500 ) {
                            $meta_text .= " {$key}: {$val_str} |";
                        }
                    }
                }
                $meta_text = mb_substr( $meta_text, 0, 1000 ); 
                
                $context .= "CONTENT TYPE: " . strtoupper(get_post_type()) . " | CATEGORIES: " . $terms_str . " | TITLE: " . get_the_title() . " | URL: " . wp_make_link_relative(get_permalink()) . " | TEXT: " . $content . " | META DATA:" . $meta_text . "\n";
            }
            wp_reset_postdata();
        }
        return $context;
    }

    private function call_openrouter_api( $api_key, $system_prompt, $context, $user_message, $contact_url, $priority_models_array, $history ) {
        $critical_rules = $this->get_critical_rules();
        $contact_url = ! empty( $contact_url ) ? $contact_url : '/contact/';
        $contact_instruction = "\n\nOFFICIAL CONTACT PAGE LINK:\nThe official contact link for this site is: [Contatti]({$contact_url}) (or in English: [Contact Us]({$contact_url})).\nWhenever suggesting to contact the agency, book an appointment, or request a quote, you MUST ALWAYS output the clickable markdown link: [Contatti]({$contact_url}). NEVER output literal '(URL)', NEVER output unclickable plain text for the contact page.";
        $strict = $critical_rules . "\n" . $system_prompt . $contact_instruction;
        $final_prompt = $strict . "\n\nSITE DATA:\n" . $context;
        
        if ( ! in_array( 'openrouter/free', $priority_models_array ) ) {
            $priority_models_array[] = 'openrouter/free';
        }

        $api_messages = [['role' => 'system', 'content' => $final_prompt]];
        foreach ($history as $msg) {
            $api_messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
        $api_messages[] = ['role' => 'user', 'content' => $user_message];

        $failed_models = [];

        foreach ( $priority_models_array as $m ) {
            $body = [
                'model' => $m, 
                'messages' => $api_messages, 
                'max_tokens' => 1500
            ];
            
            $is_reasoning_model = false;
            if ( 
                strpos( $m, 'o1' ) !== false || 
                strpos( $m, 'o3' ) !== false || 
                strpos( $m, 'gpt-5' ) !== false ||
                strpos( $m, 'reasoning' ) !== false
            ) {
                $is_reasoning_model = true;
            }

            if ( ! $is_reasoning_model ) {
                $body['temperature'] = 0.1;
            }

            $body_json = json_encode($body);
            if ($body_json === false) return new WP_Error('api_error', 'System Error: Invalid UTF-8.');

            $req = wp_remote_post( 'https://openrouter.ai/api/v1/chat/completions', ['body' => $body_json, 'timeout' => 30, 'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $api_key]] );
            
            if (is_wp_error($req)) {
                $err_msg = $req->get_error_message();
                if (strpos($err_msg, 'cURL error 28') !== false || strpos($err_msg, 'timed out') !== false) $err_msg = __( 'Timeout: The server took too long.', 'pointnet-ai-chatbot' );
                $failed_models[] = $m . " (" . substr($err_msg, 0, 50) . ")";
                continue;
            }

            $data = json_decode(wp_remote_retrieve_body($req), true);
            
            if (isset($data['choices'][0]['message']['content'])) {
                $text = $data['choices'][0]['message']['content'];
                if (strlen($text) > 10 && stripos($text, 'let me check') === false) {
                    $actual_model = isset($data['model']) ? $data['model'] : $m;
                    $display_model = ($m !== $actual_model) ? "{$m} ({$actual_model})" : $m;
                    $used_model = empty($failed_models) ? $display_model : implode(' ❌ -> ', $failed_models) . ' ❌ -> ' . $display_model . ' ✅';
                    return [
                        'text' => $text,
                        'model' => $used_model,
                        'usage' => $data['usage'] ?? null
                    ];
                }
            }
            
            $err_reason = isset($data['error']['message']) ? $data['error']['message'] : __( 'Unknown Error', 'pointnet-ai-chatbot' );
            $failed_models[] = $m . " (" . substr($err_reason, 0, 50) . ")";
        }
        
        $error_cascade = implode(' ❌ -> ', $failed_models) . ' ❌';
        return new WP_Error('api_error', __( 'All models failed. Path: ', 'pointnet-ai-chatbot' ) . $error_cascade);
    }

    private function call_gemini_api( $api_key, $system_prompt, $context, $user_message, $contact_url, $model, $history ) {
        $api_key = trim( $api_key );
        $model = trim( $model );

        if ( empty( $api_key ) ) return new WP_Error( 'api_error', __( 'Error: Google Gemini API key is missing.', 'pointnet-ai-chatbot' ) );

        $critical_rules = $this->get_critical_rules();
        $contact_url = ! empty( $contact_url ) ? $contact_url : '/contact/';
        $contact_instruction = "\n\nOFFICIAL CONTACT PAGE LINK:\nThe official contact link for this site is: [Contatti]({$contact_url}) (or in English: [Contact Us]({$contact_url})).\nWhenever suggesting to contact the agency, book an appointment, or request a quote, you MUST ALWAYS output the clickable markdown link: [Contatti]({$contact_url}). NEVER output literal '(URL)', NEVER output unclickable plain text for the contact page.";
        $strict = $critical_rules . "\n" . $system_prompt . $contact_instruction;
        $final_prompt = $strict . "\n\nSITE DATA:\n" . $context;

        $url = "https://generativelanguage.googleapis.com/v1beta/models/" . urlencode($model) . ":generateContent";
        
        $contents = [];
        foreach ($history as $msg) {
            $contents[] = [
                'role'  => ($msg['role'] === 'assistant') ? 'model' : 'user',
                'parts' => [['text' => $msg['content']]]
            ];
        }
        $contents[] = [
            'role'  => 'user',
            'parts' => [['text' => $user_message]]
        ];

        $body = [
            'contents' => $contents,
            'systemInstruction' => [
                'parts' => [['text' => $final_prompt]]
            ]
        ];
        
        $body_json = json_encode($body);
        if ($body_json === false) return new WP_Error('api_error', 'Gemini: Invalid UTF-8.');
        
        $req = wp_remote_post($url, [
            'body'    => $body_json, 
            'headers' => [
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $api_key
            ], 
            'timeout' => 45
        ]);
        
        if (is_wp_error($req)) {
            $err_msg = $req->get_error_message();
            if (strpos($err_msg, 'cURL error 28') !== false || strpos($err_msg, 'timed out') !== false) {
                $err_msg = __( 'Timeout Error: Google servers took too long to process the request. Please try again.', 'pointnet-ai-chatbot' );
            }
            return new WP_Error('api_error', $err_msg);
        }
        
        $raw_body = wp_remote_retrieve_body($req);
        $data = json_decode($raw_body, true);
        
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $text = $data['candidates'][0]['content']['parts'][0]['text'];
            $usage = isset($data['usageMetadata']) ? [
                'prompt_tokens'     => $data['usageMetadata']['promptTokenCount'] ?? 0,
                'completion_tokens' => $data['usageMetadata']['candidatesTokenCount'] ?? 0,
                'total_tokens'      => $data['usageMetadata']['totalTokenCount'] ?? 0,
            ] : null;
            return [
                'text' => $text,
                'model' => $model,
                'usage' => $usage
            ];
        }
        
        $err_msg = isset($data['error']['message']) ? $data['error']['message'] : 'Gemini API Error';
        if (strpos($err_msg, 'Expected OAuth') !== false || strpos($err_msg, 'API key not valid') !== false) {
            $err_msg = __( 'GOOGLE KEY ERROR: You inserted an invalid API string.', 'pointnet-ai-chatbot' );
        }

        return new WP_Error('api_error', $err_msg);
    }

    private function call_openai_api( $api_key, $system_prompt, $context, $user_message, $contact_url, $model, $history ) {
        $api_key = trim( $api_key );
        $model = trim( $model );
        
        if ( empty( $api_key ) ) return new WP_Error( 'api_error', __( 'Error: OpenAI API key is missing.', 'pointnet-ai-chatbot' ) );

        $url = 'https://api.openai.com/v1/chat/completions';
        $critical_rules = $this->get_critical_rules();
        $contact_url = ! empty( $contact_url ) ? $contact_url : '/contact/';
        $contact_instruction = "\n\nOFFICIAL CONTACT PAGE LINK:\nThe official contact link for this site is: [Contatti]({$contact_url}) (or in English: [Contact Us]({$contact_url})).\nWhenever suggesting to contact the agency, book an appointment, or request a quote, you MUST ALWAYS output the clickable markdown link: [Contatti]({$contact_url}). NEVER output literal '(URL)', NEVER output unclickable plain text for the contact page.";
        $strict = $critical_rules . "\n" . $system_prompt . $contact_instruction;
        $final_prompt = $strict . "\n\nSITE DATA:\n" . $context;
        
        $api_messages = [['role' => 'system', 'content' => $final_prompt]];
        foreach ($history as $msg) {
            $api_messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
        $api_messages[] = ['role' => 'user', 'content' => $user_message];

        $body = [
            'model' => $model,
            'messages' => $api_messages
        ];
        
        $is_reasoning_model = false;
        if ( 
            strpos( $model, 'o1' ) !== false || 
            strpos( $model, 'o3' ) !== false || 
            strpos( $model, 'gpt-5' ) !== false ||
            strpos( $model, 'reasoning' ) !== false
        ) {
            $is_reasoning_model = true;
        }

        if ( ! $is_reasoning_model ) {
            $body['temperature'] = 0.1;
        }
        
        $req = wp_remote_post( $url, [
            'body' => json_encode($body),
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ]
        ] );
        
        if ( is_wp_error( $req ) ) {
            $err_msg = $req->get_error_message();
            if (strpos($err_msg, 'cURL error 28') !== false || strpos($err_msg, 'timed out') !== false) $err_msg = __( 'OpenAI Timeout Error.', 'pointnet-ai-chatbot' );
            return new WP_Error('api_error', $err_msg);
        }
        
        $data = json_decode( wp_remote_retrieve_body( $req ), true );
        
        if ( isset( $data['choices'][0]['message']['content'] ) ) {
            return [
                'text' => $data['choices'][0]['message']['content'],
                'model' => $model,
                'usage' => $data['usage'] ?? null
            ];
        } else {
            $err_msg = isset($data['error']['message']) ? $data['error']['message'] : __( 'OpenAI Unknown Error', 'pointnet-ai-chatbot' );
            return new WP_Error('api_error', $err_msg);
        }
    }

    private function log_chat_session( $provider, $question, $answer, $duration = 0, $tokens_in = 0, $tokens_out = 0, $tokens_total = 0 ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'pn_ai_chatbot_logs';

        $wpdb->insert(
            $table_name,
            array(
                'timestamp'    => current_time( 'mysql' ),
                'provider'     => $provider,
                'question'     => $question,
                'answer'       => $answer,
                'duration'     => (float) $duration,
                'tokens_in'    => (int) $tokens_in,
                'tokens_out'   => (int) $tokens_out,
                'tokens_total' => (int) $tokens_total,
            ),
            array( '%s', '%s', '%s', '%s', '%f', '%d', '%d', '%d' )
        );
    }
}

/**
 * Returns the real user IP address, with Cloudflare proxy (IPv4 & IPv6) support and anti-spoofing protection.
 *
 * @return string
 */
function pn_ai_chatbot_get_real_ip(): string {
    $remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '127.0.0.1';

    // Cloudflare proxy: only trust CF-Connecting-IP if the request comes from Cloudflare IPs
    if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
        $cf_ipv4_ranges = array(
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
            '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
            '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
            '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22'
        );

        $cf_ipv6_ranges = array(
            '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32',
            '2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32'
        );

        $is_cf = false;
        if ( filter_var( $remote_addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            foreach ( $cf_ipv4_ranges as $range ) {
                if ( pn_ai_chatbot_ip_in_range( $remote_addr, $range ) ) {
                    $is_cf = true;
                    break;
                }
            }
        } elseif ( filter_var( $remote_addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
            foreach ( $cf_ipv6_ranges as $range ) {
                if ( pn_ai_chatbot_ipv6_in_range( $remote_addr, $range ) ) {
                    $is_cf = true;
                    break;
                }
            }
        }

        if ( $is_cf ) {
            $cf_ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
            if ( filter_var( $cf_ip, FILTER_VALIDATE_IP ) ) {
                return $cf_ip;
            }
        }
    }

    // X-Forwarded-For: only trust from local or private proxies (RFC 1918 / loopback)
    $is_private_proxy = (
        in_array( $remote_addr, array( '127.0.0.1', '::1' ), true ) ||
        false === filter_var( $remote_addr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE )
    );

    if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) && $is_private_proxy ) {
        $forwarded_ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
        // Pick the first valid client IP from the chain
        foreach ( $forwarded_ips as $candidate_ip ) {
            $candidate_ip = trim( $candidate_ip );
            if ( filter_var( $candidate_ip, FILTER_VALIDATE_IP ) ) {
                return $candidate_ip;
            }
        }
    }

    return filter_var( $remote_addr, FILTER_VALIDATE_IP ) ? $remote_addr : '127.0.0.1';
}

/**
 * Checks if an IPv4 address falls within a CIDR range.
 *
 * @param string $ip    The IP address to check.
 * @param string $range The CIDR range (e.g., 173.245.48.0/20).
 * @return bool
 */
function pn_ai_chatbot_ip_in_range( string $ip, string $range ): bool {
    if ( strpos( $range, '/' ) === false ) {
        return $ip === $range;
    }
    list( $subnet, $bits ) = explode( '/', $range );
    $bits = (int) $bits;
    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) && filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
        $ip_long   = ip2long( $ip );
        $subnet_long = ip2long( $subnet );
        $mask      = -1 << ( 32 - $bits );
        $subnet_long &= $mask;
        return ( $ip_long & $mask ) === $subnet_long;
    }
    return false;
}

/**
 * Checks if an IPv6 address falls within a CIDR range.
 *
 * @param string $ip    The IPv6 address to check.
 * @param string $range The CIDR range (e.g., 2400:cb00::/32).
 * @return bool
 */
function pn_ai_chatbot_ipv6_in_range( string $ip, string $range ): bool {
    if ( strpos( $range, '/' ) === false ) {
        return $ip === $range;
    }
    list( $subnet, $bits ) = explode( '/', $range );
    $bits = (int) $bits;

    $ip_bin = inet_pton( $ip );
    $subnet_bin = inet_pton( $subnet );
    if ( false === $ip_bin || false === $subnet_bin ) {
        return false;
    }

    $bytes = (int) floor( $bits / 8 );
    $remainder_bits = $bits % 8;

    if ( substr( $ip_bin, 0, $bytes ) !== substr( $subnet_bin, 0, $bytes ) ) {
        return false;
    }

    if ( $remainder_bits > 0 ) {
        $mask = chr( 0xFF << ( 8 - $remainder_bits ) );
        if ( ( $ip_bin[ $bytes ] & $mask ) !== ( $subnet_bin[ $bytes ] & $mask ) ) {
            return false;
        }
    }

    return true;
}
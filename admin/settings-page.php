<?php
declare( strict_types=1 );
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PointNet_AI_Chatbot_Admin_Settings {

    private $option_name  = 'pn_ai_chatbot_settings';
    private $option_group = 'pn_ai_chatbot_settings_group';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_plugin_settings' ) );
        add_action( 'admin_init', array( $this, 'handle_log_download' ) );
        add_action( 'admin_post_pn_ai_chatbot_clear_logs', array( $this, 'handle_log_clear_post' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

        add_action( 'wp_ajax_pn_ai_chatbot_fetch_gemini_models', array( $this, 'ajax_fetch_gemini_models' ) );
        add_action( 'wp_ajax_pn_ai_chatbot_fetch_openai_models', array( $this, 'ajax_fetch_openai_models' ) );
    }

    public function enqueue_admin_scripts( $hook ): void {
        if ( 'settings_page_pointnet-ai-chatbot' !== $hook ) {
            return;
        }

        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );

        $js_ver = file_exists( PN_AI_CHATBOT_DIR . 'admin/admin.js' )
            ? filemtime( PN_AI_CHATBOT_DIR . 'admin/admin.js' )
            : PN_AI_CHATBOT_VERSION;

        wp_enqueue_script(
            'pn-ai-chatbot-admin-js',
            PN_AI_CHATBOT_URL . 'admin/admin.js',
            array( 'jquery', 'wp-color-picker' ),
            $js_ver,
            true
        );

        wp_localize_script(
            'pn-ai-chatbot-admin-js',
            'pnAiChatbotAdmin',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonces'   => array(
                    'fetch_openai' => wp_create_nonce( 'pn_ai_chatbot_fetch_openai_models_nonce' ),
                    'fetch_gemini' => wp_create_nonce( 'pn_ai_chatbot_fetch_gemini_models_nonce' ),
                    'scan_flood'   => wp_create_nonce( 'pn_ai_chatbot_analyze_flood_words_nonce' ),
                    'save_stops'   => wp_create_nonce( 'pn_ai_chatbot_save_manual_stops_nonce' ),
                ),
                'i18n'     => array(
                    'fetching_openai' => __( 'Fetching available models from OpenAI...', 'pointnet-ai-chatbot' ),
                    'fetching_gemini' => __( 'Fetching available models from Google...', 'pointnet-ai-chatbot' ),
                    'models_updated'  => __( 'Models updated successfully! Select one and click Save Settings.', 'pointnet-ai-chatbot' ),
                    'unknown_error'   => __( 'Unknown error.', 'pointnet-ai-chatbot' ),
                    'server_error'    => __( 'Server connection error. Please try again.', 'pointnet-ai-chatbot' ),
                    'ajax_error'      => __( 'AJAX connection error. Please try again.', 'pointnet-ai-chatbot' ),
                    'saved'           => __( 'Saved!', 'pointnet-ai-chatbot' ),
                    'error_saving'    => __( 'Error saving words.', 'pointnet-ai-chatbot' ),
                    'remove'          => __( 'Remove', 'pointnet-ai-chatbot' ),
                    'add_to_stops'    => __( 'Add to Stop Words', 'pointnet-ai-chatbot' ),
                ),
            )
        );
    }

    public function add_settings_page() {
        add_options_page(
            __( 'PointNet AI Chatbot Settings', 'pointnet-ai-chatbot' ),
            __( 'PointNet AI Chatbot', 'pointnet-ai-chatbot' ),
            'manage_options',
            'pointnet-ai-chatbot',
            array( $this, 'render_admin_page' )
        );
    }

    public function register_plugin_settings() {
        register_setting(
            $this->option_group,
            $this->option_name,
            array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) )
        );

        // SECTION 1: Provider & API
        add_settings_section( 'pn_ai_chatbot_provider_section', __( '🤖 AI Provider & API Settings', 'pointnet-ai-chatbot' ), array( $this, 'provider_section_desc' ), 'pointnet-ai-chatbot' );
        add_settings_field( 'ai_provider', __( 'AI Provider', 'pointnet-ai-chatbot' ), array( $this, 'ai_provider_callback' ), 'pointnet-ai-chatbot', 'pn_ai_chatbot_provider_section' );
        add_settings_field( 'api_key', __( 'API Key (OpenAI)', 'pointnet-ai-chatbot' ), array( $this, 'openai_api_key_callback' ), 'pointnet-ai-chatbot', 'pn_ai_chatbot_provider_section' );
        add_settings_field( 'openai_model_name', __( 'OpenAI Model', 'pointnet-ai-chatbot' ), array( $this, 'openai_model_name_callback' ), 'pointnet-ai-chatbot', 'pn_ai_chatbot_provider_section' );
        add_settings_field( 'gemini_api_key', __( 'API Key (Google Gemini)', 'pointnet-ai-chatbot' ), array( $this, 'gemini_api_key_callback' ), 'pointnet-ai-chatbot', 'pn_ai_chatbot_provider_section' );
        add_settings_field( 'gemini_model_name', __( 'Gemini Model', 'pointnet-ai-chatbot' ), array( $this, 'gemini_model_name_callback' ), 'pointnet-ai-chatbot', 'pn_ai_chatbot_provider_section' );
        add_settings_field( 'openrouter_api_key', __( 'API Key (OpenRouter)', 'pointnet-ai-chatbot' ), array( $this, 'openrouter_api_key_callback' ), 'pointnet-ai-chatbot', 'pn_ai_chatbot_provider_section' );
        add_settings_field( 'openrouter_priority_models', __( 'OpenRouter Priority Models', 'pointnet-ai-chatbot' ), array( $this, 'openrouter_priority_models_callback' ), 'pointnet-ai-chatbot', 'pn_ai_chatbot_provider_section' );

        // SECTION 2: RAG & Knowledge Base
        add_settings_section( 'pn_ai_chatbot_knowledge_section', __( '🧠 Knowledge Base & RAG Tuning', 'pointnet-ai-chatbot' ), array( $this, 'knowledge_section_desc' ), 'pointnet-ai-chatbot' );
        add_settings_field( 'critical_rules', __( '🤖 Critical AI Behavior Rules', 'pointnet-ai-chatbot' ), array( $this, 'critical_rules_callback' ), 'pointnet-ai-chatbot', 'pn_ai_chatbot_knowledge_section' );
        add_settings_field( 'system_prompt', __( 'System Prompt', 'pointnet-ai-chatbot' ), array( $this, 'system_prompt_callback' ), 'pointnet-ai-chatbot', 'pn_ai_chatbot_knowledge_section' );
        add_settings_field( 'contact_url', __( 'Contact Page URL', 'pointnet-ai-chatbot' ), array( $this, 'contact_url_callback' ), 'pointnet-ai-chatbot', 'pn_ai_chatbot_knowledge_section' );
        add_settings_field( 'global_knowledge', __( 'Global Site Knowledge', 'pointnet-ai-chatbot' ), array( $this, 'global_knowledge_callback' ), 'pointnet-ai-chatbot', 'pn_ai_chatbot_knowledge_section' );
        add_settings_field( 'rag_search_depth', __( 'RAG Search Depth', 'pointnet-ai-chatbot' ), array( $this, 'rag_search_depth_callback' ), 'pointnet-ai-chatbot', 'pn_ai_chatbot_knowledge_section' );
        add_settings_field( 'content_truncation_mode', __( 'Content Truncation Mode', 'pointnet-ai-chatbot' ), array( $this, 'content_truncation_mode_callback' ), 'pointnet-ai-chatbot', 'pn_ai_chatbot_knowledge_section' );
        add_settings_field( 'content_truncation_limit', __( 'Max Characters per Post (Uniform)', 'pointnet-ai-chatbot' ), array( $this, 'content_truncation_limit_callback' ), 'pointnet-ai-chatbot', 'pn_ai_chatbot_knowledge_section' );
        add_settings_field( 'content_truncation_adaptive_limits', __( 'Adaptive Limits (Top, Mid, Rest)', 'pointnet-ai-chatbot' ), array( $this, 'content_truncation_adaptive_limits_callback' ), 'pointnet-ai-chatbot', 'pn_ai_chatbot_knowledge_section' );

        // SECTION 3: Appearance & UI
        add_settings_section( 'pn_ai_chatbot_appearance_section', __( '🎨 Appearance & UI', 'pointnet-ai-chatbot' ), array( $this, 'appearance_section_desc' ), 'pointnet-ai-chatbot' );
        add_settings_field( 'welcome_message', __( 'Welcome Message', 'pointnet-ai-chatbot' ), array( $this, 'welcome_message_callback' ), 'pointnet-ai-chatbot', 'pn_ai_chatbot_appearance_section' );
        add_settings_field( 'primary_color', __( 'Primary Color', 'pointnet-ai-chatbot' ), array( $this, 'primary_color_callback' ), 'pointnet-ai-chatbot', 'pn_ai_chatbot_appearance_section' );
        add_settings_field( 'button_opacity', __( 'Background Opacity (%)', 'pointnet-ai-chatbot' ), array( $this, 'button_opacity_callback' ), 'pointnet-ai-chatbot', 'pn_ai_chatbot_appearance_section' );

        // SECTION 4: Security & Rate Limits
        add_settings_section( 'pn_ai_chatbot_security_section', __( '🛡️ Security & Rate Limits', 'pointnet-ai-chatbot' ), array( $this, 'security_section_desc' ), 'pointnet-ai-chatbot' );
        add_settings_field( 'limit_min', __( 'Messages per Minute (per IP)', 'pointnet-ai-chatbot' ), array( $this, 'limit_min_callback' ), 'pointnet-ai-chatbot', 'pn_ai_chatbot_security_section' );
        add_settings_field( 'limit_day_ip', __( 'Daily Messages (per IP)', 'pointnet-ai-chatbot' ), array( $this, 'limit_day_ip_callback' ), 'pointnet-ai-chatbot', 'pn_ai_chatbot_security_section' );
        add_settings_field( 'limit_day_global', __( 'Daily Global Messages', 'pointnet-ai-chatbot' ), array( $this, 'limit_day_global_callback' ), 'pointnet-ai-chatbot', 'pn_ai_chatbot_security_section' );

        // SECTION 5: Advanced Options
        add_settings_section( 'pn_ai_chatbot_advanced_section', __( '⚙️ Advanced Options', 'pointnet-ai-chatbot' ), array( $this, 'advanced_section_desc' ), 'pointnet-ai-chatbot' );
        add_settings_field( 'delete_on_uninstall', __( 'Delete Data on Uninstall', 'pointnet-ai-chatbot' ), array( $this, 'delete_on_uninstall_callback' ), 'pointnet-ai-chatbot', 'pn_ai_chatbot_advanced_section' );
    }

    public function sanitize_settings( $input ) {
        $old_options = get_option( $this->option_name );
        if ( ! is_array( $old_options ) ) $old_options = array();

        $allowed_providers        = array( 'openai', 'gemini', 'openrouter' );
        $sanitized['ai_provider'] = isset( $input['ai_provider'] ) && in_array( $input['ai_provider'], $allowed_providers, true ) ? $input['ai_provider'] : 'openai';

        $sanitized['api_key'] = ( ! empty( $input['api_key'] ) ) ? sanitize_text_field( $input['api_key'] ) : ( isset($old_options['api_key']) ? $old_options['api_key'] : '' );
        $sanitized['openai_model_name'] = isset( $input['openai_model_name'] ) ? sanitize_text_field( $input['openai_model_name'] ) : 'gpt-4o-mini';

        $sanitized['gemini_api_key'] = ( ! empty( $input['gemini_api_key'] ) ) ? sanitize_text_field( $input['gemini_api_key'] ) : ( isset($old_options['gemini_api_key']) ? $old_options['gemini_api_key'] : '' );
        $sanitized['gemini_model_name'] = isset( $input['gemini_model_name'] ) ? sanitize_text_field( $input['gemini_model_name'] ) : 'gemini-3.5-flash';

        $sanitized['openrouter_api_key'] = ( ! empty( $input['openrouter_api_key'] ) ) ? sanitize_text_field( $input['openrouter_api_key'] ) : ( isset($old_options['openrouter_api_key']) ? $old_options['openrouter_api_key'] : '' );
        $sanitized['openrouter_priority_models'] = isset( $input['openrouter_priority_models'] ) ? sanitize_textarea_field( $input['openrouter_priority_models'] ) : '';

        $sanitized['rag_search_depth'] = isset( $input['rag_search_depth'] ) ? absint( $input['rag_search_depth'] ) : 12;
        if($sanitized['rag_search_depth'] < 1) $sanitized['rag_search_depth'] = 1;
        if($sanitized['rag_search_depth'] > 25) $sanitized['rag_search_depth'] = 25;

        $sanitized['welcome_message'] = isset( $input['welcome_message'] ) ? sanitize_text_field( $input['welcome_message'] ) : '';

        if ( isset( $input['primary_color'] ) && preg_match( '/^#[a-fA-F0-9]{6}$/', $input['primary_color'] ) ) {
            $sanitized['primary_color'] = strtolower($input['primary_color']);
        } else {
            $sanitized['primary_color'] = '#0073aa';
        }
        $sanitized['button_opacity'] = isset( $input['button_opacity'] ) ? absint( $input['button_opacity'] ) : 100;
        if ( $sanitized['button_opacity'] < 10 ) $sanitized['button_opacity'] = 10;
        if ( $sanitized['button_opacity'] > 100 ) $sanitized['button_opacity'] = 100;

        $sanitized['contact_url'] = isset( $input['contact_url'] ) ? sanitize_text_field( $input['contact_url'] ) : '/contact/';
        $sanitized['global_knowledge'] = isset( $input['global_knowledge'] ) ? sanitize_textarea_field( $input['global_knowledge'] ) : '';
        $sanitized['system_prompt'] = isset( $input['system_prompt'] ) ? sanitize_textarea_field( $input['system_prompt'] ) : '';

        $sanitized['limit_min'] = isset( $input['limit_min'] ) ? absint( $input['limit_min'] ) : 10;
        $sanitized['limit_day_ip'] = isset( $input['limit_day_ip'] ) ? absint( $input['limit_day_ip'] ) : 100;
        $sanitized['limit_day_global'] = isset( $input['limit_day_global'] ) ? absint( $input['limit_day_global'] ) : 2000;
        $sanitized['content_truncation_mode'] = isset( $input['content_truncation_mode'] ) && $input['content_truncation_mode'] === 'uniform' ? 'uniform' : 'adaptive';
        $sanitized['content_truncation_limit'] = isset( $input['content_truncation_limit'] ) ? absint( $input['content_truncation_limit'] ) : 1500;
        if ( $sanitized['content_truncation_limit'] < 500 ) $sanitized['content_truncation_limit'] = 500;
        if ( $sanitized['content_truncation_limit'] > 20000 ) $sanitized['content_truncation_limit'] = 20000;
        
        // Sanitize adaptive limits: parse comma-separated string, validate each value
        $raw_adaptive = isset( $input['content_truncation_adaptive_limits'] ) ? sanitize_text_field( $input['content_truncation_adaptive_limits'] ) : '6000,3500,1500';
        $parts = array_map( 'absint', explode( ',', $raw_adaptive ) );
        $parts = array_slice( $parts, 0, 3 );
        while ( count( $parts ) < 3 ) $parts[] = 1500;
        foreach ( $parts as $i => $v ) {
            if ( $v < 500 ) $parts[ $i ] = 500;
            if ( $v > 20000 ) $parts[ $i ] = 20000;
        }
        $sanitized['content_truncation_adaptive_limits'] = implode( ',', $parts );

        $sanitized['critical_rules'] = isset( $input['critical_rules'] ) ? sanitize_textarea_field( $input['critical_rules'] ) : '';
        $sanitized['critical_rules_enabled'] = isset( $input['critical_rules_enabled'] ) ? 1 : 0;

        $sanitized['delete_on_uninstall'] = isset( $input['delete_on_uninstall'] ) ? 1 : 0;
        return $sanitized;
    }

    public function provider_section_desc() { 
        echo '<p>' . esc_html__( 'Configure your chosen AI Provider and enter the necessary API keys.', 'pointnet-ai-chatbot' ) . '</p>'; 
        echo '<p style="font-size:12px; color:#666;"><em>' . esc_html__( 'Compliance Notice: By using this plugin, user prompts are securely transmitted to third-party AI APIs. Please review the privacy policies of your chosen provider:', 'pointnet-ai-chatbot' ) . ' <a href="https://openai.com/policies/privacy-policy" target="_blank">OpenAI</a>, <a href="https://policies.google.com/privacy" target="_blank">Google</a>, <a href="https://openrouter.ai/privacy" target="_blank">OpenRouter</a>.</em></p>';
    }
    
    public function knowledge_section_desc() { 
        echo '<p>' . esc_html__( 'Train your chatbot. Configure critical behavior rules, set the tone of voice, provide static company data, and fine-tune the RAG search depth.', 'pointnet-ai-chatbot' ) . '</p>';
    }
    
    public function appearance_section_desc() { 
        echo '<p>' . esc_html__( 'Customize the visual style of your chatbot to match your brand.', 'pointnet-ai-chatbot' ) . '</p>'; 
    }
    public function security_section_desc() { 
        echo '<p>' . esc_html__( 'Protect your API budget by configuring multi-layer rate limits against spam and bot networks.', 'pointnet-ai-chatbot' ) . '</p>'; 
    }
    public function advanced_section_desc() { 
        echo '<p>' . esc_html__( 'Database and uninstallation preferences.', 'pointnet-ai-chatbot' ) . '</p>'; 
    }

    public function ai_provider_callback() {
        $options = get_option( $this->option_name );
        $provider = is_array($options) && isset( $options['ai_provider'] ) ? $options['ai_provider'] : 'openai';
        ?>
        <select id="ai_provider" name="<?php echo esc_attr( $this->option_name ); ?>[ai_provider]">
            <option value="openai" <?php selected( $provider, 'openai' ); ?>><?php esc_html_e( 'OpenAI', 'pointnet-ai-chatbot' ); ?></option>
            <option value="gemini" <?php selected( $provider, 'gemini' ); ?>><?php esc_html_e( 'Google Gemini', 'pointnet-ai-chatbot' ); ?></option>
            <option value="openrouter" <?php selected( $provider, 'openrouter' ); ?>><?php esc_html_e( 'OpenRouter', 'pointnet-ai-chatbot' ); ?></option>
        </select>
        <?php
    }

    public function openai_api_key_callback() {
        $options     = get_option( $this->option_name );
        $api_key     = is_array( $options ) && isset( $options['api_key'] ) ? $options['api_key'] : '';
        $placeholder = ! empty( $api_key ) ? sprintf( /* translators: %s: last 4 characters of the saved API key */ __( 'Saved key ending in %s. Type to change.', 'pointnet-ai-chatbot' ), substr( $api_key, -4 ) ) : '';
        echo '<input type="password" id="api_key" name="' . esc_attr( $this->option_name ) . '[api_key]" value="" placeholder="' . esc_attr( $placeholder ) . '" class="regular-text" autocomplete="new-password" />';
    }

    public function openai_model_name_callback() {
        $options = get_option( $this->option_name );
        $val     = is_array( $options ) && isset( $options['openai_model_name'] ) && ! empty( $options['openai_model_name'] ) ? $options['openai_model_name'] : 'gpt-4o-mini';
        
        echo '<div style="display:flex; align-items:center; gap:10px;">';
        echo '<select id="openai_model_name" name="' . esc_attr( $this->option_name ) . '[openai_model_name]" class="regular-text">';
        echo '<option value="' . esc_attr( $val ) . '">' . esc_html( $val ) . '</option>';
        echo '</select>';
        
        echo '<button type="button" class="button" id="pn_ai_chatbot_fetch_openai_models">' . esc_html__( 'Fetch Models', 'pointnet-ai-chatbot' ) . '</button>';
        echo '<span class="spinner" id="pn_ai_chatbot_openai_spinner" style="float:none; margin:0;"></span>';
        echo '</div>';
        echo '<p class="description" id="pn_ai_chatbot_openai_msg" style="margin-top:5px;">' . esc_html__( 'Click "Fetch Models" to retrieve the list of available text models directly from OpenAI. Recommended: gpt-4o-mini.', 'pointnet-ai-chatbot' ) . '</p>';
    }

    public function ajax_fetch_openai_models() {
        while ( ob_get_level() ) {
            ob_end_clean();
        }

        if ( ! check_ajax_referer( 'pn_ai_chatbot_fetch_openai_models_nonce', 'security', false ) ) {
            wp_send_json_error( __( 'Security token expired. Please reload the page.', 'pointnet-ai-chatbot' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized action.', 'pointnet-ai-chatbot' ) );
        }

        $api_key = isset( $_POST['api_key_typed'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key_typed'] ) ) : '';
        if ( empty( $api_key ) ) {
            $options = get_option( $this->option_name );
            $api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';
        }

        if ( empty( $api_key ) ) {
            wp_send_json_error( __( 'OpenAI API Key is missing. Please enter it above.', 'pointnet-ai-chatbot' ) );
        }

        $url = "https://api.openai.com/v1/models";
        $req = wp_remote_get( $url, array(
            'headers' => array( 'Authorization' => 'Bearer ' . trim($api_key) ),
            'timeout' => 20,
            'sslverify' => apply_filters( 'pn_ai_chatbot_ssl_verify', true )
        ) );

        if ( is_wp_error( $req ) ) {
            wp_send_json_error( __( 'Network error communicating with OpenAI API: ', 'pointnet-ai-chatbot' ) . $req->get_error_message() );
        }

        $response_code = wp_remote_retrieve_response_code( $req );
        $body = wp_remote_retrieve_body( $req );
        $data = json_decode( $body, true );

        if ( $response_code !== 200 ) {
            $error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'HTTP Error ', 'pointnet-ai-chatbot' ) . $response_code;
            wp_send_json_error( 'OpenAI API Error: ' . $error_msg );
        }

        $available_models = array();
        if ( ! empty( $data['data'] ) && is_array( $data['data'] ) ) {
            foreach ( $data['data'] as $m ) {
                $slug = $m['id'];
                if ( strpos( $slug, 'gpt-' ) === 0 || strpos( $slug, 'o1' ) === 0 || strpos( $slug, 'o3' ) === 0 || strpos( $slug, 'chatgpt-' ) === 0 ) {
                    $available_models[$slug] = $slug;
                }
            }
        }
        
        if ( empty( $available_models ) ) {
            wp_send_json_error( __( 'No chat/text generation models found for this key.', 'pointnet-ai-chatbot' ) );
        }

        krsort($available_models); 
        wp_send_json_success( $available_models );
    }

    public function gemini_api_key_callback() {
        $options     = get_option( $this->option_name );
        $api_key     = is_array( $options ) && isset( $options['gemini_api_key'] ) ? $options['gemini_api_key'] : '';
        $placeholder = ! empty( $api_key ) ? sprintf( /* translators: %s: last 4 characters of the saved API key */ __( 'Saved key ending in %s. Type to change.', 'pointnet-ai-chatbot' ), substr( $api_key, -4 ) ) : '';
        echo '<input type="password" id="gemini_api_key" name="' . esc_attr( $this->option_name ) . '[gemini_api_key]" value="" placeholder="' . esc_attr( $placeholder ) . '" class="regular-text" autocomplete="new-password" />';
    }

    public function gemini_model_name_callback() {
        $options = get_option( $this->option_name );
        $val     = is_array( $options ) && isset( $options['gemini_model_name'] ) && ! empty( $options['gemini_model_name'] ) ? $options['gemini_model_name'] : 'gemini-3.5-flash';
        
        echo '<div style="display:flex; align-items:center; gap:10px;">';
        echo '<select id="gemini_model_name" name="' . esc_attr( $this->option_name ) . '[gemini_model_name]" class="regular-text">';
        echo '<option value="' . esc_attr( $val ) . '">' . esc_html( $val ) . '</option>';
        echo '</select>';
        
        echo '<button type="button" class="button" id="pn_ai_chatbot_fetch_gemini_models">' . esc_html__( 'Fetch Models', 'pointnet-ai-chatbot' ) . '</button>';
        echo '<span class="spinner" id="pn_ai_chatbot_gemini_spinner" style="float:none; margin:0;"></span>';
        echo '</div>';
        echo '<p class="description" id="pn_ai_chatbot_gemini_msg" style="margin-top:5px;">' . esc_html__( 'Click "Fetch Models" to retrieve the list of available AI models directly from Google. Recommended: gemini-3.5-flash.', 'pointnet-ai-chatbot' ) . '</p>';
    }

    public function ajax_fetch_gemini_models() {
        while ( ob_get_level() ) {
            ob_end_clean();
        }

        if ( ! check_ajax_referer( 'pn_ai_chatbot_fetch_gemini_models_nonce', 'security', false ) ) {
            wp_send_json_error( __( 'Security token expired. Please reload the page.', 'pointnet-ai-chatbot' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized action.', 'pointnet-ai-chatbot' ) );
        }

        $api_key = isset( $_POST['api_key_typed'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key_typed'] ) ) : '';
        if ( empty( $api_key ) ) {
            $options = get_option( $this->option_name );
            $api_key = isset( $options['gemini_api_key'] ) ? $options['gemini_api_key'] : '';
        }

        if ( empty( $api_key ) ) {
            wp_send_json_error( __( 'Google Gemini API Key is missing. Please enter it above.', 'pointnet-ai-chatbot' ) );
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models";
        $req = wp_remote_get( $url, array(
            'headers' => array( 'x-goog-api-key' => trim($api_key) ),
            'timeout'   => 20,
            'sslverify' => apply_filters( 'pn_ai_chatbot_ssl_verify', true )
        ) );

        if ( is_wp_error( $req ) ) {
            wp_send_json_error( __( 'Network error communicating with Google API: ', 'pointnet-ai-chatbot' ) . $req->get_error_message() );
        }

        $response_code = wp_remote_retrieve_response_code( $req );
        $body = wp_remote_retrieve_body( $req );
        $data = json_decode( $body, true );

        if ( $response_code !== 200 ) {
            $error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'HTTP Error ', 'pointnet-ai-chatbot' ) . $response_code;
            if ( strpos( $error_msg, 'API key not valid' ) !== false ) {
                $error_msg = __( 'The API Key is invalid. Ensure it is copied correctly without trailing spaces.', 'pointnet-ai-chatbot' );
            }
            wp_send_json_error( 'Google API Error: ' . $error_msg );
        }

        $available_models = array();
        if ( ! empty( $data['models'] ) && is_array( $data['models'] ) ) {
            foreach ( $data['models'] as $m ) {
                if ( isset( $m['supportedGenerationMethods'] ) && in_array( 'generateContent', $m['supportedGenerationMethods'] ) ) {
                    $slug = str_replace( 'models/', '', $m['name'] );
                    $display_name = isset( $m['displayName'] ) ? $m['displayName'] : $slug;
                    $available_models[$slug] = $display_name . ' (' . $slug . ')';
                }
            }
        }
        
        if ( empty( $available_models ) ) {
            wp_send_json_error( __( 'No text generation models found for this key.', 'pointnet-ai-chatbot' ) );
        }

        asort($available_models);
        wp_send_json_success( $available_models );
    }

    public function openrouter_api_key_callback() {
        $options     = get_option( $this->option_name );
        $api_key     = is_array( $options ) && isset( $options['openrouter_api_key'] ) ? $options['openrouter_api_key'] : '';
        $placeholder = ! empty( $api_key ) ? sprintf( /* translators: %s: last 4 characters of the saved API key */ __( 'Saved key ending in %s. Type to change.', 'pointnet-ai-chatbot' ), substr( $api_key, -4 ) ) : '';
        echo '<input type="password" id="openrouter_api_key" name="' . esc_attr( $this->option_name ) . '[openrouter_api_key]" value="" placeholder="' . esc_attr( $placeholder ) . '" class="regular-text" autocomplete="new-password" />';
    }

    public function openrouter_priority_models_callback() {
        $options = get_option( $this->option_name );
        $default_models = 'openai/gpt-oss-120b:free, google/gemini-2.5-flash:free, meta-llama/llama-3.1-8b-instruct:free, meta-llama/llama-3-8b-instruct:free';
        $val = is_array($options) && isset( $options['openrouter_priority_models'] ) && !empty($options['openrouter_priority_models']) ? $options['openrouter_priority_models'] : $default_models;
        echo '<textarea id="openrouter_priority_models" name="' . esc_attr( $this->option_name ) . '[openrouter_priority_models]" rows="3" cols="60" class="large-text">' . esc_textarea( $val ) . '</textarea>';
        echo '<p class="description">' . esc_html__( 'Enter model slugs separated by commas. The plugin will try them in order. If all fail, the automatic Joker (openrouter/free) will trigger, filtered by the Quality Control Bouncer.', 'pointnet-ai-chatbot' ) . '</p>';
    }

    public function welcome_message_callback() {
        $options = get_option( $this->option_name );
        $val = is_array($options) && isset( $options['welcome_message'] ) ? $options['welcome_message'] : '';
        echo '<input type="text" id="welcome_message" name="' . esc_attr( $this->option_name ) . '[welcome_message]" value="' . esc_attr( $val ) . '" class="large-text" placeholder="' . esc_attr__( 'Hello! How can I help you today?', 'pointnet-ai-chatbot' ) . '" />';
        echo '<p class="description">' . esc_html__( 'Enter a custom initial greeting. Leave blank for default.', 'pointnet-ai-chatbot' ) . '</p>';
    }

    public function primary_color_callback() {
        $options = get_option( $this->option_name );
        $val = is_array($options) && isset( $options['primary_color'] ) ? $options['primary_color'] : '#0073aa';
        echo '<input type="text" id="primary_color" name="' . esc_attr( $this->option_name ) . '[primary_color]" value="' . esc_attr( $val ) . '" class="pn-ai-chatbot-color-picker" />';
    }

    public function button_opacity_callback() {
        $options = get_option( $this->option_name );
        $val = is_array($options) && isset( $options['button_opacity'] ) ? $options['button_opacity'] : 100;
        echo '<input type="range" id="button_opacity" name="' . esc_attr( $this->option_name ) . '[button_opacity]" value="' . esc_attr( $val ) . '" min="10" max="100" style="vertical-align: middle;" oninput="document.getElementById(\'opacity_output\').value = this.value + \'% \'" />';
        echo '<output id="opacity_output" style="display:inline-block; margin-left:10px; font-weight:bold;">' . esc_html( $val ) . '%</output>';
        echo '<p class="description">' . esc_html__( 'Adjust the transparency of the chatbot button and header.', 'pointnet-ai-chatbot' ) . '</p>';
    }

    public function rag_search_depth_callback() {
        $options = get_option( $this->option_name );
        $val = is_array($options) && isset( $options['rag_search_depth'] ) ? $options['rag_search_depth'] : 12;
        echo '<input type="number" id="rag_search_depth" name="' . esc_attr( $this->option_name ) . '[rag_search_depth]" value="' . esc_attr( $val ) . '" class="small-text" min="1" max="25" />';
        echo '<p class="description">' . esc_html__( 'Maximum number of pages/categories the AI fetches per question. Default: 12. Higher = broader context but slower response times.', 'pointnet-ai-chatbot' ) . '</p>';
    }

    public function contact_url_callback() {
        $options = get_option( $this->option_name );
        $val = is_array($options) && isset( $options['contact_url'] ) ? $options['contact_url'] : '/contact/';
        echo '<input type="text" id="contact_url" name="' . esc_attr( $this->option_name ) . '[contact_url]" value="' . esc_attr( $val ) . '" class="regular-text" placeholder="e.g. /contact/" />';
        echo '<p class="description">' . esc_html__( 'Enter the exact URL of your contact page. The bot will automatically use this link to redirect clients.', 'pointnet-ai-chatbot' ) . '</p>';
    }

    public function content_truncation_mode_callback() {
        $options = get_option( $this->option_name );
        $mode = is_array($options) && isset( $options['content_truncation_mode'] ) ? $options['content_truncation_mode'] : 'adaptive';
        ?>
        <select id="content_truncation_mode" name="<?php echo esc_attr( $this->option_name ); ?>[content_truncation_mode]">
            <option value="uniform" <?php selected( $mode, 'uniform' ); ?>><?php esc_html_e( 'Uniform – same limit for all posts', 'pointnet-ai-chatbot' ); ?></option>
            <option value="adaptive" <?php selected( $mode, 'adaptive' ); ?>><?php esc_html_e( 'Adaptive – more chars for top results (recommended)', 'pointnet-ai-chatbot' ); ?></option>
        </select>
        <p class="description">
            <strong><?php esc_html_e( 'Uniform:', 'pointnet-ai-chatbot' ); ?></strong> <?php esc_html_e( 'all posts are truncated to the same character limit (set below).', 'pointnet-ai-chatbot' ); ?><br>
            <strong><?php esc_html_e( 'Adaptive:', 'pointnet-ai-chatbot' ); ?></strong> <?php esc_html_e( 'top 3 results get more chars (default: 4000), positions 4-7 get medium (default: 3000), the rest get less (default: 1500). Configure custom limits below. Use this to capture more content from the most relevant pages while saving tokens on the rest.', 'pointnet-ai-chatbot' ); ?>
        </p>
        <p class="description" style="color:#d63638;">
            ⚠️ <?php esc_html_e( 'Warning: higher limits = more characters sent to the AI = higher token consumption and API costs. Adjust carefully.', 'pointnet-ai-chatbot' ); ?>
        </p>
        <?php
    }

    public function content_truncation_limit_callback() {
        $options = get_option( $this->option_name );
        $val = is_array($options) && isset( $options['content_truncation_limit'] ) ? $options['content_truncation_limit'] : 1500;
        echo '<input type="number" id="content_truncation_limit" name="' . esc_attr( $this->option_name ) . '[content_truncation_limit]" value="' . esc_attr( $val ) . '" class="small-text" min="500" max="20000" step="100" />';
        echo '<p class="description">' . esc_html__( 'Maximum number of characters per post when using Uniform mode. Default: 1500. Values below 500 are not recommended (too little context).', 'pointnet-ai-chatbot' ) . '</p>';
        echo '<p class="description" style="color:#d63638;">⚠️ ' . esc_html__( 'Higher values increase token consumption and API costs.', 'pointnet-ai-chatbot' ) . '</p>';
    }

    public function content_truncation_adaptive_limits_callback() {
        $options = get_option( $this->option_name );
        $default = '4000,3000,1500';
        $val = is_array($options) && isset( $options['content_truncation_adaptive_limits'] ) ? $options['content_truncation_adaptive_limits'] : $default;
        echo '<input type="text" id="content_truncation_adaptive_limits" name="' . esc_attr( $this->option_name ) . '[content_truncation_adaptive_limits]" value="' . esc_attr( $val ) . '" class="regular-text" placeholder="e.g. 6000,3500,1500" />';
        echo '<p class="description">' . esc_html__( 'Three comma-separated numbers: positions 1-3, positions 4-7, rest (8+). Default: 4000,3000,1500. Minimum 500, maximum 20000 each.', 'pointnet-ai-chatbot' ) . '</p>';
        echo '<p class="description" style="color:#d63638;">⚠️ ' . esc_html__( 'Higher values increase token consumption and API costs.', 'pointnet-ai-chatbot' ) . '</p>';
    }

    public function critical_rules_callback() {
        $options = get_option( $this->option_name );
        $default = "CRITICAL RULES:\n1. Respond in User Language.\n2. Never show internal reasoning.\n3. Links: [Title](URL).\n4. ANTI-AMNESIA & CONVERSATION FOCUS: If the user asks a follow-up question with an implicit subject or pronoun (e.g. 'which is the strongest?', 'how do I make it?', 'how much?'), they are referring exclusively to the specific entity/topic discussed in the IMMEDIATE PREVIOUS message. You MUST analyze only the data of that previous entity from the Chat History, and completely ignore other unrelated search results in the SITE DATA.\n5. PARTIAL DATA WARNING: The SITE DATA contains page EXCERPTS (first N characters), NOT full content. If specific information (e.g. certifications, details, skills) is not found in the excerpt, do NOT assume or claim it is absent from the page. Instead respond: 'The excerpt available does not mention [topic]. See the full page: [Page Title](URL).' NEVER make definitive negative statements like 'the page does not contain', 'does not list', or 'is not indicated'.\n6. LINK INTEGRITY: Only use URLs that appear verbatim in the provided SITE DATA. NEVER guess, generate, or construct URLs for external websites based on institution names, file names, or any other contextual clues. If the SITE DATA contains text that resembles a filename or reference but does not contain a full clickable URL, the URL is absent — do NOT fabricate one. NEVER use a page URL as a generic 'download' link for individual sub-items such as certifications, courses, or files — the page URL belongs solely to the page itself, not to its items. NEVER repeat the same URL more than once in a list — if the page URL is the only available reference, link it once at the top and list the items without hyperlinks. If no specific URL (e.g. a PDF download from /wp-content/uploads/) is present for a given item, list it as plain text without any hyperlink. If the entire page is referenced, the page URL is acceptable for that purpose alone. (EXCEPTION: For contact and appointment inquiries, you are explicitly authorized and required to use the official Contact Page URL provided in the instructions).";
        $val = is_array($options) && isset( $options['critical_rules'] ) && ! empty( $options['critical_rules'] ) ? $options['critical_rules'] : $default;
        $enabled = is_array($options) && isset( $options['critical_rules_enabled'] ) ? (bool) $options['critical_rules_enabled'] : false;
        ?>
        <div style="margin-bottom:8px;">
            <input type="checkbox" id="critical_rules_enabled" name="<?php echo esc_attr( $this->option_name ); ?>[critical_rules_enabled]" value="1" <?php checked( $enabled, true ); ?> />
            <label for="critical_rules_enabled">
                <strong><?php esc_html_e( 'Enable manual editing', 'pointnet-ai-chatbot' ); ?></strong>
            </label>
        </div>
        <p class="description" id="critical_rules_warning" style="color:#d63638; display:<?php echo $enabled ? 'block' : 'none'; ?>; margin-bottom:8px;">
            ⚠️ <?php esc_html_e( 'Only modify these rules if you know what you are doing. Incorrect changes can break the chatbot behavior (language, links, data honesty).', 'pointnet-ai-chatbot' ); ?>
        </p>
        <textarea id="critical_rules" name="<?php echo esc_attr( $this->option_name ); ?>[critical_rules]" rows="12" cols="60" class="large-text" <?php echo $enabled ? '' : 'readonly'; ?>><?php echo esc_textarea( $val ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'These rules are sent before the System Prompt with every request. They define mandatory AI behavior — language, link format, data honesty, etc. Use built-in defaults when editing is disabled.', 'pointnet-ai-chatbot' ); ?>
            <br><strong><?php esc_html_e( 'Important:', 'pointnet-ai-chatbot' ); ?></strong>
            <?php esc_html_e( 'These rules are visible here so you know exactly what is sent to the AI. Avoid duplicating them in the System Prompt above to prevent unnecessary token waste.', 'pointnet-ai-chatbot' ); ?>
        </p>
        <?php
    }

    public function global_knowledge_callback() {
        $options = get_option( $this->option_name );
        $global_knowledge = is_array($options) && isset( $options['global_knowledge'] ) ? $options['global_knowledge'] : '';
        ?>
        <textarea id="global_knowledge" name="<?php echo esc_attr( $this->option_name ); ?>[global_knowledge]" rows="6" cols="60" class="large-text"><?php echo esc_textarea( $global_knowledge ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Enter here the general information that the assistant must always know, regardless of the site pages.', 'pointnet-ai-chatbot' ); ?>
            <br><strong><?php esc_html_e( 'Example:', 'pointnet-ai-chatbot' ); ?></strong>
            <em><?php esc_html_e( 'We are a web agency based in Lucca. Tel: +39 0583 123456. Open Mon-Fri 9-18. We offer SEO, hosting and WordPress development services.', 'pointnet-ai-chatbot' ); ?></em>
            <br><?php esc_html_e( 'These data are added to the context of every conversation.', 'pointnet-ai-chatbot' ); ?>
        </p>
        <?php
    }

    public function system_prompt_callback() {
        $options = get_option( $this->option_name );
        $default_prompt = 'You are the website virtual assistant. Answer exclusively using the provided site data.';
        $system_prompt = is_array($options) && isset( $options['system_prompt'] ) ? $options['system_prompt'] : $default_prompt;
        ?>
        <textarea id="system_prompt" name="<?php echo esc_attr( $this->option_name ); ?>[system_prompt]" rows="6" cols="60" class="large-text"><?php echo esc_textarea( $system_prompt ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Define the assistant\'s behaviour and tone. These instructions are followed in every response.', 'pointnet-ai-chatbot' ); ?>
            <br><strong><?php esc_html_e( 'Example:', 'pointnet-ai-chatbot' ); ?></strong>
            <em><?php esc_html_e( 'You are the website virtual assistant. Reply in Italian, politely and concisely. Never invent information. Use links in the format [Text](URL).', 'pointnet-ai-chatbot' ); ?></em>
            <br><?php esc_html_e( 'The System Prompt is sent as a system instruction to the AI with every request.', 'pointnet-ai-chatbot' ); ?>
        </p>
        <?php
    }

    public function limit_min_callback() {
        $options = get_option( $this->option_name );
        $val = is_array($options) && isset( $options['limit_min'] ) ? $options['limit_min'] : 10;
        echo '<input type="number" id="limit_min" name="' . esc_attr( $this->option_name ) . '[limit_min]" value="' . esc_attr( $val ) . '" class="small-text" min="1" />';
    }

    public function limit_day_ip_callback() {
        $options = get_option( $this->option_name );
        $val = is_array($options) && isset( $options['limit_day_ip'] ) ? $options['limit_day_ip'] : 100;
        echo '<input type="number" id="limit_day_ip" name="' . esc_attr( $this->option_name ) . '[limit_day_ip]" value="' . esc_attr( $val ) . '" class="small-text" min="1" />';
    }

    public function limit_day_global_callback() {
        $options = get_option( $this->option_name );
        $val = is_array($options) && isset( $options['limit_day_global'] ) ? $options['limit_day_global'] : 2000;
        echo '<input type="number" id="limit_day_global" name="' . esc_attr( $this->option_name ) . '[limit_day_global]" value="' . esc_attr( $val ) . '" class="small-text" min="1" />';
    }

    public function delete_on_uninstall_callback() {
        $options = get_option( $this->option_name );
        $delete_on_uninstall = is_array($options) && isset( $options['delete_on_uninstall'] ) ? $options['delete_on_uninstall'] : 0;
        ?>
        <input type="checkbox" id="delete_on_uninstall" name="<?php echo esc_attr( $this->option_name ); ?>[delete_on_uninstall]" value="1" <?php checked( $delete_on_uninstall, 1 ); ?> />
        <label for="delete_on_uninstall"><?php esc_html_e( 'Delete all keys, settings, and logs from database upon plugin deletion.', 'pointnet-ai-chatbot' ); ?></label>
        <?php
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';
        ?>
        <style>
            .pn-ai-chatbot-admin-body {
                background: #f0f0f1 !important;
                padding: 20px 0 0 0 !important;
                border: none !important;
            }
            .pn-ai-chatbot-admin-card {
                background-color: #fff;
                border: 1px solid #e2e2e2;
                margin-bottom: 24px;
                padding: 28px;
                border-radius: 8px;
                box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
            }
            .pn-ai-chatbot-admin-card h2 {
                margin-top: 0 !important;
                font-size: 1.35em !important;
                font-weight: 600 !important;
                padding-bottom: 12px;
                border-bottom: 1px solid #f0f0f1;
                color: #1d2327;
            }
            .pn-ai-chatbot-admin-card p {
                color: #646970;
                font-size: 13px;
                margin-top: 10px;
                margin-bottom: 20px;
            }
            .pn-ai-chatbot-admin-card .form-table { margin-top: 0; }
            .pn-ai-chatbot-admin-card .form-table th { width: 240px; padding: 16px 12px 16px 0; font-weight: 600; }
            .pn-ai-chatbot-admin-card .form-table td { padding: 12px 12px; }
        </style>
        <div class="wrap">
            <h1 style="display:flex; align-items:flex-start; gap:10px;">
                <?php esc_html_e( 'PointNet AI Chatbot', 'pointnet-ai-chatbot' ); ?>
                <small style="font-size:14px; font-weight:400; color:#999;">v<?php echo esc_html( PN_AI_CHATBOT_VERSION ); ?></small>
            </h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=pointnet-ai-chatbot&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Settings', 'pointnet-ai-chatbot' ); ?></a>
                <a href="?page=pointnet-ai-chatbot&tab=analyzer" class="nav-tab <?php echo $active_tab == 'analyzer' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Stop Words & Analysis', 'pointnet-ai-chatbot' ); ?></a>
                <a href="?page=pointnet-ai-chatbot&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Chat History Logs', 'pointnet-ai-chatbot' ); ?></a>
            </h2>
            <div class="pn-ai-chatbot-admin-body">
                <?php 
                if ( $active_tab == 'settings' ) { 
                    $this->render_settings_tab(); 
                } elseif ( $active_tab == 'analyzer' ) {
                    $this->render_analyzer_tab();
                } else { 
                    $this->render_logs_tab(); 
                } 
                ?>
            </div>
        </div>
        <?php
    }

    private function render_settings_tab() {
        ?>
        <form method="post" action="options.php">
            <?php 
                settings_fields( $this->option_group ); 
                do_settings_sections( 'pointnet-ai-chatbot' ); 
                submit_button( __( 'Save Settings', 'pointnet-ai-chatbot' ) ); 
            ?>
        </form>
        <?php
    }

    private function render_analyzer_tab() {
        $current_stops = get_option( 'pn_ai_chatbot_stop_words', '' );
        ?>
        
        <!-- Manual Stop Words -->
        <div class="pn-ai-chatbot-admin-card" style="margin-bottom: 24px;">
            <h2><?php esc_html_e( 'Manual Stop Words', 'pointnet-ai-chatbot' ); ?></h2>
            <p><?php esc_html_e( 'Enter words that the bot should ignore during search. The Analyzer below helps you identify redundant words (Flood Words) to add.', 'pointnet-ai-chatbot' ); ?></p>
            <div style="display:flex; gap:10px; align-items:center;">
                <input type="text" id="pn_ai_chatbot_manual_stops_input" value="<?php echo esc_attr( $current_stops ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'e.g. cocktail, recipe, info', 'pointnet-ai-chatbot' ); ?>" style="margin:0; width: 60%;">
                <button type="button" id="pn_ai_chatbot_save_manual_btn" class="button button-primary"><?php esc_html_e( 'Save Words', 'pointnet-ai-chatbot' ); ?></button>
                <span class="spinner" id="pn_ai_chatbot_manual_spinner" style="float:none; margin:0;"></span>
                <span id="pn_ai_chatbot_manual_msg" style="font-weight:bold; margin-left:10px;"></span>
            </div>
        </div>

        <!-- Flood Words Analyzer -->
        <div class="pn-ai-chatbot-admin-card">
            <h2><?php esc_html_e( 'Flood Words Analyzer', 'pointnet-ai-chatbot' ); ?></h2>
            <p><?php esc_html_e( 'Discover domain-specific words that appear in too many pages (Flood Words). The analyzer calculates Coverage and Total Frequency (TF) from the latest 500 public articles. Words with high coverage reduce RAG search precision and should be added to your Stop Words.', 'pointnet-ai-chatbot' ); ?></p>
            
            <button type="button" class="button button-primary button-large" id="pn_ai_chatbot_scan_btn">
                <?php esc_html_e( 'Scan Site and Analyze Redundant Words', 'pointnet-ai-chatbot' ); ?>
            </button>
            <span class="spinner" id="pn_ai_chatbot_scan_spinner" style="float:none; margin-left:10px;"></span>
            
            <div id="pn_ai_chatbot_scan_table_wrap" style="display:none; margin-top: 30px;">
                <table class="wp-list-table widefat fixed striped" id="pn_ai_chatbot_scan_table">
                    <thead>
                        <tr>
                            <th style="width:20%;"><?php esc_html_e( 'Word', 'pointnet-ai-chatbot' ); ?></th>
                            <th style="width:15%;"><?php esc_html_e( 'Frequency (TF)', 'pointnet-ai-chatbot' ); ?></th>
                            <th style="width:15%;"><?php esc_html_e( 'Appears in', 'pointnet-ai-chatbot' ); ?></th>
                            <th style="width:15%;"><?php esc_html_e( 'Coverage', 'pointnet-ai-chatbot' ); ?></th>
                            <th style="width:20%;"><?php esc_html_e( 'Suggestion', 'pointnet-ai-chatbot' ); ?></th>
                            <th style="width:15%;"><?php esc_html_e( 'Action', 'pointnet-ai-chatbot' ); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
        <?php
    }

    private function render_logs_tab() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'pn_ai_chatbot_logs';
        $logs = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY id DESC LIMIT 150", ARRAY_A );
        if ( ! is_array( $logs ) ) { $logs = array(); }
        ?>
        <div class="pn-ai-chatbot-admin-card">
            <h2><?php esc_html_e( 'Chat History Logs', 'pointnet-ai-chatbot' ); ?></h2>
            <p><?php esc_html_e( 'Monitor and download visitor chat interactions to optimize your prompts and knowledge base. Now includes token usage data.', 'pointnet-ai-chatbot' ); ?></p>
            <?php if ( isset( $_GET['message'] ) && $_GET['message'] === 'logs_cleared' ) : ?>
                <div class="notice notice-success is-dismissible" style="margin-left: 0;"><p><?php esc_html_e( 'Chat history cleared successfully.', 'pointnet-ai-chatbot' ); ?></p></div>
            <?php endif; ?>
            <div style="margin: 20px 0; display: flex; gap: 10px;">
                <?php if ( ! empty( $logs ) ) : ?>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'options-general.php?page=pointnet-ai-chatbot&tab=logs&action=download_csv' ), 'pn_ai_chatbot_download_logs_action', 'nonce' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Download Logs (CSV)', 'pointnet-ai-chatbot' ); ?></a>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;" onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to clear all logs?', 'pointnet-ai-chatbot' ) ); ?>');">
                        <?php wp_nonce_field( 'pn_ai_chatbot_clear_logs_post_action', 'pn_ai_chatbot_clear_nonce' ); ?>
                        <input type="hidden" name="action" value="pn_ai_chatbot_clear_logs" />
                        <button type="submit" class="button button-secondary" style="color: #bc0b0b; border-color: #bc0b0b;">
                            <?php esc_html_e( 'Clear History Logs', 'pointnet-ai-chatbot' ); ?>
                        </button>
                    </form>
                <?php else : ?>
                    <button class="button" disabled><?php esc_html_e( 'No logs available', 'pointnet-ai-chatbot' ); ?></button>
                <?php endif; ?>
            </div>
            <div style="overflow-x: auto;">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 12%;"><?php esc_html_e( 'Date & Time', 'pointnet-ai-chatbot' ); ?></th>
                            <th style="width: 15%;"><?php esc_html_e( 'Model Details', 'pointnet-ai-chatbot' ); ?></th>
                            <th style="width: 5%;"><?php esc_html_e( 'Time', 'pointnet-ai-chatbot' ); ?></th>
                            <th style="width: 6%;"><?php esc_html_e( 'Tokens In', 'pointnet-ai-chatbot' ); ?></th>
                            <th style="width: 6%;"><?php esc_html_e( 'Tokens Out', 'pointnet-ai-chatbot' ); ?></th>
                            <th style="width: 6%;"><?php esc_html_e( 'Total Tokens', 'pointnet-ai-chatbot' ); ?></th>
                            <th style="width: 20%;"><?php esc_html_e( 'User Question', 'pointnet-ai-chatbot' ); ?></th>
                            <th style="width: 30%;"><?php esc_html_e( 'Model Response', 'pointnet-ai-chatbot' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $logs ) ) : foreach ( $logs as $log ) : if ( is_array( $log ) && isset( $log['timestamp'] ) ) : ?>
                            <tr>
                                <td><strong><?php echo esc_html( $log['timestamp'] ); ?></strong></td>
                                <td><span class="badge" style="background:#e1f5fe; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:bold;"><?php echo esc_html( isset($log['provider']) ? $log['provider'] : 'Unknown' ); ?></span></td>
                                <td><span style="color:#0073aa; font-weight:bold;"><?php echo esc_html( isset($log['duration']) && $log['duration'] > 0 ? $log['duration'] . 's' : '-' ); ?></span></td>
                                <td><?php echo esc_html( isset($log['tokens_in']) ? $log['tokens_in'] : '-' ); ?></td>
                                <td><?php echo esc_html( isset($log['tokens_out']) ? $log['tokens_out'] : '-' ); ?></td>
                                <td><strong><?php echo esc_html( isset($log['tokens_total']) ? $log['tokens_total'] : '-' ); ?></strong></td>
                                <td style="white-space: pre-wrap; font-size: 13px;"><?php echo esc_html( isset($log['question']) ? $log['question'] : '' ); ?></td>
                                <td style="white-space: pre-wrap; font-size: 13px;"><?php echo esc_html( isset($log['answer']) ? $log['answer'] : '' ); ?></td>
                            </tr>
                        <?php endif; endforeach; else : ?>
                            <tr><td colspan="8" style="text-align: center; padding: 20px;"><?php esc_html_e( 'No recorded chat conversations.', 'pointnet-ai-chatbot' ); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function handle_log_download() {
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'pointnet-ai-chatbot' && isset( $_GET['tab'] ) && $_GET['tab'] === 'logs' && isset( $_GET['action'] ) && $_GET['action'] === 'download_csv' ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Insufficient permissions.', 'pointnet-ai-chatbot' ), 403 );
            }
            check_admin_referer( 'pn_ai_chatbot_download_logs_action', 'nonce' );
            global $wpdb;
            $table_name = $wpdb->prefix . 'pn_ai_chatbot_logs';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $logs = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY id DESC LIMIT 150", ARRAY_A );
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename=pointnet-ai-chatbot-logs-' . gmdate( 'Y-m-d' ) . '.csv' );
            $output = fopen( 'php://output', 'w' );
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- WP_Filesystem cannot write to php://output stream; UTF-8 BOM required for CSV Excel compatibility.
            fwrite( $output, "\xEF\xBB\xBF" );
            fputcsv(
                $output,
                array(
                    __( 'Date & Time', 'pointnet-ai-chatbot' ),
                    __( 'Specific Model', 'pointnet-ai-chatbot' ),
                    __( 'Time (s)', 'pointnet-ai-chatbot' ),
                    __( 'Tokens In', 'pointnet-ai-chatbot' ),
                    __( 'Tokens Out', 'pointnet-ai-chatbot' ),
                    __( 'Total Tokens', 'pointnet-ai-chatbot' ),
                    __( 'User Question', 'pointnet-ai-chatbot' ),
                    __( 'Response', 'pointnet-ai-chatbot' ),
                )
            );

            $sanitize_csv = static function( $val ): string {
                $str = (string) $val;
                if ( preg_match( '/^[\s=\+\-@\t\r]/', $str ) ) {
                    return "'" . $str;
                }
                return $str;
            };

            foreach ( $logs as $log ) {
                if ( is_array( $log ) ) {
                    fputcsv(
                        $output,
                        array(
                            $sanitize_csv( isset( $log['timestamp'] ) ? $log['timestamp'] : '' ),
                            $sanitize_csv( isset( $log['provider'] ) ? $log['provider'] : '' ),
                            $sanitize_csv( isset( $log['duration'] ) ? $log['duration'] : '' ),
                            $sanitize_csv( isset( $log['tokens_in'] ) ? $log['tokens_in'] : '' ),
                            $sanitize_csv( isset( $log['tokens_out'] ) ? $log['tokens_out'] : '' ),
                            $sanitize_csv( isset( $log['tokens_total'] ) ? $log['tokens_total'] : '' ),
                            $sanitize_csv( isset( $log['question'] ) ? $log['question'] : '' ),
                            $sanitize_csv( isset( $log['answer'] ) ? $log['answer'] : '' ),
                        )
                    );
                }
            }
            exit;
        }
    }

    public function handle_log_clear_post(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'pointnet-ai-chatbot' ), 403 );
        }
        check_admin_referer( 'pn_ai_chatbot_clear_logs_post_action', 'pn_ai_chatbot_clear_nonce' );
        global $wpdb;
        $table_name = $wpdb->prefix . 'pn_ai_chatbot_logs';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query( "TRUNCATE TABLE {$table_name}" );
        wp_safe_redirect( admin_url( 'options-general.php?page=pointnet-ai-chatbot&tab=logs&message=logs_cleared' ) );
        exit;
    }
}
jQuery(document).ready(function($) {
    // 1. Group form-table rows into admin cards
    $('.form-table').each(function() {
        var $table = $(this);
        var $prevSiblings = $table.prevUntil('.form-table, .pn-ai-chatbot-admin-card');
        var $group = $table.add($prevSiblings);
        $group.wrapAll('<div class="pn-ai-chatbot-admin-card"></div>');
    });

    // 2. Initialize WordPress Color Picker if available
    if ($.fn.wpColorPicker) {
        $('.pn-ai-chatbot-color-picker').wpColorPicker();
    }

    // 3. Provider fields toggle
    function toggleProviderFields() {
        var provider = $('#ai_provider').val();
        
        $('#api_key').closest('tr').hide();
        $('#openai_model_name').closest('tr').hide();
        $('#gemini_api_key').closest('tr').hide();
        $('#gemini_model_name').closest('tr').hide();
        $('#openrouter_api_key').closest('tr').hide();
        $('#openrouter_priority_models').closest('tr').hide();
        
        if (provider === 'openai') {
            $('#api_key').closest('tr').show();
            $('#openai_model_name').closest('tr').show();
        } else if (provider === 'gemini') {
            $('#gemini_api_key').closest('tr').show();
            $('#gemini_model_name').closest('tr').show();
        } else if (provider === 'openrouter') {
            $('#openrouter_api_key').closest('tr').show();
            $('#openrouter_priority_models').closest('tr').show();
        }
    }

    $('#ai_provider').on('change', toggleProviderFields);
    toggleProviderFields();

    // 4. Truncation mode toggle
    function toggleTruncationFields() {
        var mode = $('#content_truncation_mode').val();
        if (mode === 'adaptive') {
            $('#content_truncation_limit').closest('tr').hide();
            $('#content_truncation_adaptive_limits').closest('tr').show();
        } else {
            $('#content_truncation_limit').closest('tr').show();
            $('#content_truncation_adaptive_limits').closest('tr').hide();
        }
    }

    $('#content_truncation_mode').on('change', toggleTruncationFields);
    toggleTruncationFields();

    // 5. Critical rules manual editing toggle
    $('#critical_rules_enabled').on('change', function() {
        var checked = $(this).is(':checked');
        $('#critical_rules').prop('readonly', !checked);
        $('#critical_rules_warning').toggle(checked);
    });

    // 6. Fetch OpenAI models via AJAX
    $('#pn_ai_chatbot_fetch_openai_models').on('click', function(e) {
        e.preventDefault();
        var apiKeyInput = $('#api_key').val();
        var $btn = $(this);
        var $spinner = $('#pn_ai_chatbot_openai_spinner');
        var $select = $('#openai_model_name');
        var $msg = $('#pn_ai_chatbot_openai_msg');
        var currentVal = $select.val();

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $msg.css('color', '#0073aa').text(pnAiChatbotAdmin.i18n.fetching_openai);

        $.ajax({
            url: pnAiChatbotAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'pn_ai_chatbot_fetch_openai_models',
                security: pnAiChatbotAdmin.nonces.fetch_openai,
                api_key_typed: apiKeyInput
            },
            success: function(res) {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
                
                if (res && res.success) {
                    $select.empty();
                    $.each(res.data, function(slug, name) {
                        var $opt = $('<option></option>').val(slug).text(name);
                        if (slug === currentVal) {
                            $opt.prop('selected', true);
                        }
                        $select.append($opt);
                    });
                    $msg.css('color', '#00a32a').text(pnAiChatbotAdmin.i18n.models_updated);
                } else {
                    var errMsg = (res && res.data) ? res.data : pnAiChatbotAdmin.i18n.unknown_error;
                    $msg.css('color', '#d63638').text('Error: ' + errMsg);
                }
            },
            error: function(xhr, status, error) {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
                $msg.css('color', '#d63638').text(pnAiChatbotAdmin.i18n.server_error);
                console.error('OpenAI fetch models AJAX error', xhr.status, error, xhr.responseText);
            }
        });
    });

    // 7. Fetch Gemini models via AJAX
    $('#pn_ai_chatbot_fetch_gemini_models').on('click', function(e) {
        e.preventDefault();
        var apiKeyInput = $('#gemini_api_key').val();
        var $btn = $(this);
        var $spinner = $('#pn_ai_chatbot_gemini_spinner');
        var $select = $('#gemini_model_name');
        var $msg = $('#pn_ai_chatbot_gemini_msg');
        var currentVal = $select.val();

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $msg.css('color', '#0073aa').text(pnAiChatbotAdmin.i18n.fetching_gemini);

        $.ajax({
            url: pnAiChatbotAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'pn_ai_chatbot_fetch_gemini_models',
                security: pnAiChatbotAdmin.nonces.fetch_gemini,
                api_key_typed: apiKeyInput
            },
            success: function(res) {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
                
                if (res && res.success) {
                    $select.empty();
                    $.each(res.data, function(slug, name) {
                        var $opt = $('<option></option>').val(slug).text(name);
                        if (slug === currentVal) {
                            $opt.prop('selected', true);
                        }
                        $select.append($opt);
                    });
                    $msg.css('color', '#00a32a').text(pnAiChatbotAdmin.i18n.models_updated);
                } else {
                    var errMsg = (res && res.data) ? res.data : pnAiChatbotAdmin.i18n.unknown_error;
                    $msg.css('color', '#d63638').text('Error: ' + errMsg);
                }
            },
            error: function(xhr, status, error) {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
                $msg.css('color', '#d63638').text(pnAiChatbotAdmin.i18n.server_error);
                console.error('Gemini fetch models AJAX error', xhr.status, error, xhr.responseText);
            }
        });
    });

    // 8. Flood Words Analyzer & Manual Stops Management
    function getAddedWords() {
        var val = $('#pn_ai_chatbot_manual_stops_input').val().toLowerCase();
        return val.split(',').map(function(s) { return s.trim(); }).filter(function(s) { return s !== ''; });
    }

    function setAddedWords(wordsArray) {
        $('#pn_ai_chatbot_manual_stops_input').val(wordsArray.join(', '));
    }

    function createActionButton(word) {
        var addedWords = getAddedWords();
        var isAdded = (addedWords.indexOf(word) !== -1);
        var $btn = $('<button type="button" class="button pn-ai-chatbot-toggle-word-btn"></button>');
        $btn.attr('data-word', word);
        
        if (isAdded) {
            $btn.addClass('button-secondary')
                .css({'color': '#d63638', 'border-color': '#d63638'})
                .attr('data-action', 'remove')
                .text(pnAiChatbotAdmin.i18n.remove);
        } else {
            $btn.addClass('button-primary')
                .attr('data-action', 'add')
                .text(pnAiChatbotAdmin.i18n.add_to_stops);
        }
        return $btn;
    }

    function refreshToggleButtons() {
        $('.pn-ai-chatbot-toggle-word-btn').each(function() {
            var $btn = $(this);
            var word = $btn.attr('data-word');
            var $newBtn = createActionButton(word);
            $btn.replaceWith($newBtn);
        });
    }

    $('#pn_ai_chatbot_scan_btn').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $spinner = $('#pn_ai_chatbot_scan_spinner');
        var $wrap = $('#pn_ai_chatbot_scan_table_wrap');
        var $tbody = $('#pn_ai_chatbot_scan_table tbody');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $wrap.hide();
        $tbody.empty();

        $.ajax({
            url: pnAiChatbotAdmin.ajax_url,
            type: 'POST',
            data: { 
                action: 'pn_ai_chatbot_analyze_flood_words', 
                security: pnAiChatbotAdmin.nonces.scan_flood 
            },
            success: function(res) {
                $spinner.removeClass('is-active');
                $btn.prop('disabled', false);
                
                if (res.success && res.data && res.data.words) {
                    $wrap.show();
                    $.each(res.data.words, function(i, wordObj) {
                        var $actionBtn = createActionButton(wordObj.word);
                        
                        var $tr = $('<tr></tr>');
                        $tr.append($('<td></td>').append($('<strong></strong>').text(wordObj.word)));
                        $tr.append($('<td></td>').text(wordObj.tf));
                        $tr.append($('<td></td>').text(wordObj.df !== '-' ? wordObj.df + ' / ' + res.data.total_docs : '-'));
                        $tr.append($('<td></td>').append($('<strong></strong>').text(wordObj.coverage_p)));
                        $tr.append($('<td></td>').css({'color': wordObj.color, 'font-weight': 'bold'}).text(wordObj.suggestion));
                        $tr.append($('<td></td>').append($actionBtn));
                        
                        $tbody.append($tr);
                    });
                } else {
                    var errMsg = res.data ? res.data : pnAiChatbotAdmin.i18n.server_error;
                    alert(errMsg);
                }
            },
            error: function() {
                $spinner.removeClass('is-active');
                $btn.prop('disabled', false);
                alert(pnAiChatbotAdmin.i18n.ajax_error);
            }
        });
    });

    // Client-side toggle button click handler
    $(document).on('click', '.pn-ai-chatbot-toggle-word-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var word = String($btn.attr('data-word')).toLowerCase();
        var action = $btn.attr('data-action');
        var words = getAddedWords();

        if (action === 'add') {
            if (words.indexOf(word) === -1) {
                words.push(word);
            }
        } else {
            var idx = words.indexOf(word);
            if (idx !== -1) {
                words.splice(idx, 1);
            }
        }
        setAddedWords(words);
        refreshToggleButtons();
    });

    // Save manual stop words handler
    $('#pn_ai_chatbot_save_manual_btn').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $spinner = $('#pn_ai_chatbot_manual_spinner');
        var $msg = $('#pn_ai_chatbot_manual_msg');
        var wordsStr = $('#pn_ai_chatbot_manual_stops_input').val();
        
        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $msg.text('');

        $.ajax({
            url: pnAiChatbotAdmin.ajax_url,
            type: 'POST',
            data: { 
                action: 'pn_ai_chatbot_save_manual_stops', 
                words_string: wordsStr, 
                security: pnAiChatbotAdmin.nonces.save_stops 
            },
            success: function(res) {
                $spinner.removeClass('is-active');
                $btn.prop('disabled', false);
                if (res.success) {
                    $('#pn_ai_chatbot_manual_stops_input').val(res.data.new_list);
                    $msg.css('color', 'green').text(pnAiChatbotAdmin.i18n.saved);
                    setTimeout(function() { $msg.text(''); }, 3000);
                    
                    if ($('#pn_ai_chatbot_scan_table_wrap').is(':visible')) {
                        refreshToggleButtons();
                    }
                } else {
                    $msg.css('color', 'red').text(pnAiChatbotAdmin.i18n.error_saving);
                }
            },
            error: function() {
                $spinner.removeClass('is-active');
                $btn.prop('disabled', false);
                $msg.css('color', 'red').text(pnAiChatbotAdmin.i18n.server_error);
            }
        });
    });
});

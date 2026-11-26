jQuery(document).ready(function($) {
    const toggleBtn = document.getElementById("pn-ai-chatbot-toggle");
    const closeBtn = document.getElementById("pn-ai-chatbot-close-btn");
    const chatWindow = document.getElementById("pn-ai-chatbot-window");
    const sendBtn = document.getElementById("pn-ai-chatbot-send-btn");
    const inputField = document.getElementById("pn-ai-chatbot-user-input");
    const messagesContainer = document.getElementById("pn-ai-chatbot-messages");

    // Safety check: if any critical element is missing, stop
    if (!toggleBtn || !chatWindow) {
        console.warn('PointNet AI Chatbot: missing essential elements.');
        return;
    }

    let chatContext = [];
    let messageHistory = [];

    try {
        const storedContext = sessionStorage.getItem("pn_ai_chatbot_chat_context");
        if (storedContext) chatContext = JSON.parse(storedContext);
    } catch(e) { chatContext = []; }

    try {
        const storedHistory = sessionStorage.getItem("pn_ai_chatbot_chat_history");
        if (storedHistory) messageHistory = JSON.parse(storedHistory);
    } catch(e) { messageHistory = []; }

    function scrollToBottom() {
        setTimeout(() => { if (messagesContainer) messagesContainer.scrollTop = messagesContainer.scrollHeight; }, 50);
    }

    if (messageHistory.length > 0 && messagesContainer) {
        const firstBot = messagesContainer.querySelector('.bot-msg');
        if (firstBot) firstBot.remove();
        messageHistory.forEach(function(msg) {
            appendMessage(msg.text, msg.isBot ? 'bot-msg' : 'user-msg', null, false);
        });
        scrollToBottom();
    }

    if (sessionStorage.getItem("pn_ai_chatbot_chat_open") === "true") {
        chatWindow.style.display = "flex";
        toggleBtn.style.display = "none";
        scrollToBottom();
    }

    toggleBtn.addEventListener("click", () => {
        chatWindow.style.display = "flex";
        toggleBtn.style.display = "none";
        sessionStorage.setItem("pn_ai_chatbot_chat_open", "true");
        scrollToBottom();
    });

    if (closeBtn) {
        closeBtn.addEventListener("click", () => {
            chatWindow.style.display = "none";
            toggleBtn.style.display = "flex";
            sessionStorage.setItem("pn_ai_chatbot_chat_open", "false");
        });
    }

    inputField.addEventListener("keypress", (e) => {
        if (e.key === "Enter" && !inputField.disabled) sendMessage();
    });

    sendBtn.addEventListener("click", () => {
        if (!sendBtn.disabled) sendMessage();
    });

    function formatMarkdown(text) {
        if (!text) return "";
        let safe = text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
                       .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
        safe = safe.replace(/\[([^\]]+)\]\(([^)]*)\)/g, function(match, label, url) {
            let cleanUrl = url.trim();
            if (!cleanUrl || cleanUrl.toLowerCase() === 'url' || cleanUrl.toLowerCase() === '{contact_url}' || cleanUrl.toLowerCase() === '{url}') {
                cleanUrl = (typeof pnAiChatbotData !== 'undefined' && pnAiChatbotData.contact_url) ? pnAiChatbotData.contact_url : '/contact/';
            }
            if (/^(https?:\/\/|\/(?!\/))/i.test(cleanUrl)) {
                let sanitizedUrl = cleanUrl.replace(/"/g, "%22").replace(/'/g, "%27").replace(/\s/g, "%20");
                return '<a href="' + sanitizedUrl + '" target="_self" rel="noopener noreferrer" style="color: var(--pn-ai-chatbot-primary-solid, #0073aa); text-decoration: underline; font-weight: bold;">' + label + '</a>';
            }
            return label;
        });
        return safe.replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>")
                   .replace(/\*(.*?)\*/g, "<em>$1</em>")
                   .replace(/`(.*?)`/g, "<code>$1</code>")
                   .replace(/\n/g, "<br>");
    }

    function saveHistoryToStorage() {
        sessionStorage.setItem("pn_ai_chatbot_chat_history", JSON.stringify(messageHistory));
    }

    function showTypingIndicator() {
        const tid = "typing-" + Date.now();
        const d = document.createElement("div");
        d.className = "pn-ai-chatbot-msg bot-msg";
        d.id = tid;
        d.innerHTML = '<div class="pn-ai-chatbot-typing"><div class="pn-ai-chatbot-dot"></div><div class="pn-ai-chatbot-dot"></div><div class="pn-ai-chatbot-dot"></div></div>';
        messagesContainer.appendChild(d);
        return tid;
    }

    function scrollToTopOfElement(el) {
        if (!messagesContainer || !el) return;
        setTimeout(() => {
            messagesContainer.scrollTop = el.offsetTop;
        }, 100);
    }

    function appendMessage(text, cls, id = null, save = true) {
        const d = document.createElement("div");
        d.className = "pn-ai-chatbot-msg " + cls;
        d.innerHTML = formatMarkdown(text);
        if (id) d.id = id;
        messagesContainer.appendChild(d);
        if (cls === 'bot-msg') {
            scrollToTopOfElement(d);
        } else {
            scrollToBottom();
        }
        if (save) {
            const isBot = (cls === 'bot-msg');
            messageHistory.push({ text: text, isBot: isBot });
            saveHistoryToStorage();
        }
    }

    function getFreshNonce() {
        // Reuse a nonce cached in sessionStorage when possible (1 call per session).
        const cached = sessionStorage.getItem("pn_ai_chatbot_fresh_nonce");
        if (cached) {
            return Promise.resolve(cached);
        }
        return fetch(pnAiChatbotData.ajax_url, {
            method: 'POST',
            body: new URLSearchParams({ action: 'pn_ai_chatbot_get_nonce' })
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res && res.success && res.data) {
                sessionStorage.setItem("pn_ai_chatbot_fresh_nonce", res.data);
                return res.data;
            }
            // Fallback to the static page nonce.
            return pnAiChatbotData.nonce;
        })
        .catch(function() {
            // Fallback to the static page nonce on network errors.
            return pnAiChatbotData.nonce;
        });
    }

    function sendMessage() {
        const msg = inputField.value.trim();
        if (!msg) return;

        appendMessage(msg, "user-msg", null, true);
        inputField.value = "";

        inputField.disabled = true;
        sendBtn.disabled = true;
        inputField.placeholder = pnAiChatbotData.writing;

        const tid = showTypingIndicator();

        chatContext.push({role: 'user', content: msg});

        function pnAiChatbotDoSend(nonce) {
            const params = new URLSearchParams();
            params.append('action', 'pn_ai_chatbot_send_message');
            params.append('security', nonce);
            params.append('message', msg);
            params.append('history', JSON.stringify(chatContext));

            function pnAiChatbotHandleResponse(res) {
                const el = document.getElementById(tid); if (el) el.remove();
                if (res && res.success) {
                    const text = (res.data !== undefined) ? res.data : "";
                    appendMessage(text, "bot-msg", null, true);
                    chatContext.push({role: 'assistant', content: text});
                    if (chatContext.length > 6) chatContext = chatContext.slice(-6);
                    sessionStorage.setItem("pn_ai_chatbot_chat_context", JSON.stringify(chatContext));
                } else {
                    const errMsg = (res && res.data !== undefined) ? res.data : pnAiChatbotData.conn_error;
                    appendMessage(errMsg, "bot-msg", null, false);
                    chatContext.pop();
                    // If the session expired, drop the cached nonce so the next message retries fresh.
                    if (res && res.data && String(res.data).indexOf("Session expired") !== -1) {
                        sessionStorage.removeItem("pn_ai_chatbot_fresh_nonce");
                    }
                }
            }

            function pnAiChatbotHandleError() {
                const el = document.getElementById(tid); if (el) el.remove();
                appendMessage(pnAiChatbotData.conn_error, "bot-msg", null, false);
                chatContext.pop();
            }

            function pnAiChatbotFinally() {
                inputField.disabled = false;
                sendBtn.disabled = false;
                inputField.placeholder = pnAiChatbotData.ask_question;
                inputField.focus();
            }

            // Use fetch if available, fall back to XMLHttpRequest for older browsers
            if (window.fetch) {
                fetch(pnAiChatbotData.ajax_url, { method: 'POST', body: params })
                .then(function(r) {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(function(res) { pnAiChatbotHandleResponse(res); })
                .catch(function() { pnAiChatbotHandleError(); })
                .then(function() { pnAiChatbotFinally(); });
            } else {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', pnAiChatbotData.ajax_url, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
                xhr.onload = function() {
                    if (xhr.status >= 200 && xhr.status < 400) {
                        try { var res = JSON.parse(xhr.responseText); pnAiChatbotHandleResponse(res); }
                        catch(e) { pnAiChatbotHandleError(); }
                    } else { pnAiChatbotHandleError(); }
                    pnAiChatbotFinally();
                };
                xhr.onerror = function() { pnAiChatbotHandleError(); pnAiChatbotFinally(); };
                xhr.send(params.toString());
            }
        }

        getFreshNonce().then(function(nonce) { pnAiChatbotDoSend(nonce); });
    }
});
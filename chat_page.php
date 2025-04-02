<div class="chat-container" id="chat-container">
    <div class="welcome-message" id="welcome-message">
        <h1>Welcome to SignSmart</h1>
        <p>Your AI-powered contract analysis assistant</p>
        
        <div class="welcome-cards">
            <div class="welcome-card">
                <i class="fas fa-file-contract"></i>
                <h3>Analyze Contracts</h3>
                <p>Upload contracts in various formats for detailed analysis</p>
            </div>
            
            <div class="welcome-card">
                <i class="fas fa-balance-scale"></i>
                <h3>Compare Contracts</h3>
                <p>Compare up to 3 contracts to find the best option</p>
            </div>
            
            <div class="welcome-card">
                <i class="fas fa-shield-alt"></i>
                <h3>Identify Risks</h3>
                <p>Uncover unfavorable clauses and potential issues</p>
            </div>
        </div>
        
        <div class="welcome-examples">
            <h3>Try asking:</h3>
            <div class="example-prompts">
                <button class="example-prompt" data-prompt="Analyze this contract for any unfavorable clauses.">
                    "Analyze this contract for any unfavorable clauses."
                </button>
                <button class="example-prompt" data-prompt="Compare these two contracts and tell me which one is better.">
                    "Compare these two contracts and tell me which one is better."
                </button>
                <button class="example-prompt" data-prompt="What are the key terms in this agreement?">
                    "What are the key terms in this agreement?"
                </button>
            </div>
        </div>
    </div>
    
    <div class="messages" id="messages">
    </div>
</div>

<div class="input-container">
    <div class="upload-container">
        <label for="file-upload" class="file-upload-label">
            <i class="fas fa-paperclip"></i>
        </label>
        <input type="file" id="file-upload" class="file-upload" accept="image/*,.pdf,.txt,.doc,.docx">
    </div>
    
    <div class="message-input-container">
        <textarea id="message-input" placeholder="Ask about a contract or upload one for analysis..." rows="1"></textarea>
        <button id="send-button" disabled>
            <i class="fas fa-paper-plane"></i>
        </button>
    </div>
</div>

<div class="file-preview-container" id="file-preview-container">
    <!-- File preview will be shown here -->
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const messageInput = document.getElementById('message-input');
    const sendButton = document.getElementById('send-button');
    const fileUpload = document.getElementById('file-upload');
    const filePreviewContainer = document.getElementById('file-preview-container');
    const welcomeMessage = document.getElementById('welcome-message');
    const messagesContainer = document.getElementById('messages');
    const chatContainer = document.getElementById('chat-container');
    
    let selectedFile = null;
    window.currentChatId = null;
    
    messageInput.addEventListener('input', function() {
        sendButton.disabled = messageInput.value.trim() === '' && !selectedFile;
        
        messageInput.style.height = 'auto';
        messageInput.style.height = (messageInput.scrollHeight) + 'px';
    });
    
    fileUpload.addEventListener('change', function(e) {
    if (e.target.files.length > 0) {
        selectedFile = e.target.files[0];

        if (selectedFile.size > 20 * 1024 * 1024) {
            alert('File size exceeds the 20MB limit. Please choose a smaller file.');
            fileUpload.value = '';
            selectedFile = null;
            return;
        }
        
        filePreviewContainer.innerHTML = '';
        filePreviewContainer.style.display = 'block';
        
        const filePreview = document.createElement('div');
        filePreview.className = 'file-preview';
        
        if (selectedFile.type.startsWith('image/')) {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(selectedFile);
            img.onload = function() {
                URL.revokeObjectURL(this.src);
            };
            filePreview.appendChild(img);
        } else {
            const fileIcon = document.createElement('i');
            fileIcon.className = 'fas fa-file';
            filePreview.appendChild(fileIcon);
        }
        
        const fileInfo = document.createElement('div');
        fileInfo.className = 'file-info';
        
        const fileName = document.createElement('div');
        fileName.className = 'file-name';
        fileName.textContent = selectedFile.name;
        
        const fileSize = document.createElement('div');
        fileSize.className = 'file-size';
        fileSize.textContent = formatFileSize(selectedFile.size);
        
        fileInfo.appendChild(fileName);
        fileInfo.appendChild(fileSize);
        
        const removeButton = document.createElement('button');
        removeButton.className = 'remove-file';
        removeButton.innerHTML = '<i class="fas fa-times"></i>';
        removeButton.addEventListener('click', function() {
            filePreviewContainer.style.display = 'none';
            selectedFile = null;
            fileUpload.value = '';
            sendButton.disabled = messageInput.value.trim() === '';
        });
        
        filePreview.appendChild(fileInfo);
        filePreview.appendChild(removeButton);
        
        filePreviewContainer.appendChild(filePreview);
        
        sendButton.disabled = false;
    }
});
    
    sendButton.addEventListener('click', function() {
        sendMessage();
    });
    
    messageInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (!sendButton.disabled) {
                sendMessage();
            }
        }
    });
    
    document.querySelectorAll('.example-prompt').forEach(button => {
        button.addEventListener('click', function() {
            messageInput.value = this.getAttribute('data-prompt');
            sendButton.disabled = false;
            messageInput.focus();
            
            messageInput.style.height = 'auto';
            messageInput.style.height = (messageInput.scrollHeight) + 'px';
        });
    });
    
    function sendMessage() {
        const message = messageInput.value.trim();
        
        const formData = new FormData();
        formData.append('message', message);
        
        if (selectedFile) {
            formData.append('file', selectedFile);
        }
        
        if (window.currentChatId) {
            formData.append('chat_id', window.currentChatId);
        }
        
        if (welcomeMessage.style.display !== 'none') {
            welcomeMessage.style.display = 'none';
        }
        
        appendMessage('user', message, selectedFile ? URL.createObjectURL(selectedFile) : null);
        
        messageInput.value = '';
        messageInput.style.height = 'auto';
        const tempFile = selectedFile; 
        selectedFile = null;
        filePreviewContainer.style.display = 'none';
        fileUpload.value = '';
        sendButton.disabled = true;
        
        const typingIndicator = document.createElement('div');
        typingIndicator.className = 'message assistant-message typing-indicator';
        typingIndicator.innerHTML = `
            <div class="typing-dots">
                <span></span>
                <span></span>
                <span></span>
            </div>
        `;
        messagesContainer.appendChild(typingIndicator);
        
        chatContainer.scrollTop = chatContainer.scrollHeight;
        
        console.log("Sending message with file:", tempFile ? tempFile.name : "none");
        
        fetch('api.php?action=send_message', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (messagesContainer.contains(typingIndicator)) {
                messagesContainer.removeChild(typingIndicator);
            }
            
            if (data.success) {
                appendMessage('assistant', data.response, data.file_path);
                
                if (data.update_history) {
                    loadChatHistory();
                }
                
                if (data.chat_id) {
                    window.currentChatId = data.chat_id;
                }
            } else {
                appendMessage('assistant', `<div class="error-message">Sorry, an error occurred: ${data.error}</div>`);
                console.error("API Error:", data.error);
            }
            
            chatContainer.scrollTop = chatContainer.scrollHeight;
        })
        .catch(error => {
            console.error('Error sending message:', error);
            
            if (messagesContainer.contains(typingIndicator)) {
                messagesContainer.removeChild(typingIndicator);
            }
            
            const errorMessage = document.createElement('div');
            errorMessage.className = 'message assistant-message';
            errorMessage.innerHTML = `
              <div class="error-content">
                  <i class="fas fa-exclamation-circle"></i>
                  <p>Sorry, an error occurred while sending your message: ${error.message}</p>
                  <button class="retry-button"><i class="fas fa-redo"></i> Retry</button>
              </div>
          `;
            messagesContainer.appendChild(errorMessage);
            
            const retryButton = errorMessage.querySelector('.retry-button');
            if (retryButton) {
                retryButton.addEventListener('click', function() {
                    messagesContainer.removeChild(errorMessage);
                    if (tempFile) {
                        selectedFile = tempFile;
                        const filePreview = document.createElement('div');
                        filePreview.className = 'file-preview';
                        
                        if (selectedFile.type.startsWith('image/')) {
                            const img = document.createElement('img');
                            img.src = URL.createObjectURL(selectedFile);
                            filePreview.appendChild(img);
                        } else {
                            const fileIcon = document.createElement('i');
                            fileIcon.className = 'fas fa-file';
                            filePreview.appendChild(fileIcon);
                        }
                        
                        const fileInfo = document.createElement('div');
                        fileInfo.className = 'file-info';
                        fileInfo.innerHTML = `
                          <div class="file-name">${selectedFile.name}</div>
                          <div class="file-size">${formatFileSize(selectedFile.size)}</div>
                      `;
                        
                        filePreview.appendChild(fileInfo);
                        filePreviewContainer.innerHTML = '';
                        filePreviewContainer.appendChild(filePreview);
                        filePreviewContainer.style.display = 'block';
                    }
                    sendMessage();
                });
            }
            
            chatContainer.scrollTop = chatContainer.scrollHeight;
        });
    }
    
    function appendMessage(sender, message, filePath) {
        const messageDiv = document.createElement('div');
        messageDiv.classList.add('message');
        messageDiv.classList.add(sender === 'user' ? 'user-message' : 'assistant-message');

        const messageContent = document.createElement('div');
        messageContent.classList.add('message-content');
        
        if (typeof message === "string" && message.includes('<div class="error-message">')) {
            messageContent.innerHTML = message;
        } else {
            messageContent.textContent = message;
        }

        messageDiv.appendChild(messageContent);

        if (filePath) {
            if (filePath.startsWith("blob:")) {
                const filePreview = document.createElement("div");
                filePreview.className = "file-preview-in-message";

                const img = document.createElement("img");
                img.src = filePath;
                img.className = "message-image";
                img.alt = "Uploaded image";

                filePreview.appendChild(img);
                messageDiv.appendChild(filePreview);
            } else {
                const fileExtension = filePath.split(".").pop().toLowerCase();
                const isImage = ["jpg", "jpeg", "png", "gif"].includes(fileExtension);

                if (isImage) {
                    const img = document.createElement("img");
                    img.src = filePath;
                    img.className = "message-image";
                    img.alt = "Uploaded image";
                    messageDiv.appendChild(img);
                } else {
                    const fileLink = document.createElement("a");
                    fileLink.href = filePath;
                    fileLink.className = "file-link";
                    fileLink.innerHTML = '<i class="fas fa-file"></i> View File';
                    fileLink.target = "_blank";
                    messageDiv.appendChild(fileLink);
                }
            }
        }

        messagesContainer.appendChild(messageDiv);
        
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }
    
    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' bytes';
        else if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        else return (bytes / 1048576).toFixed(1) + ' MB';
    }
    
    function loadChatHistory() {
        fetch('api.php?action=get_chat_history')
            .then(response => response.json())
            .then(chats => {
                const chatHistory = document.getElementById('chat-history');
                if (chatHistory) {
                    chatHistory.innerHTML = '';
                    
                    if (chats.length === 0) {
                        chatHistory.innerHTML = '<div class="empty-history">No chat history yet</div>';
                        return;
                    }
                    
                    chats.forEach(chat => {
                        const chatItem = document.createElement('div');
                        chatItem.className = 'chat-history-item';
                        if (window.currentChatId && window.currentChatId == chat.id) {
                            chatItem.classList.add('active');
                        }
                        
                        const chatTitle = document.createElement('div');
                        chatTitle.className = 'chat-history-title';
                        chatTitle.textContent = chat.title;
                        
                        const chatDate = document.createElement('div');
                        chatDate.className = 'chat-history-date';
                        chatDate.textContent = formatDate(chat.created_at);
                        
                        chatItem.appendChild(chatTitle);
                        chatItem.appendChild(chatDate);
                        chatItem.dataset.id = chat.id;
                        
                        chatItem.addEventListener('click', function() {
                            loadChat(chat.id);
                            
                            document.querySelectorAll('.chat-history-item').forEach(item => {
                                item.classList.remove('active');
                            });
                            this.classList.add('active');
                        });
                        
                        chatHistory.appendChild(chatItem);
                    });
                }
            })
            .catch(error => console.error('Error loading chat history:', error));
    }
    
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }
    
    function loadChat(chatId) {
        fetch(`api.php?action=get_chat&chat_id=${chatId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success === false) {
                    console.error('Error loading chat:', data.error);
                    return;
                }
                
                messagesContainer.innerHTML = '';
                welcomeMessage.style.display = 'none';
                
                window.currentChatId = chatId;
                
                data.messages.forEach(message => {
                    appendMessage(message.role, message.content, message.file_path);
                });
                
                chatContainer.scrollTop = chatContainer.scrollHeight;
            })
            .catch(error => console.error('Error loading chat:', error));
    }
    
    loadChatHistory();
    
    const newChatBtn = document.getElementById('new-chat-btn');
    if (newChatBtn) {
        newChatBtn.addEventListener('click', function() {
            fetch('api.php?action=new_chat', { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        messagesContainer.innerHTML = '';
                        welcomeMessage.style.display = 'block';
                        window.currentChatId = data.chat_id;
                        
                        loadChatHistory();
                    }
                })
                .catch(error => console.error('Error creating new chat:', error));
        });
    }
});
</script>


class PhotoBrowser {
    constructor() {
        this.currentPage = 1;
        this.totalPages = 0;
        this.currentQuery = '';
        this.chatHistory = [];
        this.chatOpen = false;
        this.init();
    }

    init() {
        this.queryInput = document.getElementById('photobrowser-query');
        this.searchBtn = document.getElementById('photobrowser-search-btn');
        this.orientationSelect = document.getElementById('photobrowser-orientation');
        this.colorSelect = document.getElementById('photobrowser-color');
        this.resultsContainer = document.getElementById('photobrowser-results');
        this.loadMoreBtn = document.getElementById('photobrowser-load-more');
        this.paginationContainer = document.getElementById('photobrowser-pagination');
        this.loadingIndicator = document.getElementById('photobrowser-loading');

        if (!this.queryInput) return;

        this.searchBtn.addEventListener('click', () => this.search());
        this.queryInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') this.search();
        });
        this.orientationSelect.addEventListener('change', () => this.search());
        this.colorSelect.addEventListener('change', () => this.search());
        this.loadMoreBtn.addEventListener('click', () => this.loadMore());

        // Debounced search
        let timeout;
        this.queryInput.addEventListener('input', () => {
            clearTimeout(timeout);
            timeout = setTimeout(() => this.search(), 500);
        });

        this.initChat();
    }

    initChat() {
        const container = document.getElementById('photobrowser');
        if (!container || container.dataset.chatConfigured !== '1') return;

        this.chatPanel = document.getElementById('photobrowser-chat-panel');
        this.chatToggle = document.getElementById('photobrowser-chat-toggle');
        this.chatClose = document.getElementById('photobrowser-chat-close');
        this.chatMessages = document.getElementById('photobrowser-chat-messages');
        this.chatInput = document.getElementById('photobrowser-chat-input');
        this.chatSendBtn = document.getElementById('photobrowser-chat-send');
        this.chatSuggestions = document.getElementById('photobrowser-chat-suggestions');

        if (!this.chatPanel) return;

        this.chatToggle.addEventListener('click', () => this.toggleChat());
        this.chatClose.addEventListener('click', () => this.toggleChat(false));
        this.chatSendBtn.addEventListener('click', () => this.sendChatMessage());
        this.chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') this.sendChatMessage();
        });
    }

    toggleChat(forceState) {
        this.chatOpen = forceState !== undefined ? forceState : !this.chatOpen;
        this.chatPanel.style.display = this.chatOpen ? 'flex' : 'none';
        this.chatToggle.classList.toggle('active', this.chatOpen);

        if (this.chatOpen) {
            this.chatInput.focus();
        }
    }

    async sendChatMessage() {
        const message = this.chatInput.value.trim();
        if (!message) return;

        this.chatInput.value = '';
        this.appendChatMessage('user', message);
        this.chatSuggestions.innerHTML = '';
        this.setChatLoading(true);

        try {
            const response = await fetch(TYPO3.settings.ajaxUrls.falphotobrowser_chat, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    message: message,
                    history: this.chatHistory,
                }),
            });

            const data = await response.json();

            if (!data.success) {
                this.appendChatMessage('ai', 'Sorry, an error occurred: ' + (data.error || 'Unknown error'));
                return;
            }

            // Update conversation history
            this.chatHistory.push({ role: 'user', content: message });
            this.chatHistory.push({ role: 'assistant', content: data.chat.message });

            // Show AI response
            this.appendChatMessage('ai', data.chat.message);

            // Show search terms used
            if (data.chat.searchTerms) {
                this.appendChatInfo('Searching: "' + data.chat.searchTerms + '"');
            }

            // Update search filters to match AI suggestion
            if (data.chat.searchTerms) {
                this.queryInput.value = data.chat.searchTerms;
                this.currentQuery = data.chat.searchTerms;
            }
            if (data.chat.orientation) {
                this.orientationSelect.value = data.chat.orientation;
            }
            if (data.chat.color) {
                this.colorSelect.value = data.chat.color;
            }

            // Render search results
            if (data.search && data.search.photos) {
                this.currentPage = 1;
                this.totalPages = data.search.totalPages;
                this.resultsContainer.innerHTML = '';
                this.renderPhotos(data.search.photos);

                if (this.currentPage < this.totalPages) {
                    this.paginationContainer.style.display = 'block';
                } else {
                    this.paginationContainer.style.display = 'none';
                }
            }

            // Show suggestions as clickable chips
            if (data.chat.suggestions && data.chat.suggestions.length > 0) {
                this.renderSuggestions(data.chat.suggestions);
            }

            // Show alternative search terms
            if (data.chat.alternativeTerms && data.chat.alternativeTerms.length > 0) {
                this.appendChatInfo('Alternatives: ' + data.chat.alternativeTerms.join(', '));
            }
        } catch (error) {
            console.error('Chat failed:', error);
            this.appendChatMessage('ai', 'Connection error. Please try again.');
        } finally {
            this.setChatLoading(false);
        }
    }

    appendChatMessage(role, text) {
        const wrapper = document.createElement('div');
        wrapper.className = 'chat-message chat-message-' + (role === 'user' ? 'user' : 'ai');

        const bubble = document.createElement('div');
        bubble.className = 'chat-bubble';
        bubble.textContent = text;

        wrapper.appendChild(bubble);
        this.chatMessages.appendChild(wrapper);
        this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
    }

    appendChatInfo(text) {
        const info = document.createElement('div');
        info.className = 'chat-message chat-message-info';
        info.textContent = text;
        this.chatMessages.appendChild(info);
        this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
    }

    renderSuggestions(suggestions) {
        this.chatSuggestions.innerHTML = '';
        suggestions.forEach(text => {
            const chip = document.createElement('button');
            chip.className = 'btn btn-sm btn-outline-secondary chat-suggestion-chip';
            chip.textContent = text;
            chip.addEventListener('click', () => {
                this.chatInput.value = text;
                this.sendChatMessage();
            });
            this.chatSuggestions.appendChild(chip);
        });
    }

    setChatLoading(loading) {
        this.chatSendBtn.disabled = loading;
        this.chatInput.disabled = loading;
        if (loading) {
            const loader = document.createElement('div');
            loader.className = 'chat-message chat-message-ai chat-typing';
            loader.id = 'chat-typing-indicator';
            loader.innerHTML = '<div class="chat-bubble"><span class="typing-dots"><span>.</span><span>.</span><span>.</span></span></div>';
            this.chatMessages.appendChild(loader);
            this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
        } else {
            const loader = document.getElementById('chat-typing-indicator');
            if (loader) loader.remove();
        }
    }

    async search() {
        const query = this.queryInput.value.trim();
        if (!query) return;

        this.currentQuery = query;
        this.currentPage = 1;
        this.resultsContainer.innerHTML = '';
        await this.fetchPhotos();
    }

    async loadMore() {
        this.currentPage++;
        await this.fetchPhotos(true);
    }

    async fetchPhotos(append = false) {
        this.loadingIndicator.style.display = 'block';
        this.paginationContainer.style.display = 'none';

        const params = new URLSearchParams({
            query: this.currentQuery,
            page: this.currentPage,
            orientation: this.orientationSelect.value,
            color: this.colorSelect.value,
        });

        try {
            const response = await fetch(TYPO3.settings.ajaxUrls.falphotobrowser_search + '&' + params);
            const data = await response.json();

            this.totalPages = data.totalPages;

            if (!append) {
                this.resultsContainer.innerHTML = '';
            }

            this.renderPhotos(data.photos);

            if (this.currentPage < this.totalPages) {
                this.paginationContainer.style.display = 'block';
            }
        } catch (error) {
            console.error('Search failed:', error);
            top.TYPO3.Notification.error('Error', 'Failed to search photos');
        } finally {
            this.loadingIndicator.style.display = 'none';
        }
    }

    renderPhotos(photos) {
        photos.forEach(photo => {
            const card = document.createElement('div');
            card.className = 'photobrowser-photo-card';
            card.style.backgroundColor = photo.color;
            card.innerHTML = `
                <img src="${photo.thumbUrl}" alt="${photo.altDescription}" loading="lazy">
                <div class="photobrowser-photo-overlay">
                    <span class="photographer">${photo.photographerName}</span>
                    <button class="btn btn-sm btn-primary import-btn" data-photo-id="${photo.id}">
                        Import
                    </button>
                </div>
            `;

            card.querySelector('.import-btn').addEventListener('click', (e) => {
                e.stopPropagation();
                this.importPhoto(photo.id);
            });

            this.resultsContainer.appendChild(card);
        });
    }

    async importPhoto(photoId) {
        top.TYPO3.Notification.info('Importing', 'Downloading image...');

        try {
            const response = await fetch(TYPO3.settings.ajaxUrls.falphotobrowser_import, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    photoId: photoId,
                    storageUid: 1,
                    targetFolder: 'unsplash',
                }),
            });

            const data = await response.json();

            if (data.success) {
                top.TYPO3.Notification.success('Success', `Image imported: ${data.file.name}`);
            } else {
                top.TYPO3.Notification.error('Error', data.error);
            }
        } catch (error) {
            console.error('Import failed:', error);
            top.TYPO3.Notification.error('Error', 'Failed to import image');
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new PhotoBrowser();
});

class UnsplashBrowser {
    constructor() {
        this.currentPage = 1;
        this.totalPages = 0;
        this.currentQuery = '';
        this.init();
    }

    init() {
        this.queryInput = document.getElementById('unsplash-query');
        this.searchBtn = document.getElementById('unsplash-search-btn');
        this.orientationSelect = document.getElementById('unsplash-orientation');
        this.colorSelect = document.getElementById('unsplash-color');
        this.resultsContainer = document.getElementById('unsplash-results');
        this.loadMoreBtn = document.getElementById('unsplash-load-more');
        this.paginationContainer = document.getElementById('unsplash-pagination');
        this.loadingIndicator = document.getElementById('unsplash-loading');

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
            const response = await fetch(TYPO3.settings.ajaxUrls.unsplash_search + '&' + params);
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
            top.TYPO3.Notification.error('Error', 'Failed to search Unsplash');
        } finally {
            this.loadingIndicator.style.display = 'none';
        }
    }

    renderPhotos(photos) {
        photos.forEach(photo => {
            const card = document.createElement('div');
            card.className = 'unsplash-photo-card';
            card.style.backgroundColor = photo.color;
            card.innerHTML = `
                <img src="${photo.thumbUrl}" alt="${photo.altDescription}" loading="lazy">
                <div class="unsplash-photo-overlay">
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
        top.TYPO3.Notification.info('Importing', 'Downloading image from Unsplash...');

        try {
            const response = await fetch(TYPO3.settings.ajaxUrls.unsplash_import, {
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
    new UnsplashBrowser();
});

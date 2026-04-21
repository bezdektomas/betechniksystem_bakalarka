import { Controller } from '@hotwired/stimulus';
import { Centrifuge } from 'centrifuge';

export default class extends Controller {
    static values = {
        wsUrl: String,
        connectionToken: String,
        subscriptionToken: String,
        userSubscriptionToken: String,
        konverzaceId: Number,
        currentUserId: Number,
        userId: Number,
        csrfToken: String,
        members: Array,
    };

    static targets = [
        'messages',
        'input',
        'typing',
        'replyPreview',
        'replyId',
        'replyText',
        'directModal',
        'groupModal',
        'fileInput',
        'filePreview',
        'fileName',
        'fileThumb',
        'fileIcon',
        'infoPanel',
        'mentionDropdown',
        'lightbox',
    ];

    connect() {
        this.replyToId   = null;
        this.typingDebounce = null;
        this.typingTimers   = {};
        this._pastedFile    = null;
        this._mentionStart  = -1;
        this._mentionFiltered = [];

        this._initCentrifuge();
        this._subscribePersonal();

        if (this.konverzaceIdValue > 0) {
            this._subscribe();
        }

        this._autoResizeInput();
        this._scrollToBottom();

        if (this.hasMessagesTarget) {
            this.messagesTarget.querySelectorAll('img').forEach(img => {
                if (!img.complete) {
                    img.addEventListener('load', () => this._scrollToBottom(), { once: true });
                }
            });
        }

        this._initLoadMore();
        this._initSwipeToReply();

        if (this.hasMessagesTarget) {
            requestAnimationFrame(() => this.messagesTarget.classList.remove('opacity-0'));
        }

        this._onPaste = (e) => {
            if (this.konverzaceIdValue === 0) return;
            const items = e.clipboardData?.items;
            if (!items) return;
            for (const item of items) {
                if (item.type.startsWith('image/')) {
                    e.preventDefault();
                    const file = item.getAsFile();
                    if (file) {
                        this._pastedFile = file;
                        if (this.hasFileInputTarget) this.fileInputTarget.value = '';
                        this._previewFile(file);
                    }
                    break;
                }
            }
        };
        this.element.addEventListener('paste', this._onPaste);

        this._onLightboxClick = (e) => {
            const link = e.target.closest('a[href^="/chat/priloha/"]');
            if (!link) return;
            const img = link.querySelector('img');
            if (!img) return;
            e.preventDefault();
            this.openLightbox(link.href, img.alt);
        };
        this.element.addEventListener('click', this._onLightboxClick);

        this._onKeydown = (e) => {
            if (e.key === 'Escape' && this.hasLightboxTarget && !this.lightboxTarget.classList.contains('hidden')) {
                this.closeLightbox();
            }
        };
        document.addEventListener('keydown', this._onKeydown);

        this._onDocClick = () => {
            document.querySelectorAll('.reaction-picker').forEach(p => {
                p.classList.add('hidden');
                p.classList.remove('flex');
            });
            const tooltip = document.getElementById('reaction-tooltip');
            if (tooltip) tooltip.classList.add('hidden');
        };
        document.addEventListener('click', this._onDocClick);
    }

    disconnect() {
        if (this.personalSub) this.personalSub.unsubscribe();
        if (this.sub) this.sub.unsubscribe();
        if (this.centrifuge) this.centrifuge.disconnect();
        if (this._onDocClick) document.removeEventListener('click', this._onDocClick);
        if (this._onPaste) this.element.removeEventListener('paste', this._onPaste);
        if (this._onLightboxClick) this.element.removeEventListener('click', this._onLightboxClick);
        if (this._onKeydown) document.removeEventListener('keydown', this._onKeydown);
    }

    _initCentrifuge() {
        this.centrifuge = new Centrifuge(this.wsUrlValue, {
            token: this.connectionTokenValue,
        });
        this.centrifuge.connect();
    }

    _subscribePersonal() {
        const channel = `chat:user_${this.userIdValue}`;

        this.personalSub = this.centrifuge.newSubscription(channel, {
            token: this.userSubscriptionTokenValue,
        });

        this.personalSub.on('publication', (ctx) => {
            const data = ctx.data;
            if (data.type === 'notification') {
                if (data.konverzaceId !== this.konverzaceIdValue) {
                    this._updateConversationListItem(data.konverzaceId, data.message, true);
                }
            }
        });

        this.personalSub.subscribe();
    }

    _subscribe() {
        const channel = `chat:konverzace_${this.konverzaceIdValue}`;

        this.sub = this.centrifuge.newSubscription(channel, {
            token: this.subscriptionTokenValue,
        });

        this.sub.on('publication', (ctx) => this._handlePublication(ctx.data));
        this.sub.subscribe();
    }

    _handlePublication(data) {
        if (data.type === 'message') {
            this._appendMessage(data.message);
            this._scrollToBottom();
            this._updateConversationListItem(this.konverzaceIdValue, data.message);
            fetch(`/chat/${this.konverzaceIdValue}/precteno`, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            }).catch(() => {});
        } else if (data.type === 'typing') {
            if (data.userId !== this.currentUserIdValue) {
                this._showTyping(data.userId, data.name);
            }
        } else if (data.type === 'reaction') {
            this._updateReactions(data.zpravaId, data.reakce);
        } else if (data.type === 'pin') {
            this._updatePinBanner(data.zprava);
        }
    }

    _appendMessage(msg) {
        this.messagesTarget.insertAdjacentHTML('beforeend', this._buildMessageHtml(msg));

        const newEl = this.messagesTarget.lastElementChild;
        if (newEl) {
            newEl.querySelectorAll('img').forEach(img => {
                if (!img.complete) {
                    img.addEventListener('load', () => this._scrollToBottom(), { once: true });
                }
            });
        }
    }

    _buildMessageHtml(msg) {
        const isOwn = msg.autor.id === this.currentUserIdValue;

        let replyHtml = '';
        if (msg.replyTo) {
            replyHtml = `
                <div class="mb-1 px-3 py-1.5 rounded-lg border-l-2 border-slate-400 bg-slate-100 text-xs text-slate-600">
                    <span class="font-semibold">${this._escape(msg.replyTo.autorName)}</span>
                    <p class="truncate">${this._escape(msg.replyTo.obsah)}</p>
                </div>`;
        }

        let attachmentHtml = '';
        if (msg.prilohy && msg.prilohy.length > 0) {
            const p = msg.prilohy[0];
            const isImage = p.mimeType.startsWith('image/');
            const sizeStr = p.size > 1024 * 1024
                ? (p.size / 1024 / 1024).toFixed(1) + ' MB'
                : Math.round(p.size / 1024) + ' KB';

            if (isImage) {
                attachmentHtml = `<a href="/chat/priloha/${p.id}" target="_blank" class="block mb-2">
                    <img src="/chat/priloha/${p.id}" class="max-w-full rounded-lg max-h-48 object-contain" alt="${this._escape(p.originalName)}">
                </a>`;
            } else {
                attachmentHtml = `<a href="/chat/priloha/${p.id}" download="${this._escape(p.originalName)}"
                    class="flex items-center gap-2 mb-2 px-3 py-2 rounded-lg bg-white/20 hover:bg-white/30 transition-colors text-sm">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                    </svg>
                    <span class="truncate max-w-[160px]">${this._escape(p.originalName)}</span>
                    <span class="flex-shrink-0 opacity-70">${sizeStr}</span>
                </a>`;
            }
        }

        const side = isOwn ? 'justify-end' : 'justify-start';
        const isImageOnly = msg.prilohy && msg.prilohy.length > 0
            && msg.prilohy[0].mimeType.startsWith('image/')
            && !msg.obsah.trim();
        const bubbleClass = isImageOnly ? '' : (isOwn
            ? 'px-4 py-2 bg-[rgb(241,97,1)] text-white rounded-br-sm'
            : 'px-4 py-2 bg-slate-100 text-slate-800 rounded-bl-sm');
        const btnSide   = isOwn ? 'right-full pr-1' : 'left-full pl-1';
        const timeAlign = isOwn ? 'text-right' : 'text-left';
        const time = new Date(msg.createdAt).toLocaleTimeString('cs-CZ', { hour: '2-digit', minute: '2-digit' });

        const pickerEmojis = ['👍','❤️','😂','😮','😢','😡']
            .map(e => `<button type="button" class="text-xl hover:scale-125 transition-transform p-0.5"
                data-action="click->chat#addReaction" data-zprava-id="${msg.id}" data-emoji="${e}">${e}</button>`)
            .join('');

        const reakceHtml = (msg.reakce || []).map(r => {
            const isOwn2 = r.userIds.includes(this.currentUserIdValue);
            const borderClass = isOwn2 ? 'border-[rgb(241,97,1)] bg-orange-50' : 'border-slate-200';
            const count = r.count > 1 ? `<span class="text-xs font-medium text-slate-600">${r.count}</span>` : '';
            return `<button type="button"
                class="flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-sm bg-white shadow-sm border transition-shadow hover:shadow-md ${borderClass}"
                data-action="click->chat#showReactionInfo"
                data-emoji="${r.emoji}"
                data-user-names="${this._escape(r.userNames.join(', '))}">
                <span>${r.emoji}</span>${count}
            </button>`;
        }).join('');

        return `
            <div class="flex ${side} mt-3" id="zprava-${msg.id}">
                <div class="max-w-xs lg:max-w-md group relative">
                    ${replyHtml}
                    <div class="relative px-4 py-2 rounded-2xl text-sm leading-relaxed break-words ${bubbleClass}">
                        ${attachmentHtml}${this._linkify(this._escape(msg.obsah)).replace(/\n/g, '<br>')}
                        <div class="absolute -top-3 ${btnSide} flex gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                            <div class="relative">
                                <button type="button" class="p-1 rounded-full bg-white shadow text-base leading-none hover:scale-110 transition-transform"
                                        data-action="click->chat#toggleReactionPicker"
                                        data-zprava-id="${msg.id}" title="Reagovat">😊</button>
                                <div class="reaction-picker absolute bottom-full mb-2 left-1/2 -translate-x-1/2 hidden z-30 bg-white rounded-full shadow-xl border border-slate-100 px-2.5 py-1.5 flex gap-1"
                                     id="reaction-picker-${msg.id}">${pickerEmojis}</div>
                            </div>
                            <button type="button"
                                    class="p-1 rounded-full bg-white shadow text-slate-500 hover:text-slate-800"
                                    data-action="click->chat#setReply"
                                    data-message-id="${msg.id}"
                                    data-message-author="${this._escape(msg.autor.name)}"
                                    data-message-text="${this._escape(msg.obsah.substring(0, 60))}"
                                    title="Odpovědět">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                </svg>
                            </button>
                            <button type="button"
                                    class="p-1 rounded-full bg-white shadow text-slate-500 hover:text-amber-500"
                                    data-action="click->chat#pinMessage"
                                    data-pin-zprava-id="${msg.id}"
                                    title="Připnout">
                                <svg class="w-3.5 h-3.5 rotate-45" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5v6h2v-6h5v-2l-2-2z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="flex ${side} flex-wrap gap-1 mt-0.5 px-1" id="reakce-${msg.id}">${reakceHtml}</div>
                    <p class="text-xs ${timeAlign} text-slate-400 mt-0.5 px-1">${time}</p>
                </div>
            </div>`;
    }

    _escape(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    _linkify(escapedText) {
        return escapedText.replace(
            /(https?:\/\/[^\s<>"]+)/g,
            '<a href="$1" target="_blank" rel="noopener noreferrer" class="underline opacity-80 hover:opacity-100 break-all">$1</a>'
        );
    }

    _scrollToBottom() {
        if (this.hasMessagesTarget) {
            this.messagesTarget.scrollTop = this.messagesTarget.scrollHeight;
        }
    }

    _initSwipeToReply() {
        if (!this.hasMessagesTarget) return;

        const THRESHOLD = 65;
        let startX = 0, startY = 0, lastDeltaX = 0;
        let msgEl = null, innerEl = null, hintEl = null, isHorizontal = false;

        const reset = () => {
            if (innerEl) {
                innerEl.style.transition = 'transform 0.25s cubic-bezier(.25,.46,.45,.94)';
                innerEl.style.transform  = 'translateX(0)';
            }
            if (hintEl) { hintEl.remove(); hintEl = null; }
            msgEl = null; innerEl = null; isHorizontal = false; lastDeltaX = 0;
        };

        let isOwn = false;

        this.messagesTarget.addEventListener('touchstart', (e) => {
            const t = e.touches[0];
            startX = t.clientX; startY = t.clientY; lastDeltaX = 0; isHorizontal = false;
            msgEl   = t.target.closest('[id^="zprava-"]');
            innerEl = msgEl ? msgEl.firstElementChild : null;
            isOwn   = msgEl ? msgEl.classList.contains('justify-end') : false;
        }, { passive: true });

        this.messagesTarget.addEventListener('touchmove', (e) => {
            if (!msgEl || !innerEl) return;
            const t      = e.touches[0];
            const deltaX = t.clientX - startX;
            const deltaY = t.clientY - startY;

            if (!isHorizontal) {
                if (Math.abs(deltaX) < 6 && Math.abs(deltaY) < 6) return;
                if (Math.abs(deltaX) >= Math.abs(deltaY)) {
                    isHorizontal = true;
                } else {
                    msgEl = null; return;
                }
            }

            const validSwipe = isOwn ? deltaX < 0 : deltaX > 0;
            if (!validSwipe) return;

            e.preventDefault();

            lastDeltaX = Math.abs(deltaX);
            const translate = Math.min(lastDeltaX * 0.55, THRESHOLD + 10);
            const progress  = Math.min(lastDeltaX / THRESHOLD, 1);

            innerEl.style.transition = 'none';
            innerEl.style.transform  = `translateX(${isOwn ? -translate : translate}px)`;

            if (!hintEl) {
                hintEl = document.createElement('div');
                hintEl.className = `pointer-events-none absolute ${isOwn ? '-right-7' : '-left-7'} top-1/2 text-[rgb(241,97,1)]`;
                hintEl.innerHTML = `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                          d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                </svg>`;
                innerEl.appendChild(hintEl);
            }
            hintEl.style.opacity   = progress;
            hintEl.style.transform = `translateY(-50%) scale(${0.5 + 0.5 * progress})`;
        }, { passive: false });

        const onEnd = () => {
            if (!msgEl || !innerEl) { reset(); return; }

            if (lastDeltaX >= THRESHOLD) {
                if (navigator.vibrate) navigator.vibrate(12);
                const replyBtn = msgEl.querySelector('[data-action*="setReply"]');
                if (replyBtn) replyBtn.click();
            }
            reset();
        };

        this.messagesTarget.addEventListener('touchend',    onEnd, { passive: true });
        this.messagesTarget.addEventListener('touchcancel', reset, { passive: true });
    }

    _initLoadMore() {
        if (!this.hasMessagesTarget || this.konverzaceIdValue === 0) return;

        this._loadingMore = false;

        const first = this.messagesTarget.querySelector('[id^="zprava-"]');
        if (first) {
            this._oldestMessageId = parseInt(first.id.replace('zprava-', ''));
            const count = this.messagesTarget.querySelectorAll('[id^="zprava-"]').length;
            this._allLoaded = count < 50;
            if (this._allLoaded) this._showEndMarker();
        } else {
            this._oldestMessageId = null;
            this._allLoaded = true;
            this._showEndMarker();
        }

        this.messagesTarget.addEventListener('scroll', () => {
            if (this.messagesTarget.scrollTop < 80 && !this._loadingMore && !this._allLoaded) {
                this._loadOlderMessages();
            }
        });
    }

    async _loadOlderMessages() {
        if (this._loadingMore || this._allLoaded || !this._oldestMessageId) return;
        this._loadingMore = true;

        const spinner = document.getElementById('load-more-spinner');
        if (spinner) { spinner.classList.remove('hidden'); spinner.classList.add('flex'); }

        try {
            const res = await fetch(`/chat/${this.konverzaceIdValue}/zpravy?before=${this._oldestMessageId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) return;
            const data = await res.json();

            if (data.zpravy.length === 0) {
                this._allLoaded = true;
                this._showEndMarker();
                return;
            }

            const prevHeight    = this.messagesTarget.scrollHeight;
            const prevScrollTop = this.messagesTarget.scrollTop;

            const insertRef = document.getElementById('load-more-end') || document.getElementById('load-more-spinner');
            const html = data.zpravy.map(msg => this._buildMessageHtml(msg)).join('');
            if (insertRef) {
                insertRef.insertAdjacentHTML('afterend', html);
            } else {
                this.messagesTarget.insertAdjacentHTML('afterbegin', html);
            }

            this.messagesTarget.scrollTop = this.messagesTarget.scrollHeight - prevHeight + prevScrollTop;

            const first = this.messagesTarget.querySelector('[id^="zprava-"]');
            if (first) this._oldestMessageId = parseInt(first.id.replace('zprava-', ''));

            if (!data.hasMore) {
                this._allLoaded = true;
                this._showEndMarker();
            }
        } catch (e) {
            console.error('Chyba při načítání starších zpráv:', e);
        } finally {
            this._loadingMore = false;
            if (spinner) { spinner.classList.add('hidden'); spinner.classList.remove('flex'); }
        }
    }

    _showEndMarker() {
        const el = document.getElementById('load-more-end');
        if (el) { el.classList.remove('hidden'); }
        const spinner = document.getElementById('load-more-spinner');
        if (spinner) { spinner.classList.add('hidden'); spinner.classList.remove('flex'); }
    }

    async sendMessage(event) {
        event.preventDefault();
        const text = this.inputTarget.value.trim();
        const file = this._pastedFile || (this.hasFileInputTarget ? this.fileInputTarget.files[0] : null);

        if ((!text && !file) || this.konverzaceIdValue === 0) return;

        let fetchOptions;

        if (file) {
            const formData = new FormData();
            if (text) formData.append('obsah', text);
            formData.append('file', file);
            if (this.replyToId) formData.append('replyTo', this.replyToId);
            formData.append('_csrf_token', this.csrfTokenValue);

            fetchOptions = {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData,
            };
        } else {
            const body = { obsah: text };
            if (this.replyToId) body.replyTo = this.replyToId;

            fetchOptions = {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Csrf-Token': this.csrfTokenValue,
                },
                body: JSON.stringify(body),
            };
        }

        try {
            const res = await fetch(`/chat/${this.konverzaceIdValue}/zprava`, fetchOptions);

            if (res.ok) {
                this.inputTarget.value = '';
                this.inputTarget.style.height = 'auto';
                this.clearReply();
                this._clearFile();
                this._updateConversationListItem(this.konverzaceIdValue, {
                    obsah: text || (file ? file.name : ''),
                    createdAt: new Date().toISOString(),
                    autor: { id: this.currentUserIdValue },
                });
            }
        } catch (e) {
            console.error('Chyba při odesílání zprávy:', e);
        }
    }

    openFilePicker() {
        if (this.hasFileInputTarget) {
            this.fileInputTarget.click();
        }
    }

    onFileSelected() {
        const file = this.fileInputTarget.files[0];
        if (!file) return;
        this._pastedFile = null;
        this._previewFile(file);
    }

    _previewFile(file) {
        if (this.hasFilePreviewTarget) {
            this.filePreviewTarget.classList.remove('hidden');
            this.filePreviewTarget.classList.add('flex');
        }
        if (this.hasFileNameTarget) {
            this.fileNameTarget.textContent = file.name || 'obrázek.png';
        }

        const isImage = file.type.startsWith('image/');
        if (this.hasFileThumbTarget) {
            if (isImage) {
                if (this._thumbObjectUrl) URL.revokeObjectURL(this._thumbObjectUrl);
                this._thumbObjectUrl = URL.createObjectURL(file);
                this.fileThumbTarget.src = this._thumbObjectUrl;
                this.fileThumbTarget.classList.remove('hidden');
            } else {
                this.fileThumbTarget.classList.add('hidden');
            }
        }
        if (this.hasFileIconTarget) {
            this.fileIconTarget.classList.toggle('hidden', isImage);
        }
    }

    _clearFile() {
        this._pastedFile = null;
        if (this.hasFileInputTarget) this.fileInputTarget.value = '';
        if (this.hasFilePreviewTarget) {
            this.filePreviewTarget.classList.add('hidden');
            this.filePreviewTarget.classList.remove('flex');
        }
        if (this.hasFileThumbTarget) {
            this.fileThumbTarget.classList.add('hidden');
            this.fileThumbTarget.src = '';
        }
        if (this.hasFileIconTarget) {
            this.fileIconTarget.classList.remove('hidden');
        }
        if (this._thumbObjectUrl) {
            URL.revokeObjectURL(this._thumbObjectUrl);
            this._thumbObjectUrl = null;
        }
    }

    onInputKeydown(event) {
        if (this.hasMentionDropdownTarget && !this.mentionDropdownTarget.classList.contains('hidden')) {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                const items = this.mentionDropdownTarget.querySelectorAll('[data-mention-index]');
                if (!items.length) return;
                const max = items.length - 1;
                let idx = parseInt(this.mentionDropdownTarget.dataset.activeIndex ?? '0');
                idx = event.key === 'ArrowDown' ? Math.min(idx + 1, max) : Math.max(idx - 1, 0);
                items.forEach((el, i) => el.classList.toggle('bg-slate-100', i === idx));
                this.mentionDropdownTarget.dataset.activeIndex = idx;
                items[idx].scrollIntoView({ block: 'nearest' });
                return;
            }
            if (event.key === 'Escape') {
                this._hideMentionDropdown(); return;
            }
            if (event.key === 'Enter' || event.key === 'Tab') {
                const idx   = parseInt(this.mentionDropdownTarget.dataset.activeIndex ?? '0');
                const items = this.mentionDropdownTarget.querySelectorAll('[data-mention-index]');
                if (items[idx]) { event.preventDefault(); items[idx].click(); return; }
            }
        }

        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            this.sendMessage(event);
        }
    }

    _checkMention() {
        if (!this.hasMentionDropdownTarget) return;
        const input  = this.inputTarget;
        const before = input.value.slice(0, input.selectionStart);
        const match  = before.match(/@([\w\u00C0-\u024F]*)$/);

        if (match) {
            this._mentionStart = input.selectionStart - match[0].length;
            this._showMentionDropdown(match[1].toLowerCase());
        } else {
            this._hideMentionDropdown();
        }
    }

    _showMentionDropdown(query) {
        const members = (this.membersValue || [])
            .filter(m => m.id !== this.currentUserIdValue)
            .filter(m => m.name.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                          .includes(query.normalize('NFD').replace(/[\u0300-\u036f]/g, '')));

        if (!members.length) { this._hideMentionDropdown(); return; }

        this.mentionDropdownTarget.dataset.activeIndex = '0';
        this.mentionDropdownTarget.innerHTML = members.map((m, i) => {
            const initial = this._escape(m.name.charAt(0).toUpperCase());
            return `<button type="button"
                class="w-full flex items-center gap-3 px-4 py-2.5 text-left transition-colors ${i === 0 ? 'bg-slate-100' : 'hover:bg-slate-50'}"
                data-action="click->chat#selectMention"
                data-mention-name="${this._escape(m.name)}"
                data-mention-index="${i}">
                <div class="w-7 h-7 rounded-full bg-[rgb(241,97,1)] flex items-center justify-center text-white text-xs font-semibold flex-shrink-0">${initial}</div>
                <span class="text-sm font-medium text-slate-800">${this._escape(m.name)}</span>
            </button>`;
        }).join('');

        this.mentionDropdownTarget.classList.remove('hidden');
        this._mentionFiltered = members;
    }

    _hideMentionDropdown() {
        if (!this.hasMentionDropdownTarget) return;
        this.mentionDropdownTarget.classList.add('hidden');
        this.mentionDropdownTarget.innerHTML = '';
        this._mentionFiltered = [];
        this._mentionStart = -1;
    }

    selectMention(event) {
        const name  = event.currentTarget.dataset.mentionName;
        const input = this.inputTarget;
        const after = input.value.slice(input.selectionStart);
        input.value = input.value.slice(0, this._mentionStart) + '@' + name + ' ' + after;
        const pos   = this._mentionStart + name.length + 2;
        input.setSelectionRange(pos, pos);
        input.focus();
        this._hideMentionDropdown();
    }

    onTyping() {
        this.inputTarget.style.height = 'auto';
        this.inputTarget.style.height = Math.min(this.inputTarget.scrollHeight, 128) + 'px';

        this._checkMention();

        if (this.konverzaceIdValue === 0) return;

        clearTimeout(this.typingDebounce);
        this.typingDebounce = setTimeout(() => {
            fetch(`/chat/${this.konverzaceIdValue}/typing`, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            }).catch(() => {});
        }, 400);
    }

    _showTyping(userId, name) {
        clearTimeout(this.typingTimers[userId]);
        this.typingTimers[userId] = true;

        this._updateTypingDisplay(name);

        this.typingTimers[userId] = setTimeout(() => {
            delete this.typingTimers[userId];
            const remaining = Object.keys(this.typingTimers);
            if (remaining.length === 0) {
                this.typingTarget.classList.add('hidden');
            }
        }, 3000);
    }

    _updateTypingDisplay(name) {
        this.typingTarget.textContent = `${name} píše…`;
        this.typingTarget.classList.remove('hidden');
    }

    setReply(event) {
        const btn = event.currentTarget;
        this.replyToId = btn.dataset.messageId;

        this.replyTextTarget.textContent = `${btn.dataset.messageAuthor}: ${btn.dataset.messageText}`;
        this.replyPreviewTarget.classList.remove('hidden');
        this.replyPreviewTarget.classList.add('flex');
        this.replyIdTarget.value = this.replyToId;
        this.inputTarget.focus();
    }

    clearReply() {
        this.replyToId = null;
        this.replyPreviewTarget.classList.add('hidden');
        this.replyPreviewTarget.classList.remove('flex');
        this.replyIdTarget.value = '';
    }

    toggleReactionPicker(event) {
        event.stopPropagation();
        const zpravaId = event.currentTarget.dataset.zpravaId;
        const picker   = document.getElementById(`reaction-picker-${zpravaId}`);
        if (!picker) return;

        const isHidden = picker.classList.contains('hidden');

        // Close all open pickers first
        document.querySelectorAll('.reaction-picker').forEach(p => {
            p.classList.add('hidden');
            p.classList.remove('flex');
        });

        if (isHidden) {
            picker.classList.remove('hidden');
            picker.classList.add('flex');
        }
    }

    async addReaction(event) {
        event.stopPropagation();
        const zpravaId = event.currentTarget.dataset.zpravaId;
        const emoji    = event.currentTarget.dataset.emoji;

        const picker = document.getElementById(`reaction-picker-${zpravaId}`);
        if (picker) {
            picker.classList.add('hidden');
            picker.classList.remove('flex');
        }

        try {
            const res = await fetch(`/chat/zprava/${zpravaId}/reakce`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Csrf-Token': this.csrfTokenValue,
                },
                body: JSON.stringify({ emoji }),
            });
            if (res.ok) {
                const data = await res.json();
                this._updateReactions(parseInt(zpravaId), data.reakce);
            }
        } catch (e) {
            console.error('Chyba při přidávání reakce:', e);
        }
    }

    _updateReactions(zpravaId, reakce) {
        const container = document.getElementById(`reakce-${zpravaId}`);
        if (!container) return;

        container.innerHTML = reakce.map(r => {
            const isOwn = r.userIds.includes(this.currentUserIdValue);
            const borderClass = isOwn ? 'border-[rgb(241,97,1)] bg-orange-50' : 'border-slate-200';
            const count = r.count > 1 ? `<span class="text-xs font-medium text-slate-600">${r.count}</span>` : '';
            return `<button type="button"
                class="flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-sm bg-white shadow-sm border transition-shadow hover:shadow-md ${borderClass}"
                data-action="click->chat#showReactionInfo"
                data-emoji="${r.emoji}"
                data-user-names="${this._escape(r.userNames.join(', '))}">
                <span>${r.emoji}</span>${count}
            </button>`;
        }).join('');
    }

    showReactionInfo(event) {
        event.stopPropagation();
        const btn       = event.currentTarget;
        const emoji     = btn.dataset.emoji;
        const names     = btn.dataset.userNames;
        const tooltip   = document.getElementById('reaction-tooltip');
        if (!tooltip) return;

        tooltip.textContent = `${emoji}  ${names}`;
        tooltip.classList.remove('hidden');

        const rect = btn.getBoundingClientRect();
        const ttW  = 240;
        let left   = rect.left + rect.width / 2 - ttW / 2;
        left = Math.max(8, Math.min(left, window.innerWidth - ttW - 8));
        tooltip.style.left = left + 'px';
        tooltip.style.top  = (rect.top - 8) + 'px';
        tooltip.style.transform = 'translateY(-100%)';

        clearTimeout(this._tooltipTimer);
        this._tooltipTimer = setTimeout(() => tooltip.classList.add('hidden'), 3000);
    }

    async pinMessage(event) {
        event.stopPropagation();
        const zpravaId = parseInt(event.currentTarget.dataset.pinZpravaId);
        if (!zpravaId) return;

        try {
            const res = await fetch(`/chat/${this.konverzaceIdValue}/pin`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Csrf-Token': this.csrfTokenValue,
                },
                body: JSON.stringify({ zpravaId }),
            });
            if (res.ok) {
                const data = await res.json();
                this._updatePinBanner(data.zprava);
            }
        } catch (e) {
            console.error('Chyba při připínání zprávy:', e);
        }
    }

    async unpinMessage(event) {
        event.stopPropagation();
        const zpravaId = parseInt(document.getElementById('pin-zprava-id')?.value || '0');
        if (!zpravaId) return;

        try {
            const res = await fetch(`/chat/${this.konverzaceIdValue}/pin`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Csrf-Token': this.csrfTokenValue,
                },
                body: JSON.stringify({ zpravaId }),
            });
            if (res.ok) {
                const data = await res.json();
                this._updatePinBanner(data.zprava);
            }
        } catch (e) {
            console.error('Chyba při odepínání zprávy:', e);
        }
    }

    scrollToPin(event) {
        if (event.target.closest('[data-action*="unpinMessage"]')) return;

        const zpravaId = document.getElementById('pin-zprava-id')?.value;
        if (!zpravaId) return;

        const el = document.getElementById(`zprava-${zpravaId}`);
        if (!el) return;

        el.scrollIntoView({ behavior: 'smooth', block: 'center' });

        el.classList.add('brightness-90');
        setTimeout(() => el.classList.remove('brightness-90'), 1500);
    }

    _updatePinBanner(zprava) {
        const banner  = document.getElementById('pin-banner');
        const text    = document.getElementById('pin-text');
        const idInput = document.getElementById('pin-zprava-id');
        const unpinBtn = document.getElementById('pin-unpin-btn');
        if (!banner) return;

        if (zprava) {
            text && (text.textContent = zprava.obsah);
            idInput && (idInput.value = zprava.id);
            unpinBtn && (unpinBtn.dataset.pinZpravaId = zprava.id);
            banner.classList.remove('hidden');
            banner.classList.add('flex');
        } else {
            banner.classList.add('hidden');
            banner.classList.remove('flex');
            idInput && (idInput.value = '');
        }
    }

    toggleInfoPanel() {
        if (!this.hasInfoPanelTarget) return;
        const panel  = this.infoPanelTarget;
        const isOpen = panel.classList.contains('flex');

        if (isOpen) {
            panel.classList.remove('flex');
            panel.classList.add('hidden');
        } else {
            panel.classList.remove('hidden');
            panel.classList.add('flex');
            if (!this._filesPanelLoaded) this._loadSharedFiles();
        }
    }

    async _loadSharedFiles() {
        if (this.konverzaceIdValue === 0) return;
        this._filesPanelLoaded = true;

        const list = document.getElementById('shared-files-list');
        if (!list) return;

        try {
            const res  = await fetch(`/chat/${this.konverzaceIdValue}/prilohy`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) { list.innerHTML = '<p class="text-xs text-red-400">Chyba při načítání.</p>'; return; }
            const files = await res.json();

            if (files.length === 0) {
                list.innerHTML = '<p class="text-xs text-slate-400 italic">Zatím žádné soubory.</p>';
                return;
            }

            const images = files.filter(f => f.mimeType.startsWith('image/'));
            const others = files.filter(f => !f.mimeType.startsWith('image/'));

            let html = '';

            if (images.length > 0) {
                html += `<p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide mb-1.5">Fotky a videa</p>
                <div class="grid grid-cols-3 gap-1 mb-4">`;
                images.forEach(f => {
                    html += `<a href="/chat/priloha/${f.id}" target="_blank"
                        class="aspect-square rounded-lg overflow-hidden bg-slate-100 block hover:opacity-90 transition-opacity">
                        <img src="/chat/priloha/${f.id}" class="w-full h-full object-cover" alt="${this._escape(f.originalName)}">
                    </a>`;
                });
                html += `</div>`;
            }

            if (others.length > 0) {
                html += `<p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide mb-1.5">Soubory</p>
                <div class="space-y-1">`;
                others.forEach(f => {
                    const sizeStr = f.size > 1024 * 1024
                        ? (f.size / 1024 / 1024).toFixed(1) + ' MB'
                        : Math.round(f.size / 1024) + ' KB';
                    const date = new Date(f.createdAt).toLocaleDateString('cs-CZ', { day: 'numeric', month: 'short' });
                    html += `<a href="/chat/priloha/${f.id}" download="${this._escape(f.originalName)}"
                        class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-slate-50 transition-colors group/file">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0 text-slate-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-slate-700 truncate">${this._escape(f.originalName)}</p>
                            <p class="text-[10px] text-slate-400">${sizeStr} · ${date}</p>
                        </div>
                    </a>`;
                });
                html += `</div>`;
            }

            list.innerHTML = html;
        } catch (e) {
            console.error('Chyba při načítání souborů:', e);
            list.innerHTML = '<p class="text-xs text-red-400">Chyba při načítání.</p>';
        }
    }

    openNewDirectModal() {
        this.directModalTarget.classList.remove('hidden');
        this.directModalTarget.classList.add('flex');
    }

    closeDirectModal() {
        this.directModalTarget.classList.add('hidden');
        this.directModalTarget.classList.remove('flex');
    }

    openNewGroupModal() {
        this.groupModalTarget.classList.remove('hidden');
        this.groupModalTarget.classList.add('flex');
    }

    closeGroupModal() {
        this.groupModalTarget.classList.add('hidden');
        this.groupModalTarget.classList.remove('flex');
    }

    _updateConversationListItem(konverzaceId, msg, incrementUnread = false) {
        const item = this.element.querySelector(`[data-konverzace-id="${konverzaceId}"]`);
        if (!item) return;

        const textEl = item.querySelector('[data-last-message]');
        if (textEl) {
            const isOwn = msg.autor.id === this.currentUserIdValue;
            const preview = (isOwn ? 'Ty: ' : '') + msg.obsah.substring(0, 40) + (msg.obsah.length > 40 ? '…' : '');
            textEl.textContent = preview;
            textEl.classList.remove('italic');
            if (incrementUnread) {
                textEl.classList.remove('text-slate-500');
                textEl.classList.add('text-slate-700', 'font-medium');
            }
        }

        const timeEl = item.querySelector('[data-last-time]');
        if (timeEl) {
            const time = new Date(msg.createdAt).toLocaleTimeString('cs-CZ', { hour: '2-digit', minute: '2-digit' });
            timeEl.textContent = time;
            timeEl.classList.remove('hidden');
        }

        if (incrementUnread) {
            const nameEl = item.querySelector('[data-konverzace-name]');
            if (nameEl) {
                nameEl.classList.remove('font-semibold', 'text-slate-800');
                nameEl.classList.add('font-bold', 'text-slate-900');
            }

            let badgeEl = item.querySelector('[data-unread-badge]');
            if (!badgeEl) {
                badgeEl = document.createElement('span');
                badgeEl.dataset.unreadBadge = '';
                badgeEl.className = 'inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-[rgb(241,97,1)] text-white text-[10px] font-bold';
                badgeEl.textContent = '1';
                if (timeEl) timeEl.insertAdjacentElement('afterend', badgeEl);
            } else {
                const current = parseInt(badgeEl.textContent) || 0;
                badgeEl.textContent = current >= 99 ? '99+' : String(current + 1);
            }
        }

        const list = item.parentElement;
        if (list && list.firstElementChild !== item) {
            list.prepend(item);
        }
    }

    openLightbox(src, alt = '') {
        if (!this.hasLightboxTarget) return;
        const img = document.getElementById('lightbox-img');
        if (img) { img.src = src; img.alt = alt; }
        this.lightboxTarget.classList.remove('hidden');
        this.lightboxTarget.classList.add('flex');
    }

    closeLightbox() {
        if (!this.hasLightboxTarget) return;
        this.lightboxTarget.classList.add('hidden');
        this.lightboxTarget.classList.remove('flex');
        const img = document.getElementById('lightbox-img');
        if (img) img.src = '';
    }

    _autoResizeInput() {
        if (!this.hasInputTarget) return;
        this.inputTarget.addEventListener('input', () => {
            this.inputTarget.style.height = 'auto';
            this.inputTarget.style.height = Math.min(this.inputTarget.scrollHeight, 128) + 'px';
        });
    }
}

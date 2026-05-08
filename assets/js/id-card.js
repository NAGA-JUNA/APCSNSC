(function () {
    'use strict';

    const App = {
        state: window.APCSNSCIdCardState || {},
        dom: {},
        listenersBound: false,
        searchTimer: null,
        isExporting: false,
        toastWrap: null,

        init: function () {
            this.cacheDom();
            if (!this.dom.select || !this.dom.exportArea) {
                return;
            }

            this.ensureToastContainer();
            this.bindEvents();
            this.syncMemberOptionText();
            this.filterMembers({ autoSelectFirstVisible: false });

            const selected = this.currentMember();
            if (selected) {
                this.applyMember(selected);
            } else {
                this.renderPreview();
            }
        },

        cacheDom: function () {
            this.dom.select = document.getElementById('memberSelect');
            this.dom.search = document.getElementById('memberSearchInput');
            this.dom.loadBtn = document.getElementById('loadMemberBtn');
            this.dom.exportArea = document.getElementById('idCardExportArea');
            this.dom.pngBtn = document.getElementById('downloadPngBtn');
            this.dom.pdfBtn = document.getElementById('downloadPdfBtn');
            this.dom.printBtn = document.getElementById('printBtn');
            this.dom.generateBtn = document.getElementById('generateCardBtn');
            this.dom.previewGrid = document.getElementById('idCardPreviewGrid');
            this.dom.generatedTimestamp = document.getElementById('generatedTimestamp');
            this.dom.cardPathInput = document.getElementById('idCardPathInput');
            this.dom.storedPathText = document.getElementById('storedPathText');
            this.dom.csrfTokenInput = document.getElementById('idCardCsrfToken');
            this.dom.storeEndpointInput = document.getElementById('idCardStoreEndpoint');
            this.dom.memberViewBaseInput = document.getElementById('idCardMemberViewBase');
            this.dom.defaultPhotoInput = document.getElementById('idCardDefaultPhoto');

            this.dom.csrfToken = this.dom.csrfTokenInput ? this.dom.csrfTokenInput.value : '';
            this.dom.storeEndpoint = this.dom.storeEndpointInput ? this.dom.storeEndpointInput.value : '';
            this.dom.memberViewBase = this.dom.memberViewBaseInput ? this.dom.memberViewBaseInput.value : '/id_card.php?member_id=';
            this.dom.defaultPhoto = this.dom.defaultPhotoInput ? this.dom.defaultPhotoInput.value : '/uploads/default-avatar.png';

            this.dom.nodes = {
                name: document.getElementById('previewName'),
                memberId: document.getElementById('previewMemberId'),
                district: document.getElementById('previewDistrict'),
                bloodGroup: document.getElementById('previewBloodGroup'),
                hospital: document.getElementById('previewHospital'),
                mobile: document.getElementById('previewMobile'),
                validTill: document.getElementById('previewValidTill'),
                serialFront: document.getElementById('previewCardSerial'),
                serialBack: document.getElementById('previewCardSerialBack'),
                siteUrl: document.getElementById('previewSiteUrl'),
                role: document.getElementById('previewRole'),
                phoneNodes: Array.from(document.querySelectorAll('#previewPhone')),
                emailNodes: Array.from(document.querySelectorAll('#previewEmail')),
                photo: document.getElementById('previewPhoto'),
                signature: document.getElementById('previewSignature'),
                qr: document.getElementById('previewQr'),
                status: document.getElementById('previewStatus')
            };

            this.dom.summary = {
                photo: document.getElementById('summaryPhoto'),
                name: document.getElementById('summaryName'),
                memberId: document.getElementById('summaryMemberId'),
                district: document.getElementById('summaryDistrict'),
                role: document.getElementById('summaryRole'),
                joinDate: document.getElementById('summaryJoinDate')
            };
        },

        bindEvents: function () {
            if (this.listenersBound) {
                return;
            }

            if (this.dom.select) {
                this.dom.select.addEventListener('change', () => {
                    const member = this.currentMember();
                    if (member) {
                        this.applyMember(member);
                    }
                });
            }

            if (this.dom.loadBtn) {
                this.dom.loadBtn.addEventListener('click', () => {
                    const member = this.currentMember();
                    if (member) {
                        this.applyMember(member);
                    } else {
                        this.showToast('Please select a member first.', 'error');
                    }
                });
            }

            if (this.dom.search) {
                this.dom.search.addEventListener('input', () => {
                    if (this.searchTimer) {
                        clearTimeout(this.searchTimer);
                    }
                    this.searchTimer = setTimeout(() => {
                        this.filterMembers({ autoSelectFirstVisible: true });
                    }, 300);
                });
            }

            if (this.dom.pngBtn) {
                this.dom.pngBtn.addEventListener('click', () => {
                    this.exportAsPng();
                });
            }

            if (this.dom.pdfBtn) {
                this.dom.pdfBtn.addEventListener('click', () => {
                    this.exportAsPdf();
                });
            }

            if (this.dom.printBtn) {
                this.dom.printBtn.addEventListener('click', () => {
                    window.print();
                });
            }

            if (this.dom.generateBtn) {
                this.dom.generateBtn.addEventListener('click', () => {
                    this.renderPreview();
                    this.showToast('Preview updated.', 'success');
                });
            }

            this.listenersBound = true;
        },

        renderPreview: function () {
            this.renderFront();
            this.renderBack();
            this.renderSummary();
            this.renderQrCode();
            this.updateTimestamp();
        },

        renderFront: function () {
            const n = this.dom.nodes;
            const s = this.state;

            this.setText(n.name, s.full_name);
            this.setText(n.memberId, s.member_id);
            this.setText(n.district, s.district);
            this.setText(n.bloodGroup, s.blood_group);
            this.setText(n.hospital, s.working_place || s.hospital);
            this.setText(n.mobile, s.phone);
            this.setText(n.validTill, s.valid_till);
            this.setText(n.serialFront, s.card_serial || s.member_id);
            this.setText(n.role, s.role || s.designation);

            this.setSafeImage(n.photo, s.photo, this.dom.defaultPhoto);
            this.applyAdaptiveFrontText();
        },

        renderBack: function () {
            const n = this.dom.nodes;
            const s = this.state;

            this.setText(n.serialBack, s.card_serial || s.member_id);
            this.setSafeImage(n.signature, s.signature, this.dom.defaultPhoto);

            const helplineRaw = (n.phoneNodes[0] && n.phoneNodes[0].dataset.default) ? n.phoneNodes[0].dataset.default : '';
            const helpline = this.formatHelpline(helplineRaw);
            n.phoneNodes.forEach((el) => {
                this.setText(el, helpline);
            });

            const email = s.email || 'support@APCSNSC.in';
            n.emailNodes.forEach((el) => {
                this.setText(el, email);
            });

            if (n.siteUrl) {
                const siteHref = (n.siteUrl.getAttribute('href') || '').trim();
                const text = (n.siteUrl.textContent || '').trim();
                const finalValue = siteHref || text;
                if (finalValue) {
                    n.siteUrl.setAttribute('href', finalValue);
                    n.siteUrl.textContent = finalValue;
                }
            }

            this.setText(n.status, s.status || 'Pending');
        },

        renderSummary: function () {
            const s = this.state;
            const summary = this.dom.summary;

            this.setSafeImage(summary.photo, s.photo, this.dom.defaultPhoto);
            this.setText(summary.name, s.full_name);
            this.setText(summary.memberId, s.member_id);
            this.setText(summary.district, s.district);
            this.setText(summary.role, s.role || s.designation);
            this.setText(summary.joinDate, s.join_date);
        },

        renderQrCode: function () {
            const qrNode = this.dom.nodes.qr;
            if (!qrNode) {
                return;
            }

            const qrUrl = this.buildMemberUrl();
            if (!qrUrl) {
                this.renderFallbackQr('');
                return;
            }

            const fallback = () => {
                this.renderFallbackQr(qrUrl);
            };

            if (!window.QRCode || typeof window.QRCode.toDataURL !== 'function') {
                fallback();
                return;
            }

            window.QRCode.toDataURL(qrUrl, {
                width: 260,
                margin: 1,
                color: {
                    dark: '#121212',
                    light: '#ffffff'
                }
            }).then((dataUrl) => {
                this.setSafeImage(qrNode, dataUrl, this.dom.defaultPhoto);
            }).catch(() => {
                fallback();
            });
        },

        exportAsPng: async function () {
            if (this.isExporting) {
                return;
            }

            if (!this.dom.exportArea || typeof window.html2canvas === 'undefined') {
                this.showToast('PNG export dependency is missing.', 'error');
                return;
            }

            const desktopScale = 3;
            const mobileScale = 2;
            const scale = window.innerWidth <= 900 ? mobileScale : desktopScale;

            this.setExportState(true, this.dom.pngBtn, '<i class="fa-solid fa-spinner fa-spin me-1"></i>Exporting PNG...');
            try {
                const canvas = await this.captureExportCanvas(scale);
                if (!canvas || canvas.width < 100 || canvas.height < 100) {
                    throw new Error('Preview is not ready. Please load member details and try again.');
                }

                const dataUrl = canvas.toDataURL('image/png');
                if (!dataUrl || dataUrl === 'data:,') {
                    throw new Error('Failed to generate PNG output.');
                }

                const filename = 'APCSNSC-id-card-' + (this.state.member_id || 'member') + '.png';
                this.triggerDownload(dataUrl, filename);
                this.showToast('PNG downloaded successfully', 'success');

                await this.saveGeneratedCard(dataUrl, 'png');
            } catch (error) {
                this.showToast(this.humanizeError(error, 'Failed to export PNG.'), 'error');
            } finally {
                this.setExportState(false);
            }
        },

        exportAsPdf: async function () {
            if (this.isExporting) {
                return;
            }

            if (!this.dom.exportArea || typeof window.html2canvas === 'undefined' || typeof window.jspdf === 'undefined') {
                this.showToast('PDF export dependency is missing.', 'error');
                return;
            }

            this.setExportState(true, this.dom.pdfBtn, '<i class="fa-solid fa-spinner fa-spin me-1"></i>Exporting PDF...');
            try {
                const canvas = await this.captureExportCanvas(2);
                if (!canvas || canvas.width < 100 || canvas.height < 100) {
                    throw new Error('Preview is not ready. Please load member details and try again.');
                }

                const imgData = canvas.toDataURL('image/png');
                if (!imgData || imgData === 'data:,') {
                    throw new Error('Failed to generate PDF image.');
                }

                const jsPDFRef = window.jspdf.jsPDF;
                const pdf = new jsPDFRef('l', 'mm', 'a4');
                const pageW = pdf.internal.pageSize.getWidth();
                const pageH = pdf.internal.pageSize.getHeight();
                const imgProps = pdf.getImageProperties(imgData);

                const targetW = pageW - 12;
                const targetH = (imgProps.height * targetW) / imgProps.width;
                const y = Math.max(4, (pageH - targetH) / 2);
                pdf.addImage(imgData, 'PNG', 6, y, targetW, targetH);

                pdf.save('APCSNSC-id-card-' + (this.state.member_id || 'member') + '.pdf');
                this.showToast('PDF downloaded successfully', 'success');

                await this.saveGeneratedCard(imgData, 'pdf');
            } catch (error) {
                this.showToast(this.humanizeError(error, 'Failed to export PDF.'), 'error');
            } finally {
                this.setExportState(false);
            }
        },

        saveGeneratedCard: async function (dataUrl, extension) {
            if (!this.dom.storeEndpoint || !this.dom.csrfToken || !this.state.id) {
                return null;
            }

            const formData = new FormData();
            formData.append('csrf_token', this.dom.csrfToken);
            formData.append('member_id', String(this.state.id));
            formData.append('file_data', dataUrl);
            formData.append('extension', extension);

            const response = await fetch(this.dom.storeEndpoint, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const raw = await response.text();
            let json = null;
            try {
                json = JSON.parse(raw);
            } catch (error) {
                throw new Error('Card save response is invalid.');
            }

            if (!response.ok) {
                throw new Error((json && json.error) ? json.error : 'Unable to save generated card.');
            }

            if (json && json.path) {
                if (this.dom.cardPathInput) {
                    this.dom.cardPathInput.value = json.path;
                }
                if (this.dom.storedPathText) {
                    this.dom.storedPathText.textContent = json.path;
                }
                this.showToast('Card saved successfully', 'success');
                return json.path;
            }

            return null;
        },

        updateTimestamp: function () {
            if (!this.dom.generatedTimestamp) {
                return;
            }

            const now = new Date();
            this.dom.generatedTimestamp.textContent = now.toLocaleString('en-IN', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        },

        applyMember: function (member) {
            Object.assign(this.state, member || {});
            this.renderPreview();
        },

        currentMember: function () {
            if (!this.dom.select) {
                return null;
            }

            const option = this.dom.select.options[this.dom.select.selectedIndex];
            if (!option || !option.dataset.member) {
                return null;
            }

            try {
                return JSON.parse(option.dataset.member);
            } catch (error) {
                return null;
            }
        },

        syncMemberOptionText: function () {
            if (!this.dom.select) {
                return;
            }

            Array.from(this.dom.select.options).forEach((option, index) => {
                if (index === 0 || !option.dataset.member) {
                    return;
                }
                try {
                    const member = JSON.parse(option.dataset.member);
                    option.textContent = [member.member_id, member.full_name, member.district].filter(Boolean).join(' - ');
                } catch (error) {
                    // keep original option text
                }
            });
        },

        filterMembers: function (options) {
            if (!this.dom.select) {
                return;
            }

            const opts = options || {};
            const autoSelect = !!opts.autoSelectFirstVisible;
            const term = this.dom.search ? String(this.dom.search.value || '').toLowerCase().trim() : '';

            let firstVisibleValue = '';
            Array.from(this.dom.select.options).forEach((option, index) => {
                if (index === 0) {
                    option.hidden = false;
                    return;
                }

                const visible = term === '' || option.textContent.toLowerCase().includes(term);
                option.hidden = !visible;

                if (visible && !firstVisibleValue) {
                    firstVisibleValue = option.value;
                }
            });

            if (!autoSelect || !firstVisibleValue) {
                return;
            }

            const selectedOption = this.dom.select.options[this.dom.select.selectedIndex];
            if (selectedOption && !selectedOption.hidden) {
                return;
            }

            this.dom.select.value = firstVisibleValue;
            const firstMember = this.currentMember();
            if (firstMember) {
                this.applyMember(firstMember);
            }
        },

        applyAdaptiveFrontText: function () {
            const nameNode = this.dom.nodes.name;
            const roleNode = this.dom.nodes.role;

            if (nameNode) {
                nameNode.classList.remove('is-long', 'is-xlong', 'is-xxlong');
                this.fitText(nameNode, {
                    max: 33,
                    min: 20,
                    step: 0.5,
                    maxLines: 2
                });
            }

            if (roleNode) {
                this.fitText(roleNode, {
                    max: 19,
                    min: 12,
                    step: 0.5,
                    maxLines: 2
                });
            }
        },

        fitText: function (element, config) {
            if (!element) {
                return;
            }

            const max = Number(config.max || 20);
            const min = Number(config.min || 10);
            const step = Number(config.step || 0.5);
            const maxLines = Number(config.maxLines || 2);

            let size = max;
            element.style.fontSize = size + 'px';
            element.style.wordBreak = 'break-word';
            element.style.whiteSpace = 'normal';

            const computed = window.getComputedStyle(element);
            const lineHeightRaw = parseFloat(computed.lineHeight);
            const fallbackLineHeight = size * 1.2;

            while (size > min) {
                const lineHeight = Number.isFinite(lineHeightRaw) ? lineHeightRaw : fallbackLineHeight;
                const maxHeight = Math.ceil(lineHeight * maxLines + 1);
                if (element.scrollWidth <= element.clientWidth + 2 && element.scrollHeight <= maxHeight) {
                    break;
                }
                size -= step;
                element.style.fontSize = size + 'px';
            }
        },

        buildMemberUrl: function () {
            if (this.state.qr_url && String(this.state.qr_url).trim() !== '') {
                return String(this.state.qr_url).trim();
            }
            return this.dom.memberViewBase + encodeURIComponent(this.state.member_id || '');
        },

        renderFallbackQr: function (targetUrl) {
            const qrNode = this.dom.nodes.qr;
            if (!qrNode) {
                return '';
            }

            const safeTarget = targetUrl || this.dom.memberViewBase;
            const fallbackUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=0&data=' + encodeURIComponent(safeTarget);
            this.setSafeImage(qrNode, fallbackUrl, this.dom.defaultPhoto);
            return fallbackUrl;
        },

        setSafeImage: function (img, src, fallback) {
            if (!img) {
                return;
            }

            const fallbackSrc = fallback || this.dom.defaultPhoto;
            const nextSrc = src || fallbackSrc;

            img.onerror = () => {
                if (img.src !== fallbackSrc) {
                    img.src = fallbackSrc;
                }
            };

            img.src = nextSrc;
        },

        setText: function (node, value) {
            if (!node) {
                return;
            }
            node.textContent = (value === undefined || value === null || value === '') ? '-' : String(value);
        },

        formatHelpline: function (value) {
            const normalized = String(value || '').trim();
            if (!normalized) {
                return '-';
            }
            return /^\d{10}$/.test(normalized) ? '+91 ' + normalized : normalized;
        },

        waitForImagesLoad: async function () {
            if (!this.dom.exportArea) {
                return;
            }

            const images = Array.from(this.dom.exportArea.querySelectorAll('img'));
            const imageTimeout = 7000;

            await Promise.all(images.map((img) => {
                return new Promise((resolve) => {
                    if (!img.src) {
                        resolve();
                        return;
                    }

                    if (img.complete) {
                        resolve();
                        return;
                    }

                    let settled = false;
                    const done = () => {
                        if (settled) {
                            return;
                        }
                        settled = true;
                        clearTimeout(timer);
                        img.removeEventListener('load', done);
                        img.removeEventListener('error', done);
                        resolve();
                    };

                    const timer = setTimeout(done, imageTimeout);
                    img.addEventListener('load', done);
                    img.addEventListener('error', done);
                });
            }));
        },

        captureExportCanvas: async function (scale) {
            if (!this.dom.exportArea || typeof window.html2canvas === 'undefined') {
                throw new Error('Export canvas dependency is missing.');
            }

            await this.waitForImagesLoad();

            this.dom.exportArea.classList.add('id-card-exporting');
            try {
                const canvas = await window.html2canvas(this.dom.exportArea, {
                    scale: Math.max(1, Number(scale || 2)),
                    useCORS: true,
                    allowTaint: true,
                    backgroundColor: null,
                    imageTimeout: 25000,
                    logging: false
                });

                if (!canvas || canvas.width === 0 || canvas.height === 0) {
                    throw new Error('Export canvas is empty.');
                }

                return canvas;
            } finally {
                this.dom.exportArea.classList.remove('id-card-exporting');
            }
        },

        triggerDownload: function (href, filename) {
            const a = document.createElement('a');
            a.href = href;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        },

        setExportState: function (enabled, activeButton, activeHtml) {
            const isLoading = !!enabled;
            this.isExporting = isLoading;

            const pngBtn = this.dom.pngBtn;
            const pdfBtn = this.dom.pdfBtn;

            if (pngBtn && !pngBtn.dataset.originalHtml) {
                pngBtn.dataset.originalHtml = pngBtn.innerHTML;
            }
            if (pdfBtn && !pdfBtn.dataset.originalHtml) {
                pdfBtn.dataset.originalHtml = pdfBtn.innerHTML;
            }

            [pngBtn, pdfBtn].forEach((btn) => {
                if (!btn) {
                    return;
                }
                btn.disabled = isLoading;
            });

            if (!isLoading) {
                if (pngBtn && pngBtn.dataset.originalHtml) {
                    pngBtn.innerHTML = pngBtn.dataset.originalHtml;
                }
                if (pdfBtn && pdfBtn.dataset.originalHtml) {
                    pdfBtn.innerHTML = pdfBtn.dataset.originalHtml;
                }
                return;
            }

            if (activeButton && activeHtml) {
                activeButton.innerHTML = activeHtml;
            }
        },

        humanizeError: function (error, fallback) {
            if (error && typeof error.message === 'string' && error.message.trim() !== '') {
                return error.message;
            }
            return fallback;
        },

        ensureToastContainer: function () {
            let wrap = document.querySelector('.id-card-toast-wrap');
            if (!wrap) {
                wrap = document.createElement('div');
                wrap.className = 'id-card-toast-wrap';
                document.body.appendChild(wrap);
            }
            this.toastWrap = wrap;
        },

        showToast: function (message, type) {
            if (!message) {
                return;
            }

            this.ensureToastContainer();
            const toast = document.createElement('div');
            toast.className = 'id-card-toast ' + (type === 'error' ? 'error' : 'success');
            toast.textContent = String(message);
            this.toastWrap.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.2s ease';
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 220);
            }, 2600);
        }
    };

    App.init();
})();

(function () {
    'use strict';

    const FIELD_CLASSES = {
        photo: 'is-photo',
        qr_code: 'is-qr',
        member_id: 'badge',
        signature: 'signature'
    };

    function showToast(message, type) {
        let wrap = document.querySelector('.template-toast-wrap');
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.className = 'template-toast-wrap';
            document.body.appendChild(wrap);
        }
        const toast = document.createElement('div');
        toast.className = 'template-toast ' + (type === 'error' ? 'error' : 'success');
        toast.textContent = String(message);
        wrap.appendChild(toast);
        setTimeout(() => {
            toast.remove();
        }, 2500);
    }

    function safeText(value) {
        if (value === null || value === undefined || value === '') return '-';
        return String(value);
    }

    function applyFieldStyles(el, conf) {
        if (!el || !conf) return;
        el.style.left = (Number(conf.pos_x) || 0) + 'px';
        el.style.top = (Number(conf.pos_y) || 0) + 'px';
        el.style.width = Math.max(10, Number(conf.width) || 120) + 'px';
        el.style.height = Math.max(10, Number(conf.height) || 24) + 'px';
        el.style.fontSize = Math.max(8, Number(conf.font_size) || 12) + 'px';
        el.style.color = conf.color || '#1b2f44';
        el.style.textAlign = conf.align || 'left';
        el.style.fontWeight = conf.font_weight || '600';
        el.style.display = (Number(conf.is_enabled) === 0) ? 'none' : '';
    }

    function createQrDataUrl(text) {
        if (!window.QRCode || typeof window.QRCode.toDataURL !== 'function') {
            return Promise.resolve('https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(text || ''));
        }
        return window.QRCode.toDataURL(text, {
            width: 300,
            margin: 1,
            color: { dark: '#101010', light: '#ffffff' }
        }).catch(() => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(text || ''));
    }

    function debounce(fn, delay) {
        let t = null;
        return function () {
            clearTimeout(t);
            const args = arguments;
            t = setTimeout(() => fn.apply(null, args), delay);
        };
    }

    function initGenerator() {
        const root = document.getElementById('templateCardGenerator');
        if (!root) return;

        const state = window.APCSNSCTemplateState || {};
        const config = window.APCSNSCTemplateConfig || { positions: {}, templates: {} };

        const dom = {
            select: document.getElementById('memberSelect'),
            search: document.getElementById('memberSearchInput'),
            loadBtn: document.getElementById('loadMemberBtn'),
            generateBtn: document.getElementById('generateCardBtn'),
            pngBtn: document.getElementById('downloadPngBtn'),
            pdfBtn: document.getElementById('downloadPdfBtn'),
            printBtn: document.getElementById('printBtn'),
            exportArea: document.getElementById('idCardExportArea'),
            generatedTs: document.getElementById('generatedTimestamp'),
            csrf: document.getElementById('idCardCsrfToken'),
            saveEndpoint: document.getElementById('idCardStoreEndpoint'),
            cardPathInput: document.getElementById('idCardPathInput'),
            storedPathText: document.getElementById('storedPathText'),
            memberViewBase: document.getElementById('idCardMemberViewBase'),
            defaultPhoto: document.getElementById('idCardDefaultPhoto'),
            frontOverlay: document.getElementById('frontOverlay'),
            backOverlay: document.getElementById('backOverlay'),
            summary: {
                photo: document.getElementById('summaryPhoto'),
                name: document.getElementById('summaryName'),
                id: document.getElementById('summaryMemberId'),
                district: document.getElementById('summaryDistrict'),
                role: document.getElementById('summaryRole'),
                joinDate: document.getElementById('summaryJoinDate')
            },
            bulkBtn: document.getElementById('bulkPrintBtn'),
            bulkChecks: Array.from(document.querySelectorAll('.bulk-print-item input[type="checkbox"]')),
            bulkStage: document.getElementById('templatePrintArea')
        };

        const nodeMap = {
            full_name: [document.getElementById('previewName')],
            member_id: [document.getElementById('previewMemberId')],
            district: [document.getElementById('previewDistrict')],
            hospital: [document.getElementById('previewHospital')],
            role: [document.getElementById('previewRole')],
            join_date: [document.getElementById('previewJoinDate')],
            valid_till: [document.getElementById('previewValidTill')],
            blood_group: [document.getElementById('previewBloodGroup')],
            mobile: [document.getElementById('previewMobile')],
            website: [document.getElementById('previewSiteUrl')],
            helpline: Array.from(document.querySelectorAll('#previewPhone')),
            email: Array.from(document.querySelectorAll('#previewEmail')),
            serial_number: [document.getElementById('previewCardSerial'), document.getElementById('previewCardSerialBack')],
            signature: [document.getElementById('previewSignatureWrap')],
            photo: [document.getElementById('previewPhotoWrap')],
            qr_code: [document.getElementById('previewQrWrap')]
        };

        function memberUrl() {
            if (state.qr_url && String(state.qr_url).trim() !== '') return String(state.qr_url);
            const base = dom.memberViewBase ? dom.memberViewBase.value : '/id_card.php?member_id=';
            return base + encodeURIComponent(state.member_id || '');
        }

        function applyPositions() {
            Object.keys(config.positions || {}).forEach((field) => {
                const conf = config.positions[field];
                const nodes = nodeMap[field] || [];
                nodes.forEach((n) => applyFieldStyles(n, conf));
            });
        }

        function setSafeImage(img, src) {
            if (!img) return;
            const target = img.tagName && img.tagName.toLowerCase() === 'img'
                ? img
                : img.querySelector && img.querySelector('img')
                    ? img.querySelector('img')
                    : null;
            if (!target) return;
            const fallback = dom.defaultPhoto ? dom.defaultPhoto.value : '';
            target.onerror = () => {
                if (fallback && target.src !== fallback) target.src = fallback;
            };
            target.src = src || fallback;
        }

        async function renderQr() {
            const qr = document.getElementById('previewQr');
            if (!qr) return;
            qr.src = await createQrDataUrl(memberUrl());
        }

        function renderSummary() {
            if (dom.summary.photo) setSafeImage(dom.summary.photo, state.photo);
            if (dom.summary.name) dom.summary.name.textContent = safeText(state.full_name);
            if (dom.summary.id) dom.summary.id.textContent = safeText(state.member_id);
            if (dom.summary.district) dom.summary.district.textContent = safeText(state.district);
            if (dom.summary.role) dom.summary.role.textContent = safeText(state.role || state.designation);
            if (dom.summary.joinDate) dom.summary.joinDate.textContent = safeText(state.join_date);
        }

        function updateTimestamp() {
            if (!dom.generatedTs) return;
            dom.generatedTs.textContent = new Date().toLocaleString('en-IN', {
                day: '2-digit', month: 'short', year: 'numeric',
                hour: '2-digit', minute: '2-digit', second: '2-digit'
            });
        }

        function renderPreview() {
            (nodeMap.full_name[0] || {}).textContent = safeText(state.full_name);
            (nodeMap.member_id[0] || {}).textContent = safeText(state.member_id);
            (nodeMap.district[0] || {}).textContent = safeText(state.district);
            (nodeMap.hospital[0] || {}).textContent = safeText(state.hospital || state.working_place);
            (nodeMap.role[0] || {}).textContent = safeText(state.role || state.designation);
            (nodeMap.join_date[0] || {}).textContent = safeText(state.join_date);
            (nodeMap.valid_till[0] || {}).textContent = safeText(state.valid_till);
            (nodeMap.blood_group[0] || {}).textContent = safeText(state.blood_group);
            (nodeMap.mobile[0] || {}).textContent = safeText(state.phone);
            (nodeMap.website[0] || {}).textContent = safeText(state.website || (nodeMap.website[0] ? nodeMap.website[0].textContent : ''));
            nodeMap.helpline.forEach((n) => {
                const v = n && n.dataset.default ? n.dataset.default : '';
                n.textContent = /^\d{10}$/.test(String(v)) ? '+91 ' + v : safeText(v);
            });
            nodeMap.email.forEach((n) => { n.textContent = safeText(state.email || 'support@APCSNSC.in'); });
            [nodeMap.serial_number[0], nodeMap.serial_number[1]].forEach((n) => {
                if (n) n.textContent = safeText(state.card_serial || state.member_id);
            });

            setSafeImage(nodeMap.photo[0], state.photo);
            setSafeImage(nodeMap.signature[0], state.signature);
            renderQr();
            renderSummary();
            applyPositions();
            updateTimestamp();
        }

        function currentMember() {
            if (!dom.select) return null;
            const option = dom.select.options[dom.select.selectedIndex];
            if (!option || !option.dataset.member) return null;
            try { return JSON.parse(option.dataset.member); } catch (e) { return null; }
        }

        function applyMember(member) {
            Object.assign(state, member || {});
            renderPreview();
        }

        function filterMembers(autoSelect) {
            if (!dom.select) return;
            const term = dom.search ? String(dom.search.value || '').toLowerCase().trim() : '';
            let firstVisible = '';
            Array.from(dom.select.options).forEach((option, idx) => {
                if (idx === 0) { option.hidden = false; return; }
                const ok = term === '' || option.textContent.toLowerCase().includes(term);
                option.hidden = !ok;
                if (ok && !firstVisible) firstVisible = option.value;
            });

            if (autoSelect && firstVisible) {
                const selectedOption = dom.select.options[dom.select.selectedIndex];
                if (!selectedOption || selectedOption.hidden) {
                    dom.select.value = firstVisible;
                    const m = currentMember();
                    if (m) applyMember(m);
                }
            }
        }

        function toggleExportState(loading, btn, label) {
            [dom.pngBtn, dom.pdfBtn].forEach((b) => {
                if (!b) return;
                if (!b.dataset.originalHtml) b.dataset.originalHtml = b.innerHTML;
                b.disabled = !!loading;
            });
            if (loading && btn) btn.innerHTML = label;
            if (!loading) {
                [dom.pngBtn, dom.pdfBtn].forEach((b) => {
                    if (b && b.dataset.originalHtml) b.innerHTML = b.dataset.originalHtml;
                });
            }
        }

        async function waitImages() {
            const images = dom.exportArea ? Array.from(dom.exportArea.querySelectorAll('img')) : [];
            await Promise.all(images.map((img) => new Promise((resolve) => {
                if (!img.src || img.complete) return resolve();
                let done = false;
                const finish = () => {
                    if (done) return;
                    done = true;
                    clearTimeout(t);
                    img.removeEventListener('load', finish);
                    img.removeEventListener('error', finish);
                    resolve();
                };
                const t = setTimeout(finish, 9000);
                img.addEventListener('load', finish);
                img.addEventListener('error', finish);
            })));
        }

        async function captureCanvas(scale) {
            if (!dom.exportArea || typeof window.html2canvas === 'undefined') throw new Error('html2canvas is missing.');
            await waitImages();
            dom.exportArea.classList.add('id-card-exporting');
            try {
                const canvas = await window.html2canvas(dom.exportArea, {
                    scale: Math.max(1, Number(scale || 2)),
                    useCORS: true,
                    allowTaint: true,
                    backgroundColor: null,
                    imageTimeout: 25000,
                    logging: false
                });
                if (!canvas || canvas.width < 80 || canvas.height < 80) throw new Error('Preview is empty.');
                return canvas;
            } finally {
                dom.exportArea.classList.remove('id-card-exporting');
            }
        }

        function triggerDownload(href, filename) {
            const a = document.createElement('a');
            a.href = href;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        async function saveGeneratedCard(dataUrl, ext) {
            if (!dom.saveEndpoint || !dom.csrf || !state.id) return null;
            const form = new FormData();
            form.append('csrf_token', dom.csrf.value);
            form.append('member_id', String(state.id));
            form.append('file_data', dataUrl);
            form.append('extension', ext);

            const res = await fetch(dom.saveEndpoint.value, {
                method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: form
            });

            const raw = await res.text();
            let json = null;
            try { json = JSON.parse(raw); } catch (e) { throw new Error('Invalid server response.'); }
            if (!res.ok) throw new Error(json && json.error ? json.error : 'Unable to save card.');

            if (json && json.path) {
                if (dom.cardPathInput) dom.cardPathInput.value = json.path;
                if (dom.storedPathText) dom.storedPathText.textContent = json.path;
                showToast('Card saved successfully', 'success');
                return json.path;
            }
            return null;
        }

        async function exportAsPng() {
            const btn = dom.pngBtn;
            try {
                toggleExportState(true, btn, '<i class="fa-solid fa-spinner fa-spin me-1"></i>Exporting PNG...');
                const scale = window.innerWidth <= 900 ? 2 : 3;
                const canvas = await captureCanvas(scale);
                const dataUrl = canvas.toDataURL('image/png');
                if (!dataUrl || dataUrl === 'data:,') throw new Error('PNG generation failed.');
                triggerDownload(dataUrl, 'APCSNSC-id-card-' + (state.member_id || 'member') + '.png');
                showToast('PNG downloaded successfully', 'success');
                await saveGeneratedCard(dataUrl, 'png');
            } catch (e) {
                showToast(e.message || 'Failed to export PNG.', 'error');
            } finally {
                toggleExportState(false);
            }
        }

        async function exportAsPdf() {
            const btn = dom.pdfBtn;
            try {
                if (!window.jspdf || !window.jspdf.jsPDF) throw new Error('jsPDF is missing.');
                toggleExportState(true, btn, '<i class="fa-solid fa-spinner fa-spin me-1"></i>Exporting PDF...');
                const canvas = await captureCanvas(2);
                const imgData = canvas.toDataURL('image/png');
                if (!imgData || imgData === 'data:,') throw new Error('PDF image generation failed.');

                const Pdf = window.jspdf.jsPDF;
                const pdf = new Pdf('l', 'mm', 'a4');
                const pageW = pdf.internal.pageSize.getWidth();
                const pageH = pdf.internal.pageSize.getHeight();
                const imgProps = pdf.getImageProperties(imgData);
                const w = pageW - 12;
                const h = (imgProps.height * w) / imgProps.width;
                const y = Math.max(4, (pageH - h) / 2);
                pdf.addImage(imgData, 'PNG', 6, y, w, h);
                pdf.save('APCSNSC-id-card-' + (state.member_id || 'member') + '.pdf');
                showToast('PDF downloaded successfully', 'success');
                await saveGeneratedCard(imgData, 'pdf');
            } catch (e) {
                showToast(e.message || 'Failed to export PDF.', 'error');
            } finally {
                toggleExportState(false);
            }
        }

        function syncOptionText() {
            if (!dom.select) return;
            Array.from(dom.select.options).forEach((option, idx) => {
                if (idx === 0 || !option.dataset.member) return;
                try {
                    const m = JSON.parse(option.dataset.member);
                    option.textContent = [m.member_id, m.full_name, m.district].filter(Boolean).join(' - ');
                } catch (e) {
                    // ignore
                }
            });
        }

        function collectBulkMembers() {
            const selected = dom.bulkChecks.filter((cb) => cb.checked).map((cb) => cb.value);
            if (selected.length === 0) return [];
            const result = [];
            Array.from(dom.select.options).forEach((opt) => {
                if (!opt.dataset.member || selected.indexOf(opt.value) === -1) return;
                try { result.push(JSON.parse(opt.dataset.member)); } catch (e) { }
            });
            return result;
        }

        async function bulkPrint() {
            if (!dom.bulkStage || !dom.bulkChecks.length) {
                showToast('Bulk print list not available.', 'error');
                return;
            }
            const members = collectBulkMembers();
            if (members.length === 0) {
                showToast('Select at least one member for bulk print.', 'error');
                return;
            }

            dom.bulkStage.innerHTML = '';
            const oldState = Object.assign({}, state);

            for (const member of members) {
                Object.assign(state, member);
                renderPreview();
                await waitImages();
                const clone = dom.exportArea.cloneNode(true);
                clone.style.marginBottom = '20px';
                dom.bulkStage.appendChild(clone);
            }

            Object.assign(state, oldState);
            renderPreview();
            window.print();
        }

        if (dom.select) {
            dom.select.addEventListener('change', () => {
                const m = currentMember();
                if (m) applyMember(m);
            });
        }

        if (dom.loadBtn) {
            dom.loadBtn.addEventListener('click', () => {
                const m = currentMember();
                if (m) applyMember(m);
                else showToast('Please select a member first.', 'error');
            });
        }

        if (dom.search) {
            dom.search.addEventListener('input', debounce(() => filterMembers(true), 300));
        }

        if (dom.generateBtn) dom.generateBtn.addEventListener('click', () => renderPreview());
        if (dom.pngBtn) dom.pngBtn.addEventListener('click', exportAsPng);
        if (dom.pdfBtn) dom.pdfBtn.addEventListener('click', exportAsPdf);
        if (dom.printBtn) dom.printBtn.addEventListener('click', () => window.print());
        if (dom.bulkBtn) dom.bulkBtn.addEventListener('click', bulkPrint);

        syncOptionText();
        filterMembers(false);
        const selected = currentMember();
        if (selected) applyMember(selected);
        else renderPreview();
    }

    function initTemplateManager() {
        const root = document.getElementById('templateCardManager');
        if (!root) return;

        const data = window.APCSNSCTemplateManager || { positions: {}, csrf: '' };
        const frontCanvas = document.getElementById('templateFrontCanvas');
        const backCanvas = document.getElementById('templateBackCanvas');
        const tableBody = document.getElementById('templateFieldsBody');
        const saveBtn = document.getElementById('savePositionsBtn');
        const form = document.getElementById('positionsForm');

        const fieldNodes = {};
        let activeField = null;

        const fields = [
            { name: 'photo', side: 'front', label: 'Photo' },
            { name: 'full_name', side: 'front', label: 'Full Name' },
            { name: 'member_id', side: 'front', label: 'Member ID' },
            { name: 'district', side: 'front', label: 'District' },
            { name: 'hospital', side: 'front', label: 'Hospital' },
            { name: 'role', side: 'front', label: 'Role' },
            { name: 'join_date', side: 'front', label: 'Join Date' },
            { name: 'valid_till', side: 'front', label: 'Valid Till' },
            { name: 'qr_code', side: 'back', label: 'QR Code' },
            { name: 'website', side: 'back', label: 'Website' },
            { name: 'helpline', side: 'back', label: 'Helpline' },
            { name: 'email', side: 'back', label: 'Email' },
            { name: 'signature', side: 'back', label: 'Signature' },
            { name: 'serial_number', side: 'back', label: 'Serial Number' }
        ];

        function ensurePosition(field) {
            if (!data.positions[field.name]) {
                data.positions[field.name] = {
                    side: field.side,
                    pos_x: 20,
                    pos_y: 20,
                    font_size: 12,
                    color: '#1b2f44',
                    align: 'left',
                    width: field.name === 'photo' || field.name === 'qr_code' ? 90 : 120,
                    height: field.name === 'photo' || field.name === 'qr_code' ? 110 : 26,
                    font_weight: '600',
                    is_enabled: 1
                };
            }
            return data.positions[field.name];
        }

        function renderNodes() {
            [frontCanvas, backCanvas].forEach((canvas) => {
                if (!canvas) return;
                canvas.querySelectorAll('.template-draggable').forEach((n) => n.remove());
            });

            fields.forEach((field) => {
                const conf = ensurePosition(field);
                const canvas = field.side === 'front' ? frontCanvas : backCanvas;
                if (!canvas) return;

                const node = document.createElement('div');
                node.className = 'field-node template-draggable ' + (FIELD_CLASSES[field.name] || '');
                node.dataset.field = field.name;
                node.textContent = field.label;
                applyFieldStyles(node, conf);
                canvas.appendChild(node);
                fieldNodes[field.name] = node;
            });
        }

        function renderTable() {
            if (!tableBody) return;
            tableBody.innerHTML = '';

            fields.forEach((field) => {
                const conf = ensurePosition(field);
                const tr = document.createElement('tr');
                tr.innerHTML = '' +
                    '<td>' + field.label + '<input type="hidden" name="field_name[]" value="' + field.name + '"><input type="hidden" name="side[]" value="' + field.side + '"></td>' +
                    '<td><input type="number" name="pos_x[]" value="' + conf.pos_x + '" data-field="' + field.name + '" data-key="pos_x"></td>' +
                    '<td><input type="number" name="pos_y[]" value="' + conf.pos_y + '" data-field="' + field.name + '" data-key="pos_y"></td>' +
                    '<td><input type="number" name="width[]" value="' + conf.width + '" data-field="' + field.name + '" data-key="width"></td>' +
                    '<td><input type="number" name="height[]" value="' + conf.height + '" data-field="' + field.name + '" data-key="height"></td>' +
                    '<td><input type="number" name="font_size[]" value="' + conf.font_size + '" data-field="' + field.name + '" data-key="font_size"></td>' +
                    '<td><input type="color" name="color[]" value="' + conf.color + '" data-field="' + field.name + '" data-key="color"></td>' +
                    '<td><select name="align[]" data-field="' + field.name + '" data-key="align">' +
                        '<option value="left"' + (conf.align === 'left' ? ' selected' : '') + '>L</option>' +
                        '<option value="center"' + (conf.align === 'center' ? ' selected' : '') + '>C</option>' +
                        '<option value="right"' + (conf.align === 'right' ? ' selected' : '') + '>R</option>' +
                    '</select></td>' +
                    '<td><select name="font_weight[]" data-field="' + field.name + '" data-key="font_weight">' +
                        '<option value="500"' + (String(conf.font_weight) === '500' ? ' selected' : '') + '>500</option>' +
                        '<option value="600"' + (String(conf.font_weight) === '600' ? ' selected' : '') + '>600</option>' +
                        '<option value="700"' + (String(conf.font_weight) === '700' ? ' selected' : '') + '>700</option>' +
                        '<option value="800"' + (String(conf.font_weight) === '800' ? ' selected' : '') + '>800</option>' +
                    '</select></td>' +
                    '<td><select name="is_enabled[]" data-field="' + field.name + '" data-key="is_enabled">' +
                        '<option value="1"' + (Number(conf.is_enabled) === 0 ? '' : ' selected') + '>On</option>' +
                        '<option value="0"' + (Number(conf.is_enabled) === 0 ? ' selected' : '') + '>Off</option>' +
                    '</select></td>';
                tableBody.appendChild(tr);
            });
        }

        function refreshField(fieldName) {
            const conf = data.positions[fieldName];
            const node = fieldNodes[fieldName];
            if (!conf || !node) return;
            applyFieldStyles(node, conf);
        }

        function bindTableInputs() {
            if (!tableBody) return;
            tableBody.addEventListener('input', (e) => {
                const t = e.target;
                if (!t || !t.dataset.field || !t.dataset.key) return;
                const field = t.dataset.field;
                const key = t.dataset.key;
                const conf = data.positions[field];
                if (!conf) return;
                conf[key] = (key === 'color' || key === 'align' || key === 'font_weight') ? t.value : Number(t.value || 0);
                refreshField(field);
            });
        }

        function makeDraggable(node) {
            let startX = 0;
            let startY = 0;
            let initX = 0;
            let initY = 0;

            node.addEventListener('mousedown', (e) => {
                e.preventDefault();
                const field = node.dataset.field;
                activeField = field;
                Object.values(fieldNodes).forEach((n) => n.classList.remove('is-active'));
                node.classList.add('is-active');

                const conf = data.positions[field];
                startX = e.clientX;
                startY = e.clientY;
                initX = Number(conf.pos_x) || 0;
                initY = Number(conf.pos_y) || 0;

                function onMove(ev) {
                    const dx = ev.clientX - startX;
                    const dy = ev.clientY - startY;
                    conf.pos_x = Math.max(0, Math.round(initX + dx));
                    conf.pos_y = Math.max(0, Math.round(initY + dy));
                    refreshField(field);
                    const xInput = tableBody.querySelector('input[data-field="' + field + '"][data-key="pos_x"]');
                    const yInput = tableBody.querySelector('input[data-field="' + field + '"][data-key="pos_y"]');
                    if (xInput) xInput.value = conf.pos_x;
                    if (yInput) yInput.value = conf.pos_y;
                }

                function onUp() {
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                }

                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            });
        }

        function bindDrag() {
            Object.values(fieldNodes).forEach((node) => makeDraggable(node));
        }

        renderNodes();
        renderTable();
        bindTableInputs();
        bindDrag();

        if (form) {
            form.addEventListener('submit', () => {
                if (saveBtn) {
                    saveBtn.disabled = true;
                    saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Saving...';
                }
            });
        }

        showToast('Drag fields or edit values, then save positions.', 'success');
    }

    document.addEventListener('DOMContentLoaded', function () {
        initGenerator();
        initTemplateManager();
    });
})();

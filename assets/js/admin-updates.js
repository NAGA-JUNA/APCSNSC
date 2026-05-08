(function () {
    'use strict';

    var shortInput = document.getElementById('shortDescriptionInput');
    var fullInput = document.getElementById('fullDescriptionInput');
    var shortCount = document.getElementById('shortDescCount');
    var fullCount = document.getElementById('fullDescCount');

    function updateCounter(input, target) {
        if (!input || !target) {
            return;
        }
        target.textContent = String(input.value.length);
    }

    if (shortInput) {
        updateCounter(shortInput, shortCount);
        shortInput.addEventListener('input', function () {
            updateCounter(shortInput, shortCount);
        });
    }

    if (fullInput) {
        updateCounter(fullInput, fullCount);
        fullInput.addEventListener('input', function () {
            updateCounter(fullInput, fullCount);
        });
    }

    var fileInput = document.getElementById('updateImageInput');
    var dropZone = document.getElementById('uploadDropZone');
    var imagePreview = document.getElementById('imagePreview');
    var noPreview = document.getElementById('noImagePreview');
    var selectedStrip = document.getElementById('selectedImagesStrip');
    var initialStripHtml = selectedStrip ? selectedStrip.innerHTML : '';

    function renderPreview(file) {
        if (!file || !file.type.match(/^image\//)) {
            return;
        }

        var reader = new FileReader();
        reader.onload = function (event) {
            if (imagePreview) {
                imagePreview.src = String(event.target && event.target.result ? event.target.result : '');
                imagePreview.style.display = 'block';
            }
            if (noPreview) {
                noPreview.style.display = 'none';
            }
        };
        reader.readAsDataURL(file);
    }

    function renderSelectedFiles(files) {
        if (!selectedStrip) {
            return;
        }

        selectedStrip.innerHTML = '';

        if (!files || !files.length) {
            selectedStrip.innerHTML = initialStripHtml;
            return;
        }

        Array.from(files).forEach(function (file, index) {
            if (!file.type.match(/^image\//)) {
                return;
            }

            var url = URL.createObjectURL(file);
            var img = document.createElement('img');
            img.src = url;
            img.alt = 'Selected image ' + (index + 1);
            selectedStrip.appendChild(img);
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            if (fileInput.files && fileInput.files[0]) {
                renderPreview(fileInput.files[0]);
            }
            renderSelectedFiles(fileInput.files || []);
        });
    }

    if (dropZone && fileInput) {
        ['dragenter', 'dragover'].forEach(function (name) {
            dropZone.addEventListener(name, function (event) {
                event.preventDefault();
                dropZone.classList.add('drag-active');
            });
        });

        ['dragleave', 'drop'].forEach(function (name) {
            dropZone.addEventListener(name, function (event) {
                event.preventDefault();
                dropZone.classList.remove('drag-active');
            });
        });

        dropZone.addEventListener('drop', function (event) {
            var files = event.dataTransfer ? event.dataTransfer.files : null;
            if (!files || !files.length) {
                return;
            }

            var dt = new DataTransfer();
            dt.items.add(files[0]);
            fileInput.files = dt.files;
            renderPreview(files[0]);
        });
    }

    var statusInput = document.getElementById('statusInput');
    var publishAtInput = document.getElementById('publishAtInput');
    var scheduleQuickBtn = document.getElementById('scheduleQuickBtn');

    if (scheduleQuickBtn && statusInput && publishAtInput) {
        scheduleQuickBtn.addEventListener('click', function () {
            statusInput.value = 'scheduled';
            if (!publishAtInput.value) {
                var now = new Date();
                now.setHours(now.getHours() + 2);
                var localDate = new Date(now.getTime() - (now.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
                publishAtInput.value = localDate;
            }
            publishAtInput.focus();
            publishAtInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    }

    var searchInput = document.getElementById('updatesSearchInput');
    var categoryFilter = document.getElementById('categoryFilter');
    var statusFilter = document.getElementById('statusFilter');
    var sortFilter = document.getElementById('sortFilter');
    var table = document.getElementById('updatesTable');

    function getRows() {
        if (!table || !table.tBodies.length) {
            return [];
        }
        return Array.prototype.slice.call(table.tBodies[0].rows);
    }

    function sortRows(rows) {
        var sortValue = sortFilter ? sortFilter.value : 'latest';
        rows.sort(function (a, b) {
            var aDate = Number(a.dataset.date || '0');
            var bDate = Number(b.dataset.date || '0');
            var aViews = Number(a.dataset.views || '0');
            var bViews = Number(b.dataset.views || '0');
            var aTitle = String(a.dataset.title || '');
            var bTitle = String(b.dataset.title || '');

            if (sortValue === 'oldest') {
                return aDate - bDate;
            }
            if (sortValue === 'title_asc') {
                return aTitle.localeCompare(bTitle);
            }
            if (sortValue === 'views_desc') {
                return bViews - aViews;
            }
            return bDate - aDate;
        });
    }

    function applyFilters() {
        var rows = getRows();
        if (!rows.length) {
            return;
        }

        var q = searchInput ? searchInput.value.trim().toLowerCase() : '';
        var category = categoryFilter ? categoryFilter.value : '';
        var status = statusFilter ? statusFilter.value : '';

        sortRows(rows);
        rows.forEach(function (row) {
            var title = String(row.dataset.title || '');
            var desc = String(row.dataset.desc || '');
            var rowCategory = String(row.dataset.category || '');
            var rowStatus = String(row.dataset.status || '');

            var matchQuery = !q || title.indexOf(q) !== -1 || desc.indexOf(q) !== -1;
            var matchCategory = !category || rowCategory === category;
            var matchStatus = !status || rowStatus === status;

            row.style.display = (matchQuery && matchCategory && matchStatus) ? '' : 'none';
            if (row.parentElement) {
                row.parentElement.appendChild(row);
            }
        });
    }

    [searchInput, categoryFilter, statusFilter, sortFilter].forEach(function (node) {
        if (node) {
            node.addEventListener('input', applyFilters);
            node.addEventListener('change', applyFilters);
        }
    });

    applyFilters();

    var modalEl = document.getElementById('updatePreviewModal');
    var modal = modalEl && window.bootstrap ? new bootstrap.Modal(modalEl) : null;

    var previewButtons = document.querySelectorAll('.preview-btn');
    var modalImage = document.getElementById('previewModalImage');
    var modalTitle = document.getElementById('previewModalTitle');
    var modalCategory = document.getElementById('previewModalCategory');
    var modalStatus = document.getElementById('previewModalStatus');
    var modalDate = document.getElementById('previewModalDate');
    var modalDescription = document.getElementById('previewModalDescription');

    previewButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!modal) {
                return;
            }

            var image = btn.getAttribute('data-image') || '';
            if (modalImage) {
                if (image) {
                    modalImage.src = image;
                    modalImage.style.display = 'block';
                } else {
                    modalImage.src = '';
                    modalImage.style.display = 'none';
                }
            }

            if (modalTitle) {
                modalTitle.textContent = btn.getAttribute('data-title') || '';
            }
            if (modalCategory) {
                modalCategory.textContent = btn.getAttribute('data-category') || '';
            }
            if (modalStatus) {
                modalStatus.textContent = btn.getAttribute('data-status') || '';
            }
            if (modalDate) {
                modalDate.textContent = btn.getAttribute('data-date') || '';
            }
            if (modalDescription) {
                modalDescription.textContent = btn.getAttribute('data-description') || '';
            }

            modal.show();
        });
    });

    var createUpdateBtn = document.getElementById('createUpdateBtn');
    if (createUpdateBtn) {
        createUpdateBtn.addEventListener('click', function () {
            var formCard = document.getElementById('updateFormCard');
            if (formCard) {
                formCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    }

    var resetBtn = document.getElementById('resetFormBtn');
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            setTimeout(function () {
                updateCounter(shortInput, shortCount);
                updateCounter(fullInput, fullCount);
                if (imagePreview) {
                    imagePreview.style.display = 'none';
                    imagePreview.src = '';
                }
                if (noPreview) {
                    noPreview.style.display = 'grid';
                }
                renderSelectedFiles([]);
            }, 10);
        });
    }
})();

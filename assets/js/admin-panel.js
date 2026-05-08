(function () {
    var body = document.body;
    var menuToggle = document.getElementById('adminMenuToggle');
    var loader = document.getElementById('adminLoader');
    var darkModeToggle = document.getElementById('darkModeToggle');
    var clock = document.getElementById('adminClock');

    if (menuToggle) {
        menuToggle.addEventListener('click', function () {
            body.classList.toggle('admin-menu-open');
        });
    }

    if (loader) {
        var hideLoader = function () {
            loader.classList.remove('is-visible');
        };

        loader.classList.add('is-visible');

        // Hide as soon as DOM is usable; waiting for full window load can stall on slow assets.
        document.addEventListener('DOMContentLoaded', hideLoader);
        window.addEventListener('load', hideLoader);

        // Final fallback so loader never blocks interaction indefinitely.
        setTimeout(hideLoader, 5000);
    }

    var setDarkMode = function (enabled) {
        if (enabled) {
            body.classList.add('admin-dark');
        } else {
            body.classList.remove('admin-dark');
        }

        try {
            localStorage.setItem('APCSNSC_admin_dark_mode', enabled ? '1' : '0');
        } catch (error) {
            // Ignore storage errors in private mode.
        }

        if (darkModeToggle) {
            darkModeToggle.innerHTML = enabled
                ? '<i class="fa-solid fa-sun"></i>'
                : '<i class="fa-solid fa-moon"></i>';
        }
    };

    var darkSaved = null;
    try {
        darkSaved = localStorage.getItem('APCSNSC_admin_dark_mode');
    } catch (error) {
        darkSaved = null;
    }

    setDarkMode(darkSaved === '1');

    if (darkModeToggle) {
        darkModeToggle.addEventListener('click', function () {
            setDarkMode(!body.classList.contains('admin-dark'));
        });
    }

    if (clock) {
        var tick = function () {
            var now = new Date();
            clock.textContent = now.toLocaleTimeString('en-IN', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
        };
        tick();
        setInterval(tick, 1000);
    }

    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.DataTable) {
        window.jQuery('[data-admin-datatable]').each(function () {
            var table = window.jQuery(this);
            if (table.hasClass('dataTable')) {
                return;
            }

            table.DataTable({
                responsive: true,
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [],
                dom: "Bfrtip",
                buttons: [
                    {
                        extend: 'excelHtml5',
                        title: 'APCSNSC Export'
                    },
                    {
                        extend: 'pdfHtml5',
                        title: 'APCSNSC Export'
                    },
                    {
                        extend: 'print',
                        title: 'APCSNSC Export'
                    }
                ]
            });
        });
    }

    document.querySelectorAll('[data-confirm-delete]').forEach(function (element) {
        element.addEventListener('click', function (event) {
            event.preventDefault();
            var link = element.getAttribute('href');
            if (!link) {
                return;
            }

            if (window.Swal) {
                window.Swal.fire({
                    title: 'Are you sure?',
                    text: 'This action cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Yes, delete it'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        window.location.href = link;
                    }
                });
            } else if (window.confirm('Delete this record?')) {
                window.location.href = link;
            }
        });
    });

    document.querySelectorAll('[data-export-table]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tableId = btn.getAttribute('data-export-table');
            if (!tableId) {
                return;
            }

            var table = document.getElementById(tableId);
            if (!table || !window.XLSX) {
                return;
            }

            var workbook = window.XLSX.utils.table_to_book(table, { sheet: 'APCSNSC' });
            window.XLSX.writeFile(workbook, 'APCSNSC_export.xlsx');
        });
    });
})();

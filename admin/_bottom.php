    </section>
</div>

<?php
$adminJs = __DIR__ . '/../assets/js/admin-panel.js';
$adminJsVer = file_exists($adminJs) ? (string)filemtime($adminJs) : (string)time();
?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= esc(base_url('assets/js/admin-panel.js?v=' . $adminJsVer)); ?>"></script>
<?php if (!empty($pageScripts) && is_array($pageScripts)): ?>
    <?php foreach ($pageScripts as $scriptSrc): ?>
        <script src="<?= esc((string)$scriptSrc); ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        try {
            var activeLink = document.querySelector('.admin-sidebar nav a.active');
            if (activeLink) {
                // Scroll the sidebar so the active item is visible and centered
                activeLink.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }
        } catch (e) {
            // fail silently
            console && console.warn && console.warn('Sidebar scroll error', e);
        }
    });
</script>
</body>
</html>

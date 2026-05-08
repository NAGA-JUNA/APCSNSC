<?php
require_once __DIR__ . '/../db.php';
require_admin();

$pageTitle = 'Add New Member';
$activeMenu = 'add-member';

$districtCodes = [
    'Anantapur' => 'ATP',
    'Kurnool' => 'KNL',
    'Nandyal' => 'NDL',
    'Kadapa' => 'KDP',
    'Chittoor' => 'CTR',
    'Tirupati' => 'TPT',
    'Nellore' => 'NLR',
    'Prakasam' => 'PKM',
    'Guntur' => 'GNT',
    'Bapatla' => 'BPT',
    'Palnadu' => 'PLD',
    'Krishna' => 'KRI',
    'NTR' => 'NTR',
    'Eluru' => 'ELR',
    'West Godavari' => 'WGD',
    'East Godavari' => 'EGD',
    'Kakinada' => 'KAK',
    'Konaseema' => 'KNS',
    'Vizianagaram' => 'VZM',
    'Visakhapatnam' => 'VZG',
    'Anakapalli' => 'AKP',
    'Alluri Sitharama Raju' => 'ASR',
    'Srikakulam' => 'SKM',
];

$formError = get_flash('error');

require_once __DIR__ . '/_top.php';
?>

<?php if ($formError): ?>
    <div class="alert alert-danger"><?= esc($formError); ?></div>
<?php endif; ?>

<section class="admin-card">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h4 class="mb-1">New Member Registration</h4>
            <p class="text-secondary mb-0">Member ID is auto-generated based on selected district.</p>
        </div>
        <a class="btn btn-outline-primary" href="<?= esc(base_url('admin/members.php')); ?>"><i class="fa-solid fa-list me-1"></i>View Members</a>
    </div>

    <form id="memberAddForm" method="post" action="<?= esc(base_url('admin/save_member.php')); ?>" enctype="multipart/form-data" class="row g-3">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">

        <div class="col-md-6">
            <label class="form-label">Full Name</label>
            <input type="text" class="form-control" name="full_name" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">District</label>
            <select class="form-select" name="district" id="districtSelect" required>
                <option value="">Select District</option>
                <?php foreach ($districtCodes as $district => $code): ?>
                    <option value="<?= esc($district); ?>" data-code="<?= esc($code); ?>"><?= esc($district); ?> (<?= esc($code); ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label">Member ID</label>
            <div class="input-group">
                <input type="text" class="form-control" name="member_id" id="memberIdInput" readonly required>
                <button class="btn btn-outline-secondary" type="button" id="memberIdEditBtn" title="Manual edit"><i class="fa-solid fa-pen"></i></button>
                <button class="btn btn-outline-primary" type="button" id="memberIdRegenerateBtn"><i class="fa-solid fa-rotate"></i> Regenerate</button>
            </div>
            <div class="form-text">Auto-generated based on district selection.</div>
            <div class="invalid-feedback d-block" id="memberIdWarning" style="display:none !important;"></div>
        </div>

        <div class="col-md-6">
            <label class="form-label">Phone</label>
            <input type="text" class="form-control" name="phone" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Qualification</label>
            <input type="text" class="form-control" name="qualification" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Designation</label>
            <input type="text" class="form-control" name="designation" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Working PHC</label>
            <input type="text" class="form-control" name="working_place" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Photo Upload</label>
            <input type="file" class="form-control" name="photo" accept="image/jpeg,image/png,image/webp">
        </div>

        <div class="col-12 d-flex flex-wrap gap-2 pt-2">
            <button type="submit" class="gradient-submit" id="saveMemberBtn"><i class="fa-solid fa-floppy-disk me-1"></i>Save Member</button>
            <a class="btn btn-outline-secondary" href="<?= esc(base_url('admin/members.php')); ?>">Cancel</a>
        </div>
    </form>
</section>

<script>
    (function () {
        var districtSelect = document.getElementById('districtSelect');
        var memberIdInput = document.getElementById('memberIdInput');
        var editBtn = document.getElementById('memberIdEditBtn');
        var regenerateBtn = document.getElementById('memberIdRegenerateBtn');
        var warning = document.getElementById('memberIdWarning');
        var form = document.getElementById('memberAddForm');
        var saveBtn = document.getElementById('saveMemberBtn');

        var duplicateFound = false;
        var manualMode = false;
        var duplicateTimer = null;

        var setWarning = function (message) {
            if (!warning) {
                return;
            }

            if (message) {
                warning.textContent = message;
                warning.style.display = 'block';
                memberIdInput.classList.add('is-invalid');
            } else {
                warning.textContent = '';
                warning.style.display = 'none';
                memberIdInput.classList.remove('is-invalid');
            }
        };

        var setSaveEnabled = function (enabled) {
            if (saveBtn) {
                saveBtn.disabled = !enabled;
            }
        };

        var checkDuplicate = function () {
            var value = memberIdInput.value.trim();
            if (!value) {
                duplicateFound = false;
                setWarning('');
                setSaveEnabled(true);
                return;
            }

            fetch('<?= esc(base_url('admin/ajax_check_member_id.php')); ?>?member_id=' + encodeURIComponent(value), {
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    duplicateFound = !!(data && data.exists);
                    if (duplicateFound) {
                        setWarning('Member ID already exists. Please regenerate or edit.');
                        setSaveEnabled(false);
                    } else {
                        setWarning('');
                        setSaveEnabled(true);
                    }
                })
                .catch(function () {
                    duplicateFound = true;
                    setWarning('Could not validate Member ID. Try again.');
                    setSaveEnabled(false);
                });
        };

        var generateId = function () {
            var district = districtSelect.value;
            if (!district) {
                memberIdInput.value = '';
                duplicateFound = false;
                setWarning('');
                setSaveEnabled(true);
                return;
            }

            fetch('<?= esc(base_url('admin/ajax_generate_member_id.php')); ?>?district=' + encodeURIComponent(district), {
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (!data || !data.member_id) {
                        throw new Error('Invalid response');
                    }

                    memberIdInput.value = String(data.member_id);
                    memberIdInput.readOnly = true;
                    manualMode = false;
                    checkDuplicate();
                })
                .catch(function () {
                    setWarning('Unable to generate Member ID.');
                    setSaveEnabled(false);
                });
        };

        if (districtSelect) {
            districtSelect.addEventListener('change', function () {
                generateId();
            });
        }

        if (editBtn) {
            editBtn.addEventListener('click', function () {
                manualMode = true;
                memberIdInput.readOnly = false;
                memberIdInput.focus();
            });
        }

        if (regenerateBtn) {
            regenerateBtn.addEventListener('click', function () {
                generateId();
            });
        }

        if (memberIdInput) {
            memberIdInput.addEventListener('input', function () {
                if (duplicateTimer) {
                    clearTimeout(duplicateTimer);
                }

                duplicateTimer = setTimeout(function () {
                    checkDuplicate();
                }, 260);
            });
        }

        if (form) {
            form.addEventListener('submit', function (event) {
                if (duplicateFound) {
                    event.preventDefault();
                    setWarning('Cannot save. Member ID already exists.');
                    return;
                }

                if (!memberIdInput.value.trim()) {
                    event.preventDefault();
                    setWarning('Please generate Member ID first.');
                    return;
                }

                if (!districtSelect.value) {
                    event.preventDefault();
                    setWarning('Please select district for Member ID generation.');
                }
            });
        }
    })();
</script>

<?php require_once __DIR__ . '/_bottom.php'; ?>

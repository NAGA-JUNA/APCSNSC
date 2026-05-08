<?php
require_once __DIR__ . '/../db.php';
require_admin();

$isDistrictScoped = is_district_president() && admin_district() !== '';
$scopedDistrict = admin_district();

$canAccessMember = static function (int $memberId) use ($isDistrictScoped): bool {
    if (!$isDistrictScoped || $memberId <= 0) {
        return true;
    }

    $row = fetch_one('SELECT district FROM members WHERE id = :id LIMIT 1', [':id' => $memberId]);
    if (!$row) {
        return false;
    }

    return admin_can_access_district((string)($row['district'] ?? ''));
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'update_member') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Invalid request token. Please refresh and try again.');
        redirect_to('admin/members.php');
    }

    $memberId = (int)($_POST['member_id'] ?? 0);
    $fullName = clean((string)($_POST['full_name'] ?? ''));
    $memberCode = clean((string)($_POST['member_code'] ?? ''));
    $district = clean((string)($_POST['district'] ?? ''));
    $phone = clean((string)($_POST['phone'] ?? ''));
    $qualification = clean((string)($_POST['qualification'] ?? ''));
    $designation = clean((string)($_POST['designation'] ?? ''));
    $memberRole = clean((string)($_POST['member_role'] ?? ''));
    $workingPlace = clean((string)($_POST['working_place'] ?? ''));
    $status = strtolower(clean((string)($_POST['status'] ?? '')));

    if ($memberId <= 0 || $fullName === '' || $district === '' || $phone === '' || $qualification === '' || $designation === '' || $workingPlace === '') {
        set_flash('error', 'Please fill all required edit fields.');
        redirect_to('admin/members.php');
    }

    if (!$canAccessMember($memberId)) {
        set_flash('error', 'You can only edit members from your assigned district.');
        redirect_to('admin/members.php');
    }

    if ($isDistrictScoped) {
        $district = $scopedDistrict;
    }

    $allowedStatus = ['pending', 'approved', 'active', 'rejected', 'inactive'];
    if (!in_array($status, $allowedStatus, true)) {
        $status = 'pending';
    }

    $photoPath = upload_image($_FILES['photo'] ?? [], 'uploads/members/photos');

    $columns = fetch_all('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "members"');
    $columnSet = [];
    foreach ($columns as $col) {
        $columnSet[(string)$col['COLUMN_NAME']] = true;
    }

    $updates = [];
    $params = [':id' => $memberId];

    if (isset($columnSet['member_id']) && $memberCode !== '') {
        $updates[] = 'member_id = :member_code';
        $params[':member_code'] = $memberCode;
    }
    if (isset($columnSet['full_name'])) {
        $updates[] = 'full_name = :full_name';
        $params[':full_name'] = $fullName;
    }
    if (isset($columnSet['name'])) {
        $updates[] = 'name = :name';
        $params[':name'] = $fullName;
    }
    if (isset($columnSet['district'])) {
        $updates[] = 'district = :district';
        $params[':district'] = $district;
    }
    if (isset($columnSet['phone'])) {
        $updates[] = 'phone = :phone';
        $params[':phone'] = $phone;
    }
    if (isset($columnSet['mobile'])) {
        $updates[] = 'mobile = :mobile';
        $params[':mobile'] = $phone;
    }
    if (isset($columnSet['qualification'])) {
        $updates[] = 'qualification = :qualification';
        $params[':qualification'] = $qualification;
    }
    if (isset($columnSet['designation'])) {
        $updates[] = 'designation = :designation';
        $params[':designation'] = $designation;
    }
    if (isset($columnSet['role'])) {
        $updates[] = 'role = :role';
        $params[':role'] = $memberRole !== '' ? $memberRole : $designation;
    }
    if (isset($columnSet['working_place'])) {
        $updates[] = 'working_place = :working_place';
        $params[':working_place'] = $workingPlace;
    }
    if (isset($columnSet['hospital'])) {
        $updates[] = 'hospital = :hospital';
        $params[':hospital'] = $workingPlace;
    }
    if (isset($columnSet['status'])) {
        $updates[] = 'status = :status';
        $params[':status'] = $status;
    }
    if ($photoPath !== null && isset($columnSet['photo'])) {
        $updates[] = 'photo = :photo';
        $params[':photo'] = $photoPath;
    }

    if ($updates === []) {
        set_flash('error', 'No editable columns found for update.');
        redirect_to('admin/members.php');
    }

    execute_query('UPDATE members SET ' . implode(', ', $updates) . ' WHERE id = :id', $params);
    set_flash('success', 'Member updated successfully.');
    redirect_to('admin/members.php');
}

if (isset($_GET['approve'])) {
    if (verify_csrf($_GET['token'] ?? null)) {
        $approveId = (int)$_GET['approve'];
        if ($canAccessMember($approveId)) {
            execute_query("UPDATE members SET status = 'approved' WHERE id = :id", [':id' => $approveId]);
        } else {
            set_flash('error', 'You can only approve members from your assigned district.');
        }
    }
    redirect_to('admin/members.php');
}

if (isset($_GET['reject'])) {
    if (verify_csrf($_GET['token'] ?? null)) {
        $rejectId = (int)$_GET['reject'];
        if ($canAccessMember($rejectId)) {
            execute_query("UPDATE members SET status = 'rejected' WHERE id = :id", [':id' => $rejectId]);
        } else {
            set_flash('error', 'You can only reject members from your assigned district.');
        }
    }
    redirect_to('admin/members.php');
}

if (isset($_GET['delete'])) {
    if (verify_csrf($_GET['token'] ?? null)) {
        $deleteId = (int)$_GET['delete'];
        if ($canAccessMember($deleteId)) {
            execute_query('DELETE FROM members WHERE id = :id', [':id' => $deleteId]);
        } else {
            set_flash('error', 'You can only delete members from your assigned district.');
        }
    }
    redirect_to('admin/members.php');
}

$search = trim((string)($_GET['search'] ?? ''));
$districtFilter = trim((string)($_GET['district'] ?? ''));
$qualificationFilter = trim((string)($_GET['qualification'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(name LIKE :search OR member_id LIKE :search OR phone LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

if ($districtFilter !== '') {
    $where[] = 'district = :district';
    $params[':district'] = $districtFilter;
}

if ($statusFilter !== '') {
    $where[] = 'status = :status';
    $params[':status'] = $statusFilter;
}

if ($qualificationFilter !== '') {
    $where[] = '(qualification = :qualification OR role = :qualification)';
    $params[':qualification'] = $qualificationFilter;
}

if ($isDistrictScoped) {
    $where[] = 'district = :scope_district';
    $params[':scope_district'] = $scopedDistrict;
    $districtFilter = $scopedDistrict;
}

$sql = 'SELECT * FROM members';
if ($where !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY created_at DESC';

$members = fetch_all($sql, $params);

$districtOptions = fetch_all('SELECT DISTINCT district FROM members WHERE district IS NOT NULL AND district <> "" ORDER BY district ASC');
$qualificationOptions = fetch_all('SELECT DISTINCT COALESCE(NULLIF(qualification, ""), role) AS qualification_name FROM members ORDER BY qualification_name ASC');
$successFlash = get_flash('success');
$errorFlash = get_flash('error');

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

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=APCSNSC_members_export.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Full Name', 'Member ID', 'Qualification', 'Designation', 'Hospital', 'District', 'Phone', 'Status']);

    foreach ($members as $member) {
        fputcsv($output, [
            $member['name'] ?? '',
            $member['member_id'] ?? '',
            $member['qualification'] ?? ($member['role'] ?? ''),
            $member['designation'] ?? ($member['role'] ?? ''),
            $member['hospital'] ?? '',
            $member['district'] ?? '',
            $member['phone'] ?? '',
            $member['status'] ?? '',
        ]);
    }

    fclose($output);
    exit;
}

$pageTitle = 'Members Management';
$activeMenu = 'members';
require_once __DIR__ . '/_top.php';
?>

<?php if ($successFlash || $errorFlash): ?>
    <div class="position-fixed top-0 end-0 p-3" style="z-index: 1090;">
        <div id="memberToast" class="toast align-items-center text-bg-<?= $errorFlash ? 'danger' : 'success'; ?> border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <?= esc((string)($errorFlash ?: $successFlash)); ?>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
    <script>
        (function () {
            var el = document.getElementById('memberToast');
            if (!el || typeof bootstrap === 'undefined' || !bootstrap.Toast) {
                return;
            }
            var t = new bootstrap.Toast(el, { delay: 3600 });
            t.show();
        })();
    </script>
<?php endif; ?>

<section class="admin-card" style="margin-bottom: 12px;">
    <form method="get" class="filters-wrap">
        <input class="form-control" type="text" name="search" placeholder="Search by name/member id" value="<?= esc($search); ?>">

        <select class="form-select" name="district">
            <option value="">All Districts</option>
            <?php foreach ($districtOptions as $option): ?>
                <?php $dist = (string)($option['district'] ?? ''); ?>
                <option value="<?= esc($dist); ?>" <?= $districtFilter === $dist ? 'selected' : ''; ?>><?= esc($dist); ?></option>
            <?php endforeach; ?>
        </select>

        <select class="form-select" name="qualification">
            <option value="">All Qualifications</option>
            <?php foreach ($qualificationOptions as $option): ?>
                <?php $qualification = (string)($option['qualification_name'] ?? ''); ?>
                <?php if ($qualification === '') { continue; } ?>
                <option value="<?= esc($qualification); ?>" <?= $qualificationFilter === $qualification ? 'selected' : ''; ?>><?= esc($qualification); ?></option>
            <?php endforeach; ?>
        </select>

        <select class="form-select" name="status">
            <option value="">All Status</option>
            <?php foreach (['pending', 'approved', 'active', 'rejected', 'inactive'] as $status): ?>
                <option value="<?= esc($status); ?>" <?= $statusFilter === $status ? 'selected' : ''; ?>><?= esc(ucfirst($status)); ?></option>
            <?php endforeach; ?>
        </select>

        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter me-1"></i>Filter</button>

        <div class="d-flex gap-2">
            <a class="btn btn-outline-success" href="?<?= esc(http_build_query(array_merge($_GET, ['export' => 'excel']))); ?>"><i class="fa-solid fa-file-excel me-1"></i>Export Excel</a>
            <a class="btn btn-success" href="<?= esc(base_url('admin/add_member.php')); ?>"><i class="fa-solid fa-user-plus me-1"></i>Add Member</a>
        </div>
    </form>
</section>

<section class="admin-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle" id="membersTable" data-admin-datatable>
            <thead>
                <tr>
                    <th>Photo</th>
                    <th>Full Name</th>
                    <th>Member ID</th>
                    <th>Qualification</th>
                    <th>Designation</th>
                    <th>Role</th>
                    <th>PHC/Hospital</th>
                    <th>District</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>ID Card</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $member): ?>
                    <?php
                    $status = strtolower((string)($member['status'] ?? 'pending'));
                    $badgeClass = 'warning';
                    if ($status === 'approved' || $status === 'active') {
                        $badgeClass = 'success';
                    } elseif ($status === 'rejected') {
                        $badgeClass = 'danger';
                    }

                    $photoPath = !empty($member['photo']) ? base_url((string)$member['photo']) : base_url('uploads/default-avatar.png');
                    ?>
                    <tr>
                        <td><img src="<?= esc($photoPath); ?>" alt="Photo" class="avatar-mini"></td>
                        <td><?= esc((string)($member['name'] ?? '-')); ?></td>
                        <td><?= esc((string)($member['member_id'] ?? '-')); ?></td>
                        <td><?= esc((string)($member['qualification'] ?? ($member['role'] ?? '-'))); ?></td>
                        <td><?= esc((string)($member['designation'] ?? ($member['role'] ?? '-'))); ?></td>
                        <td><span class="role-pill"><?= esc((string)($member['role'] ?? ($member['designation'] ?? '-'))); ?></span></td>
                        <td><?= esc((string)($member['hospital'] ?? '-')); ?></td>
                        <td><?= esc((string)($member['district'] ?? '-')); ?></td>
                        <td><?= esc((string)($member['phone'] ?? '-')); ?></td>
                        <td><span class="badge-soft <?= esc($badgeClass); ?>"><?= esc(ucfirst($status)); ?></span></td>
                        <td>
                            <a class="btn btn-sm btn-outline-primary" href="<?= esc(base_url('admin/id_cards.php?member=' . (int)$member['id'])); ?>">ID Card</a>
                        </td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <a class="btn btn-sm btn-outline-success" href="?approve=<?= (int)$member['id']; ?>&token=<?= esc(csrf_token()); ?>">Approve</a>
                                <a class="btn btn-sm btn-outline-warning" href="?reject=<?= (int)$member['id']; ?>&token=<?= esc(csrf_token()); ?>">Reject</a>
                                <?php
                                $editPayload = [
                                    'id' => (int)$member['id'],
                                    'member_id' => (string)($member['member_id'] ?? ''),
                                    'full_name' => (string)($member['full_name'] ?? ($member['name'] ?? '')),
                                    'district' => (string)($member['district'] ?? ''),
                                    'phone' => (string)($member['phone'] ?? ($member['mobile'] ?? '')),
                                    'qualification' => (string)($member['qualification'] ?? ''),
                                    'designation' => (string)($member['designation'] ?? ($member['role'] ?? '')),
                                    'member_role' => (string)($member['role'] ?? ''),
                                    'working_place' => (string)($member['working_place'] ?? ($member['hospital'] ?? '')),
                                    'status' => (string)($member['status'] ?? 'pending'),
                                ];
                                ?>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-info js-edit-member"
                                    data-bs-toggle="modal"
                                    data-bs-target="#memberEditModal"
                                    data-member='<?= esc(json_encode($editPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?>'>Edit</button>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= esc(base_url('admin/id_cards.php?member=' . (int)$member['id'])); ?>">View</a>
                                <a class="btn btn-sm btn-outline-danger" data-confirm-delete href="?delete=<?= (int)$member['id']; ?>&token=<?= esc(csrf_token()); ?>">Delete</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="modal fade" id="memberEditModal" tabindex="-1" aria-labelledby="memberEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
                <input type="hidden" name="action" value="update_member">
                <input type="hidden" name="member_id" id="editMemberId" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="memberEditModalLabel">Edit Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name" id="editFullName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Member ID</label>
                            <input type="text" class="form-control" name="member_code" id="editMemberCode" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">District</label>
                            <select class="form-select" name="district" id="editDistrict" required>
                                <option value="">Select District</option>
                                <?php foreach ($districtCodes as $district => $code): ?>
                                    <option value="<?= esc($district); ?>"><?= esc($district); ?> (<?= esc($code); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone" id="editPhone" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Qualification</label>
                            <input type="text" class="form-control" name="qualification" id="editQualification" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Designation</label>
                            <input type="text" class="form-control" name="designation" id="editDesignation" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Member Role</label>
                            <select class="form-select" name="member_role" id="editMemberRole">
                                <option value="">Use designation as role</option>
                                <option value="member">Member</option>
                                <option value="district_coordinator">District Coordinator</option>
                                <option value="state_coordinator">State Coordinator</option>
                                <option value="union_leader">Union Leader</option>
                                <option value="president">President</option>
                                <option value="secretary">Secretary</option>
                                <option value="treasurer">Treasurer</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Working PHC/Hospital</label>
                            <input type="text" class="form-control" name="working_place" id="editWorkingPlace" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="editStatus" required>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="active">Active</option>
                                <option value="rejected">Rejected</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Change Photo (optional)</label>
                            <input type="file" class="form-control" name="photo" accept="image/jpeg,image/png,image/webp">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        var modal = document.getElementById('memberEditModal');
        if (!modal) {
            return;
        }

        var idInput = document.getElementById('editMemberId');
        var memberCodeInput = document.getElementById('editMemberCode');
        var fullNameInput = document.getElementById('editFullName');
        var districtInput = document.getElementById('editDistrict');
        var phoneInput = document.getElementById('editPhone');
        var qualificationInput = document.getElementById('editQualification');
        var designationInput = document.getElementById('editDesignation');
        var memberRoleInput = document.getElementById('editMemberRole');
        var workingPlaceInput = document.getElementById('editWorkingPlace');
        var statusInput = document.getElementById('editStatus');

        document.querySelectorAll('.js-edit-member').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var raw = btn.getAttribute('data-member');
                if (!raw) {
                    return;
                }

                var data;
                try {
                    data = JSON.parse(raw);
                } catch (e) {
                    return;
                }

                idInput.value = data.id || '';
                memberCodeInput.value = data.member_id || '';
                fullNameInput.value = data.full_name || '';
                districtInput.value = data.district || '';
                phoneInput.value = data.phone || '';
                qualificationInput.value = data.qualification || '';
                designationInput.value = data.designation || '';
                memberRoleInput.value = data.member_role || '';
                workingPlaceInput.value = data.working_place || '';
                statusInput.value = (data.status || 'pending').toLowerCase();
            });
        });
    })();
</script>

<?php require_once __DIR__ . '/_bottom.php'; ?>

<?php
require_once __DIR__ . '/db.php';

$success = null;
$error = null;
$generatedMemberId = null;

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

$generateMemberId = static function (PDO $pdo, string $district, array $map): string {
    $prefix = 'APCSNSC';
    $districtCode = $map[$district] ?? null;

    if ($districtCode === null) {
        throw new RuntimeException('Invalid district selected.');
    }

    $stmt = $pdo->prepare('SELECT member_id FROM members WHERE member_id LIKE :pattern ORDER BY member_id DESC LIMIT 1');
    $stmt->execute([
        ':pattern' => $prefix . '-' . $districtCode . '-%',
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $next = 1;
    if ($row && isset($row['member_id']) && preg_match('/(\d{5})$/', (string)$row['member_id'], $match)) {
        $next = ((int)$match[1]) + 1;
    }

    return $prefix . '-' . $districtCode . '-' . sprintf('%05d', $next);
};

$fields = [
    'full_name' => '',
    'father_name' => '',
    'dob' => '',
    'gender' => '',
    'blood_group' => '',
    'aadhaar' => '',
    'phone' => '',
    'mobile_alt' => '',
    'email' => '',
    'address' => '',
    'qualification' => '',
    'designation' => 'Staff Nurse',
    'registration_number' => '',
    'experience' => '',
    'employee_id' => '',
    'district' => '',
    'mandal' => '',
    'village' => '',
    'hospital' => '',
    'department' => '',
    'shift_type' => '',
    'joining_date' => '',
    'expiry_date' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request token. Please refresh and try again.';
    }

    foreach (array_keys($fields) as $key) {
        $fields[$key] = clean((string)($_POST[$key] ?? ''));
    }

    $fields['phone'] = preg_replace('/[^0-9]/', '', (string)$fields['phone']);
    $fields['mobile_alt'] = preg_replace('/[^0-9]/', '', (string)$fields['mobile_alt']);

    if ($error === null) {
        $required = ['full_name', 'district', 'phone', 'qualification', 'designation', 'hospital'];
        foreach ($required as $requiredKey) {
            if ($fields[$requiredKey] === '') {
                $error = 'Please fill all required fields with valid details.';
                break;
            }
        }
    }

    if ($error === null && strlen($fields['phone']) < 10) {
        $error = 'Please enter a valid mobile number.';
    }

    if ($error === null && !isset($districtCodes[$fields['district']])) {
        $error = 'Please select a valid district.';
    }

    if ($error === null) {
        $pdo = db();

        $memberId = $generateMemberId($pdo, $fields['district'], $districtCodes);
        $photoPath = upload_image($_FILES['photo'] ?? [], 'uploads/members/photos');
        $signaturePath = upload_image($_FILES['signature'] ?? [], 'uploads/members/signatures');
        $documentsPath = upload_image($_FILES['documents'] ?? [], 'uploads/members/documents');

        $columns = fetch_all('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "members"');
        $columnSet = [];
        foreach ($columns as $column) {
            $columnSet[(string)$column['COLUMN_NAME']] = true;
        }

        $data = [];
        if (isset($columnSet['member_id'])) {
            $data['member_id'] = $memberId;
        }
        if (isset($columnSet['name'])) {
            $data['name'] = $fields['full_name'];
        }
        if (isset($columnSet['full_name'])) {
            $data['full_name'] = $fields['full_name'];
        }
        if (isset($columnSet['father_name'])) {
            $data['father_name'] = $fields['father_name'];
        }
        if (isset($columnSet['dob'])) {
            $data['dob'] = $fields['dob'] !== '' ? $fields['dob'] : null;
        }
        if (isset($columnSet['gender'])) {
            $data['gender'] = $fields['gender'];
        }
        if (isset($columnSet['blood_group'])) {
            $data['blood_group'] = $fields['blood_group'];
        }
        if (isset($columnSet['aadhaar'])) {
            $data['aadhaar'] = $fields['aadhaar'];
        }
        if (isset($columnSet['phone'])) {
            $data['phone'] = $fields['phone'];
        }
        if (isset($columnSet['mobile_alt'])) {
            $data['mobile_alt'] = $fields['mobile_alt'];
        }
        if (isset($columnSet['email'])) {
            $data['email'] = $fields['email'];
        }
        if (isset($columnSet['address'])) {
            $data['address'] = $fields['address'];
        }
        if (isset($columnSet['qualification'])) {
            $data['qualification'] = $fields['qualification'];
        }
        if (isset($columnSet['designation'])) {
            $data['designation'] = $fields['designation'];
        }
        if (isset($columnSet['registration_number'])) {
            $data['registration_number'] = $fields['registration_number'];
        }
        if (isset($columnSet['experience'])) {
            $data['experience'] = $fields['experience'];
        }
        if (isset($columnSet['employee_id'])) {
            $data['employee_id'] = $fields['employee_id'];
        }
        if (isset($columnSet['district'])) {
            $data['district'] = $fields['district'];
        }
        if (isset($columnSet['mandal'])) {
            $data['mandal'] = $fields['mandal'];
        }
        if (isset($columnSet['village'])) {
            $data['village'] = $fields['village'];
        }
        if (isset($columnSet['hospital'])) {
            $data['hospital'] = $fields['hospital'];
        }
        if (isset($columnSet['working_place'])) {
            $data['working_place'] = $fields['hospital'];
        }
        if (isset($columnSet['department'])) {
            $data['department'] = $fields['department'];
        }
        if (isset($columnSet['shift_type'])) {
            $data['shift_type'] = $fields['shift_type'];
        }
        if (isset($columnSet['joining_date'])) {
            $data['joining_date'] = $fields['joining_date'] !== '' ? $fields['joining_date'] : null;
        }
        if (isset($columnSet['expiry_date'])) {
            $data['expiry_date'] = $fields['expiry_date'] !== '' ? $fields['expiry_date'] : null;
        }
        if (isset($columnSet['status'])) {
            $data['status'] = 'pending';
        }
        if (isset($columnSet['role'])) {
            $data['role'] = $fields['designation'];
        }
        if (isset($columnSet['photo'])) {
            $data['photo'] = $photoPath;
        }
        if (isset($columnSet['signature'])) {
            $data['signature'] = $signaturePath;
        }
        if (isset($columnSet['documents'])) {
            $data['documents'] = $documentsPath;
        }

        try {
            $pdo->beginTransaction();

            $placeholders = [];
            foreach (array_keys($data) as $key) {
                $placeholders[] = ':' . $key;
            }

            $sql = 'INSERT INTO members (' . implode(', ', array_keys($data)) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $stmt = $pdo->prepare($sql);
            foreach ($data as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->execute();

            $pdo->commit();
            $success = 'Registration submitted successfully. Your Member ID: ' . $memberId . ' (status: pending approval).';
            $generatedMemberId = $memberId;
            $fields = array_map(static fn() => '', $fields);
            $fields['designation'] = 'Staff Nurse';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Could not save registration. ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/header.php';
?>

<section class="container page-hero fade-in">
    <h1>Join APCSNSC</h1>
    <p>Register as a member with complete personal, professional, and working details.</p>
</section>

<section class="section">
    <div class="container">
        <div class="card fade-in">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <h2>Membership Registration Form</h2>
                    <p class="muted mb-0">Please provide accurate information for APCSNSC member verification and ID card generation.</p>
                </div>
                <?php if ($generatedMemberId): ?>
                    <div class="badge-soft success">Member ID: <?= esc($generatedMemberId); ?></div>
                <?php endif; ?>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= esc($success); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= esc($error); ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="admin-registration-form">
                <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">

                <div class="section-block">
                    <h4 class="form-section-title">A. Personal Details</h4>
                    <div class="form-grid">
                        <div class="form-group full">
                            <label>Full Name *</label>
                            <input type="text" name="full_name" value="<?= esc($fields['full_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Father/Husband Name</label>
                            <input type="text" name="father_name" value="<?= esc($fields['father_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" name="dob" value="<?= esc($fields['dob']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Gender</label>
                            <select name="gender">
                                <option value="">Select</option>
                                <option value="Female" <?= $fields['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Male" <?= $fields['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Other" <?= $fields['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Blood Group</label>
                            <input type="text" name="blood_group" value="<?= esc($fields['blood_group']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Aadhaar</label>
                            <input type="text" name="aadhaar" value="<?= esc($fields['aadhaar']); ?>">
                        </div>
                    </div>
                </div>

                <div class="section-block">
                    <h4 class="form-section-title">B. Contact Details</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Mobile *</label>
                            <input type="tel" name="phone" value="<?= esc($fields['phone']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Alternate Mobile</label>
                            <input type="tel" name="mobile_alt" value="<?= esc($fields['mobile_alt']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?= esc($fields['email']); ?>">
                        </div>
                        <div class="form-group full">
                            <label>Address</label>
                            <textarea name="address"><?= esc($fields['address']); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="section-block">
                    <h4 class="form-section-title">C. Professional Details</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Qualification *</label>
                            <input type="text" name="qualification" value="<?= esc($fields['qualification']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Designation *</label>
                            <input type="text" name="designation" value="<?= esc($fields['designation']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Registration Number</label>
                            <input type="text" name="registration_number" value="<?= esc($fields['registration_number']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Experience</label>
                            <input type="text" name="experience" value="<?= esc($fields['experience']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Employee ID</label>
                            <input type="text" name="employee_id" value="<?= esc($fields['employee_id']); ?>">
                        </div>
                    </div>
                </div>

                <div class="section-block">
                    <h4 class="form-section-title">D. Working Details</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>District *</label>
                            <select name="district" required>
                                <option value="">Select District</option>
                                <?php foreach ($districtCodes as $district => $code): ?>
                                    <option value="<?= esc($district); ?>" <?= $fields['district'] === $district ? 'selected' : ''; ?>><?= esc($district); ?> (<?= esc($code); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Mandal</label>
                            <input type="text" name="mandal" value="<?= esc($fields['mandal']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Village</label>
                            <input type="text" name="village" value="<?= esc($fields['village']); ?>">
                        </div>
                        <div class="form-group full">
                            <label>PHC / CHC / Hospital Name *</label>
                            <input type="text" name="hospital" value="<?= esc($fields['hospital']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Department</label>
                            <input type="text" name="department" value="<?= esc($fields['department']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Shift Type</label>
                            <input type="text" name="shift_type" value="<?= esc($fields['shift_type']); ?>">
                        </div>
                    </div>
                </div>

                <div class="section-block">
                    <h4 class="form-section-title">E. Membership Details</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Joining Date</label>
                            <input type="date" name="joining_date" value="<?= esc($fields['joining_date']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Expiry Date</label>
                            <input type="date" name="expiry_date" value="<?= esc($fields['expiry_date']); ?>">
                        </div>
                    </div>
                </div>

                <div class="section-block">
                    <h4 class="form-section-title">F. Uploads</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Photo Upload</label>
                            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp">
                        </div>
                        <div class="form-group">
                            <label>Signature Upload</label>
                            <input type="file" name="signature" accept="image/jpeg,image/png,image/webp">
                        </div>
                        <div class="form-group full">
                            <label>Documents Upload</label>
                            <input type="file" name="documents" accept="image/jpeg,image/png,image/webp,application/pdf">
                        </div>
                    </div>
                </div>

                <div class="form-group full">
                    <button type="submit" class="btn btn-primary">Submit Registration</button>
                </div>
            </form>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>

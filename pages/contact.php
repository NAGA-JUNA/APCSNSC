<?php
require_once __DIR__ . '/../db.php';

$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request token. Please refresh and try again.';
    }

    $name = clean($_POST['name'] ?? '');
    $district = clean($_POST['district'] ?? '');
    $issue = clean($_POST['issue'] ?? '');
    $description = clean($_POST['description'] ?? '');

    if ($error === null && ($district === '' || $issue === '' || $description === '')) {
        $error = 'District, issue, and description are required.';
    } elseif ($error === null) {
        execute_query('INSERT INTO complaints (name, district, issue, description, status, created_at) VALUES (:name, :district, :issue, :description, :status, NOW())', [
            ':name' => $name !== '' ? $name : 'Anonymous',
            ':district' => $district,
            ':issue' => $issue,
            ':description' => $description,
            ':status' => 'pending',
        ]);
        $success = 'Complaint submitted successfully. APCSNSC support team will review it.';
    }
}

require_once __DIR__ . '/../header.php';
?>

<section class="container page-hero fade-in">
    <h1>Contact & Complaint Desk</h1>
    <p>Use this form to report issues related to working conditions, salary, duty scheduling, or district-level grievances.</p>
</section>

<section class="section">
    <div class="container">
        <div class="card fade-in">
            <h2>Submit Complaint</h2>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= esc($success); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= esc($error); ?></div>
            <?php endif; ?>

            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
                <div class="form-group">
                    <label>Name (Optional)</label>
                    <input type="text" name="name" placeholder="Your name">
                </div>
                <div class="form-group">
                    <label>District *</label>
                    <input type="text" name="district" required>
                </div>
                <div class="form-group full">
                    <label>Issue *</label>
                    <input type="text" name="issue" required>
                </div>
                <div class="form-group full">
                    <label>Description *</label>
                    <textarea name="description" required></textarea>
                </div>
                <div class="form-group full">
                    <button type="submit" class="btn btn-primary">Submit Complaint</button>
                </div>
            </form>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../footer.php'; ?>

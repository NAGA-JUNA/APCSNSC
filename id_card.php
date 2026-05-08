<?php
require_once __DIR__ . '/db.php';

$memberId = clean($_GET['member_id'] ?? '');
$member = null;
if ($memberId !== '') {
    $member = fetch_one('SELECT * FROM members WHERE member_id = :member_id LIMIT 1', [
        ':member_id' => $memberId,
    ]);
}

// Check if member can generate ID card
$canGenerateCard = false;
if ($member && can_generate_id_card($member)) {
    $canGenerateCard = true;
}

$settingsRows = fetch_all('SELECT setting_key, setting_value FROM settings');
$settings = [];
foreach ($settingsRows as $row) {
    $settings[(string)$row['setting_key']] = (string)($row['setting_value'] ?? '');
}

$siteName = $settings['site_name'] ?? 'APCSNSC';
$siteEmail = $settings['site_email'] ?? ($settings['contact_info_email'] ?? 'support@APCSNSC.org');
$sitePhone = $settings['site_phone'] ?? ($settings['contact_info_phone'] ?? '');
$siteAddress = $settings['site_address'] ?? 'Andhra Pradesh, India';
$siteUrl = base_url('index.php');
$unionLine = $settings['site_tagline'] ?? 'Andhra Pradesh Contract Staff Nurses Struggle Committee';

require_once __DIR__ . '/header.php';
?>

<section class="section" style="padding-top: 48px; padding-bottom: 48px; background: linear-gradient(180deg, #f4f8fc 0%, #ffffff 100%);">
    <div class="container" style="max-width: 1080px;">
        <?php if (!$member): ?>
            <div class="card" style="border-radius: 20px; padding: 28px; box-shadow: 0 18px 40px rgba(15,39,71,.12);">
                <h2>Invalid Member</h2>
                <p>Member record not found.</p>
                <p class="mb-0"><a href="<?= esc($siteUrl); ?>">Visit Website</a></p>
            </div>
        <?php elseif (!$canGenerateCard): ?>
            <div class="card" style="border-radius: 20px; padding: 28px; box-shadow: 0 18px 40px rgba(15,39,71,.12);">
                <h2 style="color: #dc3545;">Membership Inactive</h2>
                <p style="font-size: 16px; color: #475569; margin: 16px 0;">
                    <?php 
                    $paymentStatus = strtolower((string)($member['payment_status'] ?? 'unpaid'));
                    $membershipStatus = strtolower((string)($member['membership_status'] ?? 'unpaid'));
                    
                    if ($paymentStatus !== 'paid') {
                        echo 'Your membership payment is pending. Please contact the office to complete payment and generate your ID card.';
                    } elseif ($membershipStatus === 'expired') {
                        echo 'Your membership has expired. Please renew your membership to generate a new ID card.';
                    } elseif ($membershipStatus === 'suspended') {
                        echo 'Your membership is currently suspended. Please contact the office for more information.';
                    } else {
                        echo 'ID card generation is not available at this time. Please verify your membership status.';
                    }
                    ?>
                </p>
                <p class="mb-0"><a href="<?= esc(base_url('member_dashboard.php')); ?>">Back to Dashboard</a></p>
            </div>
        <?php else: ?>
            <div style="display:grid; gap:18px;">
                <div style="border-radius:24px; padding:24px; color:#fff; background: linear-gradient(135deg, #164b83 0%, #1d6b93 55%, #207a61 100%); box-shadow: 0 18px 40px rgba(15,39,71,.18);">
                    <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:center;">
                        <div>
                            <div style="display:inline-flex; padding:6px 12px; border-radius:999px; background:rgba(255,255,255,.14); font-size:12px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; margin-bottom:10px;">Member Scan Result</div>
                            <h1 style="margin:0 0 6px; font-size:30px; font-weight:800;"><?= esc($siteName); ?></h1>
                            <p style="margin:0; max-width:720px; opacity:.92;"><?= esc($unionLine); ?></p>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:12px; opacity:.85; text-transform:uppercase; letter-spacing:.08em;">Website</div>
                            <div style="font-size:16px; font-weight:700;"><?= esc($siteUrl); ?></div>
                        </div>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 360px minmax(0, 1fr); gap:18px; align-items:start;">
                    <div style="border-radius:22px; padding:22px; background:#0f2747; color:#fff; box-shadow: 0 18px 40px rgba(15,39,71,.12);">
                        <div style="display:flex; gap:14px; align-items:center; margin-bottom:18px;">
                            <img src="<?= esc(!empty($member['photo']) ? base_url($member['photo']) : 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&w=400&q=80'); ?>" alt="Photo" style="width:78px; height:78px; border-radius:18px; object-fit:cover; border:3px solid rgba(255,255,255,.18);">
                            <div>
                                <div style="font-size:12px; opacity:.8; text-transform:uppercase; letter-spacing:.08em;">Member</div>
                                <div style="font-size:22px; font-weight:800; line-height:1.1;">
                                    <?= esc($member['name'] ?? $member['full_name'] ?? ''); ?>
                                </div>
                                <div style="font-size:12px; opacity:.8; margin-top:4px;"><?= esc((string)$member['member_id']); ?></div>
                            </div>
                        </div>

                        <div style="display:grid; gap:10px;">
                            <div style="display:flex; justify-content:space-between; gap:12px; padding:10px 12px; border-radius:14px; background:rgba(255,255,255,.08);"><span>District</span><strong><?= esc((string)$member['district']); ?></strong></div>
                            <div style="display:flex; justify-content:space-between; gap:12px; padding:10px 12px; border-radius:14px; background:rgba(255,255,255,.08);"><span>Hospital</span><strong><?= esc((string)($member['hospital'] ?? $member['working_place'] ?? '-')); ?></strong></div>
                            <div style="display:flex; justify-content:space-between; gap:12px; padding:10px 12px; border-radius:14px; background:rgba(255,255,255,.08);"><span>Role</span><strong><?= esc((string)($member['role'] ?? $member['designation'] ?? '-')); ?></strong></div>
                            <div style="display:flex; justify-content:space-between; gap:12px; padding:10px 12px; border-radius:14px; background:rgba(255,255,255,.08);"><span>Join Date</span><strong><?= esc((string)($member['join_date'] ?? $member['joining_date'] ?? '-')); ?></strong></div>
                            <div style="display:flex; justify-content:space-between; gap:12px; padding:10px 12px; border-radius:14px; background:rgba(255,255,255,.08);"><span>Phone</span><strong><?= esc((string)($member['phone'] ?? '-')); ?></strong></div>
                            <div style="display:flex; justify-content:space-between; gap:12px; padding:10px 12px; border-radius:14px; background:rgba(255,255,255,.08);"><span>Email</span><strong style="text-align:right;"><?= esc((string)($member['email'] ?? '-')); ?></strong></div>
                        </div>
                    </div>

                    <div style="display:grid; gap:18px;">
                        <div style="border-radius:22px; padding:22px; background:#ffffff; box-shadow: 0 18px 40px rgba(15,39,71,.10); border:1px solid rgba(15,39,71,.08);">
                            <h3 style="margin:0 0 14px; font-size:18px; font-weight:800; color:#10213a;">Union Information</h3>
                            <div style="display:grid; gap:12px;">
                                <div><strong>Union Name:</strong> <?= esc($siteName); ?></div>
                                <div><strong>Website:</strong> <a href="<?= esc($siteUrl); ?>" target="_blank" rel="noopener"><?= esc($siteUrl); ?></a></div>
                                <div><strong>Email:</strong> <a href="mailto:<?= esc($siteEmail); ?>"><?= esc($siteEmail); ?></a></div>
                                <div><strong>Phone:</strong> <?= esc($sitePhone !== '' ? $sitePhone : 'Not provided'); ?></div>
                                <div><strong>Address:</strong> <?= esc($siteAddress); ?></div>
                            </div>
                        </div>

                        <div style="display:flex; gap:14px; flex-wrap:wrap; align-items:center; justify-content:space-between; border-radius:22px; padding:18px 22px; background: linear-gradient(135deg, #f7fbff, #eef6fb); box-shadow: 0 14px 30px rgba(15,39,71,.08); border:1px solid rgba(15,39,71,.08);">
                            <div>
                                <div style="font-size:12px; font-weight:800; color:#526174; text-transform:uppercase; letter-spacing:.08em;">Scan QR</div>
                                <div style="font-size:14px; color:#10213a;">This QR opens the member record and union details page.</div>
                            </div>
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=<?= urlencode(base_url('id_card.php?member_id=' . urlencode((string)$member['member_id']))); ?>" alt="QR" style="width:140px; height:140px; border-radius:18px; background:#fff; padding:8px; box-shadow: 0 12px 24px rgba(15,39,71,.12);">
                        </div>

                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                            <a class="btn btn-primary no-print" href="<?= esc(base_url('id_card.php?member_id=' . urlencode((string)$member['member_id']))); ?>" target="_blank" rel="noopener">Open Member Page</a>
                            <button class="btn btn-secondary no-print" onclick="window.print()" type="button">Print Card</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>

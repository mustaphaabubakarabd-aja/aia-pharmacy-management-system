<?php

$title = 'Settings';
require 'includes/header.php';

if (($_SESSION['user']['role'] ?? '') !== 'admin') {
    die('Access denied');
}

$pdo = db();

/*
|--------------------------------------------------------------------------
| Save Settings
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    check_csrf();

    $settings = [
        'pharmacy_name' => trim($_POST['pharmacy_name'] ?? ''),
        'phone'         => trim($_POST['phone'] ?? ''),
        'address'       => trim($_POST['address'] ?? ''),
        'receipt_footer'=> trim($_POST['receipt_footer'] ?? '')
    ];

    foreach ($settings as $key => $value) {

        $stmt = $pdo->prepare("
            INSERT INTO settings (`key`, `value`)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
        ");

        $stmt->execute([$key, $value]);
    }

    flash('success', 'Pharmacy settings saved successfully.');
    redirect('settings.php');
}

/*
|--------------------------------------------------------------------------
| Load Settings
|--------------------------------------------------------------------------
*/
$set = [];

$stmt = $pdo->query("
    SELECT `key`, `value`
    FROM settings
");

foreach ($stmt as $row) {
    $set[$row['key']] = $row['value'];
}

/*
|--------------------------------------------------------------------------
| Default Values
|--------------------------------------------------------------------------
*/
$pharmacyName  = $set['pharmacy_name'] ?? 'AIA PHARMACY';
$phone         = $set['phone'] ?? '';
$address       = $set['address'] ?? 'Sumaila, Kano State, Nigeria';
$receiptFooter = $set['receipt_footer'] ?? 'Thank you for patronizing AIA PHARMACY.';
?>

<style>
.settings-wrapper {
    max-width: 1000px;
    margin: 20px auto;
}

.settings-header {
    background: linear-gradient(135deg, #087f4f, #05663f);
    color: #fff;
    padding: 25px;
    border-radius: 14px;
    margin-bottom: 20px;
    box-shadow: 0 8px 25px rgba(0,0,0,.10);
}

.settings-header h1 {
    margin: 0 0 7px;
    font-size: 28px;
}

.settings-header p {
    margin: 0;
    opacity: .9;
}

.settings-card {
    background: #fff;
    border-radius: 14px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,.07);
    border: 1px solid #e5e7eb;
}

.settings-card h2 {
    margin-top: 0;
    color: #087f4f;
    font-size: 20px;
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 12px;
}

.settings-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.settings-field {
    display: flex;
    flex-direction: column;
}

.settings-field.full {
    grid-column: 1 / -1;
}

.settings-field label {
    font-weight: 600;
    margin-bottom: 7px;
    color: #374151;
}

.settings-field input,
.settings-field textarea {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 15px;
    outline: none;
    transition: .2s;
}

.settings-field input:focus,
.settings-field textarea:focus {
    border-color: #087f4f;
    box-shadow: 0 0 0 3px rgba(8,127,79,.10);
}

.settings-field textarea {
    min-height: 90px;
    resize: vertical;
}

.settings-help {
    font-size: 12px;
    color: #6b7280;
    margin-top: 5px;
}

.settings-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.save-btn {
    background: #087f4f;
    color: #fff;
    border: none;
    padding: 12px 25px;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
}

.save-btn:hover {
    background: #05663f;
}

.reset-btn {
    background: #f3f4f6;
    color: #374151;
    text-decoration: none;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 600;
}

.receipt-preview {
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
}

.receipt-preview .pharmacy-name {
    font-size: 20px;
    font-weight: 700;
    color: #087f4f;
}

.receipt-preview .small {
    font-size: 13px;
    color: #555;
}

.receipt-preview hr {
    border: none;
    border-top: 1px dashed #aaa;
    margin: 12px 0;
}

@media (max-width: 700px) {
    .settings-grid {
        grid-template-columns: 1fr;
    }

    .settings-field.full {
        grid-column: auto;
    }
}
</style>

<div class="settings-wrapper">

    <div class="settings-header">
        <h1>⚙️ Pharmacy Settings</h1>
        <p>Manage the basic information displayed throughout AIA PHARMACY Management System.</p>
    </div>

    <form method="post">

        <input type="hidden"
               name="csrf"
               value="<?= e(csrf()) ?>">

        <div class="settings-card">

            <h2>🏥 Pharmacy Information</h2>

            <div class="settings-grid">

                <div class="settings-field full">
                    <label for="pharmacy_name">
                        Pharmacy Name
                    </label>

                    <input
                        type="text"
                        id="pharmacy_name"
                        name="pharmacy_name"
                        value="<?= e($pharmacyName) ?>"
                        required
                    >

                    <div class="settings-help">
                        This name will appear on the system and receipts.
                    </div>
                </div>


                <div class="settings-field">

                    <label for="phone">
                        Phone Number
                    </label>

                    <input
                        type="text"
                        id="phone"
                        name="phone"
                        value="<?= e($phone) ?>"
                        placeholder="e.g. 08012345678"
                    >

                </div>


                <div class="settings-field">

                    <label for="address">
                        Pharmacy Address
                    </label>

                    <input
                        type="text"
                        id="address"
                        name="address"
                        value="<?= e($address) ?>"
                        placeholder="e.g. Sumaila, Kano State, Nigeria"
                    >

                </div>

            </div>

        </div>


        <div class="settings-card">

            <h2>🧾 Receipt Settings</h2>

            <div class="settings-field">

                <label for="receipt_footer">
                    Receipt Footer Message
                </label>

                <textarea
                    id="receipt_footer"
                    name="receipt_footer"
                    placeholder="e.g. Thank you for patronizing AIA PHARMACY."
                ><?= e($receiptFooter) ?></textarea>

                <div class="settings-help">
                    This message can be displayed at the bottom of the customer's receipt.
                </div>

            </div>

        </div>


        <div class="settings-card">

            <h2>👁️ Receipt Preview</h2>

            <div class="receipt-preview">

                <div class="pharmacy-name">
                    <?= e($pharmacyName) ?>
                </div>

                <div class="small">
                    <?= e($address) ?>
                </div>

                <?php if ($phone !== ''): ?>
                    <div class="small">
                        Tel: <?= e($phone) ?>
                    </div>
                <?php endif; ?>

                <hr>

                <div class="small">
                    RECEIPT
                </div>

                <hr>

                <div class="small">
                    <?= e($receiptFooter) ?>
                </div>

            </div>

        </div>


        <div class="settings-actions">

            <a
                href="<?= BASE_URL ?>index.php"
                class="reset-btn"
            >
                Cancel
            </a>

            <button
                type="submit"
                class="save-btn"
            >
                💾 Save Settings
            </button>

        </div>

    </form>

</div>

<?php require 'includes/footer.php'; ?>
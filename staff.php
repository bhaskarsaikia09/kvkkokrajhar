<?php
require_once __DIR__ . '/app/content.php';

$page_title = 'Staff | ' . APP_NAME;

$directorate = [
    ['name' => 'Dr. Manoranjan Neog', 'designation' => 'Director of Extension Education', 'phone' => '+91-9435093577', 'email' => 'manoranjan.neog[at]aau.ac.in', 'photo' => 'assets/img/staff/DEE1.jpg', 'link' => 'https://www.aau.ac.in/faculty/profile/Dr-Manoranjan-neog/718'],
    ['name' => 'Dr. Ranjit Kumar Saud', 'designation' => 'Assoc. Director of Extn. Edu. (P&I)', 'phone' => '+91-9435293057', 'email' => 'ranjit.saud[at]aau.ac.in', 'photo' => 'assets/img/staff/RkSaud.png', 'link' => 'https://www.aau.ac.in/faculty/profile/Dr-Manoranjan-neog/719'],
    ['name' => 'Dr. Bornali Mahanta', 'designation' => 'Senior Scientist', 'phone' => '+91-9954411012', 'email' => 'bornali.mahanta[at]aau.ac.in', 'photo' => 'assets/img/staff/BMahanta.png', 'link' => 'https://www.aau.ac.in/faculty/profile/Dr-Phuleswar-Nath/721'],
    ['name' => 'Dr. Rupjyoti Borah', 'designation' => 'Senior Scientist', 'phone' => '+91-9435090414', 'email' => 'rupjyoti.borah[at]aau.ac.in', 'photo' => 'assets/img/staff/RUPJYOTI_BORAH1.jpg', 'link' => 'https://www.aau.ac.in/faculty/profile/Dr-Bornali-Mahanta/1270'],
    ['name' => 'Dr. Arifa Momtaz Begum', 'designation' => 'Senior Scientist', 'phone' => '+91-9678231760', 'email' => 'arifamomtaz.begum[at]aau.ac.in', 'photo' => 'assets/img/staff/ARifa.jpg', 'link' => 'https://www.aau.ac.in/faculty/profile/Dr-Arifa-Momtaz-Begum/962'],
    ['name' => 'Dr. Bhrigu Kumar Neog', 'designation' => 'Scientist', 'phone' => '+91-9101421532', 'email' => 'bhrigu.neog[at]aau.ac.in', 'photo' => 'assets/img/staff/bhrigu_blue_back.jpg', 'link' => 'https://www.aau.ac.in/faculty/profile/Dr-Bhrigu--Kumar-Neog/964'],
    ['name' => 'Dr. Manoj Kumar Chauhan', 'designation' => 'Scientist', 'phone' => '+91-7896152408', 'email' => 'manoj.chouhan[at]aau.ac.in', 'photo' => 'assets/img/staff/MKC.png', 'link' => 'https://aau.ac.in/faculty/profile/Dr-Manoj-Kumar-Chauhan/1100'],
];

$kvkStaff = [
    ['name' => 'Ms. Porna Sarmah', 'designation' => 'Head (I/c)', 'qualification' => 'Master Degree', 'expertise' => 'Community Science', 'phone' => '+91-9435286898', 'email' => 'smtiporna.sarmah[at]aau.ac.in', 'photo' => 'assets/img/staff/PSI.png', 'link' => 'https://www.aau.ac.in/faculty/profile/Mrs-Porna-Sarmah/932'],
    ['name' => 'Mr. Gautom Bhagawati', 'designation' => 'Subject Matter Specialist', 'qualification' => 'Master Degree', 'expertise' => 'Plant Protection', 'phone' => '+91-7086668106', 'email' => 'gautom.bhagawati[at]aau.ac.in', 'photo' => 'assets/img/staff/Goutom Bhagawati.jpg', 'link' => 'https://www.aau.ac.in/faculty/profile/Mr-Goutom-Bhagawati/1069'],
    ['name' => 'Ms. Puja Basumatary', 'designation' => 'Subject Matter Specialist', 'qualification' => 'Master Degree', 'expertise' => 'Horticulture', 'phone' => '+91-9678418734', 'email' => 'puja.basumatary[at]aau.ac.in', 'photo' => 'assets/img/staff/PB.JPG', 'link' => 'https://www.aau.ac.in/faculty/profile/Miss-Puja-Basumatary/928'],
    ['name' => 'Ms. Porna Sarmah', 'designation' => 'Subject Matter Specialist', 'qualification' => 'Master Degree', 'expertise' => 'Community Science', 'phone' => '+91-9435286898', 'email' => 'smtiporna.sarmah[at]aau.ac.in', 'photo' => 'assets/img/staff/PSI.png', 'link' => 'https://www.aau.ac.in/faculty/profile/Mrs-Porna-Sarmah/932'],
	['name' => 'Dr. Muzaharul Islam', 'designation' => 'Subject Matter Specialist', 'qualification' => 'Master Degree', 'expertise' => 'Animal Science', 'phone' => '+91-9101638902', 'email' => 'muzaharul.islam[at]aau.ac.in', 'photo' => 'assets/img/staff/MI.JPG', 'link' => 'https://www.aau.ac.in/faculty/profile/Dr-Muzaharul-Islam/920'],
    ['name' => 'Mr. Santanu Kaushik Borah', 'designation' => 'Subject Matter Specialist', 'qualification' => 'Master Degree', 'expertise' => 'Agronomy', 'phone' => '+91-7578805941', 'email' => '', 'photo' => 'assets/img/staff/SKB.jpg', 'link' => 'https://aau.ac.in/faculty/profile/Mr-Santanu-Kaushik-Borah/1268'],
    ['name' => 'Vacant', 'designation' => 'Subject Matter Specialist', 'qualification' => '---', 'expertise' => '---', 'phone' => '---', 'email' => '---', 'photo' => 'assets/img/staff/passport.jpg', 'link' => '#'],
    ['name' => 'Mr. Dipangka Saikia', 'designation' => 'Programme Assistant', 'qualification' => 'Master Degree', 'expertise' => 'Fishery Science', 'phone' => '+91-9854024838', 'email' => 'dipangka.saikia[at]aau.ac.in', 'photo' => 'assets/img/staff/Dipangka Saikia.jpg', 'link' => 'https://www.aau.ac.in/faculty/profile/Mr-Dipangka-Saikia/1055'],
    ['name' => 'Mr. Bhaskar Jyoti Saikia', 'designation' => 'Programme Assistant (Computer)', 'qualification' => 'Master Degree', 'expertise' => 'Computer Science', 'phone' => '+91-9706206340', 'email' => 'bhaskar.saikia[at]aau.ac.in', 'photo' => 'assets/img/staff/BJS.png', 'link' => 'https://aau.ac.in/faculty/profile/Mr-Bhaskar-Jyoti-Saikia/1249'],
    ['name' => 'Vacant', 'designation' => 'Farm Manager', 'qualification' => '---', 'expertise' => '---', 'phone' => '---', 'email' => '---', 'photo' => 'assets/img/staff/passport.jpg', 'link' => '#'],
    ['name' => 'Mr. Akhil Roy Choudhury', 'designation' => 'Office Superintendent cum Accountant', 'qualification' => 'Master Degree', 'expertise' => 'Accountancy', 'phone' => '+91-9435076761', 'email' => 'akhil.roychoudhury[at]aau.ac.in', 'photo' => 'assets/img/staff/Akhil Roy Choudhury.jpg', 'link' => '#'],
    ['name' => 'Mrs. Pallavi Dutta', 'designation' => 'Junior Stenographer cum Computer Operator', 'qualification' => 'BA', 'expertise' => 'Office Administration', 'phone' => '+91-8638266269', 'email' => 'pallavi.dutta[at]aau.ac.in', 'photo' => 'assets/img/staff/pallaviJ.jpg', 'link' => '#'],
    ['name' => 'Mr. Sabed Ali Sheikh', 'designation' => 'Driver cum Mechanic', 'qualification' => 'High School', 'expertise' => 'Professional Driver', 'phone' => '+91-8876990931', 'email' => '', 'photo' => 'assets/img/staff/Sabed Ali Sk.jpg', 'link' => '#'],
    ['name' => 'Mr. Sikandar Basumatary', 'designation' => 'Driver cum Mechanic', 'qualification' => 'High School', 'expertise' => 'Professional Driver', 'phone' => '+91-79706967835', 'email' => '', 'photo' => 'assets/img/staff/SB.jpg', 'link' => '#'],
    ['name' => 'Vacant', 'designation' => 'Supporting Staff', 'qualification' => '---', 'expertise' => '---', 'phone' => '---', 'email' => '---', 'photo' => 'assets/img/staff/passport.jpg', 'link' => '#'],
	['name' => 'Vacant', 'designation' => 'Supporting Staff', 'qualification' => '---', 'expertise' => '---', 'phone' => '---', 'email' => '---', 'photo' => 'assets/img/staff/passport.jpg', 'link' => '#'],
];

$positionRows = [
    ['Sr. Scientist & Head', '1', '0'],
    ['Subject Matter Specialist', '6', '5'],
    ['Programme Assistant', '2', '2'],
    ['Farm Manager', '1', '0'],
    ['Office Superintendent cum Accountant', '1', '1'],
    ['Jr. Stenographer cum Computer Operator', '1', '1'],
    ['Driver cum Mechanic', '2', '2'],
    ['Multitasking Staff', '2', '0'],
];

$cadreRows = [
    ['Technical', '12', '9'],
    ['Administrative', '4', '2'],
];

$headRows = [
    ['1', 'Dr. Manoj K. Bhuyan', '2000-2022'],
    ['2', 'Dr. Sunil Kumar Paul', '2022-31.12.2025'],
    ['3', 'Mrs. Porna Sarmah', '31.12.2025-Till Date'],
];

$staffRows = db_all('staff', 'is_active = 1', 'sort_order ASC, name ASC', 250);
$hasManagedStaff = (bool) $staffRows;
if ($hasManagedStaff) {
    $managedDirectorate = [];
    $managedKvkStaff = [];

    foreach ($staffRows as $row) {
        $group = strtolower(trim((string) ($row['group_type'] ?? 'kvk')));
        $person = [
            'name' => (string) ($row['name'] ?? ''),
            'designation' => (string) ($row['designation'] ?? ''),
            'qualification' => (string) ($row['qualification'] ?? ''),
            'expertise' => (string) (($row['expertise'] ?? '') ?: ($row['discipline'] ?? '')),
            'phone' => (string) ($row['phone'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'photo' => (string) (($row['photo_path'] ?? '') ?: 'assets/img/staff/passport.jpg'),
            'link' => (string) (($row['profile_link'] ?? '') ?: '#'),
        ];

        if ($group === 'directorate') {
            $managedDirectorate[] = $person;
        } else {
            $managedKvkStaff[] = $person;
        }
    }

    if ($managedDirectorate) {
        $directorate = $managedDirectorate;
    }
    if ($managedKvkStaff) {
        $kvkStaff = $managedKvkStaff;
    }
}

$positionRecords = db_all('staff_positions', 'is_active = 1', 'sort_order ASC, designation ASC', 100);
if ($positionRecords) {
    $positionRows = array_map(fn($row) => [
        (string) ($row['designation'] ?? ''),
        (string) ($row['strength'] ?? ''),
        (string) ($row['in_position'] ?? ''),
    ], $positionRecords);
}

$cadreRecords = db_all('staff_cadres', 'is_active = 1', 'sort_order ASC, cadre ASC', 100);
if ($cadreRecords) {
    $cadreRows = array_map(fn($row) => [
        (string) ($row['cadre'] ?? ''),
        (string) ($row['strength'] ?? ''),
        (string) ($row['in_position'] ?? ''),
    ], $cadreRecords);
}

$headRecords = db_all('staff_heads', 'is_active = 1', 'sort_order ASC, serial_no ASC', 100);
if ($headRecords) {
    $headRows = array_map(fn($row) => [
        (string) ($row['serial_no'] ?? ''),
        (string) ($row['name'] ?? ''),
        (string) ($row['duration'] ?? ''),
    ], $headRecords);
}

require_once __DIR__ . '/includes/header.php';
?>
<style>
/* Staff page refinements - scoped to this page */
.staff-directory-grid {
    align-items: stretch;
}

.staff-profile-card {
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(20, 83, 45, .10);
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 10px 28px rgba(15, 23, 42, .07);
    transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
}

.staff-profile-card:hover {
    transform: translateY(-4px);
    border-color: rgba(34, 197, 94, .28);
    box-shadow: 0 16px 38px rgba(15, 23, 42, .12);
}

.staff-profile-card > img {
    width: 100%;
    aspect-ratio: 4 / 3;
    object-fit: cover;
    object-position: top center;
    background: #f3f6f4;
}

.staff-profile-card__body {
    display: flex;
    flex-direction: column;
    min-height: 270px;
    padding: 1.2rem 1.25rem 1.3rem;
}

.staff-profile-card__body h3 {
    margin: 0 0 .35rem;
    line-height: 1.25;
}

.staff-profile-card__body .role {
    display: inline-flex;
    align-self: flex-start;
    margin: 0 0 1rem;
    padding: .38rem .7rem;
    border-radius: 999px;
    background: rgba(34, 197, 94, .10);
    color: #166534;
    font-size: .84rem;
    font-weight: 700;
    line-height: 1.25;
}

.staff-profile-card__body dl {
    display: grid;
    gap: .7rem;
    margin: 0 0 1rem;
}

.staff-profile-card__body dl > div {
    display: grid;
    grid-template-columns: minmax(92px, .8fr) minmax(0, 1.4fr);
    gap: .75rem;
    align-items: start;
    padding-bottom: .65rem;
    border-bottom: 1px solid rgba(15, 23, 42, .08);
}

.staff-profile-card__body dt {
    color: #64748b;
    font-size: .78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .035em;
}

.staff-profile-card__body dd {
    margin: 0;
    color: #1f2937;
    font-weight: 600;
    overflow-wrap: anywhere;
}

.staff-profile-card__body .staff-email {
    display: inline-flex;
    gap: .45rem;
    align-items: center;
}

.staff-profile-card__body .staff-profile-link {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    margin-top: auto;
    width: fit-content;
    font-weight: 700;
}

.staff-profile-card--vacant {
    background: linear-gradient(180deg, #ffffff 0%, #f8faf9 100%);
}

.staff-profile-card--vacant::after {
    content: 'Vacant';
    position: absolute;
    top: 12px;
    right: 12px;
    padding: .35rem .7rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, .94);
    color: #475569;
    font-size: .75rem;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
    box-shadow: 0 4px 14px rgba(15, 23, 42, .10);
}

@media (max-width: 575.98px) {
    .staff-profile-card__body {
        min-height: 0;
    }

    .staff-profile-card__body dl > div {
        grid-template-columns: 1fr;
        gap: .2rem;
    }
}
</style>
<section class="staff-hero">
    <div class="container">
        <p class="eyebrow">Staff of KVK Kokrajhar</p>
        <h1>People behind extension, training, field demonstrations, and farmer advisory.</h1>
        <p>Krishi Vigyan Kendra Kokrajhar functions under the administrative control of the Directorate of Extension Education, Assam Agricultural University, Jorhat.</p>
    </div>
</section>

<section class="section">
    <div class="container staff-page-layout">
        <aside class="staff-panel">
            <div class="staff-panel__head">
                <p class="eyebrow">Directorate</p>
                <h2>Directorate of Extension Education, AAU, Jorhat</h2>
            </div>
            <div class="directorate-list">
                <?php foreach ($directorate as $person): ?>
                    <article class="director-card">
                        <img src="<?= e($person['photo']) ?>" alt="<?= e($person['name']) ?>">
                        <div>
                            <h3><?= e($person['name']) ?></h3>
                            <p><?= e($person['designation']) ?></p>
                            <span><i class="fa-solid fa-phone"></i> <?= e($person['phone']) ?></span>
                            <span><i class="fa-solid fa-envelope"></i> <?= e($person['email']) ?></span>
                            <a href="<?= e($person['link']) ?>" target="_blank" rel="noopener noreferrer">Learn more <i class="fa-solid fa-arrow-right"></i></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </aside>

        <div>
            <div class="staff-panel__head staff-panel__head--main">
                <p class="eyebrow">KVK Kokrajhar</p>
                <h2>Staff directory</h2>
                <p>Subject Matter Specialists, programme assistants, administrative staff, and support staff serving farmers across Kokrajhar district.</p>
            </div>
            <div class="staff-directory-grid">
                <?php foreach ($kvkStaff as $person): ?>
                    <?php $isVacant = strcasecmp(trim((string) $person['name']), 'Vacant') === 0; ?>
                    <article class="staff-profile-card<?= $isVacant ? ' staff-profile-card--vacant' : '' ?>">
                        <img
                            src="<?= e($person['photo']) ?>"
                            alt="<?= e($isVacant ? $person['designation'] . ' - vacant position' : $person['name']) ?>"
                            loading="lazy"
                            decoding="async"
                        >
                        <div class="staff-profile-card__body">
                            <h3><?= e($person['name']) ?></h3>
                            <p class="role"><?= e($person['designation']) ?></p>
                            <dl>
                                <div>
                                    <dt>Qualification</dt>
                                    <dd><?= e($person['qualification'] ?: '---') ?></dd>
                                </div>
                                <div>
                                    <dt>Expertise</dt>
                                    <dd><?= e($person['expertise'] ?: '---') ?></dd>
                                </div>
                                <div>
                                    <dt>Email</dt>
                                    <dd class="staff-email">
                                        <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                                        <span><?= e($person['email'] ?: '---') ?></span>
                                    </dd>
                                </div>
                            </dl>
                            <?php if (!$isVacant && !empty($person['link']) && $person['link'] !== '#'): ?>
                                <a
                                    class="staff-profile-link"
                                    href="<?= e($person['link']) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    aria-label="View profile of <?= e($person['name']) ?>"
                                >
                                    View profile <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<section class="section section--tinted">
    <div class="container staff-tables-grid">
        <article class="staff-table-card">
            <h2>Staff position of KVK</h2>
            <table>
                <thead><tr><th>Designation</th><th>Strength</th><th>In-position</th></tr></thead>
                <tbody>
                    <?php foreach ($positionRows as $row): ?>
                        <tr><td><?= e($row[0]) ?></td><td><?= e($row[1]) ?></td><td><?= e($row[2]) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </article>
        <div class="staff-table-stack">
            <article class="staff-table-card">
                <h2>Cadre wise position</h2>
                <table>
                    <thead><tr><th>Cadre</th><th>Strength</th><th>In-position</th></tr></thead>
                    <tbody>
                        <?php foreach ($cadreRows as $row): ?>
                            <tr><td><?= e($row[0]) ?></td><td><?= e($row[1]) ?></td><td><?= e($row[2]) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </article>
            <article class="staff-table-card">
                <h2>List of Head since inception</h2>
                <table>
                    <thead><tr><th>Sl. No</th><th>Name</th><th>Duration</th></tr></thead>
                    <tbody>
                        <?php foreach ($headRows as $row): ?>
                            <tr><td><?= e($row[0]) ?></td><td><?= e($row[1]) ?></td><td><?= e($row[2]) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </article>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

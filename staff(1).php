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


$positionStrength = array_sum(array_map(static fn(array $row): int => (int) ($row[1] ?? 0), $positionRows));
$positionFilled = array_sum(array_map(static fn(array $row): int => (int) ($row[2] ?? 0), $positionRows));
$positionVacant = max(0, $positionStrength - $positionFilled);

$activeKvkStaff = array_values(array_filter(
    $kvkStaff,
    static fn(array $person): bool => strtolower(trim((string) ($person['name'] ?? ''))) !== 'vacant'
));
$vacantKvkPosts = count($kvkStaff) - count($activeKvkStaff);

$expertiseAreas = array_values(array_unique(array_filter(array_map(
    static fn(array $person): string => trim((string) ($person['expertise'] ?? '')),
    $activeKvkStaff
), static fn(string $value): bool => $value !== '' && $value !== '---')));
sort($expertiseAreas, SORT_NATURAL | SORT_FLAG_CASE);

$designationOptions = array_values(array_unique(array_filter(array_map(
    static fn(array $person): string => trim((string) ($person['designation'] ?? '')),
    $kvkStaff
))));
sort($designationOptions, SORT_NATURAL | SORT_FLAG_CASE);

$staffEmailHref = static function (string $email): string {
    $address = str_ireplace(['[at]', '(at)', ' at '], '@', trim($email));
    return filter_var($address, FILTER_VALIDATE_EMAIL) ? 'mailto:' . $address : '';
};

$staffPhoneHref = static function (string $phone): string {
    $clean = preg_replace('/(?!^\+)[^\d]/', '', trim($phone));
    return ($clean !== null && preg_match('/^\+?\d{8,15}$/', $clean)) ? 'tel:' . $clean : '';
};

$staffInitials = static function (string $name): string {
    $name = preg_replace('/^(Dr|Mr|Mrs|Ms|Miss)\.?\s+/i', '', trim($name)) ?? trim($name);
    $parts = preg_split('/\s+/', $name) ?: [];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $initials !== '' ? $initials : 'KVK';
};

require_once __DIR__ . '/includes/header.php';
?>

<style>
    .kvk-staff-page {
        --staff-green: #176b3a;
        --staff-green-dark: #0c4527;
        --staff-green-soft: #eaf6ee;
        --staff-gold: #e7aa32;
        --staff-navy: #102536;
        --staff-text: #24362d;
        --staff-muted: #66766e;
        --staff-border: #dce8df;
        --staff-surface: #ffffff;
        --staff-bg: #f7faf7;
        --staff-danger: #b42318;
        --staff-radius: 18px;
        color: var(--staff-text);
        background: var(--staff-bg);
    }

    .kvk-staff-page *,
    .kvk-staff-page *::before,
    .kvk-staff-page *::after {
        box-sizing: border-box;
    }

    .kvk-staff-page a {
        text-decoration: none;
    }

    .staff-hero {
        position: relative;
        isolation: isolate;
        overflow: hidden;
        padding: 92px 0 86px;
        color: #fff;
        background:
            radial-gradient(circle at 85% 18%, rgba(231, 170, 50, .30), transparent 28%),
            linear-gradient(108deg, rgba(5, 44, 25, .97) 0%, rgba(16, 83, 48, .92) 55%, rgba(16, 37, 54, .88) 100%),
            url('assets/img/slider-1.jpg') center/cover no-repeat;
    }

    .staff-hero::before {
        content: "";
        position: absolute;
        inset: 0;
        z-index: -1;
        opacity: .18;
        background-image:
            linear-gradient(rgba(255,255,255,.18) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,.18) 1px, transparent 1px);
        background-size: 54px 54px;
        mask-image: linear-gradient(90deg, #000 0 58%, transparent 92%);
    }

    .staff-hero__grid {
        display: grid;
        grid-template-columns: minmax(0, 1.2fr) minmax(310px, .8fr);
        gap: 54px;
        align-items: center;
    }

    .staff-breadcrumb {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 9px;
        margin: 0 0 22px;
        color: rgba(255,255,255,.76);
        font-size: .85rem;
        font-weight: 700;
    }

    .staff-breadcrumb a {
        color: #fff;
    }

    .staff-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        margin: 0 0 14px;
        color: #ffe1a2;
        font-size: .78rem;
        font-weight: 900;
        letter-spacing: .11em;
        text-transform: uppercase;
    }

    .staff-eyebrow::before {
        content: "";
        width: 28px;
        height: 3px;
        border-radius: 999px;
        background: var(--staff-gold);
    }

    .staff-hero h1 {
        max-width: 780px;
        margin: 0 0 20px;
        color: #fff;
        font-family: Merriweather, Georgia, serif;
        font-size: clamp(2.35rem, 4.5vw, 4.7rem);
        line-height: 1.06;
        letter-spacing: -.035em;
        text-wrap: balance;
    }

    .staff-hero__lead {
        max-width: 720px;
        margin: 0;
        color: rgba(245, 252, 247, .9);
        font-size: 1.08rem;
        line-height: 1.8;
    }

    .staff-hero__stats {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 13px;
        margin-top: 30px;
        max-width: 590px;
    }

    .staff-hero__stat {
        padding: 16px 17px;
        border: 1px solid rgba(255,255,255,.22);
        border-radius: 14px;
        background: rgba(255,255,255,.11);
        backdrop-filter: blur(10px);
    }

    .staff-hero__stat strong {
        display: block;
        color: #fff;
        font-size: 1.55rem;
        line-height: 1;
    }

    .staff-hero__stat span {
        display: block;
        margin-top: 6px;
        color: rgba(255,255,255,.76);
        font-size: .79rem;
        font-weight: 700;
    }

    .staff-hero__portraits {
        position: relative;
        min-height: 370px;
    }

    .staff-hero__portrait {
        position: absolute;
        overflow: hidden;
        border: 7px solid rgba(255,255,255,.92);
        border-radius: 24px;
        background: #eff5ef;
        box-shadow: 0 28px 55px rgba(0,0,0,.27);
    }

    .staff-hero__portrait img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: top center;
        display: block;
    }

    .staff-hero__portrait--one {
        top: 18px;
        right: 16px;
        width: 230px;
        height: 300px;
        transform: rotate(3deg);
    }

    .staff-hero__portrait--two {
        left: 8px;
        bottom: 4px;
        width: 188px;
        height: 242px;
        transform: rotate(-5deg);
    }

    .staff-hero__portrait--three {
        right: 6px;
        bottom: -8px;
        width: 150px;
        height: 188px;
        transform: rotate(7deg);
    }

    .staff-section-nav {
        position: relative;
        z-index: 5;
        margin-top: -27px;
    }

    .staff-section-nav__inner {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px;
        overflow-x: auto;
        border: 1px solid var(--staff-border);
        border-radius: 16px;
        background: rgba(255,255,255,.96);
        box-shadow: 0 14px 34px rgba(20, 72, 43, .12);
        scrollbar-width: thin;
    }

    .staff-section-nav a {
        flex: 0 0 auto;
        padding: 10px 15px;
        border-radius: 10px;
        color: var(--staff-text);
        font-size: .86rem;
        font-weight: 800;
        transition: .2s ease;
    }

    .staff-section-nav a:hover,
    .staff-section-nav a:focus-visible {
        color: #fff;
        background: var(--staff-green);
    }

    .staff-section {
        padding: 78px 0;
        scroll-margin-top: 110px;
    }

    .staff-section--white {
        background: #fff;
    }

    .staff-section--soft {
        background:
            radial-gradient(circle at 100% 0, rgba(231,170,50,.08), transparent 28%),
            linear-gradient(180deg, #f2f8f3, #fbfdfb);
    }

    .staff-section-head {
        display: flex;
        align-items: end;
        justify-content: space-between;
        gap: 30px;
        margin-bottom: 32px;
    }

    .staff-section-head__copy {
        max-width: 760px;
    }

    .staff-section-head h2 {
        margin: 0 0 10px;
        color: var(--staff-navy);
        font-size: clamp(1.8rem, 3vw, 2.65rem);
        line-height: 1.15;
    }

    .staff-section-head p:last-child {
        margin: 0;
        color: var(--staff-muted);
        line-height: 1.75;
    }

    .directorate-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 20px;
    }

    .director-card {
        position: relative;
        display: grid;
        grid-template-columns: 112px minmax(0, 1fr);
        min-height: 178px;
        overflow: hidden;
        border: 1px solid var(--staff-border);
        border-radius: var(--staff-radius);
        background: var(--staff-surface);
        box-shadow: 0 12px 30px rgba(24, 75, 45, .07);
        transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
    }

    .director-card:hover {
        transform: translateY(-5px);
        border-color: #bdd7c4;
        box-shadow: 0 20px 42px rgba(24, 75, 45, .12);
    }

    .director-card__photo {
        min-height: 178px;
        background: var(--staff-green-soft);
    }

    .director-card__photo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: top center;
        display: block;
    }

    .director-card__body {
        min-width: 0;
        padding: 21px 18px;
    }

    .director-card h3 {
        margin: 0 0 5px;
        color: var(--staff-navy);
        font-size: 1.04rem;
        line-height: 1.3;
    }

    .director-card__role {
        min-height: 38px;
        margin: 0 0 13px;
        color: var(--staff-green);
        font-size: .82rem;
        font-weight: 800;
        line-height: 1.45;
    }

    .staff-contact-line {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        min-width: 0;
        margin-top: 7px;
        color: var(--staff-muted);
        font-size: .78rem;
        line-height: 1.4;
    }

    .staff-contact-line i {
        width: 15px;
        margin-top: 2px;
        color: var(--staff-green);
        text-align: center;
    }

    .staff-contact-line span,
    .staff-contact-line a {
        min-width: 0;
        overflow-wrap: anywhere;
        color: inherit;
    }

    .staff-card-link {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        margin-top: 14px;
        color: var(--staff-green);
        font-size: .78rem;
        font-weight: 900;
    }

    .staff-card-link:hover {
        color: var(--staff-green-dark);
    }

    .staff-directory-tools {
        display: grid;
        grid-template-columns: minmax(230px, 1fr) 220px;
        gap: 10px;
        width: min(100%, 600px);
    }

    .staff-input-wrap {
        position: relative;
    }

    .staff-input-wrap i {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--staff-muted);
        pointer-events: none;
    }

    .staff-directory-tools input,
    .staff-directory-tools select {
        width: 100%;
        min-height: 46px;
        padding: 11px 14px;
        border: 1px solid var(--staff-border);
        border-radius: 12px;
        outline: none;
        background: #fff;
        color: var(--staff-text);
        font: inherit;
        font-size: .88rem;
        transition: .2s ease;
    }

    .staff-directory-tools input {
        padding-left: 40px;
    }

    .staff-directory-tools input:focus,
    .staff-directory-tools select:focus {
        border-color: var(--staff-green);
        box-shadow: 0 0 0 4px rgba(23,107,58,.11);
    }

    .staff-results-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        margin: -12px 0 20px;
        color: var(--staff-muted);
        font-size: .84rem;
        font-weight: 700;
    }

    .staff-clear-filter {
        padding: 0;
        border: 0;
        background: transparent;
        color: var(--staff-green);
        font: inherit;
        font-size: .82rem;
        font-weight: 900;
        cursor: pointer;
    }

    .staff-directory-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 22px;
    }

    .staff-profile-card {
        position: relative;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        min-width: 0;
        border: 1px solid var(--staff-border);
        border-radius: var(--staff-radius);
        background: #fff;
        box-shadow: 0 12px 32px rgba(20, 72, 43, .07);
        transition: transform .24s ease, box-shadow .24s ease, border-color .24s ease;
    }

    .staff-profile-card:hover {
        transform: translateY(-6px);
        border-color: #bad6c1;
        box-shadow: 0 22px 46px rgba(20, 72, 43, .13);
    }

    .staff-profile-card[hidden] {
        display: none !important;
    }

    .staff-profile-card__media {
        position: relative;
        height: 265px;
        overflow: hidden;
        background:
            radial-gradient(circle at 50% 18%, #f6fbf7, #e5f1e8 66%, #d8e8dc);
    }

    .staff-profile-card__media::after {
        content: "";
        position: absolute;
        inset: auto 0 0;
        height: 38%;
        background: linear-gradient(transparent, rgba(7,54,30,.20));
        pointer-events: none;
    }

    .staff-profile-card__media img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: top center;
        display: block;
        transition: transform .35s ease;
    }

    .staff-profile-card:hover .staff-profile-card__media img {
        transform: scale(1.035);
    }

    .staff-profile-card__status {
        position: absolute;
        top: 14px;
        left: 14px;
        z-index: 2;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 10px;
        border-radius: 999px;
        color: #fff;
        background: rgba(12,69,39,.92);
        box-shadow: 0 8px 18px rgba(0,0,0,.16);
        font-size: .7rem;
        font-weight: 900;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .staff-profile-card__status::before {
        content: "";
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #8ee7a9;
    }

    .staff-profile-card--vacant .staff-profile-card__status {
        background: rgba(180,35,24,.94);
    }

    .staff-profile-card--vacant .staff-profile-card__status::before {
        background: #ffd0cc;
    }

    .staff-profile-card--vacant .staff-profile-card__media img {
        filter: grayscale(1);
        opacity: .72;
    }

    .staff-profile-card__body {
        display: flex;
        flex: 1;
        flex-direction: column;
        padding: 22px;
    }

    .staff-profile-card h3 {
        margin: 0 0 5px;
        color: var(--staff-navy);
        font-size: 1.15rem;
        line-height: 1.28;
    }

    .staff-profile-card__role {
        margin: 0 0 16px;
        color: var(--staff-green);
        font-size: .84rem;
        font-weight: 850;
        line-height: 1.45;
    }

    .staff-profile-card__chips {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
        margin-bottom: 16px;
    }

    .staff-profile-card__chips span {
        display: inline-flex;
        align-items: center;
        min-height: 28px;
        padding: 5px 9px;
        border-radius: 8px;
        color: #345544;
        background: var(--staff-green-soft);
        font-size: .72rem;
        font-weight: 800;
        line-height: 1.25;
    }

    .staff-profile-card__contact {
        margin-top: auto;
        padding-top: 15px;
        border-top: 1px solid #edf2ee;
    }

    .staff-profile-card__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 17px;
    }

    .staff-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        min-height: 38px;
        padding: 9px 12px;
        border: 1px solid var(--staff-border);
        border-radius: 10px;
        color: var(--staff-text);
        background: #fff;
        font-size: .76rem;
        font-weight: 900;
        transition: .2s ease;
    }

    .staff-action:hover {
        border-color: var(--staff-green);
        color: #fff;
        background: var(--staff-green);
    }

    .staff-action--primary {
        border-color: var(--staff-green);
        color: #fff;
        background: var(--staff-green);
    }

    .staff-action--primary:hover {
        background: var(--staff-green-dark);
    }

    .staff-no-results {
        display: none;
        padding: 44px 24px;
        border: 1px dashed #b9cdbf;
        border-radius: var(--staff-radius);
        background: #fff;
        text-align: center;
    }

    .staff-no-results.show {
        display: block;
    }

    .staff-no-results i {
        display: block;
        margin-bottom: 13px;
        color: var(--staff-green);
        font-size: 2rem;
    }

    .staff-no-results h3 {
        margin: 0 0 6px;
        color: var(--staff-navy);
    }

    .staff-no-results p {
        margin: 0;
        color: var(--staff-muted);
    }

    .staff-workforce-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.2fr) minmax(320px, .8fr);
        gap: 24px;
        align-items: start;
    }

    .staff-data-card {
        overflow: hidden;
        border: 1px solid var(--staff-border);
        border-radius: var(--staff-radius);
        background: #fff;
        box-shadow: 0 14px 34px rgba(20, 72, 43, .07);
    }

    .staff-data-card + .staff-data-card {
        margin-top: 24px;
    }

    .staff-data-card__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding: 22px 24px;
        border-bottom: 1px solid var(--staff-border);
        background: linear-gradient(135deg, #f9fcf9, #eef7f0);
    }

    .staff-data-card__head h3 {
        margin: 0;
        color: var(--staff-navy);
        font-size: 1.15rem;
    }

    .staff-data-card__head span {
        color: var(--staff-muted);
        font-size: .78rem;
        font-weight: 800;
    }

    .staff-table-scroll {
        overflow-x: auto;
    }

    .staff-data-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 620px;
    }

    .staff-data-table th,
    .staff-data-table td {
        padding: 14px 18px;
        border-bottom: 1px solid #edf2ee;
        vertical-align: middle;
        text-align: left;
    }

    .staff-data-table th {
        color: #4c6256;
        background: #fbfdfb;
        font-size: .73rem;
        font-weight: 900;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .staff-data-table td {
        color: var(--staff-text);
        font-size: .86rem;
    }

    .staff-data-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .staff-number-cell {
        width: 78px;
        font-weight: 900;
    }

    .staff-occupancy {
        min-width: 150px;
    }

    .staff-occupancy__top {
        display: flex;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 6px;
        color: var(--staff-muted);
        font-size: .72rem;
        font-weight: 800;
    }

    .staff-occupancy__bar {
        height: 7px;
        overflow: hidden;
        border-radius: 999px;
        background: #e9efea;
    }

    .staff-occupancy__bar span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, var(--staff-green), #58a96f);
    }

    .staff-vacancy-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 28px;
        min-height: 28px;
        padding: 4px 8px;
        border-radius: 8px;
        color: #176b3a;
        background: #e8f6ec;
        font-size: .76rem;
        font-weight: 900;
    }

    .staff-vacancy-badge.has-vacancy {
        color: var(--staff-danger);
        background: #fff0ee;
    }

    .staff-head-timeline {
        padding: 6px 24px 24px;
    }

    .staff-head-item {
        position: relative;
        display: grid;
        grid-template-columns: 42px minmax(0, 1fr);
        gap: 14px;
        padding-top: 20px;
    }

    .staff-head-item:not(:last-child)::before {
        content: "";
        position: absolute;
        left: 20px;
        top: 48px;
        bottom: -18px;
        width: 2px;
        background: #dbe9de;
    }

    .staff-head-item__number {
        position: relative;
        z-index: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        border-radius: 50%;
        color: #fff;
        background: var(--staff-green);
        box-shadow: 0 0 0 6px var(--staff-green-soft);
        font-size: .8rem;
        font-weight: 900;
    }

    .staff-head-item h4 {
        margin: 2px 0 4px;
        color: var(--staff-navy);
        font-size: .98rem;
    }

    .staff-head-item p {
        margin: 0;
        color: var(--staff-muted);
        font-size: .82rem;
        font-weight: 750;
    }

    .staff-summary-strip {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 26px;
    }

    .staff-summary-box {
        padding: 20px;
        border: 1px solid var(--staff-border);
        border-radius: 15px;
        background: #fff;
        box-shadow: 0 10px 25px rgba(20,72,43,.05);
    }

    .staff-summary-box i {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        margin-bottom: 14px;
        border-radius: 11px;
        color: var(--staff-green);
        background: var(--staff-green-soft);
    }

    .staff-summary-box strong {
        display: block;
        color: var(--staff-navy);
        font-size: 1.45rem;
        line-height: 1;
    }

    .staff-summary-box span {
        display: block;
        margin-top: 7px;
        color: var(--staff-muted);
        font-size: .78rem;
        font-weight: 800;
    }

    @media (max-width: 1199px) {
        .directorate-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .staff-directory-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 991px) {
        .staff-hero {
            padding: 76px 0 70px;
        }

        .staff-hero__grid {
            grid-template-columns: 1fr;
        }

        .staff-hero__portraits {
            display: none;
        }

        .staff-section-head {
            align-items: flex-start;
            flex-direction: column;
        }

        .staff-directory-tools {
            width: 100%;
        }

        .staff-workforce-grid {
            grid-template-columns: 1fr;
        }

        .staff-summary-strip {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 767px) {
        .staff-hero {
            padding: 62px 0 60px;
        }

        .staff-hero h1 {
            font-size: 2.25rem;
        }

        .staff-hero__stats {
            grid-template-columns: 1fr 1fr;
        }

        .staff-section {
            padding: 58px 0;
        }

        .directorate-grid,
        .staff-directory-grid {
            grid-template-columns: 1fr;
        }

        .director-card {
            grid-template-columns: 96px minmax(0, 1fr);
        }

        .director-card__photo {
            min-height: 166px;
        }

        .staff-directory-tools {
            grid-template-columns: 1fr;
        }

        .staff-profile-card {
            display: grid;
            grid-template-columns: 124px minmax(0, 1fr);
        }

        .staff-profile-card__media {
            height: 100%;
            min-height: 248px;
        }

        .staff-profile-card__body {
            padding: 18px;
        }
    }

    @media (max-width: 520px) {
        .staff-hero__stats,
        .staff-summary-strip {
            grid-template-columns: 1fr;
        }

        .staff-profile-card {
            display: flex;
        }

        .staff-profile-card__media {
            height: 270px;
            min-height: 0;
        }

        .staff-results-meta {
            align-items: flex-start;
            flex-direction: column;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .staff-profile-card,
        .staff-profile-card__media img,
        .director-card,
        .staff-section-nav a {
            transition: none !important;
        }
    }
</style>

<main class="kvk-staff-page">
    <section class="staff-hero">
        <div class="container staff-hero__grid">
            <div>
                <nav class="staff-breadcrumb" aria-label="Breadcrumb">
                    <a href="index.php">Home</a>
                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                    <span>Staff</span>
                </nav>
                <p class="staff-eyebrow">KVK Kokrajhar Team</p>
                <h1>People powering field extension and farmer-focused innovation.</h1>
                <p class="staff-hero__lead">Meet the specialists, programme personnel, administrative team, and institutional leadership supporting training, demonstrations, technology assessment, and advisory services across Kokrajhar district.</p>

                <div class="staff-hero__stats" aria-label="Staff overview">
                    <div class="staff-hero__stat">
                        <strong><?= e((string) count($activeKvkStaff)) ?></strong>
                        <span>Personnel listed</span>
                    </div>
                    <div class="staff-hero__stat">
                        <strong><?= e((string) count($expertiseAreas)) ?></strong>
                        <span>Areas of expertise</span>
                    </div>
                    <div class="staff-hero__stat">
                        <strong><?= e((string) $positionFilled) ?>/<?= e((string) $positionStrength) ?></strong>
                        <span>Posts currently filled</span>
                    </div>
                    <div class="staff-hero__stat">
                        <strong><?= e((string) $vacantKvkPosts) ?></strong>
                        <span>Vacant entries listed</span>
                    </div>
                </div>
            </div>

            <div class="staff-hero__portraits" aria-hidden="true">
                <?php foreach (array_slice($activeKvkStaff, 0, 3) as $index => $person): ?>
                    <div class="staff-hero__portrait staff-hero__portrait--<?= ['one', 'two', 'three'][$index] ?>">
                        <img src="<?= e((string) $person['photo']) ?>" alt="" onerror="this.onerror=null;this.src='assets/img/staff/passport.jpg';">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <div class="staff-section-nav">
        <div class="container">
            <nav class="staff-section-nav__inner" aria-label="Staff page sections">
                <a href="#directorate"><i class="fa-solid fa-building-columns"></i> Directorate</a>
                <a href="#kvk-team"><i class="fa-solid fa-people-group"></i> KVK Team</a>
                <a href="#workforce"><i class="fa-solid fa-chart-column"></i> Workforce</a>
                <a href="#heads"><i class="fa-solid fa-clock-rotate-left"></i> Heads Since Inception</a>
            </nav>
        </div>
    </div>

    <section class="staff-section staff-section--white" id="directorate">
        <div class="container">
            <div class="staff-section-head">
                <div class="staff-section-head__copy">
                    <p class="staff-eyebrow">Institutional Guidance</p>
                    <h2>Directorate of Extension Education, AAU, Jorhat</h2>
                    <p>The Directorate provides administrative leadership, technical guidance, planning support, and institutional coordination for extension activities.</p>
                </div>
            </div>

            <div class="directorate-grid">
                <?php foreach ($directorate as $person): ?>
                    <?php
                    $directorEmail = $staffEmailHref((string) ($person['email'] ?? ''));
                    $directorPhone = $staffPhoneHref((string) ($person['phone'] ?? ''));
                    $directorProfile = trim((string) ($person['link'] ?? ''));
                    ?>
                    <article class="director-card">
                        <div class="director-card__photo">
                            <img src="<?= e((string) ($person['photo'] ?? 'assets/img/staff/passport.jpg')) ?>"
                                 alt="<?= e((string) ($person['name'] ?? 'Staff member')) ?>"
                                 loading="lazy"
                                 onerror="this.onerror=null;this.src='assets/img/staff/passport.jpg';">
                        </div>
                        <div class="director-card__body">
                            <h3><?= e((string) ($person['name'] ?? '')) ?></h3>
                            <p class="director-card__role"><?= e((string) ($person['designation'] ?? '')) ?></p>

                            <?php if ($directorPhone !== ''): ?>
                                <div class="staff-contact-line">
                                    <i class="fa-solid fa-phone" aria-hidden="true"></i>
                                    <a href="<?= e($directorPhone) ?>"><?= e((string) $person['phone']) ?></a>
                                </div>
                            <?php endif; ?>

                            <?php if ($directorEmail !== ''): ?>
                                <div class="staff-contact-line">
                                    <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                                    <a href="<?= e($directorEmail) ?>"><?= e((string) $person['email']) ?></a>
                                </div>
                            <?php endif; ?>

                            <?php if ($directorProfile !== '' && $directorProfile !== '#'): ?>
                                <a class="staff-card-link" href="<?= e($directorProfile) ?>" target="_blank" rel="noopener noreferrer">
                                    View profile <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="staff-section staff-section--soft" id="kvk-team">
        <div class="container">
            <div class="staff-section-head">
                <div class="staff-section-head__copy">
                    <p class="staff-eyebrow">Staff Directory</p>
                    <h2>KVK Kokrajhar team</h2>
                    <p>Search by name, designation, or expertise, and use the designation filter to quickly locate a staff member or identify a vacant post.</p>
                </div>

                <div class="staff-directory-tools" role="search">
                    <div class="staff-input-wrap">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" id="staffSearch" placeholder="Search name, role or expertise..." aria-label="Search staff">
                    </div>
                    <select id="staffRoleFilter" aria-label="Filter by designation">
                        <option value="">All designations</option>
                        <?php foreach ($designationOptions as $designation): ?>
                            <option value="<?= e(mb_strtolower($designation)) ?>"><?= e($designation) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="staff-results-meta">
                <span id="staffResultCount"><?= e((string) count($kvkStaff)) ?> entries shown</span>
                <button class="staff-clear-filter" type="button" id="staffClearFilter">Clear search and filter</button>
            </div>

            <div class="staff-directory-grid" id="staffDirectoryGrid">
                <?php foreach ($kvkStaff as $person): ?>
                    <?php
                    $personName = trim((string) ($person['name'] ?? ''));
                    $isVacant = strtolower($personName) === 'vacant';
                    $personEmail = $staffEmailHref((string) ($person['email'] ?? ''));
                    $personPhone = $staffPhoneHref((string) ($person['phone'] ?? ''));
                    $personProfile = trim((string) ($person['link'] ?? ''));
                    $searchText = mb_strtolower(implode(' ', [
                        $personName,
                        (string) ($person['designation'] ?? ''),
                        (string) ($person['qualification'] ?? ''),
                        (string) ($person['expertise'] ?? ''),
                    ]));
                    ?>
                    <article class="staff-profile-card<?= $isVacant ? ' staff-profile-card--vacant' : '' ?>"
                             data-staff-card
                             data-search="<?= e($searchText) ?>"
                             data-role="<?= e(mb_strtolower(trim((string) ($person['designation'] ?? '')))) ?>">
                        <div class="staff-profile-card__media">
                            <span class="staff-profile-card__status"><?= $isVacant ? 'Vacant post' : 'In position' ?></span>
                            <img src="<?= e((string) ($person['photo'] ?? 'assets/img/staff/passport.jpg')) ?>"
                                 alt="<?= e($isVacant ? (string) ($person['designation'] ?? 'Vacant post') : $personName) ?>"
                                 loading="lazy"
                                 onerror="this.onerror=null;this.src='assets/img/staff/passport.jpg';">
                        </div>

                        <div class="staff-profile-card__body">
                            <h3><?= e($personName) ?></h3>
                            <p class="staff-profile-card__role"><?= e((string) ($person['designation'] ?? '')) ?></p>

                            <div class="staff-profile-card__chips">
                                <?php if (($person['qualification'] ?? '') !== '' && ($person['qualification'] ?? '') !== '---'): ?>
                                    <span><i class="fa-solid fa-graduation-cap"></i>&nbsp; <?= e((string) $person['qualification']) ?></span>
                                <?php endif; ?>
                                <?php if (($person['expertise'] ?? '') !== '' && ($person['expertise'] ?? '') !== '---'): ?>
                                    <span><i class="fa-solid fa-seedling"></i>&nbsp; <?= e((string) $person['expertise']) ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if (!$isVacant): ?>
                                <div class="staff-profile-card__contact">
                                    <?php if ($personPhone !== ''): ?>
                                        <div class="staff-contact-line">
                                            <i class="fa-solid fa-phone" aria-hidden="true"></i>
                                            <a href="<?= e($personPhone) ?>"><?= e((string) $person['phone']) ?></a>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($personEmail !== ''): ?>
                                        <div class="staff-contact-line">
                                            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                                            <a href="<?= e($personEmail) ?>"><?= e((string) $person['email']) ?></a>
                                        </div>
                                    <?php endif; ?>

                                    <div class="staff-profile-card__actions">
                                        <?php if ($personEmail !== ''): ?>
                                            <a class="staff-action" href="<?= e($personEmail) ?>">
                                                <i class="fa-solid fa-envelope" aria-hidden="true"></i> Email
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($personProfile !== '' && $personProfile !== '#'): ?>
                                            <a class="staff-action staff-action--primary" href="<?= e($personProfile) ?>" target="_blank" rel="noopener noreferrer">
                                                Profile <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="staff-profile-card__contact">
                                    <div class="staff-contact-line">
                                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                        <span>Position currently shown as vacant.</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="staff-no-results" id="staffNoResults" aria-live="polite">
                <i class="fa-solid fa-user-slash" aria-hidden="true"></i>
                <h3>No matching staff found</h3>
                <p>Try a different name, designation, or expertise area.</p>
            </div>
        </div>
    </section>

    <section class="staff-section staff-section--white" id="workforce">
        <div class="container">
            <div class="staff-section-head">
                <div class="staff-section-head__copy">
                    <p class="staff-eyebrow">Workforce Overview</p>
                    <h2>Sanctioned strength and position status</h2>
                    <p>A concise view of designation-wise strength, cadre composition, current occupancy, and vacancies.</p>
                </div>
            </div>

            <div class="staff-summary-strip">
                <div class="staff-summary-box">
                    <i class="fa-solid fa-users-gear" aria-hidden="true"></i>
                    <strong><?= e((string) $positionStrength) ?></strong>
                    <span>Total sanctioned posts</span>
                </div>
                <div class="staff-summary-box">
                    <i class="fa-solid fa-user-check" aria-hidden="true"></i>
                    <strong><?= e((string) $positionFilled) ?></strong>
                    <span>Posts in position</span>
                </div>
                <div class="staff-summary-box">
                    <i class="fa-solid fa-user-clock" aria-hidden="true"></i>
                    <strong><?= e((string) $positionVacant) ?></strong>
                    <span>Vacancies by position table</span>
                </div>
                <div class="staff-summary-box">
                    <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                    <strong><?= e((string) count($cadreRows)) ?></strong>
                    <span>Cadre categories</span>
                </div>
            </div>

            <div class="staff-workforce-grid">
                <article class="staff-data-card">
                    <div class="staff-data-card__head">
                        <h3>Designation-wise position</h3>
                        <span>Strength versus in-position</span>
                    </div>
                    <div class="staff-table-scroll">
                        <table class="staff-data-table">
                            <thead>
                                <tr>
                                    <th>Designation</th>
                                    <th>Strength</th>
                                    <th>In position</th>
                                    <th>Occupancy</th>
                                    <th>Vacant</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($positionRows as $row): ?>
                                    <?php
                                    $strength = max(0, (int) ($row[1] ?? 0));
                                    $filled = max(0, (int) ($row[2] ?? 0));
                                    $vacancy = max(0, $strength - $filled);
                                    $occupancy = $strength > 0 ? min(100, (int) round(($filled / $strength) * 100)) : 0;
                                    ?>
                                    <tr>
                                        <td><strong><?= e((string) ($row[0] ?? '')) ?></strong></td>
                                        <td class="staff-number-cell"><?= e((string) $strength) ?></td>
                                        <td class="staff-number-cell"><?= e((string) $filled) ?></td>
                                        <td class="staff-occupancy">
                                            <div class="staff-occupancy__top">
                                                <span><?= e((string) $occupancy) ?>%</span>
                                                <span><?= e((string) $filled) ?>/<?= e((string) $strength) ?></span>
                                            </div>
                                            <div class="staff-occupancy__bar" aria-label="<?= e((string) $occupancy) ?> percent occupied">
                                                <span style="width: <?= e((string) $occupancy) ?>%"></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="staff-vacancy-badge<?= $vacancy > 0 ? ' has-vacancy' : '' ?>"><?= e((string) $vacancy) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </article>

                <div>
                    <article class="staff-data-card">
                        <div class="staff-data-card__head">
                            <h3>Cadre-wise position</h3>
                            <span>Current composition</span>
                        </div>
                        <div class="staff-table-scroll">
                            <table class="staff-data-table">
                                <thead>
                                    <tr>
                                        <th>Cadre</th>
                                        <th>Strength</th>
                                        <th>In position</th>
                                        <th>Vacant</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cadreRows as $row): ?>
                                        <?php
                                        $cadreStrength = max(0, (int) ($row[1] ?? 0));
                                        $cadreFilled = max(0, (int) ($row[2] ?? 0));
                                        $cadreVacant = max(0, $cadreStrength - $cadreFilled);
                                        ?>
                                        <tr>
                                            <td><strong><?= e((string) ($row[0] ?? '')) ?></strong></td>
                                            <td class="staff-number-cell"><?= e((string) $cadreStrength) ?></td>
                                            <td class="staff-number-cell"><?= e((string) $cadreFilled) ?></td>
                                            <td><span class="staff-vacancy-badge<?= $cadreVacant > 0 ? ' has-vacancy' : '' ?>"><?= e((string) $cadreVacant) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </article>

                    <article class="staff-data-card" id="heads">
                        <div class="staff-data-card__head">
                            <h3>Heads since inception</h3>
                            <span>Leadership timeline</span>
                        </div>
                        <div class="staff-head-timeline">
                            <?php foreach ($headRows as $row): ?>
                                <div class="staff-head-item">
                                    <span class="staff-head-item__number"><?= e((string) ($row[0] ?? '')) ?></span>
                                    <div>
                                        <h4><?= e((string) ($row[1] ?? '')) ?></h4>
                                        <p><?= e((string) ($row[2] ?? '')) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('staffSearch');
    const roleFilter = document.getElementById('staffRoleFilter');
    const clearButton = document.getElementById('staffClearFilter');
    const cards = Array.from(document.querySelectorAll('[data-staff-card]'));
    const countLabel = document.getElementById('staffResultCount');
    const noResults = document.getElementById('staffNoResults');

    const normalize = (value) => String(value || '').trim().toLocaleLowerCase();

    const updateDirectory = () => {
        const query = normalize(searchInput?.value);
        const role = normalize(roleFilter?.value);
        let visibleCount = 0;

        cards.forEach((card) => {
            const searchable = normalize(card.dataset.search);
            const cardRole = normalize(card.dataset.role);
            const matchesSearch = query === '' || searchable.includes(query);
            const matchesRole = role === '' || cardRole === role;
            const visible = matchesSearch && matchesRole;

            card.hidden = !visible;
            if (visible) visibleCount += 1;
        });

        if (countLabel) {
            countLabel.textContent = `${visibleCount} ${visibleCount === 1 ? 'entry' : 'entries'} shown`;
        }

        noResults?.classList.toggle('show', visibleCount === 0);
    };

    searchInput?.addEventListener('input', updateDirectory);
    roleFilter?.addEventListener('change', updateDirectory);

    clearButton?.addEventListener('click', () => {
        if (searchInput) searchInput.value = '';
        if (roleFilter) roleFilter.value = '';
        updateDirectory();
        searchInput?.focus();
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
// ============================================================
// mock_db.php — In-memory mock database (PHP session)
// Covers all 5 ERD tables:
//   USER, ITEM_POST, ITEM_IMAGE, CLAIM_REQUEST, CONTACT
// ============================================================

require_once __DIR__ . '/config.php';

// ============================================================
// USER
// ============================================================
function mockGetUsers(): array {
    if (!isset($_SESSION['ftmk_users'])) {
        $_SESSION['ftmk_users'] = [
            [
                'id'           => 'B032310001',
                'name'         => 'Demo Student',
                'email'        => 'B032310001@student.utem.edu.my',
                'role'         => 'Student',
                'password_hash'=> password_hash('password123', PASSWORD_DEFAULT),
                'initials'     => 'DS',
                'avatarColor'  => 'blue',
            ],
        ];
    }
    return $_SESSION['ftmk_users'];
}
function mockSaveUsers(array $u): void { $_SESSION['ftmk_users'] = $u; }
function mockFindUser(string $id): ?array {
    foreach (mockGetUsers() as $u) {
        if (strcasecmp($u['id'], $id) === 0 || strcasecmp($u['email'], $id) === 0)
            return $u;
    }
    return null;
}

// ============================================================
// ITEM_POST
// ============================================================
function getDefaultPosts(): array {
    return [
        ['id'=>1,  'type'=>'lost',  'title'=>'Black Samsung Galaxy A54',      'cat'=>'Electronics', 'desc'=>'Lost my phone near the printing room. Has a cracked screen protector and blue case with sticker on back.',          'loc'=>'Block A, Level 2 — Printing Room',    'date'=>'2025-07-10','status'=>'active',   'poster'=>'Ahmad Faris',          'posterId'=>'B032310012','role'=>'Student', 'contact'=>'B032310012@student.utem.edu.my','avatar'=>'AF','avatarColor'=>'blue'],
        ['id'=>2,  'type'=>'found', 'title'=>'Matric Card — FTMK Student',    'cat'=>'ID / Card',   'desc'=>'Found a matric card near Lab 3 entrance. The card belongs to a female student. Keeping it at the faculty office.', 'loc'=>'Lab 3, Block B',                      'date'=>'2025-07-09','status'=>'active',   'poster'=>'Nur Izzati',           'posterId'=>'B032310045','role'=>'Student', 'contact'=>'B032310045@student.utem.edu.my','avatar'=>'NI','avatarColor'=>'orange'],
        ['id'=>3,  'type'=>'lost',  'title'=>'Blue Laptop Bag (Lenovo)',       'cat'=>'Bag / Wallet','desc'=>'Left my dark blue Lenovo laptop bag in the FYP lab. Contains charger and lecture notes inside.',                   'loc'=>'FYP Lab, Level 3, Block C',           'date'=>'2025-07-08','status'=>'active',   'poster'=>'Mohamad Haziq',        'posterId'=>'B032310078','role'=>'Student', 'contact'=>'B032310078@student.utem.edu.my','avatar'=>'MH','avatarColor'=>'blue'],
        ['id'=>4,  'type'=>'found', 'title'=>'Car Keys — Toyota',              'cat'=>'Keys',        'desc'=>'Found a bunch of car keys with a Toyota remote and small plushie keychain near the surau.',                        'loc'=>'Near Surau, Block A',                 'date'=>'2025-07-07','status'=>'active',   'poster'=>'Ts. Roslan Haji Ibrahim','posterId'=>'STF0034', 'role'=>'Lecturer','contact'=>'roslan@utem.edu.my',  'avatar'=>'RI','avatarColor'=>'blue'],
        ['id'=>5,  'type'=>'lost',  'title'=>'AirPods Pro (White)',            'cat'=>'Electronics', 'desc'=>'Lost my AirPods pro with white charging case. Last seen in the cafe area during lunch.',                          'loc'=>'Faculty Cafe, Block A Ground Floor',  'date'=>'2025-07-06','status'=>'active',   'poster'=>'Siti Nurul Ain',        'posterId'=>'B032320021','role'=>'Student', 'contact'=>'B032320021@student.utem.edu.my','avatar'=>'SA','avatarColor'=>'orange'],
        ['id'=>6,  'type'=>'found', 'title'=>'Grey Hoodie (Size M)',           'cat'=>'Clothing',    'desc'=>'Left behind in Lecture Hall 4. Grey hoodie with no name tag. Stored at the security counter.',                    'loc'=>'Lecture Hall 4, Block D',             'date'=>'2025-07-05','status'=>'active',   'poster'=>'Khairul Azmi',         'posterId'=>'B032310099','role'=>'Student', 'contact'=>'B032310099@student.utem.edu.my','avatar'=>'KA','avatarColor'=>'blue'],
        ['id'=>7,  'type'=>'lost',  'title'=>'Wallet (Brown Leather)',         'cat'=>'Bag / Wallet','desc'=>'Brown leather wallet with IC, ATM cards and some cash. Last seen at the photocopy shop counter.',                  'loc'=>'Photocopy Shop, Block B',             'date'=>'2025-07-04','status'=>'resolved', 'poster'=>'Farah Nabilah',        'posterId'=>'B032310033','role'=>'Student', 'contact'=>'B032310033@student.utem.edu.my','avatar'=>'FN','avatarColor'=>'orange'],
        ['id'=>8,  'type'=>'found', 'title'=>'Mechanical Pencil + Ruler Set', 'cat'=>'Stationery',  'desc'=>'Found a transparent pencil case with a mechanical pencil, ruler, and correction tape after the Drawing class.',    'loc'=>'Drawing Lab, Level 1, Block C',       'date'=>'2025-07-03','status'=>'resolved', 'poster'=>'Wong Wei Liang',       'posterId'=>'B032310055','role'=>'Student', 'contact'=>'B032310055@student.utem.edu.my','avatar'=>'WW','avatarColor'=>'blue'],
        ['id'=>9,  'type'=>'lost',  'title'=>'Power Bank (Xiaomi 10000mAh)',  'cat'=>'Electronics', 'desc'=>'Blue Xiaomi power bank with scratch on the back. May have left it on the desk in the open lab.',                  'loc'=>'Open Lab, Level 2, Block B',          'date'=>'2025-07-11','status'=>'active',   'poster'=>'Luqmanul Hakim',       'posterId'=>'B032320044','role'=>'Student', 'contact'=>'B032320044@student.utem.edu.my','avatar'=>'LH','avatarColor'=>'orange'],
        ['id'=>10, 'type'=>'found', 'title'=>'UTeM ID Card (Staff)',           'cat'=>'ID / Card',   'desc'=>'Found a staff ID card in the faculty parking area. Dropping it off at the admin counter.',                        'loc'=>'Faculty Parking, Block A',            'date'=>'2025-07-11','status'=>'active',   'poster'=>'Amirah Zahirah',       'posterId'=>'B032310060','role'=>'Student', 'contact'=>'B032310060@student.utem.edu.my','avatar'=>'AZ','avatarColor'=>'orange'],
        ['id'=>11, 'type'=>'lost',  'title'=>'Black Umbrella',                 'cat'=>'Other',       'desc'=>'Plain black umbrella with a curved handle. Left it in Lecture Hall 2 after the afternoon class.',                 'loc'=>'Lecture Hall 2, Block D',             'date'=>'2025-07-12','status'=>'active',   'poster'=>'Hafizuddin Zainal',    'posterId'=>'B032310081','role'=>'Student', 'contact'=>'B032310081@student.utem.edu.my','avatar'=>'HZ','avatarColor'=>'blue'],
    ];
}
function mockGetPosts(): array {
    if (!isset($_SESSION['ftmk_posts'])) $_SESSION['ftmk_posts'] = getDefaultPosts();
    return $_SESSION['ftmk_posts'];
}
function mockSavePosts(array $p): void { $_SESSION['ftmk_posts'] = array_values($p); }
function mockNextPostId(): int {
    $posts = mockGetPosts();
    return $posts ? max(array_column($posts, 'id')) + 1 : 1;
}

// ============================================================
// ITEM_IMAGE  (ERD: image_id PK, post_id FK, image_url)
// Stored as a flat list; each image belongs to one post.
// ============================================================
function mockGetImages(): array {
    if (!isset($_SESSION['ftmk_images'])) $_SESSION['ftmk_images'] = [];
    return $_SESSION['ftmk_images'];
}
function mockSaveImages(array $imgs): void { $_SESSION['ftmk_images'] = array_values($imgs); }
function mockNextImageId(): int {
    $imgs = mockGetImages();
    return $imgs ? max(array_column($imgs, 'id')) + 1 : 1;
}
/** Return images for a post (array of image_url strings) */
function mockGetPostImages(int $postId): array {
    return array_values(array_map(
        fn($i) => $i['image_url'],
        array_filter(mockGetImages(), fn($i) => $i['post_id'] === $postId)
    ));
}
function mockAddImage(int $postId, string $imageUrl): int {
    $id = mockNextImageId();
    $imgs = mockGetImages();
    $imgs[] = ['id' => $id, 'post_id' => $postId, 'image_url' => $imageUrl];
    mockSaveImages($imgs);
    return $id;
}

// ============================================================
// CLAIM_REQUEST  (ERD: claim_id PK, post_id FK, user_id FK,
//                       message, claim_status, date_request)
// claim_status: 'pending' | 'accepted' | 'rejected'
// ============================================================
function mockGetClaims(): array {
    if (!isset($_SESSION['ftmk_claims'])) $_SESSION['ftmk_claims'] = [];
    return $_SESSION['ftmk_claims'];
}
function mockSaveClaims(array $c): void { $_SESSION['ftmk_claims'] = array_values($c); }
function mockNextClaimId(): int {
    $c = mockGetClaims();
    return $c ? max(array_column($c, 'id')) + 1 : 1;
}
function mockFindClaim(int $claimId): ?array {
    foreach (mockGetClaims() as $c) { if ($c['id'] === $claimId) return $c; }
    return null;
}
/** Has this user already submitted a claim for this post? */
function mockUserAlreadyClaimed(int $postId, string $userId): bool {
    foreach (mockGetClaims() as $c) {
        if ($c['post_id'] === $postId && $c['user_id'] === $userId) return true;
    }
    return false;
}
/** All claims for a given post (used by post owner to review) */
function mockGetClaimsForPost(int $postId): array {
    return array_values(array_filter(mockGetClaims(), fn($c) => $c['post_id'] === $postId));
}
/** All claims submitted BY a given user (used in "My Claims" view) */
function mockGetClaimsByUser(string $userId): array {
    return array_values(array_filter(mockGetClaims(), fn($c) => $c['user_id'] === $userId));
}
/** Count pending claims on posts OWNED by a user (for notification badge) */
function mockCountIncomingPendingClaims(string $ownerId): int {
    $myPostIds = array_column(
        array_filter(mockGetPosts(), fn($p) => $p['posterId'] === $ownerId),
        'id'
    );
    $count = 0;
    foreach (mockGetClaims() as $c) {
        if (in_array($c['post_id'], $myPostIds) && $c['claim_status'] === 'pending') $count++;
    }
    return $count;
}

// ============================================================
// CONTACT  (ERD: contact_id PK, user_id FK, post_id FK,
//                contact_info, message_note, date_contacted)
// A contact record is created when a logged-in user sends
// a message to a post owner (via the detail modal).
// ============================================================
function mockGetContacts(): array {
    if (!isset($_SESSION['ftmk_contacts'])) $_SESSION['ftmk_contacts'] = [];
    return $_SESSION['ftmk_contacts'];
}
function mockSaveContacts(array $c): void { $_SESSION['ftmk_contacts'] = array_values($c); }
function mockNextContactId(): int {
    $c = mockGetContacts();
    return $c ? max(array_column($c, 'id')) + 1 : 1;
}
/** Contacts sent TO the owner of a post (inbox for post owner) */
function mockGetContactsForPost(int $postId): array {
    return array_values(array_filter(mockGetContacts(), fn($c) => $c['post_id'] === $postId));
}
/** Contacts initiated BY a user */
function mockGetContactsByUser(string $userId): array {
    return array_values(array_filter(mockGetContacts(), fn($c) => $c['user_id'] === $userId));
}
/** Count unread contacts on posts owned by a user */
function mockCountUnreadContacts(string $ownerId): int {
    $myPostIds = array_column(
        array_filter(mockGetPosts(), fn($p) => $p['posterId'] === $ownerId),
        'id'
    );
    $count = 0;
    foreach (mockGetContacts() as $c) {
        if (in_array($c['post_id'], $myPostIds) && ($c['read'] ?? false) === false) $count++;
    }
    return $count;
}

<?php
// ============================================================
// api/contacts.php — CONTACT endpoints
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../mock_db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') jsonResponse(['ok' => true]);

$action = $_GET['action'] ?? '';

function requireUser(): array {
    if (!isset($_SESSION['ftmk_user']))
        jsonResponse(['ok' => false, 'error' => 'Not logged in.'], 401);
    return $_SESSION['ftmk_user'];
}

function enrichContactDB(array $c): array {
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT ip.title, ip.type, ip.user_id AS post_owner,
                u.name, u.initials, u.avatar_color
         FROM item_post ip
         JOIN users u ON u.user_id = ?
         WHERE ip.post_id = ?'
    );
    $stmt->execute([$c['user_id'], $c['post_id']]);
    $row = $stmt->fetch();
    if ($row) {
        $c['post_title']    = $row['title'];
        $c['post_type']     = $row['type'];
        $c['post_owner']    = $row['post_owner'];
        $c['sender_name']   = $row['name'];
        $c['sender_avatar'] = $row['initials'];
        $c['sender_color']  = $row['avatar_color'];
    }
    return $c;
}

function enrichContactMock(array $c): array {
    foreach (mockGetPosts() as $p) {
        if ($p['id'] === $c['post_id']) {
            $c['post_title'] = $p['title'];
            $c['post_type']  = $p['type'];
            $c['post_owner'] = $p['posterId'];
            break;
        }
    }
    $sender = mockFindUser($c['user_id']);
    $c['sender_name']   = $sender['name']        ?? $c['user_id'];
    $c['sender_avatar'] = $sender['initials']    ?? substr($c['user_id'], 0, 2);
    $c['sender_color']  = $sender['avatarColor'] ?? 'blue';
    return $c;
}

function enrichContact(array $c): array {
    return USE_MOCK_DB ? enrichContactMock($c) : enrichContactDB($c);
}

// ------ SEND CONTACT --------------------------------------------------------
if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user    = requireUser();
    $post_id = (int)($_GET['post_id'] ?? 0);
    $body    = getRequestBody();

    $message_note = trim($body['message_note'] ?? '');
    $contact_info = trim($body['contact_info'] ?? $user['email'] ?? '');

    if (!$post_id)      jsonResponse(['ok' => false, 'error' => 'Missing post_id.'], 400);
    if (!$message_note) jsonResponse(['ok' => false, 'error' => 'Please enter a message.'], 400);

    if (USE_MOCK_DB) {
        $post = null;
        foreach (mockGetPosts() as $p) { if ($p['id'] === $post_id) { $post = $p; break; } }
        if (!$post) jsonResponse(['ok' => false, 'error' => 'Post not found.'], 404);
        if ($post['posterId'] === $user['id'])
            jsonResponse(['ok' => false, 'error' => 'You cannot contact yourself.'], 400);

        $contact = [
            'id'             => mockNextContactId(),
            'user_id'        => $user['id'],
            'post_id'        => $post_id,
            'contact_info'   => $contact_info,
            'message_note'   => $message_note,
            'date_contacted' => date('Y-m-d H:i:s'),
            'read'           => false,
        ];
        $contacts   = mockGetContacts();
        $contacts[] = $contact;
        mockSaveContacts($contacts);
    } else {
        $db   = getDB();
        $chk  = $db->prepare('SELECT user_id FROM item_post WHERE post_id = ?');
        $chk->execute([$post_id]);
        $post = $chk->fetch();
        if (!$post) jsonResponse(['ok' => false, 'error' => 'Post not found.'], 404);
        if ($post['user_id'] === $user['id'])
            jsonResponse(['ok' => false, 'error' => 'You cannot contact yourself.'], 400);

        $ins = $db->prepare(
            'INSERT INTO contact (user_id, post_id, contact_info, message_note)
             VALUES (?, ?, ?, ?)'
        );
        $ins->execute([$user['id'], $post_id, $contact_info, $message_note]);
        $contact = [
            'id'             => (int)$db->lastInsertId(),
            'user_id'        => $user['id'],
            'post_id'        => $post_id,
            'contact_info'   => $contact_info,
            'message_note'   => $message_note,
            'date_contacted' => date('Y-m-d H:i:s'),
            'is_read'        => 0,
        ];
    }

    jsonResponse(['ok' => true, 'contact' => enrichContact($contact)]);
}

// ------ INBOX ---------------------------------------------------------------
if ($action === 'inbox') {
    $user = requireUser();

    if (USE_MOCK_DB) {
        $myPostIds = array_column(
            array_filter(mockGetPosts(), fn($p) => $p['posterId'] === $user['id']),
            'id'
        );
        $contacts = array_values(array_filter(
            mockGetContacts(), fn($c) => in_array($c['post_id'], $myPostIds)
        ));
        $contacts = array_map('enrichContact', $contacts);
        usort($contacts, fn($a, $b) => strcmp($b['date_contacted'], $a['date_contacted']));
    } else {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT c.* FROM contact c
             JOIN item_post ip ON c.post_id = ip.post_id
             WHERE ip.user_id = ?
             ORDER BY c.date_contacted DESC'
        );
        $stmt->execute([$user['id']]);
        $contacts = array_map(fn($r) => enrichContact([
            'id'             => $r['contact_id'],
            'user_id'        => $r['user_id'],
            'post_id'        => $r['post_id'],
            'contact_info'   => $r['contact_info'],
            'message_note'   => $r['message_note'],
            'date_contacted' => $r['date_contacted'],
            'is_read'        => $r['is_read'],
        ]), $stmt->fetchAll());
    }

    jsonResponse(['ok' => true, 'contacts' => $contacts]);
}

// ------ SENT ----------------------------------------------------------------
if ($action === 'sent') {
    $user = requireUser();

    if (USE_MOCK_DB) {
        $contacts = array_map('enrichContact', mockGetContactsByUser($user['id']));
        usort($contacts, fn($a, $b) => strcmp($b['date_contacted'], $a['date_contacted']));
    } else {
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM contact WHERE user_id = ? ORDER BY date_contacted DESC');
        $stmt->execute([$user['id']]);
        $contacts = array_map(fn($r) => enrichContact([
            'id'             => $r['contact_id'],
            'user_id'        => $r['user_id'],
            'post_id'        => $r['post_id'],
            'contact_info'   => $r['contact_info'],
            'message_note'   => $r['message_note'],
            'date_contacted' => $r['date_contacted'],
            'is_read'        => $r['is_read'],
        ]), $stmt->fetchAll());
    }

    jsonResponse(['ok' => true, 'contacts' => $contacts]);
}

// ------ MARK READ -----------------------------------------------------------
if ($action === 'read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user       = requireUser();
    $contact_id = (int)($_GET['contact_id'] ?? 0);
    if (!$contact_id) jsonResponse(['ok' => false, 'error' => 'Missing contact_id.'], 400);

    if (USE_MOCK_DB) {
        $contacts = mockGetContacts();
        foreach ($contacts as &$c) {
            if ($c['id'] === $contact_id) { $c['read'] = true; break; }
        }
        mockSaveContacts($contacts);
    } else {
        $db = getDB();
        $db->prepare('UPDATE contact SET is_read = 1 WHERE contact_id = ?')->execute([$contact_id]);
    }

    jsonResponse(['ok' => true]);
}

// ------ BADGE ---------------------------------------------------------------
if ($action === 'badge') {
    $user = requireUser();

    if (USE_MOCK_DB) {
        $count = mockCountUnreadContacts($user['id']);
    } else {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM contact c
             JOIN item_post ip ON c.post_id = ip.post_id
             WHERE ip.user_id = ? AND c.is_read = 0'
        );
        $stmt->execute([$user['id']]);
        $count = (int)$stmt->fetchColumn();
    }

    jsonResponse(['ok' => true, 'unread' => $count]);
}

jsonResponse(['ok' => false, 'error' => 'Unknown action.'], 400);

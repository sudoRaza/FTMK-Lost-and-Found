<?php
// ============================================================
// api/posts.php — ITEM_POST + ITEM_IMAGE CRUD
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

function withImages(array $post): array {
    if (USE_MOCK_DB) {
        $post['images'] = mockGetPostImages((int)$post['id']);
    } else {
        $db   = getDB();
        $stmt = $db->prepare('SELECT image_url FROM item_image WHERE post_id = ?');
        $stmt->execute([$post['id']]);
        $post['images'] = $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
    if (!isset($post['img'])) $post['img'] = $post['images'][0] ?? null;
    return $post;
}

function dbRowToPost(array $row): array {
    return [
        'id'          => (int)$row['post_id'],
        'type'        => $row['type'],
        'title'       => $row['title'],
        'cat'         => $row['category'],
        'desc'        => $row['description'],
        'loc'         => $row['location'],
        'date'        => $row['date_posted'],
        'status'      => $row['status'],
        'poster'      => $row['poster_name'],
        'posterId'    => $row['user_id'],
        'role'        => $row['poster_role'],
        'contact'     => $row['contact_email'],
        'avatar'      => $row['avatar'],
        'avatarColor' => $row['avatar_color'],
    ];
}

// ------ LIST ----------------------------------------------------------------
if ($action === 'list') {
    $type  = $_GET['type']  ?? '';
    $cat   = $_GET['cat']   ?? '';
    $q     = strtolower($_GET['q'] ?? '');
    $owner = $_GET['owner'] ?? '';

    if (USE_MOCK_DB) {
        $posts = mockGetPosts();
        if ($type)  $posts = array_filter($posts, fn($p) => $p['type']     === $type);
        if ($cat)   $posts = array_filter($posts, fn($p) => $p['cat']      === $cat);
        if ($owner) $posts = array_filter($posts, fn($p) => $p['posterId'] === $owner);
        if ($q)     $posts = array_filter($posts, fn($p) =>
            str_contains(strtolower($p['title']), $q) ||
            str_contains(strtolower($p['loc']),   $q) ||
            str_contains(strtolower($p['desc']),  $q)
        );
        $posts = array_values(array_map('withImages', $posts));
    } else {
        $db  = getDB();
        $sql = 'SELECT * FROM item_post WHERE 1=1';
        $params = [];
        if ($type)  { $sql .= ' AND type = ?';     $params[] = $type; }
        if ($cat)   { $sql .= ' AND category = ?'; $params[] = $cat; }
        if ($owner) { $sql .= ' AND user_id = ?';  $params[] = $owner; }
        if ($q)     { $sql .= ' AND (title LIKE ? OR location LIKE ? OR description LIKE ?)';
                      $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows  = $stmt->fetchAll();
        $posts = array_map(fn($r) => withImages(dbRowToPost($r)), $rows);
    }

    jsonResponse(['ok' => true, 'posts' => array_values($posts)]);
}

// ------ GET SINGLE ----------------------------------------------------------
if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['ok' => false, 'error' => 'Missing id.'], 400);

    if (USE_MOCK_DB) {
        $post = null;
        foreach (mockGetPosts() as $p) { if ($p['id'] === $id) { $post = $p; break; } }
        if (!$post) jsonResponse(['ok' => false, 'error' => 'Post not found.'], 404);
    } else {
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM item_post WHERE post_id = ?');
        $stmt->execute([$id]);
        $row  = $stmt->fetch();
        if (!$row) jsonResponse(['ok' => false, 'error' => 'Post not found.'], 404);
        $post = dbRowToPost($row);
    }

    jsonResponse(['ok' => true, 'post' => withImages($post)]);
}

// ------ CREATE --------------------------------------------------------------
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user  = requireUser();
    $body  = getRequestBody();

    $type  = $body['type']  ?? '';
    $title = trim($body['title'] ?? '');
    $cat   = $body['cat']   ?? 'Other';
    $desc  = trim($body['desc']  ?? '');
    $loc   = trim($body['loc']   ?? '');
    $date  = $body['date']  ?? date('Y-m-d');
    $img   = $body['img']   ?? null;

    if (!$title || !$desc || !$loc || !in_array($type, ['lost','found']))
        jsonResponse(['ok' => false, 'error' => 'Please fill in all required fields.'], 400);

    $validCats = ['Electronics','ID / Card','Keys','Bag / Wallet','Clothing','Stationery','Other'];
    if (!in_array($cat, $validCats)) $cat = 'Other';

    $contactEmail = str_contains($user['email'] ?? '', '@')
        ? $user['email']
        : strtolower($user['id']) . '@student.utem.edu.my';

    if (USE_MOCK_DB) {
        $newId   = mockNextPostId();
        $newPost = [
            'id'          => $newId,
            'type'        => $type,
            'title'       => $title,
            'cat'         => $cat,
            'desc'        => $desc,
            'loc'         => $loc,
            'date'        => $date,
            'status'      => 'active',
            'poster'      => $user['name'],
            'posterId'    => $user['id'],
            'role'        => $user['role'] ?? 'Student',
            'contact'     => $contactEmail,
            'avatar'      => $user['initials'],
            'avatarColor' => $user['avatarColor'] ?? 'blue',
            'img'         => $img,
        ];
        $posts = mockGetPosts();
        array_unshift($posts, $newPost);
        mockSavePosts($posts);
        if ($img) mockAddImage($newId, $img);
    } else {
        $db   = getDB();
        $stmt = $db->prepare(
            'INSERT INTO item_post
             (user_id, title, description, category, type, status, location, date_posted,
              poster_name, poster_role, contact_email, avatar, avatar_color)
             VALUES (?, ?, ?, ?, ?, "active", ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $user['id'], $title, $desc, $cat, $type, $loc, $date,
            $user['name'], $user['role'] ?? 'Student', $contactEmail,
            $user['initials'], $user['avatarColor'] ?? 'blue',
        ]);
        $newId = (int)$db->lastInsertId();

        if ($img) {
            $imgStmt = $db->prepare('INSERT INTO item_image (post_id, image_url) VALUES (?, ?)');
            $imgStmt->execute([$newId, $img]);
        }

        $newPost = [
            'id'          => $newId,
            'type'        => $type,
            'title'       => $title,
            'cat'         => $cat,
            'desc'        => $desc,
            'loc'         => $loc,
            'date'        => $date,
            'status'      => 'active',
            'poster'      => $user['name'],
            'posterId'    => $user['id'],
            'role'        => $user['role'] ?? 'Student',
            'contact'     => $contactEmail,
            'avatar'      => $user['initials'],
            'avatarColor' => $user['avatarColor'] ?? 'blue',
        ];
    }

    jsonResponse(['ok' => true, 'post' => withImages($newPost)]);
}

// ------ RESOLVE -------------------------------------------------------------
if ($action === 'resolve' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = requireUser();
    $id   = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['ok' => false, 'error' => 'Missing id.'], 400);

    if (USE_MOCK_DB) {
        $posts = mockGetPosts();
        $found = false;
        foreach ($posts as &$p) {
            if ($p['id'] === $id) {
                if ($p['posterId'] !== $user['id'])
                    jsonResponse(['ok' => false, 'error' => 'Not your post.'], 403);
                $p['status'] = 'resolved';
                $found = true;
                break;
            }
        }
        if (!$found) jsonResponse(['ok' => false, 'error' => 'Post not found.'], 404);
        mockSavePosts($posts);
    } else {
        $db   = getDB();
        $stmt = $db->prepare('SELECT user_id FROM item_post WHERE post_id = ?');
        $stmt->execute([$id]);
        $row  = $stmt->fetch();
        if (!$row) jsonResponse(['ok' => false, 'error' => 'Post not found.'], 404);
        if ($row['user_id'] !== $user['id'])
            jsonResponse(['ok' => false, 'error' => 'Not your post.'], 403);
        $db->prepare('UPDATE item_post SET status = "resolved" WHERE post_id = ?')->execute([$id]);
    }

    jsonResponse(['ok' => true]);
}

// ------ DELETE --------------------------------------------------------------
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = requireUser();
    $id   = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['ok' => false, 'error' => 'Missing id.'], 400);

    if (USE_MOCK_DB) {
        $posts  = mockGetPosts();
        $target = null;
        foreach ($posts as $p) { if ($p['id'] === $id) { $target = $p; break; } }
        if (!$target) jsonResponse(['ok' => false, 'error' => 'Post not found.'], 404);
        if ($target['posterId'] !== $user['id'])
            jsonResponse(['ok' => false, 'error' => 'Not your post.'], 403);
        mockSavePosts(array_filter($posts, fn($p) => $p['id'] !== $id));
        mockSaveImages(array_filter(mockGetImages(),   fn($i) => $i['post_id']  !== $id));
        mockSaveClaims(array_filter(mockGetClaims(),   fn($c) => $c['post_id']  !== $id));
        mockSaveContacts(array_filter(mockGetContacts(), fn($c) => $c['post_id'] !== $id));
    } else {
        $db   = getDB();
        $stmt = $db->prepare('SELECT user_id FROM item_post WHERE post_id = ?');
        $stmt->execute([$id]);
        $row  = $stmt->fetch();
        if (!$row) jsonResponse(['ok' => false, 'error' => 'Post not found.'], 404);
        if ($row['user_id'] !== $user['id'])
            jsonResponse(['ok' => false, 'error' => 'Not your post.'], 403);
        // CASCADE handles related rows
        $db->prepare('DELETE FROM item_post WHERE post_id = ?')->execute([$id]);
    }

    jsonResponse(['ok' => true]);
}

jsonResponse(['ok' => false, 'error' => 'Unknown action.'], 400);

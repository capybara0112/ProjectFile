<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/layout.php';

require_login('candidate');

$pdo         = db();
$user        = current_user();
$candidateId = (int)$user['id'];

// --- Xử lý gửi tin nhắn (AJAX POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    verify_csrf();

    $convId  = (int)($_POST['conversation_id'] ?? 0);
    $message = trim((string)($_POST['message'] ?? ''));

    if ($convId <= 0 || $message === '') {
        echo json_encode(['ok' => false, 'error' => 'Dữ liệu không hợp lệ']);
        exit;
    }

    // Kiểm tra conversation thuộc về candidate này
    $chk = $pdo->prepare('SELECT id FROM conversations WHERE id = :id AND candidate_id = :cid LIMIT 1');
    $chk->execute([':id' => $convId, ':cid' => $candidateId]);
    if (!$chk->fetch()) {
        echo json_encode(['ok' => false, 'error' => 'Không có quyền']);
        exit;
    }

    $stmt = $pdo->prepare('
        INSERT INTO messages (conversation_id, sender_id, receiver_id, message, is_read)
        SELECT :conv_id, :sender_id, employer_id, :message, 0
        FROM conversations WHERE id = :conv_id2
    ');
    $stmt->execute([
        ':conv_id'   => $convId,
        ':sender_id' => $candidateId,
        ':message'   => $message,
        ':conv_id2'  => $convId,
    ]);

    $newId = (int)$pdo->lastInsertId();

    // Thông báo cho employer
    $convRow = $pdo->prepare('SELECT employer_id FROM conversations WHERE id = :id LIMIT 1');
    $convRow->execute([':id' => $convId]);
    $convData = $convRow->fetch();
    if ($convData) {
        notify_user($pdo, (int)$convData['employer_id'], "Ứng viên " . $user['name'] . " đã gửi tin nhắn mới.");
    }

    echo json_encode(['ok' => true, 'id' => $newId, 'created_at' => date('H:i d/m/Y')]);
    exit;
}
// --- Đánh dấu đã đọc khi mở conversation ---
$activeConvId = (int)($_GET['conv'] ?? 0);

if ($activeConvId > 0) {
    $markReadStmt = $pdo->prepare('
        UPDATE messages 
        SET is_read = 1
        WHERE conversation_id = :cid 
          AND sender_id != :uid
    ');

    $markReadStmt->execute([
        'cid' => $activeConvId,
        'uid' => $candidateId
    ]);
}

// --- Lấy danh sách conversations ---
$convStmt = $pdo->prepare('
    SELECT
        c.id AS conv_id,
        c.created_at AS conv_created,
        a.id AS application_id,
        j.title AS job_title,
        u.id AS employer_user_id,
        u.name AS employer_name,
        u.avatar AS employer_avatar,

        (SELECT COUNT(*) 
         FROM messages m 
         WHERE m.conversation_id = c.id 
           AND m.sender_id != :uid_unread 
           AND m.is_read = 0) AS unread_count,

        (SELECT m2.message 
         FROM messages m2 
         WHERE m2.conversation_id = c.id 
         ORDER BY m2.created_at DESC 
         LIMIT 1) AS last_message,

        (SELECT m3.created_at 
         FROM messages m3 
         WHERE m3.conversation_id = c.id 
         ORDER BY m3.created_at DESC 
         LIMIT 1) AS last_time

    FROM conversations c
    JOIN applications a ON a.id = c.application_id
    JOIN jobs j ON j.id = a.job_id
    JOIN users u ON u.id = c.employer_id
    WHERE c.candidate_id = :uid_candidate
    ORDER BY last_time DESC
');

$convStmt->execute([
    'uid_unread'    => $candidateId,
    'uid_candidate' => $candidateId
]);

$conversations = $convStmt->fetchAll();

// --- Lấy messages của conversation đang mở ---
$messages      = [];
$activeConv    = null;
if ($activeConvId > 0) {
    $acStmt = $pdo->prepare('
        SELECT c.id, c.employer_id, j.title AS job_title, u.name AS employer_name, u.avatar AS employer_avatar
        FROM conversations c
        JOIN applications a ON a.id = c.application_id
        JOIN jobs j ON j.id = a.job_id
        JOIN users u ON u.id = c.employer_id
        WHERE c.id = :id AND c.candidate_id = :cid
        LIMIT 1
    ');
    $acStmt->execute([':id' => $activeConvId, ':cid' => $candidateId]);
    $activeConv = $acStmt->fetch();

    if ($activeConv) {
        $msgStmt = $pdo->prepare('
            SELECT m.id, m.sender_id, m.message, m.is_read, m.created_at, u.name AS sender_name, u.avatar AS sender_avatar
            FROM messages m
            JOIN users u ON u.id = m.sender_id
            WHERE m.conversation_id = :conv_id
            ORDER BY m.created_at ASC
        ');
        $msgStmt->execute([':conv_id' => $activeConvId]);
        $messages = $msgStmt->fetchAll();
    }
}

render_header('Tin nhắn', [
    'extra_head' => '<style>
.chat-sidebar{height:70vh;overflow-y:auto;border-right:1px solid #dee2e6}
.chat-body{height:55vh;overflow-y:auto;display:flex;flex-direction:column;gap:8px;padding:16px}
.msg-bubble{max-width:70%;padding:10px 14px;border-radius:18px;line-height:1.4;font-size:.92rem;word-break:break-word}
.msg-mine{background:#0d6efd;color:#fff;align-self:flex-end;border-bottom-right-radius:4px}
.msg-theirs{background:#f0f0f0;color:#222;align-self:flex-start;border-bottom-left-radius:4px}
.msg-time{font-size:.72rem;opacity:.65;margin-top:3px}
.conv-item{cursor:pointer;transition:background .15s}
.conv-item:hover,.conv-item.active{background:#e7f1ff}
.conv-item.active{border-left:3px solid #0d6efd}
.chat-input-row{padding:12px;border-top:1px solid #dee2e6}
</style>'
]);
?>

<div class="row g-0" style="min-height:70vh;border:1px solid #dee2e6;border-radius:12px;overflow:hidden">

    <!-- Sidebar: danh sách conversations -->
    <div class="col-lg-4 chat-sidebar">
        <div class="p-3 border-bottom fw-bold bg-light">
            <i class="fa-solid fa-comments me-2 text-primary"></i>Tin nhắn
        </div>
        <?php if (empty($conversations)): ?>
            <div class="p-4 text-muted text-center small">Chưa có cuộc trò chuyện nào.<br>Hãy ứng tuyển và nhà tuyển dụng sẽ liên hệ bạn.</div>
        <?php else: ?>
        <?php foreach ($conversations as $conv): ?>
        <a href="?page=chat&conv=<?= $conv['conv_id'] ?>"
           class="d-block text-decoration-none text-dark conv-item p-3 border-bottom <?= $activeConvId === (int)$conv['conv_id'] ? 'active' : '' ?>">
            <div class="d-flex align-items-center gap-2">
                <img src="<?= e($conv['employer_avatar'] ? BASE_URL . '/' . $conv['employer_avatar'] : BASE_URL . '/assets/images/default-avatar.png') ?>"
                     width="42" height="42" class="rounded-circle object-fit-cover" style="flex-shrink:0">
                <div class="flex-grow-1 overflow-hidden">
                    <div class="d-flex justify-content-between">
                        <span class="fw-semibold small"><?= e($conv['employer_name']) ?></span>
                        <span class="text-muted" style="font-size:.72rem"><?= $conv['last_time'] ? date('H:i', strtotime($conv['last_time'])) : '' ?></span>
                    </div>
                    <div class="text-muted small text-truncate"><?= e($conv['job_title']) ?></div>
                    <div class="text-truncate" style="font-size:.8rem;color:#555"><?= e((string)($conv['last_message'] ?? '')) ?></div>
                </div>
                <?php if ((int)$conv['unread_count'] > 0): ?>
                <span class="badge bg-danger rounded-pill"><?= (int)$conv['unread_count'] ?></span>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Chat area -->
    <div class="col-lg-8 d-flex flex-column">
        <?php if ($activeConv): ?>
        <!-- Header -->
        <div class="p-3 border-bottom bg-light d-flex align-items-center gap-2">
            <img src="<?= e($activeConv['employer_avatar'] ? BASE_URL . '/' . $activeConv['employer_avatar'] : BASE_URL . '/assets/images/default-avatar.png') ?>"
                 width="38" height="38" class="rounded-circle">
            <div>
                <div class="fw-semibold"><?= e($activeConv['employer_name']) ?></div>
                <div class="text-muted small"><?= e($activeConv['job_title']) ?></div>
            </div>
        </div>

        <!-- Messages -->
        <div class="chat-body" id="chatBody">
            <?php foreach ($messages as $msg): ?>
            <?php $isMine = (int)$msg['sender_id'] === $candidateId; ?>
            <div class="d-flex flex-column <?= $isMine ? 'align-items-end' : 'align-items-start' ?>">
                <div class="msg-bubble <?= $isMine ? 'msg-mine' : 'msg-theirs' ?>">
                    <?= e($msg['message']) ?>
                </div>
                <div class="msg-time"><?= date('H:i d/m', strtotime($msg['created_at'])) ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Input -->
        <div class="chat-input-row mt-auto">
            <form id="chatForm" class="d-flex gap-2">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="conversation_id" value="<?= $activeConvId ?>">
                <input type="text" name="message" id="msgInput" class="form-control" placeholder="Nhập tin nhắn..." autocomplete="off" required>
                <button type="submit" class="btn btn-primary px-3"><i class="fa-solid fa-paper-plane"></i></button>
            </form>
        </div>

        <script>
        const chatBody = document.getElementById('chatBody');
        chatBody.scrollTop = chatBody.scrollHeight;

        document.getElementById('chatForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const msgInput = document.getElementById('msgInput');
            const msg = msgInput.value.trim();
            if (!msg) return;

            const formData = new FormData(this);
            msgInput.value = '';

            try {
                const res = await fetch('', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.ok) {
                    chatBody.insertAdjacentHTML('beforeend', `
                        <div class="d-flex flex-column align-items-end">
                            <div class="msg-bubble msg-mine">${escHtml(msg)}</div>
                            <div class="msg-time">${data.created_at}</div>
                        </div>
                    `);
                    chatBody.scrollTop = chatBody.scrollHeight;
                }
            } catch(err) { console.error(err); }
        });

        function escHtml(str) {
            return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

        // Auto-refresh messages mỗi 15 giây
        setInterval(async () => {
            const res = await fetch('?page=chat&conv=<?= $activeConvId ?>&_ajax=messages&_t=' + Date.now());
            if (res.ok) {
                const html = await res.text();
                // reload nhẹ nếu có tin mới
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newBody = doc.getElementById('chatBody');
                if (newBody && newBody.children.length > chatBody.children.length) {
                    chatBody.innerHTML = newBody.innerHTML;
                    chatBody.scrollTop = chatBody.scrollHeight;
                }
            }
        }, 15000);
        </script>

        <?php else: ?>
        <div class="d-flex align-items-center justify-content-center flex-grow-1 text-muted">
            <div class="text-center">
                <i class="fa-regular fa-comments fa-3x mb-3 text-secondary"></i>
                <p>Chọn một cuộc trò chuyện để bắt đầu</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php render_footer(); ?>
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/layout.php';

require_login('candidate');

$pdo         = db();
$user        = current_user();
$candidateId = (int)$user['id'];

// --- Lấy context của ứng viên để đưa vào chatbot ---
$profileStmt = $pdo->prepare('
    SELECT cp.experience, cp.education, cp.career_goal, cp.experience_years, cp.address,
           cp.expected_salary, cp.legacy_skills
    FROM candidate_profiles cp
    WHERE cp.user_id = :uid LIMIT 1
');
$profileStmt->execute([':uid' => $candidateId]);
$profile = $profileStmt->fetch() ?: [];

// Kỹ năng
$skillsStmt = $pdo->prepare('
    SELECT s.name FROM user_skills us JOIN skills s ON s.id = us.skill_id WHERE us.user_id = :uid
');
$skillsStmt->execute([':uid' => $candidateId]);
$skillNames = array_column($skillsStmt->fetchAll(), 'name');

// Lịch sử ứng tuyển
$appStmt = $pdo->prepare('
    SELECT j.title, a.status FROM applications a
    JOIN jobs j ON j.id = a.job_id
    WHERE a.candidate_id = :uid ORDER BY a.apply_date DESC LIMIT 5
');
$appStmt->execute([':uid' => $candidateId]);
$appHistory = $appStmt->fetchAll();

$jobsStmt = $pdo->prepare('
    SELECT j.id, j.title, j.salary_min, j.salary_max, j.salary_type,
           co.name AS company_name,
           COALESCE(p.name, cb.legacy_province, cb.full_address) AS location,
           GROUP_CONCAT(DISTINCT js.skill_name ORDER BY js.skill_name SEPARATOR ", ") AS required_skills,
           GROUP_CONCAT(DISTINCT cat.name ORDER BY cat.name SEPARATOR ", ") AS categories
    FROM jobs j
    JOIN companies co ON co.id = j.company_id
    LEFT JOIN company_branches cb ON cb.id = j.branch_id
    LEFT JOIN provinces p ON p.id = cb.province_id
    LEFT JOIN job_skills js ON js.job_id = j.id
    LEFT JOIN job_categories jc ON jc.job_id = j.id
    LEFT JOIN categories cat ON cat.id = jc.category_id
    WHERE j.status = "approved"
    GROUP BY j.id, j.title, j.salary_min, j.salary_max, j.salary_type, co.name, location
    ORDER BY j.created_at DESC
    LIMIT 50
');
$jobsStmt->execute();
$availableJobs = $jobsStmt->fetchAll();

// Chuẩn bị context string cho AI
$candidateContext = "Thông tin ứng viên:\n";
$candidateContext .= "- Tên: " . $user['name'] . "\n";
$candidateContext .= "- Kỹ năng: " . (empty($skillNames) ? ($profile['legacy_skills'] ?? 'Chưa cập nhật') : implode(', ', $skillNames)) . "\n";
$candidateContext .= "- Kinh nghiệm: " . ($profile['experience'] ?? 'Chưa cập nhật') . "\n";
$candidateContext .= "- Số năm kinh nghiệm: " . ($profile['experience_years'] ?? 0) . "\n";
$candidateContext .= "- Học vấn: " . ($profile['education'] ?? 'Chưa cập nhật') . "\n";
$candidateContext .= "- Địa chỉ: " . ($profile['address'] ?? 'Chưa cập nhật') . "\n";
$candidateContext .= "- Mức lương kỳ vọng: " . ($profile['expected_salary'] ? number_format((float)$profile['expected_salary'], 0, ',', '.') . ' VNĐ/tháng' : 'Chưa cập nhật') . "\n";

if (!empty($appHistory)) {
    $candidateContext .= "- Lịch sử ứng tuyển gần đây: " . implode(', ', array_map(fn($a) => $a['title'] . '(' . $a['status'] . ')', $appHistory)) . "\n";
}

$jobsContext = "\nDanh sách việc làm đang tuyển (tóm tắt):\n";
foreach ($availableJobs as $j) {
    $salary = '';
    if ($j['salary_min'] || $j['salary_max']) {
        $salary = ' | Lương: ' . ($j['salary_min'] ? number_format((float)$j['salary_min'], 0, ',', '.') : '?')
                . ' - ' . ($j['salary_max'] ? number_format((float)$j['salary_max'], 0, ',', '.') : '?') . ' VNĐ';
    }
    $jobsContext .= "- [ID:{$j['id']}] {$j['title']} tại {$j['company_name']}"
                 . ($j['location'] ? " ({$j['location']})" : '')
                 . $salary
                 . ($j['categories'] ? " | Ngành: {$j['categories']}" : '')
                 . ($j['required_skills'] ? " | Yêu cầu: {$j['required_skills']}" : '')
                 . "\n";
}

render_header('Chatbot tìm việc', [
    'extra_head' => '<style>
#chatContainer{height:60vh;overflow-y:auto;display:flex;flex-direction:column;gap:10px;padding:16px;background:#f8f9fa;border-radius:12px}
.cb-bubble{max-width:80%;padding:12px 16px;border-radius:16px;line-height:1.5;font-size:.9rem;word-break:break-word}
.cb-user{background:#0d6efd;color:#fff;align-self:flex-end;border-bottom-right-radius:4px}
.cb-bot{background:#fff;color:#222;align-self:flex-start;border-bottom-left-radius:4px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
.cb-typing{background:#fff;align-self:flex-start;padding:12px 16px;border-radius:16px;border-bottom-left-radius:4px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
.dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:#aaa;animation:blink 1.2s infinite}
.dot:nth-child(2){animation-delay:.3s}.dot:nth-child(3){animation-delay:.6s}
@keyframes blink{0%,80%,100%{opacity:.2}40%{opacity:1}}
.quick-chip{cursor:pointer;border:1px solid #0d6efd;color:#0d6efd;background:#fff;border-radius:20px;padding:4px 14px;font-size:.82rem;transition:.15s}
.quick-chip:hover{background:#0d6efd;color:#fff}
</style>'
]);
?>

<div class="row justify-content-center">
<div class="col-lg-9">

<div class="app-card">
    <div class="d-flex align-items-center gap-3 mb-4">
        <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white" style="width:46px;height:46px;flex-shrink:0">
            <i class="fa-solid fa-robot"></i>
        </div>
        <div>
            <h5 class="mb-0 fw-bold">JobBot - Trợ lý tìm việc</h5>
            <div class="text-muted small">Hỏi tôi về công việc phù hợp với bạn</div>
        </div>
    </div>

    <!-- Gợi ý nhanh -->
    <div class="d-flex flex-wrap gap-2 mb-3">
        <button class="quick-chip" onclick="sendQuick(this)">Công việc lương cao nhất</button>
        <button class="quick-chip" onclick="sendQuick(this)">Công việc IT mới nhất</button>
        <button class="quick-chip" onclick="sendQuick(this)">Công việc phù hợp kỹ năng của tôi</button>
        <button class="quick-chip" onclick="sendQuick(this)">Gợi ý theo kinh nghiệm của tôi</button>
        <button class="quick-chip" onclick="sendQuick(this)">Công việc gần địa chỉ của tôi</button>
    </div>

    <!-- Chat container -->
    <div id="chatContainer">
        <div class="d-flex align-items-start gap-2">
            <div class="cb-bubble cb-bot">
                <strong>Xin chào <?= e($user['name']) ?>! 👋</strong><br>
                Tôi là JobBot — trợ lý tìm việc thông minh của bạn.<br>
                Bạn có thể hỏi tôi về:<br>
                • Công việc phù hợp kỹ năng của bạn<br>
                • Công việc lương cao nhất<br>
                • Công việc theo ngành nghề hoặc địa điểm<br>
                • Lời khuyên cải thiện hồ sơ
            </div>
        </div>
    </div>

    <!-- Input -->
    <div class="d-flex gap-2 mt-3">
        <input type="text" id="userInput" class="form-control" placeholder="Nhập câu hỏi của bạn..." autocomplete="off">
        <button id="sendBtn" class="btn btn-primary px-4" onclick="sendMessage()">
            <i class="fa-solid fa-paper-plane"></i>
        </button>
    </div>
</div>

</div>
</div>

<script>
const CANDIDATE_CONTEXT = <?= json_encode($candidateContext, JSON_UNESCAPED_UNICODE) ?>;
const JOBS_CONTEXT      = <?= json_encode($jobsContext, JSON_UNESCAPED_UNICODE) ?>;
const BASE_URL          = <?= json_encode(BASE_URL, JSON_HEX_TAG) ?>;

const chatHistory = [];

const systemPrompt = `Bạn là JobBot, trợ lý tìm việc thông minh cho website tuyển dụng.

${CANDIDATE_CONTEXT}
${JOBS_CONTEXT}

Nhiệm vụ của bạn:
1. Gợi ý các công việc phù hợp nhất dựa trên kỹ năng, kinh nghiệm, địa điểm, mức lương kỳ vọng của ứng viên.
2. Khi gợi ý việc làm, luôn ghi rõ: tên vị trí, công ty, mức lương, vị trí, và ID để ứng viên có thể truy cập.
3. Format link xem chi tiết: ${BASE_URL}/?page=jobs&id=ID_VIEC_LAM
4. Trả lời bằng tiếng Việt, thân thiện, súc tích.
5. Nếu hỏi về kỹ năng thiếu, hãy so sánh kỹ năng ứng viên với yêu cầu công việc.
6. Không bịa đặt thông tin ngoài dữ liệu được cung cấp.`;

function escHtml(str) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

function formatBotMessage(text) {
    // Convert markdown-like formatting
    return text
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.*?)\*/g, '<em>$1</em>')
        .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" class="text-primary">$1</a>')
        .replace(/\n/g, '<br>');
}

function appendMessage(role, text) {
    const container = document.getElementById('chatContainer');
    const div = document.createElement('div');
    div.className = 'd-flex align-items-start gap-2 ' + (role === 'user' ? 'justify-content-end' : '');
    const bubble = document.createElement('div');
    bubble.className = 'cb-bubble ' + (role === 'user' ? 'cb-user' : 'cb-bot');
    bubble.innerHTML = role === 'user' ? escHtml(text) : formatBotMessage(text);
    div.appendChild(bubble);
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}

function showTyping() {
    const container = document.getElementById('chatContainer');
    const div = document.createElement('div');
    div.id = 'typingIndicator';
    div.innerHTML = `<div class="cb-typing"><span class="dot"></span><span class="dot"></span><span class="dot"></span></div>`;
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}

function removeTyping() {
    const el = document.getElementById('typingIndicator');
    if (el) el.remove();
}

let isSending = false;   // ← THÊM trước hàm (tránh spam click)
 
    async function sendMessage() {
        if (isSending) return;   // ← THÊM guard
 
        const input = document.getElementById('userInput');
        const msg = input.value.trim();
        if (!msg) return;
 
        isSending = true;
        input.value = '';
        document.getElementById('sendBtn').disabled = true;
 
        appendMessage('user', msg);
        chatHistory.push({ role: 'user', content: msg });
        showTyping();
 
        try {
            const response = await fetch(BASE_URL + '/api/chatbot_proxy.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    max_tokens: 1000,
                    messages: [
                        { role: 'system', content: systemPrompt },
                        ...chatHistory
                    ]
                })
            });
 
            const rawText = await response.text();   // ← ĐỌC TEXT TRƯỚC rồi parse
            let data;
            try {
                data = JSON.parse(rawText);
            } catch (e) {
                console.error('Proxy không trả JSON:', rawText);
                throw new Error('Proxy lỗi');
            }
 
            removeTyping();
 
            if (!response.ok) {
                let errorMsg = data.error?.message || data.error || 'Lỗi gọi API.';
                if (response.status === 429) {
                    errorMsg = 'Quá nhiều yêu cầu, vui lòng chờ rồi thử lại.';
                }
                appendMessage('assistant', errorMsg);
                return;
            }
 
            const botReply = data.choices?.[0]?.message?.content
                           || 'Xin lỗi, tôi không thể trả lời lúc này.';
            chatHistory.push({ role: 'assistant', content: botReply });
            appendMessage('assistant', botReply);
 
        } catch (err) {
            removeTyping();
            console.error(err);
            appendMessage('assistant', 'Lỗi kết nối. Mở Console để xem chi tiết.');
        }
 
        isSending = false;
        document.getElementById('sendBtn').disabled = false;
        input.focus();
    }
function sendQuick(btn) {
    document.getElementById('userInput').value = btn.textContent;
    sendMessage();
}

document.getElementById('userInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') sendMessage();
});
</script>

<?php render_footer(); ?>
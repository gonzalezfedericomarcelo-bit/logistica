<?php
// chat.php - VERSIÓN DEFINITIVA (Con Filtros Rápidos)
session_start();
include 'conexion.php';
if (!isset($_SESSION['usuario_id'])) header("Location: login.php");
include 'navbar.php'; 


$mi_id = $_SESSION['usuario_id'];

$sql_users = "
    SELECT u.id_usuario, u.nombre_completo, u.foto_perfil, u.rol,
    (SELECT MAX(fecha) FROM chat WHERE (id_usuario=u.id_usuario AND id_destino=:mi_id) OR (id_usuario=:mi_id AND id_destino=u.id_usuario)) as u_fecha,
    (SELECT COUNT(*) FROM chat WHERE id_usuario=u.id_usuario AND id_destino=:mi_id AND leido=0) as sin_leer
    FROM usuarios u WHERE u.id_usuario != :mi_id 
    ORDER BY CASE WHEN u_fecha IS NULL THEN 1 ELSE 0 END, u_fecha DESC, u.nombre_completo ASC";
$stmt = $pdo->prepare($sql_users);
$stmt->execute([':mi_id' => $mi_id]);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
    
    <style>
        html, body { height: 100%; margin: 0; padding: 0; overflow: hidden; background-color: #d1d7db; display: flex; flex-direction: column; }
        .navbar { flex-shrink: 0; z-index: 1000; }
        .chat-app-container { flex: 1; display: flex; width: 100%; max-width: 1600px; margin: 0 auto; background: white; position: relative; overflow: hidden; }
        .sidebar { width: 350px; display: flex; flex-direction: column; border-right: 1px solid #ddd; background: white; flex-shrink: 0; }
        .sidebar-header { padding: 15px; background: #f0f2f5; font-weight: bold; color: #54656f; }
        .search-box { padding: 10px; border-bottom: 1px solid #f0f0f0; }
        .contact-list { flex: 1; overflow-y: auto; }
        .contact-item { display: flex; align-items: center; padding: 12px; cursor: pointer; border-bottom: 1px solid #f0f0f0; transition:0.2s; }
        .contact-item:hover { background: #f5f5f5; }
        .contact-item.active { background: #e9edef; border-left: 4px solid #00a884; }
        .avatar { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; margin-right: 15px; }
        .unread-badge { background: #00a884; color: white; border-radius: 50%; padding: 2px 8px; font-size: 11px; margin-left: auto; min-width: 20px; text-align: center; }
        .chat-area { flex: 1; display: flex; flex-direction: column; background-color: #efe7dd; background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png'); position: relative; }
        .chat-header { height: 60px; padding: 0 15px; background: #f0f2f5; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #ddd; }
        .messages-box { flex: 1; overflow-y: auto; padding: 20px 5%; display: flex; flex-direction: column; gap: 10px; }
        .chat-footer { padding: 10px; background: #f0f2f5; display: flex; align-items: flex-end; gap: 10px; padding-bottom: calc(10px + env(safe-area-inset-bottom)); }
        .chat-input-container { flex: 1; display: flex; flex-direction: column; background: white; border-radius: 20px; overflow: hidden; border: 1px solid #ddd; }
        #editor-toolbar { background: #f9f9f9; border-bottom: 1px solid #eee; padding: 5px 10px; display: flex; flex-wrap: wrap; }
        #editor { min-height: 50px; max-height: 150px; overflow-y: auto; font-size: 16px; border: none; }
        .ql-toolbar.ql-snow { border: none !important; padding: 5px !important; }
        .ql-container.ql-snow { border: none !important; }
        .ql-editor { padding: 10px 15px !important; }
        .btn-circle { width: 45px; height: 45px; border-radius: 50%; border: none; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; cursor: pointer; transition: 0.2s; flex-shrink: 0; margin-bottom: 2px; }
        .btn-send { background-color: #00a884; color: white; }
        .btn-mic { background-color: #f0f2f5; color: #54656f; }
        .btn-stop { background-color: #dc3545; color: white; animation: pulse 1s infinite; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.7; } 100% { opacity: 1; } }
        .msg-row { display: flex; width: 100%; }
        .msg-row.me { justify-content: flex-end; }
        .bubble { max-width: 80%; padding: 8px 12px; border-radius: 10px; font-size: 15px; position: relative; box-shadow: 0 1px 1px rgba(0,0,0,0.1); background: white; word-wrap: break-word; }
        .msg-row.me .bubble { background: #dcf8c6; border-top-right-radius: 0; }
        .msg-row.other .bubble { background: #fff; border-top-left-radius: 0; }
        .msg-time { font-size: 10px; color: #999; text-align: right; margin-top: 4px; }
        .bubble p { margin-bottom: 0; }
        .highlighted-msg { animation: highlightFade 3s ease-out; background-color: #fff9c4 !important; border: 2px solid #fbc02d; }
        @keyframes highlightFade { 0% { transform: scale(1.02); } 50% { transform: scale(1); } }
        .msg-media img { max-width: 100%; border-radius: 8px; cursor: pointer; max-height: 300px; object-fit: contain; margin-bottom: 5px; }
        audio { max-width: 100%; margin-top: 5px; }
        #preview-float { position: absolute; bottom: 80px; left: 15px; right: 15px; background: white; padding: 10px; border-radius: 10px; box-shadow: 0 -2px 10px rgba(0,0,0,0.1); display: none; z-index: 50; }
        #preview-img { max-height: 150px; width: 100%; border-radius: 5px; display: none; object-fit: contain; margin-bottom: 10px; }
        #preview-audio { width: 100%; display: none; margin-bottom: 10px; }
        .toast-container { z-index: 2050; cursor: pointer; }
        .toast { background-color: #25D366; color: white; border: none; }
        .btn-back { display: none; font-size: 1.5rem; margin-right: 10px; color: #54656f; background: none; border: none; }
        
        /* ESTILOS MODAL Y FILTROS */
        .item-card { transition: transform 0.1s; border: 1px solid #eee; margin-bottom: 8px; border-radius: 8px; cursor: pointer; }
        .item-card:hover { transform: scale(1.01); background-color: #f8f9fa; border-color: #00a884; }
        .item-status-badge { font-size: 0.75rem; padding: 4px 8px; border-radius: 12px; }
        .modal-search-container { position: sticky; top: 0; background: white; z-index: 10; padding-bottom: 10px; border-bottom: 1px solid #eee; margin-bottom: 10px; display: flex; gap: 10px; }
        
        /* Chips de filtro */
        .filter-chip { font-size: 0.85rem; border-radius: 20px; padding: 5px 12px; cursor: pointer; border: 1px solid #ddd; background: #f8f9fa; transition: 0.2s; white-space: nowrap; }
        .filter-chip:hover, .filter-chip.active { background: #00a884; color: white; border-color: #00a884; }
        #filterContainer { overflow-x: auto; padding-bottom: 5px; display: none; gap: 8px; margin-bottom: 10px; }
        
        @media (max-width: 768px) {
            .sidebar { width: 100%; }
            .chat-area { position: absolute; top: 0; left: 0; width: 100%; height: 100%; transform: translateX(100%); transition: transform 0.2s; z-index: 100; }
            .chat-area.active { transform: translateX(0); }
            .btn-back { display: block; }
        }
    </style>
</head>
<body>

<div class="chat-app-container">
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">Chats</div>
        <div class="p-2"><input type="text" id="buscador" class="form-control rounded-pill border-0 bg-light" placeholder="🔍 Buscar..." onkeyup="filtrarContactos()"></div>
        <div class="contact-list">
            <div class="contact-item" id="contact-0" onclick="abrirChat(0, 'Grupo General', 'assets/grupo_icon.png', this)">
                <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center avatar"><i class="fas fa-users"></i></div>
                <div><div class="fw-bold">Grupo General</div><small class="text-muted">Todos</small></div>
            </div>
            <?php foreach($usuarios as $u): ?>
            <div class="contact-item user-item" id="contact-<?php echo $u['id_usuario']; ?>" onclick="abrirChat(<?php echo $u['id_usuario']; ?>, '<?php echo $u['nombre_completo']; ?>', 'uploads/perfiles/<?php echo $u['foto_perfil'] ?? 'default.png'; ?>', this)" data-nombre="<?php echo strtolower($u['nombre_completo']); ?>">
                <img src="uploads/perfiles/<?php echo $u['foto_perfil'] ?? 'default.png'; ?>" class="avatar">
                <div style="flex:1;"><div class="fw-bold"><?php echo $u['nombre_completo']; ?></div><small class="text-muted"><?php echo ucfirst($u['rol']); ?></small></div>
                <?php if($u['sin_leer'] > 0): ?><div class="unread-badge"><?php echo $u['sin_leer']; ?></div><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="chat-area" id="chatArea">
        <div class="chat-header">
            <div class="d-flex align-items-center">
                <button class="btn-back" onclick="cerrarChat()"><i class="fas fa-arrow-left"></i></button>
                <img src="assets/default.png" class="avatar" id="headerAvatar" style="width: 35px; height: 35px;">
                <div class="d-flex flex-column"><span class="fw-bold" id="headerName">Selecciona un chat</span><small class="text-muted" id="headerStatus"></small></div>
            </div>
        </div>

        <div class="messages-box" id="msgBox">
            <div class="text-center mt-5 opacity-50"><i class="fas fa-comments fa-4x mb-3"></i><p>Selecciona un contacto</p></div>
        </div>

        <div id="preview-float">
            <div class="d-flex flex-column align-items-center">
                <img id="preview-img" src="">
                <audio id="preview-audio" controls></audio>
                <div class="d-flex justify-content-between w-100 align-items-center mt-2">
                    <span id="preview-info"><i class="fas fa-paperclip text-primary"></i> <span id="fileName">archivo</span></span>
                    <button class="btn btn-sm btn-danger rounded-circle" onclick="cancelAllPreviews()">X</button>
                </div>
            </div>
        </div>

        <div class="chat-footer">
            <div class="dropup" style="margin-bottom: 2px;">
                <button class="btn btn-light rounded-circle text-secondary fs-5" data-bs-toggle="dropdown"><i class="fas fa-plus"></i></button>
                <ul class="dropdown-menu shadow border-0 mb-2">
                    <li><a class="dropdown-item" href="#" onclick="abrirModalItems('tareas')"><i class="fas fa-tasks text-warning me-2"></i> Tareas</a></li>
                    <li><a class="dropdown-item" href="#" onclick="abrirModalItems('pedidos')"><i class="fas fa-box text-info me-2"></i> Pedidos</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <label class="dropdown-item cursor-pointer">
                            <i class="fas fa-image text-success me-2"></i> Foto/Archivo
                            <input type="file" id="adjunto" hidden onchange="handleFile(this)">
                        </label>
                    </li>
                </ul>
            </div>

            <div class="chat-input-container shadow-sm">
                <div id="editor-toolbar">
                    <span class="ql-formats"><button class="ql-bold"></button><button class="ql-italic"></button><button class="ql-underline"></button></span>
                    <span class="ql-formats"><select class="ql-color"></select></span>
                    <span class="ql-formats"><button class="ql-list" value="bullet"></button></span>
                </div>
                <div id="editor"></div>
            </div>
            
            <div id="audio-bar" class="d-none align-items-center flex-grow-1 bg-white rounded-pill px-3 shadow-sm" style="height: 45px; margin-bottom: 2px;">
                <button class="btn btn-link text-danger p-0 me-3" onclick="cancelAudio(true)"><i class="fas fa-trash-alt"></i></button>
                <div class="recording-waves" style="flex:1; color: red; font-weight: bold;">Grabando...</div>
                <span class="text-danger fw-bold flex-grow-1 text-end" id="recTime">00:00</span>
            </div>
            
            <button id="btnAction" class="btn-circle btn-mic shadow-sm ms-2" onclick="handleAction()">
                <i class="fas fa-microphone"></i>
            </button>
        </div>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="notifToast" class="toast align-items-center border-0" role="alert"><div class="d-flex"><div class="toast-body pointer-event" id="toastBody"><i class="fas fa-bell me-2"></i> <span id="notifText"></span></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>
</div>
<audio id="notifSound" src="https://assets.mixkit.co/sfx/preview/mixkit-software-interface-start-2574.mp3" preload="auto"></audio>

<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="itemModalTitle">Seleccionar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="modal-search-container">
                    <input type="text" id="itemSearch" class="form-control rounded-pill" placeholder="🔍 Buscar..." onkeyup="onSearchInput()">
                    <button class="btn btn-outline-secondary rounded-circle" style="width: 38px; height: 38px;" onclick="toggleFilters()" title="Filtrar">
                        <i class="fas fa-filter"></i>
                    </button>
                </div>
                <div id="filterContainer" class="d-flex flex-wrap"></div>
                
                <div id="itemList" class="list-group list-group-flush"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>

<script>
    let currentChatId = -1, lastMsgId = 0, isInitialLoad = true;
    let targetMessageId = null; 
    let currentModalType = '';
    let searchTimeout = null;

    const msgBox = document.getElementById('msgBox'), btnAction = document.getElementById('btnAction');
    const miId = <?php echo $mi_id; ?>;
    const toastEl = document.getElementById('notifToast'), notifToast = new bootstrap.Toast(toastEl), notifSound = document.getElementById('notifSound');
    
    let isRecording = false, mediaRecorder, audioChunks, audioBlobFinal, recInterval;
    let currentStream = null; 

    const quill = new Quill('#editor', { modules: { toolbar: '#editor-toolbar' }, theme: 'snow', placeholder: 'Escribe un mensaje...' });
    quill.on('text-change', updateBtns);

    if(window.innerWidth > 768) abrirChat(0, 'Grupo General', 'assets/grupo_icon.png', document.getElementById('contact-0'));

    window.onload = function() {
        const urlParams = new URLSearchParams(window.location.search);
        const paramChatId = urlParams.get('chat_id');
        const paramMsgId = urlParams.get('msg_id');
        if (paramChatId) {
            targetMessageId = paramMsgId ? parseInt(paramMsgId) : null;
            const contactEl = document.getElementById('contact-' + paramChatId);
            if (contactEl) { contactEl.click(); if(window.innerWidth <= 768) document.getElementById('chatArea').classList.add('active'); }
        }
        
        const itemModal = document.getElementById('itemModal');
        itemModal.addEventListener('shown.bs.modal', function () {
            document.getElementById('itemSearch').focus();
        });
    };

    function updateBtns() {
        const textLen = quill.getText().trim().length;
        if(textLen > 0 || audioBlobFinal || document.getElementById('adjunto').value) {
            btnAction.className = "btn-circle btn-send shadow-sm ms-2";
            btnAction.innerHTML = '<i class="fas fa-paper-plane"></i>';
            btnAction.onclick = enviarMensaje;
        } else {
            if(isRecording) {
                btnAction.className = "btn-circle btn-stop bg-danger text-white shadow-sm ms-2";
                btnAction.innerHTML = '<i class="fas fa-stop"></i>';
                btnAction.onclick = detenerGrabacion;
            } else {
                btnAction.className = "btn-circle btn-mic shadow-sm ms-2";
                btnAction.innerHTML = '<i class="fas fa-microphone"></i>';
                btnAction.onclick = iniciarGrabacion;
            }
        }
    }

    function handleAction() {
        if(isRecording) detenerGrabacion();
        else if(quill.getText().trim().length > 0 || audioBlobFinal || document.getElementById('adjunto').files[0]) enviarMensaje();
        else iniciarGrabacion();
    }

    function abrirChat(id, nombre, foto, el) {
        currentChatId = id; lastMsgId = 0; isInitialLoad = true;
        document.getElementById('headerName').innerText = nombre;
        document.getElementById('headerAvatar').src = foto;
        document.getElementById('headerStatus').innerText = (id===0) ? 'Grupo' : 'En línea';
        document.getElementById('chatArea').classList.add('active');
        document.querySelectorAll('.contact-item').forEach(c => c.classList.remove('active'));
        if(el) { el.classList.add('active'); if(el.querySelector('.unread-badge')) el.querySelector('.unread-badge').remove(); }
        if(id > 0) { const fd=new FormData(); fd.append('remitente_id', id); fetch('chat_marcar_leido.php', {method:'POST', body:fd}); }
        msgBox.innerHTML = '<div class="text-center mt-5"><div class="spinner-border text-success"></div></div>';
        cargarMensajes();
    }
    function cerrarChat() { document.getElementById('chatArea').classList.remove('active'); currentChatId = -1; }

    function cargarMensajes() {
        if(currentChatId === -1) return;
        const audios = document.querySelectorAll('audio'); for(let a of audios) { if(!a.paused) return; }

        fetch(`chat_fetch.php?chat_id=${currentChatId}&last_id=${lastMsgId}`)
            .then(r => r.json())
            .then(data => {
                if(lastMsgId === 0) msgBox.innerHTML = '';
                data.forEach(msg => {
                    const div = document.createElement('div');
                    div.className = `msg-row ${msg.es_mio ? 'me' : 'other'}`;
                    div.id = `msg-${msg.id}`; 
                    div.innerHTML = `<div class="bubble">${!msg.es_mio && currentChatId===0 ? `<div class="fw-bold text-success small mb-1">${msg.nombre}</div>` : ''}${msg.media_html ? `<div class="msg-media">${msg.media_html}</div>` : ''}<div>${msg.mensaje_html}</div><div class="msg-time">${msg.hora} ${msg.es_mio?'<i class="fas fa-check-double text-primary"></i>':''}</div></div>`;
                    msgBox.appendChild(div);
                    if (!isInitialLoad && !msg.es_mio) {
                        document.getElementById('notifText').innerText = `💬 ${msg.remitente_nombre}: ${msg.mensaje_plain.substring(0,30)}...`;
                        document.getElementById('toastBody').onclick = () => { window.location.href = `chat.php?chat_id=${msg.remitente_id}&msg_id=${msg.id}`; };
                        notifToast.show(); notifSound.play().catch(()=>{});
                    }
                    lastMsgId = msg.id;
                });
                if(data.length > 0) {
                    if(targetMessageId) { setTimeout(() => scrollToMessage(targetMessageId), 300); setTimeout(() => scrollToMessage(targetMessageId), 1000); } 
                    else { msgBox.scrollTop = msgBox.scrollHeight; }
                }
                isInitialLoad = false;
            }).catch(e=>{});
    }
    setInterval(cargarMensajes, 3000);

    function scrollToMessage(id) {
        const el = document.getElementById(`msg-${id}`);
        if(el) { el.scrollIntoView({block: "center", behavior: "smooth"}); const b = el.querySelector('.bubble'); if(b){ b.classList.remove('highlighted-msg'); void b.offsetWidth; b.classList.add('highlighted-msg'); } targetMessageId = null; }
    }

    function enviarMensaje() {
        if(currentChatId === -1) return alert("Selecciona un chat");
        const fd = new FormData();
        fd.append('destino_id', currentChatId);
        let hasContent = false;
        const htmlContent = quill.root.innerHTML; const plainText = quill.getText().trim();
        if(plainText.length > 0 || htmlContent.includes('<img')){ fd.append('mensaje', htmlContent); hasContent=true; }
        if(audioBlobFinal){ fd.append('audio', audioBlobFinal, 'voice.webm'); hasContent=true; }
        if(document.getElementById('adjunto').files[0]){ fd.append('adjunto', document.getElementById('adjunto').files[0]); hasContent=true; }
        
        if(!hasContent) return;
        btnAction.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; btnAction.disabled = true;

        fetch('chat_enviar.php', {method:'POST', body:fd})
            .then(r=>r.json())
            .then(res => { limpiarInterfazCompleta(); })
            .catch(() => { limpiarInterfazCompleta(); })
            .finally(() => { btnAction.disabled = false; updateBtns(); });
    }
    
    function limpiarInterfazCompleta() { quill.setContents([]); cancelAllPreviews(); updateBtns(); setTimeout(cargarMensajes, 500); }

    async function iniciarGrabacion() {
        try {
            currentStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mediaRecorder = new MediaRecorder(currentStream);
            audioChunks = []; 
            mediaRecorder.ondataavailable = e => audioChunks.push(e.data);
            mediaRecorder.onstop = () => {
                clearInterval(recInterval);
                audioBlobFinal = new Blob(audioChunks, { type: 'audio/webm' });
                const audioUrl = URL.createObjectURL(audioBlobFinal);
                document.getElementById('preview-float').classList.remove('d-none'); document.getElementById('preview-float').style.display = 'block';
                document.getElementById('preview-img').style.display = 'none'; 
                document.getElementById('fileName').innerText = "Nota de Voz";
                const aud = document.getElementById('preview-audio'); aud.src = audioUrl; aud.style.display = 'block'; 
                document.getElementById('audio-bar').classList.add('d-none'); document.getElementById('audio-bar').classList.remove('d-flex'); 
                document.querySelector('.chat-input-container').style.display = 'flex'; 
                isRecording = false; updateBtns();
                if(currentStream) { currentStream.getTracks().forEach(track => track.stop()); currentStream = null; }
            };
            mediaRecorder.start(); isRecording = true;
            document.querySelector('.chat-input-container').style.display = 'none'; 
            document.getElementById('audio-bar').classList.remove('d-none'); document.getElementById('audio-bar').classList.add('d-flex');
            updateBtns();
            let sec = 0; document.getElementById('recTime').innerText = "00:00";
            recInterval = setInterval(() => { sec++; const m=Math.floor(sec/60).toString().padStart(2,'0'), s=(sec%60).toString().padStart(2,'0'); document.getElementById('recTime').innerText = `${m}:${s}`; }, 1000);
        } catch(e) { alert("Micrófono bloqueado"); }
    }
    function detenerGrabacion() { if(mediaRecorder) mediaRecorder.stop(); }
    
    window.cancelAudio = (duringRecording = false) => { 
        if(currentStream) { currentStream.getTracks().forEach(track => track.stop()); currentStream = null; }
        audioBlobFinal = null; isRecording = false; 
        if(mediaRecorder && mediaRecorder.state!='inactive') { mediaRecorder.onstop = null; mediaRecorder.stop(); }
        mediaRecorder = null; clearInterval(recInterval); 
        document.getElementById('audio-bar').classList.add('d-none'); document.getElementById('audio-bar').classList.remove('d-flex'); 
        document.querySelector('.chat-input-container').style.display = 'flex'; updateBtns(); 
    };

    window.handleFile = (inp) => { 
        if(inp.files[0]) { 
            const f = inp.files[0];
            const p = document.getElementById('preview-float'); p.classList.remove('d-none'); p.style.display = 'block';    
            document.getElementById('fileName').innerText = f.name;
            document.getElementById('preview-audio').style.display = 'none';
            const imgPrev = document.getElementById('preview-img');
            if (f.type.startsWith('image/')) { const reader = new FileReader(); reader.onload = e => { imgPrev.src = e.target.result; imgPrev.style.display = 'block'; }; reader.readAsDataURL(f); } else { imgPrev.style.display = 'none'; }
            updateBtns(); 
        } 
    };
    window.cancelAllPreviews = () => { document.getElementById('adjunto').value=''; audioBlobFinal = null; document.getElementById('preview-float').classList.add('d-none'); document.getElementById('preview-float').style.display = 'none'; document.getElementById('preview-img').src = ''; document.getElementById('preview-audio').src = ''; updateBtns(); };
    
    window.filtrarContactos = () => { const t = document.getElementById('buscador').value.toLowerCase(); document.querySelectorAll('.user-item').forEach(el => el.style.display = el.dataset.nombre.includes(t) ? 'flex' : 'none'); };
    window.magicAI = () => { const t = quill.getText(); if(t.trim()) fetch('https://text.pollinations.ai/'+encodeURIComponent("Mejora formalmente: "+t)).then(r=>r.text()).then(d=>quill.setText(d.trim())); };
    
    // --- BÚSQUEDA Y FILTROS ---
    window.onSearchInput = () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            const term = document.getElementById('itemSearch').value;
            loadItems(currentModalType, term);
        }, 300);
    };

    // LÓGICA DE FILTROS
    window.toggleFilters = () => {
        const c = document.getElementById('filterContainer');
        if (c.style.display === 'flex') {
            c.style.display = 'none';
        } else {
            c.style.display = 'flex';
            if (c.innerHTML === '') loadFilterChips(); // Cargar solo si está vacío
        }
    };

    function loadFilterChips() {
        const c = document.getElementById('filterContainer');
        c.innerHTML = '<span class="text-muted small">Cargando...</span>';
        
        fetch(`chat_listar_items.php?action=filtros&tipo=${currentModalType}`)
            .then(r => r.json())
            .then(data => {
                c.innerHTML = '';
                if (data && data.length > 0) {
                    // Opción "Todos"
                    c.innerHTML += `<span class="filter-chip" onclick="applyFilter('')">Todos</span>`;
                    data.forEach(cat => {
                        c.innerHTML += `<span class="filter-chip" onclick="applyFilter('${cat}')">${cat}</span>`;
                    });
                } else {
                    c.innerHTML = '<span class="text-muted small">Sin filtros disponibles</span>';
                }
            });
    }

    window.applyFilter = (text) => {
        document.getElementById('itemSearch').value = text;
        loadItems(currentModalType, text); // Buscar con el filtro
    };

    window.abrirModalItems = (tipo) => {
        currentModalType = tipo; 
        const m = new bootstrap.Modal(document.getElementById('itemModal'));
        document.getElementById('itemModalTitle').innerText = (tipo === 'tareas' ? 'Seleccionar Tarea' : 'Seleccionar Pedido');
        document.getElementById('itemList').innerHTML = '<div class="text-center p-3"><div class="spinner-border text-primary"></div></div>';
        document.getElementById('itemSearch').value = ''; 
        document.getElementById('filterContainer').style.display = 'none'; // Ocultar filtros al abrir
        document.getElementById('filterContainer').innerHTML = ''; // Reset
        m.show();
        loadItems(tipo, '');
        
        window.insertTag = (t) => { 
            quill.insertText(quill.getSelection() ? quill.getSelection().index : quill.getLength(), t + " "); 
            m.hide(); updateBtns(); 
        };
    };

    function loadItems(tipo, query) {
        const listDiv = document.getElementById('itemList');
        listDiv.innerHTML = '<div class="text-center p-3"><div class="spinner-border text-primary"></div></div>';
        
        fetch(`chat_listar_items.php?tipo=${tipo}&q=${encodeURIComponent(query)}`)
            .then(r => r.json())
            .then(items => {
                listDiv.innerHTML = '';
                if(items.length > 0) {
                    items.forEach(item => {
                        const card = document.createElement('div');
                        card.className = 'item-card p-2 d-flex justify-content-between align-items-center';
                        card.onclick = () => { insertTag(item.tag); };
                        card.innerHTML = `
                            <div>
                                <div class="fw-bold text-dark">${item.titulo}</div>
                                <div class="text-muted small">${item.subtexto}</div>
                            </div>
                            <span class="badge bg-${item.badge_color} item-status-badge">${item.badge_text}</span>
                        `;
                        listDiv.appendChild(card);
                    });
                } else {
                    listDiv.innerHTML = '<div class="p-4 text-center text-muted">No se encontraron registros.</div>';
                }
            })
            .catch(() => { listDiv.innerHTML = '<div class="text-danger p-3 text-center">Error al cargar datos.</div>'; });
    }
</script>
</body>
</html>
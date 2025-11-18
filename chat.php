<?php
session_start();
include 'conexion.php';
// Nota: 'navbar.php' se incluye más abajo. Asegúrate de haber quitado 'session_start()' de 'navbar.php' para evitar los Notice.

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$id_usuario = $_SESSION['usuario_id'];
$nombre_usuario = $_SESSION['usuario_nombre'];
$foto_perfil = $_SESSION['usuario_perfil'];

// ID de la conversación actualmente seleccionada
$id_conversacion_activa = $_GET['id_conversacion'] ?? null;
$conversacion_activa_info = null;
$mensajes_iniciales = []; // Inicializar
$mostrar_chat_activo = false;

// 1. Cargar todas las conversaciones del usuario
$conversaciones_listado = [];
try {
    // CORRECCIÓN CLAVE DE SINTAXIS SQL (para obtener nombres)
    $sql = "SELECT 
                c.id_conversacion, 
                c.tipo, 
                c.nombre_grupo, 
                c.fecha_creacion,
                GROUP_CONCAT(u.nombre_completo SEPARATOR ', ') as nombres_participantes
            FROM conversaciones c
            -- 1. Obtenemos las conversaciones en las que el usuario actual participa
            JOIN participantes_chat pc_user ON c.id_conversacion = pc_user.id_conversacion
            -- 2. Unimos con la tabla de participantes (pc_all) y usuarios (u) para obtener TODOS los nombres
            JOIN participantes_chat pc_all ON c.id_conversacion = pc_all.id_conversacion
            JOIN usuarios u ON pc_all.id_usuario = u.id_usuario
            -- La cláusula WHERE debe ir después de todos los JOINs
            WHERE pc_user.id_usuario = :id_usuario
            -- Agrupamos por las columnas no agregadas (necesario para GROUP_CONCAT)
            GROUP BY c.id_conversacion, c.tipo, c.nombre_grupo, c.fecha_creacion
            ORDER BY c.fecha_creacion DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
    $stmt->execute();
    $conversaciones_listado = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Si hay una conversación activa, cargar su información y mensajes
    if ($id_conversacion_activa) {
        // Verificar que la conversación existe y el usuario es participante
        $sql_info = "SELECT c.id_conversacion, c.tipo, c.nombre_grupo, 
                            GROUP_CONCAT(u.nombre_completo SEPARATOR ', ') as nombres_participantes
                     FROM conversaciones c
                     JOIN participantes_chat pc ON c.id_conversacion = pc.id_conversacion
                     JOIN usuarios u ON pc.id_usuario = u.id_usuario
                     WHERE c.id_conversacion = :id_conv
                     GROUP BY c.id_conversacion";
        
        $stmt_info = $pdo->prepare($sql_info);
        $stmt_info->bindParam(':id_conv', $id_conversacion_activa, PDO::PARAM_INT);
        $stmt_info->execute();
        $conversacion_activa_info = $stmt_info->fetch();

        // Obtener IDs de participantes para una verificación más precisa
        $sql_participantes = "SELECT id_usuario FROM participantes_chat WHERE id_conversacion = :id_conv";
        $stmt_part = $pdo->prepare($sql_participantes);
        $stmt_part->bindParam(':id_conv', $id_conversacion_activa, PDO::PARAM_INT);
        $stmt_part->execute();
        $participantes_ids = $stmt_part->fetchAll(PDO::FETCH_COLUMN);

        // Si la conversación es válida y el usuario está en ella
        if ($conversacion_activa_info && in_array($id_usuario, $participantes_ids)) {
            $mostrar_chat_activo = true;
            // Cargar mensajes iniciales
            $sql_mensajes = "SELECT m.id_mensaje, m.id_emisor, m.contenido, m.fecha_envio, u.nombre_completo AS nombre_emisor
                             FROM mensajes m
                             JOIN usuarios u ON m.id_emisor = u.id_usuario
                             WHERE m.id_conversacion = :id_conv
                             ORDER BY m.fecha_envio ASC";
            $stmt_mensajes = $pdo->prepare($sql_mensajes);
            $stmt_mensajes->bindParam(':id_conv', $id_conversacion_activa, PDO::PARAM_INT);
            $stmt_mensajes->execute();
            $mensajes_iniciales = $stmt_mensajes->fetchAll(PDO::FETCH_ASSOC);

        } else {
             $id_conversacion_activa = null; // Resetear si no es válida o no autorizado
        }
    }

} catch (PDOException $e) {
    // Si persiste, mostrar error.
    die("Error al cargar conversaciones: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - Gestión Logística</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script type="module" src="https://cdn.jsdelivr.net/npm/emoji-picker-element@^1/index.js"></script>
    <style>
        /* CRÍTICO: Usar 100% de altura solo para el body */
        html, body {
            height: 100%;
            overflow: hidden; /* Evita el doble scroll */
        }
        .chat-container {
            display: flex;
            /* CRÍTICO: Usa 100% de la altura disponible debajo del navbar */
            height: 100%; 
            overflow: hidden;
            
            /* (INICIO) MODIFICACIÓN: Añadimos bordes y sombra para que se vea bien centrado */
            border: 1px solid #dee2e6;
            border-radius: .375rem;
            box-shadow: 0 .125rem .25rem rgba(0,0,0,.075);
            /* (FIN) MODIFICACIÓN */
        }
        .conversations-list {
            width: 300px; 
            border-right: 1px solid #ccc;
            overflow-y: auto;
            flex-shrink: 0;
            background-color: #f8f9fa;
            /* Si usamos 100% en chat-container, necesitamos que esto se ajuste */
            height: 100%; 
        }
        .chat-area {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            position: relative;
            /* CRÍTICO: Aseguramos que ocupe el espacio */
            height: 100%; 
        }

        /* Área de Encabezado y Pie (Input) Fijos en ESCRITORIO */
        .chat-header-fixed {
            flex-shrink: 0;
            z-index: 10;
            border-bottom: 1px solid #ccc;
        }
        
        /* (INICIO) MODIFICACIÓN: Área de Input "Vistosa" */
        .message-input-area {
            flex-shrink: 0;
            z-index: 10;
            /* Borde más notorio */
            border-top: 2px solid #0d6efd; 
            padding: 1rem 1.25rem; /* Más padding */
            background-color: #f8f9fa; /* Color de fondo suave */
            position: relative; /* Necesario para el picker de emojis */
        }
        /* (FIN) MODIFICACIÓN */

        /* Área de Mensajes Desplazable */
        .messages-display {
            flex-grow: 1;
            overflow-y: auto; 
            padding: 15px;
            background-color: #e9ecef;
        }

        /* Estilos de Burbuja */
        .message-bubble {
            padding: 8px 12px;
            border-radius: 15px;
            margin-bottom: 10px;
            max-width: 80%;
            word-wrap: break-word; 
        }
        .message-mine {
            background-color: #0d6efd;
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 2px;
        }
        .message-other {
            background-color: #ffffff;
            color: #212529;
            margin-right: auto;
            border-bottom-left-radius: 2px;
            box-shadow: 0 1px 1px rgba(0,0,0,.05);
        }
        .conversation-item.active {
            background-color: #0d6efd;
            color: white;
        }
        .conversation-item:hover {
            background-color: #dee2e6;
        }
        
        /* (INICIO) MODIFICACIÓN EMOJI: Estilos del Picker y botón */
        .emoji-button {
            font-size: 1.25rem;
            color: #6c757d;
        }
        .emoji-button:hover {
            color: #0d6efd;
        }
        
        emoji-picker {
            position: absolute;
            bottom: 100%; /* Se abre ARRIBA del input */
            right: 15px;
            width: 320px;
            height: 400px;
            display: none; /* Oculto por defecto */
            z-index: 1050;
        }
        /* (FIN) MODIFICACIÓN EMOJI */
        
        /* RESPONSIVE: Adaptación para Móviles */
        @media (max-width: 991.98px) {
            .conversations-list {
                width: 100%;
                display: <?php echo $mostrar_chat_activo ? 'none' : 'block'; ?>;
            }
            .chat-area-responsive {
                width: 100%;
                display: <?php echo $mostrar_chat_activo ? 'flex' : 'none'; ?>;
                /* Eliminamos el height del flex para que lo calcule el JS */
            }
            .chat-container {
                flex-direction: column;
                /* El JS calculará esta altura, pero forzamos 100% de la ventana por si acaso */
                height: calc(100vh - 56px); 
                
                /* (INICIO) MODIFICACIÓN: En móvil quitamos bordes/sombra */
                border: none;
                border-radius: 0;
                box-shadow: none;
                /* (FIN) MODIFICACIÓN */
            }
            
            /* CRÍTICO PARA MÓVILES: FIJAR LA BARRA DE INPUT EN LA PARTE INFERIOR */
            .message-input-area {
                /* Usamos 'fixed' para que no se mueva con el teclado */
                position: fixed; 
                bottom: 0;
                left: 0;
                right: 0;
                width: 100%;
                margin: 0;
                padding: 10px; /* Reducimos padding en móvil */
                box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.1);
                border-top: 1px solid #ccc; /* Borde más simple en móvil */
            }
            
            /* CRÍTICO: Padding extra para que el último mensaje no quede oculto bajo la barra de input fijo */
            .messages-display {
                padding-bottom: 90px; /* (MODIFICADO) Aumentado para el input más grande */
            }

            .btn-volver-mobile {
                 display: inline-block;
            }
            
            /* (INICIO) MODIFICACIÓN EMOJI: Posición del picker en móvil */
            emoji-picker {
                width: 95%;
                left: 50%;
                transform: translateX(-50%);
                height: 350px;
            }
            /* (FIN) MODIFICACIÓN EMOJI */
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="d-flex justify-content-center px-2 py-4">

    <div class="chat-container" style="width: 100%; max-width: 1400px;">
    <div class="conversations-list" id="conversations-list">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Chats</h5>
                <a href="chat_crear.php" class="btn btn-sm btn-success" title="Crear nuevo chat">
                    <i class="fas fa-plus"></i>
                </a>
            </div>
            <ul class="list-group list-group-flush">
                <?php if (empty($conversaciones_listado)): ?>
                    <li class="list-group-item text-center text-muted">No tienes chats.</li>
                <?php endif; ?>
                <?php foreach ($conversaciones_listado as $conv): ?>
                    <?php
                        // Lógica para mostrar el nombre del otro usuario en chats individuales (individuales)
                        $nombre_display = $conv['nombre_grupo'];
                        if ($conv['tipo'] === 'individual') {
                             // Usamos la columna 'nombres_participantes' obtenida en la consulta principal
                             $participantes = explode(', ', $conv['nombres_participantes']);
                             $participantes = array_filter($participantes, function($nombre) use ($nombre_usuario) {
                                 // Filtra el nombre del usuario actual para mostrar el del otro
                                 return $nombre !== $nombre_usuario;
                             });
                             // El nombre a mostrar es el del otro participante, o 'Chat Individual' si no se encuentra
                             $nombre_display = !empty($participantes) ? reset($participantes) : 'Chat Individual';
                        }
                        // Si es grupal, se usa $conv['nombre_grupo'], que es el valor inicial de $nombre_display.
                    ?>
                    <li class="list-group-item conversation-item <?php echo ($id_conversacion_activa == $conv['id_conversacion']) ? 'active' : ''; ?>">
                        <a href="chat.php?id_conversacion=<?php echo $conv['id_conversacion']; ?>" class="text-decoration-none d-block <?php echo ($id_conversacion_activa == $conv['id_conversacion']) ? 'text-white' : 'text-dark'; ?>">
                            <div class="fw-bold text-truncate"><?php echo htmlspecialchars($nombre_display); ?></div>
                            <small class="<?php echo ($id_conversacion_activa == $conv['id_conversacion']) ? 'text-white-50' : 'text-muted'; ?>">Iniciado: <?php echo (new DateTime($conv['fecha_creacion']))->format('d/m H:i'); ?></small>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="chat-area chat-area-responsive" id="chat-area">
            <?php if ($mostrar_chat_activo): ?>
                <div class="p-3 bg-white d-flex align-items-center flex-shrink-0 chat-header-fixed">
                    <a href="chat.php" class="btn btn-sm btn-light me-2 btn-volver-mobile" title="Volver a chats">
                         <i class="fas fa-arrow-left"></i>
                    </a>
                    <h5 class="mb-0 me-3 text-truncate">
                        <?php 
                            $titulo = $conversacion_activa_info['nombre_grupo'];
                            if ($conversacion_activa_info['tipo'] === 'individual') {
                                $participantes = explode(', ', $conversacion_activa_info['nombres_participantes']);
                                $participantes = array_filter($participantes, function($nombre) use ($nombre_usuario) {
                                    return $nombre !== $nombre_usuario;
                                });
                                $titulo = !empty($participantes) ? reset($participantes) : 'Chat Individual';
                            }
                            echo htmlspecialchars($titulo);
                        ?>
                    </h5>
                    <span class="badge bg-secondary flex-shrink-0"><?php echo ucfirst($conversacion_activa_info['tipo']); ?></span>
                </div>

                <div class="messages-display" id="messages-display">
                    <?php foreach ($mensajes_iniciales as $mensaje): ?>
                        <?php $is_mine = $mensaje['id_emisor'] == $id_usuario; ?>
                        <div class="d-flex <?php echo $is_mine ? 'justify-content-end' : 'justify-content-start'; ?>">
                            <div class="message-bubble <?php echo $is_mine ? 'message-mine' : 'message-other'; ?>">
                                <?php if (!$is_mine): ?>
                                    <small class="fw-bold d-block mb-1"><?php echo htmlspecialchars($mensaje['nombre_emisor']); ?></small>
                                <?php endif; ?>
                                <p class="mb-0"><?php echo htmlspecialchars($mensaje['contenido']); ?></p>
                                <small class="text-end d-block <?php echo $is_mine ? 'text-white-50' : 'text-muted'; ?>">
                                    <?php echo (new DateTime($mensaje['fecha_envio']))->format('H:i'); ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="message-input-area">
                    <emoji-picker class="light" id="emoji-picker"></emoji-picker>
                    
                    <form id="chat-form" action="chat_enviar.php" method="POST">
                        <input type="hidden" name="id_conversacion" value="<?php echo htmlspecialchars($id_conversacion_activa); ?>">
                        <div class="input-group">
                            
                            <button class="btn btn-outline-secondary emoji-button" type="button" id="emoji-toggle-button" title="Mostrar emojis">
                                <i class="far fa-smile"></i>
                            </button>
                            
                            <textarea class="form-control flex-grow-1" id="message-content" name="contenido" rows="2" placeholder="Escribe un mensaje..." required oninput="this.style.height='auto'; this.style.height=(this.scrollHeight)+'px';"></textarea>
                            
                            <button class="btn btn-primary" type="submit" id="send-button">
                                <i class="fas fa-paper-plane"></i> Enviar
                            </button>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <div class="chat-area d-flex justify-content-center align-items-center text-center">
                    <div>
                        <i class="fas fa-comments fa-5x text-muted mb-3"></i>
                        <h2>Selecciona o Inicia un Chat</h2>
                        <p class="text-muted">Elige una conversación de la lista de la izquierda o haz clic en <i class="fas fa-plus"></i> para crear una nueva.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div> </div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php if ($mostrar_chat_activo): ?>
    <script>
        // Variables globales
        const MESSAGES_DISPLAY = document.getElementById('messages-display');
        const CHAT_FORM = document.getElementById('chat-form');
        const MESSAGE_CONTENT_INPUT = document.getElementById('message-content');
        const SEND_BUTTON = document.getElementById('send-button'); 
        const CURRENT_USER_ID = <?php echo json_encode($id_usuario); ?>;
        
        let lastMessageId = 0; 
        const initialMessages = <?php echo json_encode($mensajes_iniciales ?? []); ?>;
        if (initialMessages.length > 0) {
            const messageIds = initialMessages.map(m => parseInt(m.id_mensaje)).filter(id => !isNaN(id));
            if (messageIds.length > 0) {
                lastMessageId = Math.max(...messageIds);
            }
        }
        
        // =========================================================
        // CRÍTICO MÓVIL: FUNCIÓN PARA AJUSTAR LA ALTURA EN REAL-TIME
        // Esto soluciona el conflicto del teclado virtual con 100vh.
        // =========================================================
        function setFullHeight() {
            // Calcula la altura real de la ventana menos el navbar (56px)
            const navbarHeight = 56;
            const fullHeight = window.innerHeight;
            
            // (INICIO) MODIFICACIÓN: Aplicamos el cálculo de altura al wrapper si existe, o al chat-container
            const chatWrapper = document.querySelector('.d-flex.justify-content-center.px-2.py-4');
            const chatContainer = document.querySelector('.chat-container');

            if (window.innerWidth < 992) {
                 // En móvil, usamos la lógica original para que ocupe 100vh - navbar
                 if (chatContainer) {
                     chatContainer.style.height = `${fullHeight - navbarHeight}px`;
                 }
                 if(chatWrapper) {
                     chatWrapper.style.padding = '0'; // Quitamos padding en móvil
                 }
            } else {
                // En escritorio, usamos la lógica de 80vh (definida en CSS) 
                // pero nos aseguramos que el wrapper (d-flex) tenga la altura correcta
                // para contener el chat (100vh - 56px - paddings)
                if(chatWrapper) {
                    const wrapperPaddingY = (16 * 2); // py-4 (1rem * 2)
                    chatWrapper.style.height = `${fullHeight - navbarHeight}px`;
                }
                if (chatContainer) {
                    // (INICIO) MODIFICACIÓN: En escritorio, le decimos que ocupe el 100% de la altura del wrapper
                    chatContainer.style.height = '100%';
                    // (FIN) MODIFICACIÓN
                }
            }
        }

        // 1. Función para desplazar el scroll al final
        function autoScroll() {
             // Si hay elementos, desplazar el último a la vista
            if (MESSAGES_DISPLAY.lastElementChild) {
                // Usamos 'end' para que la burbuja quede en la parte inferior visible
                MESSAGES_DISPLAY.lastElementChild.scrollIntoView({ behavior: 'smooth', block: 'end' });
            } else {
                 // Fallback: desplaza el contenedor directamente
                MESSAGES_DISPLAY.scrollTop = MESSAGES_DISPLAY.scrollHeight;
            }
        }

        // 2. Función para añadir un mensaje al DOM
        function appendMessage(msg) {
            const is_mine = msg.id_emisor == CURRENT_USER_ID;
            const messageClass = is_mine ? 'message-mine' : 'message-other';
            const alignmentClass = is_mine ? 'justify-content-end' : 'justify-content-start';
            const timestampClass = is_mine ? 'text-white-50' : 'text-muted';
            
            // Reemplazar guiones por barras para compatibilidad en algunos navegadores al crear Date
            const dbDate = new Date(msg.fecha_envio.replace(/-/g, "/")); 
            const timeString = dbDate.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });

            const senderNameHtml = is_mine ? '' : `<small class="fw-bold d-block mb-1">${msg.nombre_emisor}</small>`;

            const messageHtml = `
                <div class="d-flex ${alignmentClass}">
                    <div class="message-bubble ${messageClass}">
                        ${senderNameHtml}
                        <p class="mb-0">${msg.contenido}</p>
                        <small class="text-end d-block ${timestampClass}">
                            ${timeString}
                        </small>
                    </div>
                </div>
            `;
            MESSAGES_DISPLAY.insertAdjacentHTML('beforeend', messageHtml);
        }
        
        // 3. Función de Polling (para recibir mensajes nuevos)
        function fetchNewMessages() {
            const conversationId = <?php echo json_encode($id_conversacion_activa); ?>;
            
            if (!conversationId || conversationId <= 0) return;

            fetch(`chat_fetch.php?id_conversacion=${conversationId}&last_id=${lastMessageId}`) 
                .then(response => {
                    if (!response.ok) {
                         throw new Error(`Respuesta de red no satisfactoria: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.messages.length > 0) {
                        let shouldScroll = false;
                        // Chequea si el usuario está cerca del final (últimos 300px)
                        if (MESSAGES_DISPLAY.scrollHeight - MESSAGES_DISPLAY.scrollTop < MESSAGES_DISPLAY.clientHeight + 300) {
                            shouldScroll = true;
                        }

                        data.messages.forEach(msg => {
                            appendMessage(msg); 
                            lastMessageId = Math.max(lastMessageId, parseInt(msg.id_mensaje)); 
                        });
                        
                        if (shouldScroll) {
                            autoScroll();
                        }
                    }
                })
                .catch(error => {
                    console.error('Error en el polling de chat:', error);
                });
        }

        // 4. Lógica de Envío de Mensajes
        if (CHAT_FORM) {
            CHAT_FORM.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const contenido = MESSAGE_CONTENT_INPUT.value.trim();
                if (contenido === '') return;

                SEND_BUTTON.disabled = true;

                const formData = new FormData(CHAT_FORM);

                fetch(CHAT_FORM.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // El mensaje enviado es el primer mensaje nuevo, así que lo añadimos y actualizamos el lastMessageId
                        appendMessage(data.message_data); 
                        
                        MESSAGE_CONTENT_INPUT.value = '';
                        MESSAGE_CONTENT_INPUT.style.height='auto'; // Restablece la altura del textarea

                        lastMessageId = data.message_data.id_mensaje;
                        autoScroll(); 
                        MESSAGE_CONTENT_INPUT.focus();

                    } else if (data.error) {
                        console.error('Error al enviar mensaje:', data.error);
                        alert('Error al enviar mensaje: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error de conexión al enviar:', error);
                    alert('Error de red al enviar el mensaje. Revise la consola: ' + error.message);
                })
                .finally(() => {
                    SEND_BUTTON.disabled = false;
                });
            });
        }

        // 5. Iniciar la autocarga inicial y el Polling
        document.addEventListener('DOMContentLoaded', () => {
             // 1. Scroll inicial
             autoScroll();

             // 2. Ejecutar la corrección de altura al cargar y al cambiar el tamaño (ej. rotación, teclado)
             // Esto es clave para el móvil
             setFullHeight();
             window.addEventListener('resize', setFullHeight);
             
             // 3. Iniciar el polling de mensajes nuevos cada 3 segundos
             setInterval(fetchNewMessages, 3000);
             
             
             // --- (INICIO) MODIFICACIÓN EMOJI: Lógica del Picker ---
             const emojiPicker = document.getElementById('emoji-picker');
             const emojiButton = document.getElementById('emoji-toggle-button');
             
             if (emojiPicker && emojiButton && MESSAGE_CONTENT_INPUT) {
                 
                 // Mostrar/Ocultar el picker
                 emojiButton.addEventListener('click', (e) => {
                     e.stopPropagation();
                     const isVisible = emojiPicker.style.display === 'block';
                     emojiPicker.style.display = isVisible ? 'none' : 'block';
                 });
                 
                 // Ocultar el picker si se hace clic fuera de él
                 document.addEventListener('click', (e) => {
                     if (emojiPicker.style.display === 'block' && !emojiPicker.contains(e.target) && e.target !== emojiButton) {
                         emojiPicker.style.display = 'none';
                     }
                 });

                 // Insertar el emoji en el textarea
                 emojiPicker.addEventListener('emoji-click', event => {
                     const emoji = event.detail.unicode;
                     const textarea = MESSAGE_CONTENT_INPUT;
                     const start = textarea.selectionStart;
                     const end = textarea.selectionEnd;
                     
                     // Insertar el emoji en la posición actual del cursor
                     textarea.value = textarea.value.substring(0, start) + emoji + textarea.value.substring(end);
                     
                     // Mover el cursor después del emoji insertado
                     textarea.selectionStart = textarea.selectionEnd = start + emoji.length;
                     
                     // Ocultar el picker después de seleccionar
                     emojiPicker.style.display = 'none';
                     textarea.focus();
                 });
             }
             // --- (FIN) MODIFICACIÓN EMOJI ---
             
        });

    </script>
<?php endif; ?>
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="notificationToastContainer" style="z-index: 1080;">
</div>
</body>
</html>
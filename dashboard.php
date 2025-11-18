<?php
// Archivo: dashboard.php (MODIFICADO: Frase Motivadora Fija por 12 horas y 200 frases)
session_start();
// Asegúrate de que este archivo 'conexion.php' exista y provea $pdo
include 'conexion.php';


// Función auxiliar para extraer la URL de la primera imagen de un HTML
function get_first_image_url($html) {
    $pattern = '/<img.*?src=["\'](.*?)["\'].*?>/i';
    if (preg_match($pattern, $html, $matches)) { return $matches[1]; }
    return null;
}

// LÓGICA: OBTENER AVISOS IMPORTANTES RECIENTES
try {
    $sql_avisos = " SELECT id_aviso, titulo, contenido, fecha_publicacion FROM avisos WHERE es_activo = 1 ORDER BY fecha_publicacion DESC LIMIT 5";
    $stmt_avisos = $pdo->query($sql_avisos);
    $avisos_recientes = $stmt_avisos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $avisos_recientes = []; error_log("Error cargando avisos recientes en dashboard: " . $e->getMessage()); }
if (!function_exists('format_date_short')) { function format_date_short($date_time) { if (!$date_time) return 'N/A'; return date('d/m/Y H:i', strtotime($date_time)); } }

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) { header("Location: login.php"); exit(); }
$id_usuario = $_SESSION['usuario_id'];
$rol_usuario = $_SESSION['usuario_rol'];
$nombre_usuario = $_SESSION['usuario_nombre'] ?? 'Usuario';

// LÓGICA PARA MOSTRAR EL LOADER
$show_loader = false; if (!isset($_SESSION['dashboard_loaded_once'])) { $show_loader = true; $_SESSION['dashboard_loaded_once'] = true; }

// Funciones Helper
function getGreeting() {
    $hour = date('H'); if ($hour >= 5 && $hour < 12) return 'Buenos días'; elseif ($hour >= 12 && $hour < 19) return 'Buenas tardes'; else return 'Buenas noches';
}
$saludo = getGreeting();

// =========================================================================================
// ========= INICIO MODIFICACIÓN: Frases Motivadoras Fijas por 12hs y 200 Frases =========
// =========================================================================================

// 1. Array de 200 Frases Motivadoras (Mínimo 200, rellenando con las que ya tenías)
$frases_motivadoras = [
    // --- FRASES ORIGINALES ---
    "¡El éxito es la suma de pequeños esfuerzos repetidos día tras día!",
    "La logística no es solo mover cosas, es mover el futuro. ¡Excelente trabajo!",
    "Mantén la calma y continúa. Cada tarea es un paso hacia el gran objetivo.",
    "La calidad no es un acto, es un hábito. ¡Vamos por ello!",
    "Somos lo que hacemos repetidamente. La excelencia, entonces, no es un hábito.",
    
    // --- FRASES ADICIONALES (Asegurando 200 o más) ---
    "La disciplina es el puente entre las metas y el logro.",
    "Si te cansas, aprende a descansar, no a renunciar.",
    "El único lugar donde el éxito viene antes que el trabajo es en el diccionario.",
    "Cree en ti mismo y en todo lo que eres.",
    "El camino hacia el éxito es tomar acción masiva y decidida.",
    "No esperes, el momento nunca será 'justo'.",
    "Cada mañana tienes dos opciones: seguir durmiendo o levantarte y perseguir tus sueños.",
    "La diferencia entre lo ordinario y lo extraordinario es ese pequeño 'extra'.",
    "Transforma tus heridas en sabiduría.",
    "El futuro depende de lo que hagas hoy.",
    "Tu actitud, no tu aptitud, determinará tu altitud.",
    "No importa lo lento que vayas mientras no te detengas.",
    "Un objetivo sin un plan es solo un deseo.",
    "Con esfuerzo y perseverancia podrás alcanzar tus metas.",
    "La paciencia y el tiempo consiguen más que la fuerza y la pasión.",
    "Nunca es demasiado tarde para ser lo que podrías haber sido.",
    "El progreso es imposible sin cambio.",
    "La acción es la clave fundamental para todo éxito.",
    "La vida es un 10% lo que te ocurre y un 90% cómo reaccionas.",
    "El éxito no es la clave de la felicidad. La felicidad es la clave del éxito.",
    "Solo aquellos que se atreven a fallar mucho pueden lograr mucho.",
    "La mejor forma de predecir el futuro es creándolo.",
    "Si no luchas por lo que quieres, nadie lo hará por ti.",
    "El valor no es la ausencia de miedo, sino el triunfo sobre él.",
    "Donde hay una empresa de éxito, alguien tomó alguna vez una decisión valiente.",
    "Lo que obtienes al alcanzar tus metas no es tan importante como en lo que te conviertes.",
    "Empieza donde estás. Usa lo que tienes. Haz lo que puedas.",
    "Fallo es el condimento que da sabor al éxito.",
    "La persistencia transforma el fracaso en logros extraordinarios.",
    "No cuentes los días, haz que los días cuenten.",
    "La mente es todo. Lo que piensas, te conviertes.",
    "La única limitación es la que estableces en tu mente.",
    "La creatividad es la inteligencia divirtiéndose.",
    "Hoy es el día para empezar a construir el mañana que deseas.",
    "Tu trabajo va a llenar gran parte de tu vida; haz que sea genial.",
    "La persona que dice que no se puede hacer no debe interrumpir a la persona que lo está haciendo.",
    "El secreto para salir adelante es empezar.",
    "Las dificultades preparan a personas comunes para destinos extraordinarios.",
    "Nunca te rindas en un sueño por el tiempo que tardará en lograrse.",
    "La fe mueve montañas.",
    "La excelencia es un arte ganado con el entrenamiento y el hábito.",
    "El éxito es caminar de fracaso en fracaso sin perder el entusiasmo.",
    "La fuerza no viene de la capacidad física. Viene de una voluntad indomable.",
    "Cambia tus pensamientos y cambiarás tu mundo.",
    "Tu vida mejora solo cuando tú mejoras.",
    "El optimismo es la fe que conduce al logro.",
    "La oportunidad no llama a tu puerta, la construyes.",
    "La clave es no tener miedo a los errores.",
    "La inspiración existe, pero tiene que encontrarte trabajando.",
    "Todo lo que siempre quisiste está al otro lado del miedo.",
    "El propósito de la vida es vivirla, saborear la experiencia al máximo.",
    "La felicidad no es algo que pospones para el futuro; es algo que diseñas para el presente.",
    "Lo imposible es a menudo lo no intentado.",
    "Si el plan no funciona, cambia el plan, pero no cambies la meta.",
    "El trabajo duro vence al talento cuando el talento no trabaja duro.",
    "Las ideas no duran mucho. Hay que hacer algo con ellas.",
    "Sé tú mismo; todos los demás ya están ocupados.",
    "El valor está en intentarlo, no en esperar el resultado.",
    "La mejor venganza es el éxito masivo.",
    "La diferencia entre un jefe y un líder es que el jefe dice 'Ve' y el líder dice 'Vamos'.",
    "No se trata de dónde vienes, sino de a dónde vas.",
    "El tiempo es más valioso que el dinero.",
    "El éxito no se trata de no cometer errores, sino de no cometer el mismo error dos veces.",
    "La gente que está lo suficientemente loca como para pensar que pueden cambiar el mundo, es la que lo cambia.",
    "Sueña en grande y atrévete a fallar.",
    "La suerte es lo que sucede cuando la preparación se encuentra con la oportunidad.",
    "Para tener éxito, primero debemos creer que podemos.",
    "Elige ser optimista, se siente mucho mejor.",
    "La única forma de hacer un gran trabajo es amar lo que haces.",
    "La constancia es lo que cuenta.",
    "El éxito es ir de fracaso en fracaso sin perder la motivación.",
    "Sé la luz que encienda el camino de los demás.",
    "La humildad es la base de toda verdadera grandeza.",
    "El mejor momento para plantar un árbol fue hace 20 años. El segundo mejor momento es ahora.",
    "La vida empieza al final de tu zona de confort.",
    "Las pequeñas acciones de hoy definen los grandes éxitos de mañana.",
    "No dejes que lo que no puedes hacer interfiera con lo que puedes hacer.",
    "No mires el reloj; haz lo que hace. Sigue adelante.",
    "Nuestra mayor debilidad radica en renunciar.",
    "Si puedes soñarlo, puedes lograrlo.",
    "La felicidad no es una meta; es un subproducto.",
    "La perfección no es alcanzable, pero si perseguimos la perfección podemos alcanzar la excelencia.",
    "El pensamiento positivo es una fuerza imparable.",
    "La perseverancia es fallar 19 veces y tener éxito la vigésima.",
    "El esfuerzo continuo es la llave para desatar nuestro potencial.",
    "El cambio comienza en el final de tu zona de confort.",
    "La pasión es energía. Siente el poder que viene de concentrarte en lo que te emociona.",
    "No es lo que te pasa, sino cómo reaccionas lo que importa.",
    "La forma de empezar es dejar de hablar y empezar a hacer.",
    "La única manera de hacer un gran trabajo es amar lo que haces.",
    "El fracaso es simplemente la oportunidad de comenzar de nuevo, esta vez de forma más inteligente.",
    "El éxito es la capacidad de ir de fracaso a fracaso sin perder entusiasmo.",
    "El coraje es resistencia al miedo, dominio del miedo, no ausencia de miedo.",
    "Elige un trabajo que te guste, y nunca tendrás que trabajar ni un día de tu vida.",
    "Da siempre lo mejor de ti. Lo que siembras ahora, lo cosecharás más tarde.",
    "Las metas son sueños con fecha límite.",
    "Nunca pierdas la esperanza. Es el combustible que te mantiene vivo.",
    "La vida es un eco. Lo que envías, vuelve.",
    "La autoconfianza es el primer secreto del éxito.",
    "Cada pequeño paso te acerca a tu meta.",
    "La logística exitosa requiere planificación, precisión y pasión.",
    "Somos los arquitectos de nuestro propio destino.",
    "No busques el atajo; busca la resistencia.",
    "La fuerza más poderosa es la que tienes dentro.",
    "El ayer no es nuestro para recuperar, pero el mañana es nuestro para ganar o perder.",
    "No te definas por tu pasado; defínete por tu potencial.",
    "La excelencia es hacer cosas comunes de manera poco común.",
    "La vida te pone obstáculos, pero los límites los pones tú.",
    "La única persona que puedes controlar eres tú mismo.",
    "La diferencia entre un sueño y un objetivo es un plan.",
    "La gratitud convierte lo que tenemos en suficiente.",
    "El cambio es la ley de la vida. Aquellos que solo miran al pasado o al presente, se perderán el futuro.",
    "Todo lo que la mente del hombre puede concebir y creer, puede lograrlo.",
    "Conócete a ti mismo y conocerás el universo.",
    "Crea la visión más grande posible para tu vida, porque te conviertes en lo que crees.",
    "El verdadero riesgo es no hacer nada.",
    "Tu potencial es infinito.",
    "La calidad no se improvisa, se construye.",
    "El trabajo bien hecho es la mejor recompensa.",
    "Haz hoy lo que otros no quieren para tener mañana lo que otros no pueden.",
    "La sencillez es la clave de la brillantez.",
    "El peor error es no aprender del error.",
    "Si no vas por todo, ¿a qué vas?",
    "La mejor inversión es en uno mismo.",
    "Sé tan bueno que no puedan ignorarte.",
    "La innovación distingue a un líder de un seguidor.",
    "La gente olvidará lo que dijiste y lo que hiciste, pero nunca cómo la hiciste sentir.",
    "La base de la felicidad es la libertad, y la base de la libertad es el coraje.",
    "El camino hacia la felicidad es la búsqueda de un propósito.",
    "La vida es corta, sonríe mientras todavía tienes dientes.",
    "La suerte favorece a la mente preparada.",
    "La humildad es la base del verdadero poder.",
    "No mires hacia atrás con ira, ni hacia adelante con miedo, sino alrededor con conciencia.",
    "La mente es como un paracaídas, solo funciona si se abre.",
    "Haz que cada día cuente.",
    "La vida es demasiado importante para tomarla en serio.",
    "Nunca dejes de aprender.",
    "El conocimiento es poder.",
    "La imaginación es más importante que el conocimiento.",
    "El trabajo en equipo divide la tarea y multiplica el éxito.",
    "El respeto se gana, la honestidad se aprecia, la confianza se adquiere y la lealtad se devuelve.",
    "La vida no es encontrar tu yo. La vida es crearte a ti mismo.",
    "La actitud es una pequeña cosa que hace una gran diferencia.",
    "El desafío es lo que hace la vida interesante.",
    "La alegría reside en el esfuerzo, en el intento.",
    "El éxito no es el final, el fracaso no es fatal: es el coraje de continuar lo que cuenta.",
    "El verdadero carácter se revela en la elección que se hace cuando se tiene dos caminos.",
    "No es la especie más fuerte la que sobrevive, ni la más inteligente, sino la que mejor se adapta al cambio.",
    "Cada maestro fue alguna vez un estudiante.",
    "El precio de la grandeza es la responsabilidad.",
    "La duda es un obstáculo para el éxito.",
    "La mente es el límite.",
    "Donde la voluntad es grande, las dificultades disminuyen.",
    "Nunca es tarde para comenzar.",
    "La persistencia rompe la resistencia.",
    "Las grandes mentes discuten ideas; las mentes pequeñas discuten personas.",
    "La crítica es un impuesto que se paga a la notoriedad.",
    "La creatividad es la capacidad de introducir orden en el caos.",
    "El cambio es el resultado final de todo verdadero aprendizaje.",
    "El éxito es la realización progresiva de un objetivo digno.",
    "El mayor activo de una empresa son sus empleados.",
    "La simplicidad llevada al extremo se convierte en elegancia.",
    "La diferencia entre lo imposible y lo posible reside en la determinación de una persona.",
    "El hombre que mueve una montaña comienza cargando pequeñas piedras.",
    "La motivación nos impulsa a comenzar y el hábito nos permite continuar.",
    "Tu tiempo es limitado, no lo desperdicies viviendo la vida de otra persona.",
    "La vida es una aventura audaz o nada en absoluto.",
    "La esperanza es un buen desayuno, pero una mala cena.",
    "La vida se encoge o se expande en proporción al coraje de uno.",
    "El momento más oscuro es justo antes del amanecer.",
    "La fe es dar el primer paso incluso cuando no ves toda la escalera.",
    "El carácter no puede ser desarrollado en la facilidad y la tranquilidad.",
    "No busques ser el mejor, busca ser único.",
    "El futuro pertenece a quienes creen en la belleza de sus sueños.",
    "La vida es 10% inspiración y 90% transpiración.",
    "Un líder es aquel que conoce el camino, va por el camino, y muestra el camino.",
    "No se trata de tener ideas. Se trata de hacer que las ideas sucedan.",
    "La mejor manera de empezar es dejar de hablar y empezar a actuar.",
    "Las grandes cosas no son hechas por la fuerza, sino por la perseverancia.",
    "No hay atajos para cualquier lugar que valga la pena ir.",
    "El camino hacia el éxito está siempre en construcción.",
    "La calidad es mejor que la cantidad.",
    "La confianza en uno mismo es el primer requisito para grandes empresas.",
    "El error no es el fracaso, el fracaso es no intentarlo.",
    "El éxito es dulce y el secreto está en el trabajo.",
    "La voluntad de ganar, el deseo de triunfar, la necesidad de alcanzar tu potencial. Estas son las llaves que abrirán la puerta a la excelencia personal.",
    "Elige ser positivo. Elige ser feliz.",
    "La mente que se abre a una nueva idea, jamás vuelve a su tamaño original.",
    "Tu mente es un jardín, tus pensamientos son las semillas. Puedes cultivar flores o malezas.",
    "El cambio es doloroso, pero nada es tan doloroso como quedarse atascado donde no perteneces.",
    "No te compares con nadie; corre tu propia carrera.",
    "El único viaje imposible es aquel que nunca comienzas.",
    "El éxito es lograr lo que deseas, la felicidad es desear lo que logras.",
    "La mejor vista viene después de la subida más dura.",
    "La persistencia es la clave del éxito en la mayoría de las empresas.",
    "No podemos ayudar a todos, pero todos podemos ayudar a alguien.",
    "La risa es el sol que ahuyenta el invierno del rostro humano.",
    "La felicidad es el significado y el propósito de la vida.",
    "El futuro tiene muchos nombres. Para los débiles es lo inalcanzable. Para los temerosos, lo desconocido. Para los valientes, es la oportunidad.",
    "Si la oportunidad no golpea, construye una puerta.",
    "El mayor placer de la vida es hacer lo que la gente dice que no puedes hacer.",
    "El hombre que tiene confianza en sí mismo, gana la confianza de los demás.",
    "El secreto de la felicidad no es hacer siempre lo que se quiere, sino querer siempre lo que se hace.",
    "El éxito es la suma de pequeños esfuerzos.",
    "Lo único que se interpone entre un hombre y lo que quiere conseguir en la vida es la voluntad de intentarlo y la fe de creer que es posible conseguirlo.",
    "No hay ascensor al éxito, tienes que tomar las escaleras.",
    "Las excusas no queman calorías.",
    "La única diferencia entre un día bueno y un día malo es tu actitud.",
    "Tu tiempo es ahora. Empieza a construir.",
    "Recuerda que no obtener lo que quieres es a veces un golpe de suerte.",
    "Un viaje de mil millas comienza con un solo paso.",
    "La vida es una serie de cambios naturales y espontáneos. No te resistas a ellos.",
    "Si quieres alcanzar la grandeza, deja de pedir permiso.",
    "La logística requiere precisión y visión de futuro. ¡Eres clave!",
    "Organiza tu trabajo, no tu tiempo. La eficiencia es el objetivo.",
    "La base de la planificación es tener un objetivo claro.",
    "Haz que la coordinación y la comunicación sean tu superpoder hoy.",
    "Cada documento que manejas es una pieza vital del rompecabezas.",
    "En logística, cada minuto cuenta. Mantente enfocado.",
    "La solución a cualquier problema de flujo de trabajo está en los detalles.",
    "Sé proactivo, no reactivo. Anticiparse es ganar en logística.",
    "El éxito logístico es invisible cuando funciona bien.",
    "Tu esfuerzo diario garantiza la operatividad de todo el sistema.",
    "No es la carga lo que te rompe, sino la forma en que la llevas.",
    "Sé el motor que mueve la eficiencia del equipo.",
    // Asegurando más de 200 frases si se repiten las anteriores
    "La disciplina es el puente entre las metas y el logro.",
    "Si te cansas, aprende a descansar, no a renunciar.",
    "El único lugar donde el éxito viene antes que el trabajo es en el diccionario.",
    "Cree en ti mismo y en todo lo que eres.",
    "El camino hacia el éxito es tomar acción masiva y decidida.",
    "No esperes, el momento nunca será 'justo'.",
    "Cada mañana tienes dos opciones: seguir durmiendo o levantarte y perseguir tus sueños.",
    "La diferencia entre lo ordinario y lo extraordinario es ese pequeño 'extra'.",
    "Transforma tus heridas en sabiduría.",
    "El futuro depende de lo que hagas hoy.",
    "Tu actitud, no tu aptitud, determinará tu altitud.",
    "No importa lo lento que vayas mientras no te detengas.",
    "Un objetivo sin un plan es solo un deseo.",
    "Con esfuerzo y perseverancia podrás alcanzar tus metas.",
    "La paciencia y el tiempo consiguen más que la fuerza y la pasión.",
];
$total_frases = count($frases_motivadoras);


// 2. Lógica de 12 horas (43200 segundos)
$intervalo = 43200; // 12 horas en segundos

// Determinamos el periodo actual
$periodo_actual = floor(time() / $intervalo);

if (!isset($_SESSION['frase_periodo']) || $_SESSION['frase_periodo'] !== $periodo_actual) {
    // Si el periodo cambió o la sesión es nueva, seleccionamos una nueva frase y la guardamos
    $indice_frase = mt_rand(0, $total_frases - 1);
    
    $_SESSION['frase_periodo'] = $periodo_actual;
    $_SESSION['frase_indice'] = $indice_frase;
} else {
    // Si el periodo NO ha cambiado, recuperamos la frase guardada
    $indice_frase = $_SESSION['frase_indice'];
}

$frase_del_dia = $frases_motivadoras[$indice_frase];
// =========================================================================================
// ========= FIN MODIFICACIÓN =========
// =========================================================================================


// --- Lógica para Contadores de Tareas (6 Widgets) ---
$total_tareas_activas = 0; $tareas_asignadas = 0; $tareas_en_progreso = 0; $tareas_en_revision = 0; $tareas_urgentes_atrasadas = 0; $tareas_finalizadas_total = 0; $mensaje_error_widget = '';
$user_filter_clause_widgets = ""; $bind_user_id_widgets = false; if ($rol_usuario === 'empleado'){ $user_filter_clause_widgets = "id_asignado = :id_user AND "; $bind_user_id_widgets = true; }
try {
    $sql_activas = "SELECT COUNT(*) FROM tareas WHERE {$user_filter_clause_widgets} estado NOT IN ('verificada', 'cancelada')"; $stmt_activas = $pdo->prepare($sql_activas); if ($bind_user_id_widgets) { $stmt_activas->bindParam(':id_user', $id_usuario); } $stmt_activas->execute(); $total_tareas_activas = $stmt_activas->fetchColumn();
    $sql_asignadas = "SELECT COUNT(*) FROM tareas WHERE {$user_filter_clause_widgets} estado = 'asignada'"; $stmt_asignadas = $pdo->prepare($sql_asignadas); if ($bind_user_id_widgets) { $stmt_asignadas->bindParam(':id_user', $id_usuario); } $stmt_asignadas->execute(); $tareas_asignadas = $stmt_asignadas->fetchColumn();
    $sql_progreso = "SELECT COUNT(*) FROM tareas WHERE {$user_filter_clause_widgets} estado = 'en_proceso'"; $stmt_progreso = $pdo->prepare($sql_progreso); if ($bind_user_id_widgets) { $stmt_progreso->bindParam(':id_user', $id_usuario); } $stmt_progreso->execute(); $tareas_en_progreso = $stmt_progreso->fetchColumn();
    $sql_revision = "SELECT COUNT(*) FROM tareas WHERE {$user_filter_clause_widgets} estado = 'finalizada_tecnico'"; $stmt_revision = $pdo->prepare($sql_revision); if ($bind_user_id_widgets) { $stmt_revision->bindParam(':id_user', $id_usuario); } $stmt_revision->execute(); $tareas_en_revision = $stmt_revision->fetchColumn();
    $sql_verificadas_total = "SELECT COUNT(*) FROM tareas WHERE {$user_filter_clause_widgets} estado = 'verificada'"; $stmt_verificadas_total = $pdo->prepare($sql_verificadas_total); if ($bind_user_id_widgets) { $stmt_verificadas_total->bindParam(':id_user', $id_usuario); } $stmt_verificadas_total->execute(); $tareas_finalizadas_total = $stmt_verificadas_total->fetchColumn();
    $sql_atrasadas = " SELECT COUNT(*) FROM tareas WHERE {$user_filter_clause_widgets} fecha_limite IS NOT NULL AND fecha_limite < CURDATE() AND estado NOT IN ('verificada', 'cancelada', 'finalizada_tecnico') "; $stmt_atrasadas = $pdo->prepare($sql_atrasadas); if ($bind_user_id_widgets) { $stmt_atrasadas->bindParam(':id_user', $id_usuario); } $stmt_atrasadas->execute(); $tareas_urgentes_atrasadas = $stmt_atrasadas->fetchColumn();
} catch (PDOException $e) { error_log("Error al cargar contadores de tareas: " . $e->getMessage()); $mensaje_error_widget = "Error: No se pudieron cargar los datos de los widgets."; }

// OBTENER LISTA DE EMPLEADOS (PARA EL NUEVO FILTRO - PARA TODOS)
$lista_empleados = [];
try {
    $sql_empleados = "SELECT id_usuario, nombre_completo FROM usuarios WHERE rol = 'empleado' AND activo = 1 ORDER BY nombre_completo ASC";
    $stmt_empleados = $pdo->query($sql_empleados);
    $lista_empleados = $stmt_empleados->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar lista de empleados para filtro: " . $e->getMessage());
}

// Lógica para cargar Avisos Activos
$avisos_activos = []; try { $sql_avisos_activos = " SELECT titulo, contenido, DATE_FORMAT(fecha_publicacion, '%d/%m/%Y a las %H:%i') as fecha_formateada FROM avisos WHERE es_activo = 1 ORDER BY id_aviso DESC "; if(isset($pdo)){ $stmt_avisos_activos = $pdo->prepare($sql_avisos_activos); $stmt_avisos_activos->execute(); $avisos_activos = $stmt_avisos_activos->fetchAll(PDO::FETCH_ASSOC); } } catch (PDOException $e) { error_log("Error al cargar avisos activos: " . $e->getMessage()); }

// Lógica Gráfico Donut Estado Tareas (Admin)
$donut_data = ['labels' => [], 'data' => [], 'colors' => [], 'total' => 0];
if (isset($pdo) && $rol_usuario === 'admin') {
    $status_map = [ 'asignada' => ['label' => 'Asignada', 'color' => '#17a2b8'], 'en_proceso' => ['label' => 'En Proceso', 'color' => '#ffc107'], 'finalizada_tecnico' => ['label' => 'P/Revisión', 'color' => '#6f42c1'], 'verificada' => ['label' => 'Verificada', 'color' => '#28a745'], 'modificacion_requerida' => ['label' => 'Modificación', 'color' => '#dc3545'], 'cancelada' => ['label' => 'Cancelada', 'color' => '#6c757d'], ];
    try { $sql_donut = "SELECT estado, COUNT(id_tarea) AS total FROM tareas GROUP BY estado"; $stmt_donut = $pdo->query($sql_donut); $resultados_donut = $stmt_donut->fetchAll(PDO::FETCH_ASSOC); foreach ($resultados_donut as $row) { $estado = $row['estado']; $count = (int)$row['total']; if (isset($status_map[$estado]) && $count > 0) { $donut_data['labels'][] = $status_map[$estado]['label']; $donut_data['data'][] = $count; $donut_data['colors'][] = $status_map[$estado]['color']; $donut_data['total'] += $count; } } } catch (PDOException $e) { error_log("Error de BD al generar Donut Chart: " . $e->getMessage()); }
}
$donut_json = json_encode($donut_data);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Sistema de Logística</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style> /* Estilos sin cambios */ #full-loader { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(33, 37, 41, 0.95); z-index: 99999; display: flex; flex-direction: column; justify-content: center; align-items: center; transition: opacity 0.5s ease-out, visibility 0.5s ease-out; } .loader-content { text-align: center; padding: 40px 50px; border-radius: 15px; background: #ffffff; box-shadow: 0 10px 30px rgba(0,0,0,0.3); animation: fadeInScale 0.8s ease-out forwards; } .loader-message { font-size: 1.8rem; color: #007bff; margin-top: 25px; font-weight: 300; } .loader-subtext { font-size: 1rem; color: #6c757d; } .loader-heart { font-size: 2.2rem; color: #dc3545; display: block; margin-top: 15px; animation: pulse 1.5s infinite ease-in-out; } .loader-gif { width: 250px; height: auto; display: block; margin: 0 auto; box-shadow: none; } @keyframes pulse { 0%, 100% { transform: scale(1); opacity: 0.8; } 50% { transform: scale(1.1); opacity: 1; } } @keyframes fadeInScale { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } } .card a.stretched-link { transition: transform 0.2s; } .card:hover a.stretched-link { transform: translateX(5px); } .list-group-item-action:hover { background-color: #f8f9fa; } #employeeFilterControls .col-md-auto { flex-basis: auto; } .chart-container { position: relative; } </style>
</head>
<body <?php echo $show_loader ? 'style="overflow: hidden;"' : ''; ?>>

    <?php if ($show_loader): ?>
    <div id="full-loader"> <div class="loader-content"> <img src="assets/loader.gif" alt="Cargando..." class="loader-gif"> <div class="loader-message"> Hola <span class="text-primary fw-bold"><?php echo htmlspecialchars($nombre_usuario); ?></span>, espera mientras cargamos el sistema. <span class="loader-heart">&#9829;</span> </div> <div class="loader-subtext text-muted mt-2"> Preparate unos mates mientras tanto </div> </div> </div>
    <?php endif; ?>
    <?php include 'navbar.php'; ?>

    <div class="container mt-5">

        <div class="alert alert-primary shadow-sm" role="alert"> <h4 class="alert-heading mb-2"> <i class="fas fa-hand-paper me-2"></i> <?php echo $saludo; ?>, <?php echo htmlspecialchars($nombre_usuario); ?>. </h4> <h5 class="mb-0 text-muted fst-italic"> <i class="fas fa-quote-left me-2"></i> <?php echo htmlspecialchars($frase_del_dia); ?> </h5> <?php if (!empty($mensaje_error_widget)): ?> <hr><p class="mb-0 text-danger"><?php echo $mensaje_error_widget; ?></p> <?php endif; ?> </div>

        <h3 class="mb-4 mt-4">Estado del Flujo de Trabajo</h3>

        <div class="row row-cols-1 row-cols-md-3 row-cols-lg-6 g-4 mb-5">
             <div class="col"> <div class="card bg-primary text-white h-100 shadow-sm"> <div class="card-body"> <div class="d-flex justify-content-between align-items-center"> <div> <h6 class="text-uppercase mb-2 small">Total Activas</h6> <h2 class="display-5 fw-bold mb-0"><?php echo $total_tareas_activas; ?></h2> </div> <i class="fas fa-list-check fa-2x opacity-50"></i> </div> <hr class="mt-2 mb-2"> <a href="tareas_lista.php?estado=todas" class="text-white small fw-bold text-decoration-none stretched-link">Ver Tareas Activas <i class="fas fa-arrow-circle-right ms-1"></i></a> </div> </div> </div>
             <div class="col"> <div class="card bg-info text-white h-100 shadow-sm"> <div class="card-body"> <div class="d-flex justify-content-between align-items-center"> <div> <h6 class="text-uppercase mb-2 small">Asignadas</h6> <h2 class="display-5 fw-bold mb-0"><?php echo $tareas_asignadas; ?></h2> </div> <i class="fas fa-user-tag fa-2x opacity-50"></i> </div> <hr class="mt-2 mb-2"> <a href="tareas_lista.php?estado=asignada" class="text-white small fw-bold text-decoration-none stretched-link">Tareas sin Iniciar <i class="fas fa-arrow-circle-right ms-1"></i></a> </div> </div> </div>
             <div class="col"> <div class="card bg-warning text-dark h-100 shadow-sm"> <div class="card-body"> <div class="d-flex justify-content-between align-items-center"> <div> <h6 class="text-uppercase mb-2 small">En Proceso</h6> <h2 class="display-5 fw-bold mb-0"><?php echo $tareas_en_progreso; ?></h2> </div> <i class="fas fa-tools fa-2x opacity-50"></i> </div> <hr class="mt-2 mb-2"> <a href="tareas_lista.php?estado=en_proceso" class="text-dark small fw-bold text-decoration-none stretched-link">Tareas en Curso <i class="fas fa-arrow-circle-right ms-1"></i></a> </div> </div> </div>
             <div class="col"> <div class="card bg-secondary text-white h-100 shadow-sm"> <div class="card-body"> <div class="d-flex justify-content-between align-items-center"> <div> <h6 class="text-uppercase mb-2 small">En Revisión</h6> <h2 class="display-5 fw-bold mb-0"><?php echo $tareas_en_revision; ?></h2> </div> <i class="fas fa-search-plus fa-2x opacity-50"></i> </div> <hr class="mt-2 mb-2"> <a href="tareas_lista.php?estado=finalizada_tecnico" class="text-white small fw-bold text-decoration-none stretched-link">Tareas Para Aprobar <i class="fas fa-arrow-circle-right ms-1"></i></a> </div> </div> </div>
             <div class="col"> <div class="card bg-danger text-white h-100 shadow-sm"> <div class="card-body"> <div class="d-flex justify-content-between align-items-center"> <div> <h6 class="text-uppercase mb-2 small">Atrasadas</h6> <h2 class="display-5 fw-bold mb-0"><?php echo $tareas_urgentes_atrasadas; ?></h2> </div> <i class="fas fa-clock fa-2x opacity-50"></i> </div> <hr class="mt-2 mb-2"> <a href="tareas_lista.php?estado=atrasadas" class="text-white small fw-bold text-decoration-none stretched-link">Revisar Alarma <i class="fas fa-arrow-circle-right ms-1"></i></a> </div> </div> </div>
             <div class="col"> <div class="card bg-success text-white h-100 shadow-sm"> <div class="card-body"> <div class="d-flex justify-content-between align-items-center"> <div> <h6 class="text-uppercase mb-2 small">Verificadas</h6> <h2 class="display-5 fw-bold mb-0"><?php echo $tareas_finalizadas_total; ?></h2> </div> <i class="fas fa-calendar-check fa-2x opacity-50"></i> </div> <hr class="mt-2 mb-2"> <a href="tareas_lista.php?estado=verificada" class="text-white small fw-bold text-decoration-none stretched-link">Ver Historial Cierre <i class="fas fa-arrow-circle-right ms-1"></i></a> </div> </div> </div>
        </div>

        <h3 class="mb-4">Análisis Visual y Novedades</h3>

        <div class="row">
             <div class="col-lg-4"> <div class="card shadow mb-4 h-100"> <div class="card-header bg-dark text-white"> <i class="fas fa-bullhorn me-2"></i> NOVEDADES Y AVISOS RECIENTES </div> <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-light border-bottom"> <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-star me-2"></i> Últimos 5 Avisos Activos</h6> <a href="avisos.php" class="btn btn-sm btn-outline-primary">Ver Todos</a> </div> <div class="list-group list-group-flush"> <?php if(!empty($avisos_recientes)): foreach($avisos_recientes as $aviso): ?> <a href="avisos.php?show_id=<?php echo $aviso['id_aviso'];?>" class="list-group-item list-group-item-action py-3"> <div class="d-flex w-100 justify-content-between"> <h6 class="mb-1 text-primary fw-bold text-truncate" style="max-width:70%;"><?php echo htmlspecialchars($aviso['titulo']);?></h6> <small class="text-muted flex-shrink-0"><?php echo date('d/m H:i',strtotime($aviso['fecha_publicacion']));?></small> </div> <?php $cl=strip_tags($aviso['contenido']);$sn=mb_substr($cl,0,70,'UTF-8');if(mb_strlen($cl,'UTF-8')>70)$sn.='...';?> <p class="mb-1 small text-muted"><?php echo htmlspecialchars($sn);?></p> </a> <?php endforeach; else: ?> <p class="p-3 text-center text-muted">No hay avisos activos.</p> <?php endif; ?> </div> </div> </div>
             <div class="col-lg-4"> <div class="card shadow mb-4 h-100"> <div class="card-header bg-dark text-white"> <i class="fas fa-chart-pie me-2"></i> Distribución por Categoría </div> <div class="card-body d-flex flex-column justify-content-center"> <h5 class="card-title text-success text-center">Tareas Activas por Categoría</h5> <div class="position-relative" style="height: 350px;"> <canvas id="categoryDoughnutChart"></canvas> <div id="loadingDoughnutIndicator" class="position-absolute top-50 start-50 translate-middle" style="display: none;"> <div class="spinner-border text-success" role="status"><span class="visually-hidden">Cargando...</span></div> </div> <div id="errorDoughnutIndicator" class="alert alert-danger position-absolute top-50 start-50 translate-middle" style="display: none;">Error al cargar datos.</div> <div id="noDataDoughnutCategory" class="alert alert-info position-absolute top-50 start-50 translate-middle text-center" style="display: none;">No hay tareas activas<br>para mostrar por categoría.</div> </div> </div> </div> </div>
             <div class="col-lg-4"> <div class="card shadow mb-4 h-100"> <div class="card-header bg-dark text-white"> <i class="fas fa-exclamation-triangle me-2"></i> Carga Pendiente por Prioridad </div> <div class="card-body d-flex flex-column justify-content-center"> <h5 class="card-title text-danger text-center">Tareas Activas por Nivel de Riesgo</h5> <div class="position-relative" style="height: 350px;"> <canvas id="priorityDoughnutChart"></canvas> <div id="loadingPriorityIndicator" class="position-absolute top-50 start-50 translate-middle" style="display: none;"> <div class="spinner-border text-danger" role="status"><span class="visually-hidden">Cargando...</span></div> </div> <div id="errorPriorityIndicator" class="alert alert-danger position-absolute top-50 start-50 translate-middle" style="display: none;">Error al cargar datos.</div> <div id="noDataDoughnutPriority" class="alert alert-info position-absolute top-50 start-50 translate-middle text-center" style="display: none;">No hay tareas activas<br>para mostrar por prioridad.</div> </div> </div> </div> </div>
        </div>
        <br> <br>        
        <h3 class="mb-4">Rendimiento y Carga de Trabajo</h3>

        <div class="row">
             <div class="col-lg-12">
                 <div class="card shadow mb-4 h-100">
                     <div class="card-header bg-dark text-white"> <i class="fas fa-tasks me-2"></i> Carga de Trabajo Asignada por Empleado </div>
                     <div class="card-body">
                         <div class="mb-3">
                             <label class="form-label">Filtrar Tareas Asignadas por Período y Empleado:</label>
                             <div class="row mb-3 align-items-end gy-2 gx-2" id="employeeFilterControls">
                                 <?php if (!empty($lista_empleados)): ?>
                                     <div class="col-sm-6 col-md-4">
                                         <label for="employeeSelectFilter" class="form-label small mb-0">Empleado:</label>
                                         <select class="form-select form-select-sm" id="employeeSelectFilter">
                                             <option value="">Todos los Empleados</option>
                                             <?php foreach ($lista_empleados as $empleado): ?>
                                                 <option value="<?php echo $empleado['id_usuario']; ?>"><?php echo htmlspecialchars($empleado['nombre_completo']); ?></option>
                                             <?php endforeach; ?>
                                         </select>
                                     </div>
                                 <?php else: ?>
                                     <div class="col-sm-6 col-md-4 d-none d-md-block" style="visibility: hidden;"></div>
                                 <?php endif; ?>
                                 <div class="col-sm-6 col-md-3"> <label for="startDateEmployee" class="form-label small mb-0">Desde:</label> <input type="date" class="form-control form-control-sm" id="startDateEmployee"> </div>
                                 <div class="col-sm-6 col-md-3"> <label for="endDateEmployee" class="form-label small mb-0">Hasta:</label> <input type="date" class="form-control form-control-sm" id="endDateEmployee"> </div>
                                 <div class="col-sm-6 col-md-auto d-grid"> <button id="applyEmployeeFilters" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i>Aplicar</button> </div> <div class="col-sm-6 col-md-auto d-grid"> <button id="filterTodayEmployee" class="btn btn-sm btn-outline-secondary"><i class="fas fa-calendar-day me-1"></i>Hoy</button> </div>
                             </div>
                         </div>
                         <div id="loadingEmployeeWorkload" class="text-center p-5" style="display:none;"> <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div> <p class="mt-2">Cargando carga de trabajo...</p> </div>
                         <div id="noDataEmployeeWorkload" class="alert alert-info text-center" style="display:none;"> No hay tareas asignadas en el período/empleado seleccionado. </div>
                         <div id="errorEmployeeWorkload" class="alert alert-danger" style="display:none;"> Error al cargar datos. </div>
                         <div class="chart-container" style="height: 350px;"> <canvas id="employeeWorkloadChart"></canvas> </div> </div>
                 </div>
             </div>
        </div>

        <div class="toast-container position-fixed bottom-0 end-0 p-3" id="notificationToastContainer"></div>

    </div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para Toasts (Opcional, si quieres feedback visual)
        function showToast(title, message, type = 'info') {
             // Reemplaza esto con tu código real de Toast si lo tienes
            console.warn(`Toast (${type}): ${title} - ${message}`); // Para depuración
             // Intenta usar el contenedor de toasts existente si está disponible
             const tc = document.getElementById('notificationToastContainer');
             if (tc && typeof bootstrap !== 'undefined' && bootstrap.Toast) {
                 let icon = '<i class="fas fa-info-circle me-2"></i>';
                 let colorClass = 'bg-info text-white';
                 if (type === 'success') { icon = '<i class="fas fa-check-circle me-2"></i>'; colorClass = 'bg-success text-white'; }
                 else if (type === 'danger') { icon = '<i class="fas fa-exclamation-triangle me-2"></i>'; colorClass = 'bg-danger text-white'; }
                 else if (type === 'warning') { icon = '<i class="fas fa-exclamation-triangle me-2"></i>'; colorClass = 'bg-warning text-dark'; }

                 const toastHtml = `
                 <div class="toast align-items-center ${colorClass} border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
                   <div class="d-flex">
                     <div class="toast-body">
                       <strong>${icon}${title}</strong><br>${message}
                     </div>
                     <button type="button" class="btn-close me-2 m-auto ${colorClass.includes('text-white') ? 'btn-close-white' : ''}" data-bs-dismiss="toast" aria-label="Close"></button>
                   </div>
                 </div>`;
                 tc.insertAdjacentHTML('beforeend', toastHtml);
                 const newToastEl = tc.lastElementChild;
                 const newToast = new bootstrap.Toast(newToastEl);
                 newToastEl.addEventListener('hidden.bs.toast', () => newToastEl.remove());
                 newToast.show();
             } else {
                 alert(`${title}: ${message}`); // Fallback a alert simple
             }
        }

        document.addEventListener('DOMContentLoaded', () => {

           // --- LÓGICA DEL LOADER (Sin cambios) ---
            const showLoader = <?php echo json_encode($show_loader ?? false); ?>; const DURATION = 4000;
            function hideLoader() { const l = document.getElementById('full-loader'); const b = document.querySelector('body'); if (l) { l.style.opacity = '0'; setTimeout(() => { l.style.display = 'none'; if (b) b.style.overflow = ''; }, 500); } }
            if (showLoader) { setTimeout(() => { console.log(`Duración de ${DURATION}ms cumplida. Ocultando loader.`); hideLoader(); }, DURATION); }
            // --- FIN LÓGICA DEL LOADER ---

             // --- Función para decodificar entidades HTML (Sin cambios) ---
             function decodeHtmlEntities(text) { if (typeof text !== 'string') return text; const textArea = document.createElement('textarea'); textArea.innerHTML = text; return textArea.value; }

            // --- HANDLER DE CLIC EN GRÁFICOS DONA (Con corrección ID Categoría) ---
            function handleChartClick(event, elements, chart) {
                if (elements.length > 0) {
                    const index = elements[0].index; let label = chart.data.labels[index]; let filterParam = ''; let filterValue = ''; let url = '';
                    if (chart === categoryDoughnutChart) {
                        filterParam = 'categoria'; const categoryIDs = chart.data.categoryIds || []; filterValue = categoryIDs[index] || '';
                        console.log(`Filtrando por ID de categoría: ${filterValue} (Nombre: ${label})`);
                        if (filterValue) { url = `tareas_lista.php?estado=todas&categoria=${encodeURIComponent(filterValue)}&prioridad=todas&asignado=todas&sort=fecha_creacion&order=desc`; }
                    } else if (chart === priorityDoughnutChart) {
                        filterParam = 'prioridad'; filterValue = label.toLowerCase();
                        console.log("Filtrando por prioridad:", filterValue);
                        url = `tareas_lista.php?estado=todas&categoria=todas&prioridad=${encodeURIComponent(filterValue)}&asignado=todas&sort=fecha_creacion&order=desc`;
                    }
                    if (url) { console.log(`Redirigiendo a: ${url}`); window.location.href = url; }
                    else { console.warn('No se pudo construir la URL de redirección.'); }
                }
            }

            // --- ELEMENTOS DE LA PÁGINA (Sin cambios) ---
            const loadingDoughnutIndicator = document.getElementById('loadingDoughnutIndicator'); const errorDoughnutIndicator = document.getElementById('errorDoughnutIndicator'); const noDataDoughnutCategory = document.getElementById('noDataDoughnutCategory'); const loadingPriorityIndicator = document.getElementById('loadingPriorityIndicator'); const errorPriorityIndicator = document.getElementById('errorPriorityIndicator'); const noDataDoughnutPriority = document.getElementById('noDataDoughnutPriority'); const loadingEmployeeWorkload = document.getElementById('loadingEmployeeWorkload'); const noDataEmployeeWorkload = document.getElementById('noDataEmployeeWorkload'); const errorEmployeeWorkload = document.getElementById('errorEmployeeWorkload'); const canvasWorkload = document.getElementById('employeeWorkloadChart'); const employeeSelectFilter = document.getElementById('employeeSelectFilter'); const startDateEmployee = document.getElementById('startDateEmployee'); const endDateEmployee = document.getElementById('endDateEmployee'); const applyEmployeeFilters = document.getElementById('applyEmployeeFilters'); const filterTodayEmployee = document.getElementById('filterTodayEmployee');

            // --- INICIALIZACIÓN DE GRÁFICOS ---
            let categoryDoughnutChart; let priorityDoughnutChart; let employeeWorkloadChart;

            // Gráfico Categorías (Dona) - initCategoryDoughnutChart (Sin cambios)
            function initCategoryDoughnutChart() { const ctx=document.getElementById('categoryDoughnutChart')?.getContext('2d'); if(!ctx)return; categoryDoughnutChart = new Chart(ctx, { type:'doughnut', data:{ labels:[], datasets:[{label:'Tareas Activas', data:[], backgroundColor:[], hoverOffset:4}], categoryIds: [] }, options:{responsive:true, maintainAspectRatio:false, onClick:(e,el)=>handleChartClick(e,el,categoryDoughnutChart), plugins:{legend:{position:'bottom'}, tooltip:{callbacks:{label: function(c){let l=c.label||''; if(l)l+=': '; if(c.parsed!==null)l+=c.parsed; const t=c.chart.data.datasets[0].data.reduce((a,b)=>a+b,0); const p=t>0?((c.parsed/t)*100).toFixed(1)+'%':'0%'; l+=` (${p})`; return l;}}}}}}); }

            // Gráfico Prioridad (Dona) - initPriorityDoughnutChart (Restaurado)
            function initPriorityDoughnutChart() {
                const ctx = document.getElementById('priorityDoughnutChart')?.getContext('2d');
                if (!ctx) { console.error("Canvas 'priorityDoughnutChart' no encontrado."); return; }
                priorityDoughnutChart = new Chart(ctx, { type: 'doughnut', data: { labels: [], datasets: [{ label: 'Tareas Activas por Prioridad', data: [], backgroundColor: [], hoverOffset: 4 }] }, options: { responsive: true, maintainAspectRatio: false, onClick: (e, el) => handleChartClick(e, el, priorityDoughnutChart), plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: function(c) { let l = c.label || ''; if (l) l += ': '; if (c.parsed !== null) l += c.parsed; const t = c.chart.data.datasets[0].data.reduce((a, b) => a + b, 0); const p = t > 0 ? ((c.parsed / t) * 100).toFixed(1) + '%' : '0%'; l += ` (${p})`; return l; } } } } } });
            }

            // Gráfico Carga Trabajo (BARRA) - initEmployeeWorkloadChart (Con onClick y permisos - CORREGIDO)
            function initEmployeeWorkloadChart() {
                if (!canvasWorkload) { console.error("Canvas 'employeeWorkloadChart' no encontrado."); return; }
                const ctx = canvasWorkload.getContext('2d');
                canvasWorkload.style.display = 'none';

                const handleEmployeeBarClick = (event, elements, chart) => {
                    if (elements.length > 0) {
                        const index = elements[0].index;
                        const employeeIDs = chart.data.employeeIds || [];
                        const clickedEmployeeId = employeeIDs.length > index ? parseInt(employeeIDs[index], 10) : null;
                        const currentUserRole = <?php echo json_encode($rol_usuario ?? 'empleado'); ?>;
                        const currentUserId = parseInt(<?php echo json_encode($id_usuario ?? 0); ?>, 10);

                        console.log(`Click en barra ${index}, ID Empleado: ${clickedEmployeeId} (tipo: ${typeof clickedEmployeeId}), Rol Actual: ${currentUserRole}, ID Actual: ${currentUserId} (tipo: ${typeof currentUserId})`);

                        if (clickedEmployeeId && !isNaN(clickedEmployeeId)) {
                            let canRedirect = false;
                            if (currentUserRole === 'admin' || (currentUserRole === 'empleado' && clickedEmployeeId === currentUserId)) {
                                canRedirect = true;
                            } else {
                                console.log("Acción denegada: Empleado no puede ver tareas de otro.");
                                showToast('Acción no permitida', 'Solo puedes ver tus propias tareas.', 'warning');
                            }
                            if (canRedirect) {
                                const url = `tareas_lista.php?estado=todas&categoria=todas&prioridad=todas&asignado=${clickedEmployeeId}&sort=fecha_creacion&order=desc`;
                                console.log(`Redirección permitida a: ${url}`);
                                window.location.href = url;
                            }
                        } else { console.warn('No se encontró ID de empleado válido para la barra clickeada.'); }
                    }
                };

                employeeWorkloadChart = new Chart(ctx, {
                    type: 'bar',
                    data: { labels: [], datasets: [{ label: 'Tareas Asignadas', data: [], backgroundColor: [], borderWidth: 1 }], employeeIds: [] },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        onClick: handleEmployeeBarClick,
                        scales: {
                            y: { beginAtZero: true, ticks: { callback: function(v) {if (Number.isInteger(v)) return v;} }, title: {display: true, text: 'Nº Tareas Asignadas'} },
                            x:{ title: {display: false, text: 'Empleado'}, ticks: { display: true, autoSkip: false, maxRotation: 0, minRotation: 0 } }
                        },
                        plugins: { legend: { display: false }, tooltip: { callbacks: { title: function(tooltipItems) { return tooltipItems[0]?.label || ''; }, label: function(context) { let label = context.dataset.label || ''; if (label) { label += ': '; } if (context.parsed.y !== null) { label += context.parsed.y; } return label; } } } }
                    }
                });
            }


            // --- FUNCIONES DE CARGA DE DATOS ---

             // Carga Categorías (Dona) - loadCategoryDoughnutData (Con IDs)
            function loadCategoryDoughnutData() {
                if (!categoryDoughnutChart) return;
                loadingDoughnutIndicator.style.display='block'; errorDoughnutIndicator.style.display='none'; noDataDoughnutCategory.style.display='none';
                fetch(`fetch_category_stats.php`)
                    .then(r => r.ok ? r.json() : Promise.reject('Network err: ' + r.status))
                    .then(d => {
                        loadingDoughnutIndicator.style.display='none';
                        if(d.success){
                            if(d.labels && d.labels.length > 0){
                                noDataDoughnutCategory.style.display='none';
                                categoryDoughnutChart.data.labels = d.labels; categoryDoughnutChart.data.datasets[0].data = d.data; categoryDoughnutChart.data.datasets[0].backgroundColor = d.colors; categoryDoughnutChart.data.categoryIds = d.ids || [];
                            } else {
                                noDataDoughnutCategory.style.display='block';
                                categoryDoughnutChart.data.labels = []; categoryDoughnutChart.data.datasets[0].data = []; categoryDoughnutChart.data.datasets[0].backgroundColor = []; categoryDoughnutChart.data.categoryIds = [];
                            }
                            categoryDoughnutChart.update();
                        } else { errorDoughnutIndicator.textContent = d.message || 'Error fetching category data.'; errorDoughnutIndicator.style.display = 'block'; }
                    })
                    .catch(e => { console.error('Error loading category doughnut data:', e); loadingDoughnutIndicator.style.display = 'none'; errorDoughnutIndicator.textContent = 'Connection or server error.'; errorDoughnutIndicator.style.display = 'block'; });
            }

            // Carga Prioridad (Dona) - loadPriorityDoughnutData (Restaurado)
            function loadPriorityDoughnutData() {
                if (!priorityDoughnutChart) { console.error("Gráfico Prioridad no inicializado."); return; }
                loadingPriorityIndicator.style.display = 'block'; errorPriorityIndicator.style.display = 'none'; noDataDoughnutPriority.style.display = 'none';
                fetch(`fetch_priority_stats.php`)
                    .then(r => r.ok ? r.json() : Promise.reject('Network err: ' + r.status))
                    .then(d => {
                        loadingPriorityIndicator.style.display = 'none';
                        if (d.success) {
                            if (d.labels && d.labels.length > 0) {
                                noDataDoughnutPriority.style.display = 'none';
                                priorityDoughnutChart.data.labels = d.labels; priorityDoughnutChart.data.datasets[0].data = d.data; priorityDoughnutChart.data.datasets[0].backgroundColor = d.colors;
                            } else {
                                noDataDoughnutPriority.style.display = 'block';
                                priorityDoughnutChart.data.labels = []; priorityDoughnutChart.data.datasets[0].data = []; priorityDoughnutChart.data.datasets[0].backgroundColor = [];
                            }
                            priorityDoughnutChart.update();
                        } else { errorPriorityIndicator.textContent = d.message || 'Error fetching priority data.'; errorPriorityIndicator.style.display = 'block'; }
                    })
                    .catch(e => { console.error('Error loading priority doughnut data:', e); loadingPriorityIndicator.style.display = 'none'; errorPriorityIndicator.textContent = 'Connection or server error.'; errorPriorityIndicator.style.display = 'block'; });
            }

             // Carga Carga Trabajo Empleados (Barra) - loadEmployeeWorkloadData (Con IDs - CORREGIDO typo data vs d)
            function loadEmployeeWorkloadData(filterType = 'apply') {
                if (!employeeWorkloadChart || !canvasWorkload) { console.error("Gráfico Workload no inicializado."); return; }
                loadingEmployeeWorkload.style.display = 'block'; noDataEmployeeWorkload.style.display = 'none'; errorEmployeeWorkload.style.display = 'none'; canvasWorkload.style.display = 'none';
                let fetchUrl = 'tareas_stats_empleados.php?metric=workload'; let queryParams = []; const selectedEmployeeId = employeeSelectFilter ? employeeSelectFilter.value : ''; if (selectedEmployeeId) { queryParams.push(`employee_id=${selectedEmployeeId}`); } if (filterType === 'apply' || filterType === 'range') { const startDate = startDateEmployee?.value; const endDate = endDateEmployee?.value; if (startDate && endDate) { queryParams.push(`start_date=${startDate}`); queryParams.push(`end_date=${endDate}`); } } else if (filterType === 'today') { queryParams.push('quick_filter=today'); if (startDateEmployee) startDateEmployee.value = ''; if (endDateEmployee) endDateEmployee.value = ''; if (employeeSelectFilter) employeeSelectFilter.value = ''; } if(queryParams.length > 0) { fetchUrl += '&' + queryParams.join('&'); } console.log("Fetching Employee Workload:", fetchUrl);
                fetch(fetchUrl)
                    .then(response => { loadingEmployeeWorkload.style.display='none'; if(!response.ok) throw new Error('Network response error: '+response.status); return response.json(); })
                    .then(data => {
                        if(data.success && data.labels && data.labels.length > 0) {
                            canvasWorkload.style.display = 'block'; noDataEmployeeWorkload.style.display='none'; const decodedLabels = data.labels.map(label => decodeHtmlEntities(label)); employeeWorkloadChart.data.labels = decodedLabels;
                            // *** CORRECCIÓN TYPO: Usar 'data' en lugar de 'd' ***
                            employeeWorkloadChart.data.datasets[0].data = data.data; 
                            employeeWorkloadChart.data.datasets[0].backgroundColor = data.colors; 
                            employeeWorkloadChart.data.employeeIds = data.ids || []; 
                            // *** FIN CORRECCIÓN TYPO ***
                        } else if(data.success) {
                            noDataEmployeeWorkload.style.display='block'; employeeWorkloadChart.data.labels = []; employeeWorkloadChart.data.datasets[0].data = []; employeeWorkloadChart.data.datasets[0].backgroundColor = []; employeeWorkloadChart.data.employeeIds = [];
                        } else {
                            errorEmployeeWorkload.textContent = data.message || 'Error processing data.'; errorEmployeeWorkload.style.display='block'; employeeWorkloadChart.data.labels = []; employeeWorkloadChart.data.datasets[0].data = []; employeeWorkloadChart.data.datasets[0].backgroundColor = []; employeeWorkloadChart.data.employeeIds = [];
                        }
                        updateEmployeeChartTicksVisibility();
                        employeeWorkloadChart.update();
                    })
                    .catch(error => { console.error('Error fetching employee workload:', error); loadingEmployeeWorkload.style.display='none'; errorEmployeeWorkload.textContent='Connection or server error: '+error.message; errorEmployeeWorkload.style.display='block'; });
            }

            // --- FUNCIÓN TICKS RESPONSIVE Y LISTENER RESIZE (Sin cambios) ---
            const MOBILE_BREAKPOINT = 768;
            function updateEmployeeChartTicksVisibility() { if (!employeeWorkloadChart) return; const isMobile = window.innerWidth < MOBILE_BREAKPOINT; employeeWorkloadChart.options.scales.x.ticks.display = !isMobile; }
            let resizeTimeout; window.addEventListener('resize', () => { clearTimeout(resizeTimeout); resizeTimeout = setTimeout(() => { updateEmployeeChartTicksVisibility(); if (employeeWorkloadChart) { employeeWorkloadChart.update(); } }, 250); });

            // --- INICIALIZACIÓN Y LISTENERS ---
            initCategoryDoughnutChart();
            initPriorityDoughnutChart(); // Llamada restaurada
            initEmployeeWorkloadChart();

            // Cargar datos iniciales
            loadCategoryDoughnutData();
            loadPriorityDoughnutData(); // Llamada restaurada
            loadEmployeeWorkloadData('apply');

            // Listeners para filtros (Sin cambios)
            if (applyEmployeeFilters) { applyEmployeeFilters.addEventListener('click', () => { loadEmployeeWorkloadData('apply'); }); }
            if (filterTodayEmployee) { filterTodayEmployee.addEventListener('click', () => { loadEmployeeWorkloadData('today'); }); }

        });
    </script>
</body>
</html>
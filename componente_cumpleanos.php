<?php
// Archivo: componente_cumpleanos.php (FINAL: 10 Frases x Signo + Rosa Fija + Fiesta 20s)

if (isset($_SESSION['usuario_id']) && !isset($_SESSION['cumple_pospuesto'])) {
    
    // 1. OBTENER DATOS
    $stmt_cumple = $pdo->prepare("SELECT fecha_nacimiento, ultimo_saludo_cumple, nombre_completo, genero FROM usuarios WHERE id_usuario = :id");
    $stmt_cumple->execute([':id' => $_SESSION['usuario_id']]);
    $user_cumple = $stmt_cumple->fetch(PDO::FETCH_ASSOC);

    if ($user_cumple && !empty($user_cumple['fecha_nacimiento'])) {
        
        // 2. CONFIGURAR FECHAS
        $hoy = new DateTime('today'); 
        $fecha_nac = new DateTime($user_cumple['fecha_nacimiento']);
        $anio_actual = $hoy->format('Y');
        $cumple_actual = new DateTime($anio_actual . '-' . $fecha_nac->format('m-d'));
        $diff = $cumple_actual->diff($hoy);
        
        $es_cumple = false;
        $es_atrasado = false;
        $anio_objetivo = $anio_actual;

        // Lógica de 3 días
        if ($diff->invert == 0 && $diff->days <= 2) { 
            $es_cumple = true;
            if ($diff->days > 0) $es_atrasado = true;
        }
        elseif ($hoy->format('m') == '01' && $fecha_nac->format('m') == '12') {
            $anio_pasado = $anio_actual - 1;
            $cumple_pasado = new DateTime($anio_pasado . '-' . $fecha_nac->format('m-d'));
            $diff_past = $cumple_pasado->diff($hoy);
            if ($diff_past->invert == 0 && $diff_past->days <= 2) {
                $es_cumple = true;
                $es_atrasado = true;
                $anio_objetivo = $anio_pasado; 
            }
        }

        // 3. MOSTRAR SI CORRESPONDE
        if ($es_cumple && (int)$user_cumple['ultimo_saludo_cumple'] != $anio_objetivo) {
            
            $nombre_user = htmlspecialchars($user_cumple['nombre_completo']);
            
            // --- CALCULAR SIGNO ZODIACAL ---
            $mes = (int)$fecha_nac->format('m');
            $dia = (int)$fecha_nac->format('d');
            $signo = '';

            if (($mes == 3 && $dia >= 21) || ($mes == 4 && $dia <= 19)) { $signo = 'Aries'; }
            elseif (($mes == 4 && $dia >= 20) || ($mes == 5 && $dia <= 20)) { $signo = 'Tauro'; }
            elseif (($mes == 5 && $dia >= 21) || ($mes == 6 && $dia <= 20)) { $signo = 'Géminis'; }
            elseif (($mes == 6 && $dia >= 21) || ($mes == 7 && $dia <= 22)) { $signo = 'Cáncer'; }
            elseif (($mes == 7 && $dia >= 23) || ($mes == 8 && $dia <= 22)) { $signo = 'Leo'; }
            elseif (($mes == 8 && $dia >= 23) || ($mes == 9 && $dia <= 22)) { $signo = 'Virgo'; }
            elseif (($mes == 9 && $dia >= 23) || ($mes == 10 && $dia <= 22)) { $signo = 'Libra'; }
            elseif (($mes == 10 && $dia >= 23) || ($mes == 11 && $dia <= 21)) { $signo = 'Escorpio'; }
            elseif (($mes == 11 && $dia >= 22) || ($mes == 12 && $dia <= 21)) { $signo = 'Sagitario'; }
            elseif (($mes == 12 && $dia >= 22) || ($mes == 1 && $dia <= 19)) { $signo = 'Capricornio'; }
            elseif (($mes == 1 && $dia >= 20) || ($mes == 2 && $dia <= 18)) { $signo = 'Acuario'; }
            elseif (($mes == 2 && $dia >= 19) || ($mes == 3 && $dia <= 20)) { $signo = 'Piscis'; }

            // --- 10 FRASES MOTIVADORAS POR SIGNO ---
            $frases_signos = [
                'Aries' => [
                    "Como buen Aries, naciste para liderar y abrir caminos donde nadie más se atreve. ¡Que este año tu fuego interior sea imparable!",
                    "Tu energía es el motor que mueve al mundo. Hoy el universo celebra tu fuerza y tu coraje.",
                    "No hay obstáculo que tu determinación de Aries no pueda derribar. ¡Este es tu año para conquistar!",
                    "Tu pasión es contagiosa. Que este nuevo ciclo te traiga desafíos a la altura de tu grandeza.",
                    "Eres el inicio, la chispa, el primero. ¡Feliz cumpleaños al guerrero del zodiaco!",
                    "Que tu impulsividad te lleve a vivir las aventuras más increíbles de tu vida este año.",
                    "El mundo necesita tu valentía. Nunca apagues esa luz intensa que llevas dentro.",
                    "Hoy renace tu espíritu de lucha. ¡Ve y toma lo que es tuyo por derecho!",
                    "Tu franqueza y tu fuerza son tu mejor escudo. ¡A brillar más fuerte que nunca!",
                    "Aries: Naciste para ganar. Que este cumpleaños marque el inicio de tu mejor victoria."
                ],
                'Tauro' => [
                    "Tauro, tu perseverancia construye imperios. Que este año coseches todos los frutos de tu esfuerzo.",
                    "Eres la roca en la que todos confían. Hoy el universo te regala la estabilidad y el placer que mereces.",
                    "Tu fuerza de voluntad es inquebrantable. ¡Disfruta de la belleza y la abundancia que atraes!",
                    "Nadie sabe disfrutar la vida como tú. Que este nuevo año esté lleno de lujos, amor y paz.",
                    "Tu lealtad es un regalo para el mundo. Hoy celebramos tu corazón noble y firme.",
                    "Que la vida te regale momentos tan sólidos y valiosos como tu propia esencia.",
                    "Paso a paso, llegas más lejos que nadie. ¡Sigue construyendo tu destino con esa firmeza!",
                    "La paciencia es tu virtud, y el éxito tu destino. ¡Feliz cumpleaños, fuerza de la naturaleza!",
                    "Tienes el don de materializar sueños. ¡Que este año todo lo que toques se convierta en oro!",
                    "Tauro: Tu determinación es tu magia. ¡Ve por todo lo que deseas, te lo has ganado!"
                ],
                'Géminis' => [
                    "Tu mente brillante no tiene límites, Géminis. ¡Que este año tus ideas cambien el mundo!",
                    "Eres el comunicador del zodiaco. Que tus palabras abran todas las puertas que desees hoy.",
                    "Tu versatilidad es tu superpoder. ¡Que la vida te sorprenda con aventuras a tu medida!",
                    "Nunca dejes de curiosear, porque ahí radica tu magia. ¡Feliz cumpleaños al alma más despierta!",
                    "Que tu inteligencia y tu chispa iluminen cada día de este nuevo ciclo solar.",
                    "Tienes el don de conectar con todos. Hoy el universo conecta contigo para cumplir tus deseos.",
                    "Tu energía es aire fresco para quienes te rodean. ¡Nunca pierdas esa alegría contagiosa!",
                    "Adaptarse y vencer es tu lema. ¡Que este año te traiga mil motivos para sonreír!",
                    "Géminis: Tu dualidad es tu mayor riqueza. ¡Disfruta de todas las facetas de tu vida!",
                    "Que la agilidad de tu mente te lleve a lugares donde otros solo sueñan llegar."
                ],
                'Cáncer' => [
                    "Cáncer, tu intuición es tu brújula. Confía en ella, porque este año te llevará a la felicidad absoluta.",
                    "Tu corazón es el hogar de muchos. Hoy el universo te devuelve todo ese amor multiplicado.",
                    "Tienes una fuerza emocional que mueve montañas. ¡Que nada apague tu sensibilidad única!",
                    "Eres la protección y el cariño hechos persona. ¡Feliz cumpleaños al alma más noble!",
                    "Que la luna te guíe en este nuevo ciclo y te llene de sueños cumplidos.",
                    "Tu empatía es un regalo divino. Hoy permítete recibir todo el cuidado que siempre das.",
                    "Cáncer: Tu tenacidad es silenciosa pero poderosa. ¡Lograrás todo lo que te propongas!",
                    "Que la calidez que brindas a los demás regrese a ti en forma de bendiciones infinitas.",
                    "Protege tus sueños con la misma garra con la que proteges a los tuyos. ¡Este es tu año!",
                    "Eres raíz y eres fruto. Que este cumpleaños te traiga la paz y el amor que tanto valoras."
                ],
                'Leo' => [
                    "¡El Rey ha llegado! Leo, tu luz es innegable. Que este año brilles más fuerte que el sol.",
                    "Naciste para destacar y liderar. ¡Que el mundo entero sea tu escenario este año!",
                    "Tu generosidad no conoce límites. Hoy el universo te colma de abundancia y aplausos.",
                    "Tienes un corazón de oro y una fuerza de león. ¡Nada puede detenerte!",
                    "Leo: Tu confianza es tu corona. Llévala con orgullo y conquista tus metas.",
                    "Que tu carisma abra todas las puertas y tu pasión encienda todos los corazones.",
                    "Eres el protagonista de tu vida. ¡Haz que este capítulo sea inolvidable!",
                    "Tu vitalidad es contagiosa. ¡Celebra a lo grande, porque te mereces lo mejor!",
                    "La lealtad y el honor son tu sello. ¡Feliz cumpleaños a la realeza del zodiaco!",
                    "Que tu fuego interior nunca se apague. ¡Ruge fuerte y ve por tus sueños!"
                ],
                'Virgo' => [
                    "Virgo, tu búsqueda de la excelencia te llevará a la cima. ¡Que este año todo salga perfecto!",
                    "Tu mente analítica es una obra de arte. Hoy relájate y deja que el universo te sorprenda.",
                    "Eres el orden en el caos y la solución a los problemas. ¡Gracias por existir!",
                    "Que la cosecha de tu esfuerzo sea abundante este año. ¡Te lo has ganado con creces!",
                    "Tu humildad y tu servicio te hacen grande. ¡Feliz cumpleaños al signo más dedicado!",
                    "Virgo: Tu atención al detalle es mágica. ¡Que la vida te regale detalles hermosos hoy!",
                    "Tienes el poder de sanar y mejorar todo lo que tocas. ¡Sigue transformando el mundo!",
                    "Que encuentres la perfección en los pequeños momentos de felicidad este año.",
                    "Tu inteligencia práctica es tu mejor aliada. ¡Confía en ti, eres capaz de todo!",
                    "Eres tierra fértil donde crecen los sueños. ¡Que este nuevo ciclo sea próspero!"
                ],
                'Libra' => [
                    "Libra, traes equilibrio y belleza a este mundo. ¡Que tu vida sea tan armoniosa como tu alma!",
                    "Tu encanto es irresistible. Hoy el universo conspira para darte todo el amor que mereces.",
                    "Eres la justicia y la paz. Que este año encuentres el balance perfecto en todo lo que hagas.",
                    "Tu sonrisa ilumina los días grises. ¡Nunca dejes de buscar lo bello en la vida!",
                    "Libra: Tu diplomacia te abrirá puertas increíbles este año. ¡Confía en tu don de gentes!",
                    "Que la elegancia que te caracteriza te acompañe en cada triunfo de este nuevo ciclo.",
                    "Naciste para conectar y amar. ¡Que este cumpleaños esté lleno de abrazos sinceros!",
                    "Tu mente clara y justa es una guía para todos. ¡Feliz cumpleaños, portador de armonía!",
                    "Que decidas ser feliz por encima de todo. ¡Este es tu momento para brillar!",
                    "El arte de vivir es tu especialidad. ¡Haz de este año tu mejor obra maestra!"
                ],
                'Escorpio' => [
                    "Escorpio, tu intensidad es tu poder. ¡Transforma el mundo con esa pasión que te define!",
                    "Eres el ave Fénix del zodiaco. Renace hoy con más fuerza y sabiduría que nunca.",
                    "Tu mirada ve lo que nadie más ve. Que tu intuición te guíe al éxito rotundo este año.",
                    "Nadie ama con tanta profundidad como tú. ¡Que recibas un amor tan inmenso como el tuyo!",
                    "Escorpio: Tu misterio atrae milagros. ¡Prepárate para un año lleno de sorpresas!",
                    "Tu determinación es legendaria. No hay meta que no puedas alcanzar si te lo propones.",
                    "Eres leal hasta el final. Hoy celebramos tu fuerza y tu corazón inquebrantable.",
                    "Que la pasión que pones en todo sea el combustible de tus mayores logros.",
                    "Posees una magia oculta capaz de cambiarlo todo. ¡Úsala a tu favor este año!",
                    "¡Feliz cumpleaños! Que tu magnetismo atraiga solo lo mejor del universo."
                ],
                'Sagitario' => [
                    "Sagitario, tu espíritu libre no conoce fronteras. ¡Que este año vueles más alto que nunca!",
                    "Tu optimismo es un faro de luz. Gracias por enseñarnos a ver siempre el lado bueno.",
                    "La aventura te llama. ¡Que este nuevo ciclo esté lleno de viajes y descubrimientos!",
                    "Eres la flecha que siempre da en el blanco. ¡Apunta alto y dispara hacia tus sueños!",
                    "Sagitario: Tu sabiduría y tu alegría son un regalo. ¡Nunca pierdas esa chispa!",
                    "Que la expansión y la abundancia sean tus compañeras de viaje este año.",
                    "Tu risa cura el alma. ¡Que hoy tengas motivos de sobra para reír a carcajadas!",
                    "Naciste para explorar y aprender. ¡El mundo es tuyo, sal a conquistarlo!",
                    "Que tu fe en la vida te traiga milagros inesperados. ¡Feliz cumpleaños, aventurero!",
                    "Eres fuego que inspira. ¡Sigue iluminando el camino con tu verdad!"
                ],
                'Capricornio' => [
                    "Capricornio, tu disciplina construye imperios. ¡Hoy celebra que estás más cerca de la cima!",
                    "Eres la prueba de que con esfuerzo todo es posible. ¡Que este año coseches grandes éxitos!",
                    "Tu ambición es tu motor. No te detengas hasta ver tus sueños hechos realidad.",
                    "La madurez y la sabiduría son tus aliadas. ¡Feliz cumpleaños al maestro del zodiaco!",
                    "Capricornio: Eres fuerte como una montaña. ¡Nada puede derribarte!",
                    "Que la estructura que creas sea la base de tu felicidad y prosperidad.",
                    "Tu legado es importante. Sigue construyendo con esa excelencia que te caracteriza.",
                    "Detrás de tu seriedad hay un corazón de oro y un humor único. ¡Disfruta tu día!",
                    "El tiempo siempre juega a tu favor. ¡Este año serás mejor, más sabio y más fuerte!",
                    "Tienes el poder de materializar lo imposible. ¡Ve por ello!"
                ],
                'Acuario' => [
                    "Acuario, tu visión única cambia el mundo. ¡Nunca dejes de ser tan auténtico y genial!",
                    "Eres el futuro hoy. Que tus ideas revolucionarias te lleven a lugares increíbles este año.",
                    "Tu libertad es sagrada. ¡Vuela alto y rompe todos los esquemas!",
                    "La amistad es tu tesoro. Hoy celebramos lo gran amigo y ser humano que eres.",
                    "Acuario: Tu mente es un universo de posibilidades. ¡Explóralas todas!",
                    "Que tu originalidad sea la llave que abra las puertas del éxito este ciclo.",
                    "Eres diferente y eso es lo que te hace especial. ¡Brilla con tu luz propia!",
                    "Tu humanismo inspira a todos. Gracias por hacer del mundo un lugar mejor.",
                    "Que la innovación y la sorpresa sean la constante en tu vida este año.",
                    "¡Feliz cumpleaños! Sigue soñando despierto, porque tus sueños crean realidades."
                ],
                'Piscis' => [
                    "Piscis, tu sensibilidad es magia pura. ¡Que tus sueños se hagan realidad hoy y siempre!",
                    "Nadas en océanos de emoción y creatividad. ¡Que este año encuentres tesoros en tu interior!",
                    "Tu empatía sana corazones. Hoy el universo te abraza con todo su amor.",
                    "Eres el soñador del zodiaco. ¡Nunca despiertes, haz que la realidad se parezca a tus sueños!",
                    "Piscis: Tu intuición nunca falla. Síguela, porque te llevará a la felicidad.",
                    "Que la conexión espiritual que tienes te brinde paz y claridad este año.",
                    "Eres arte en movimiento. ¡Expresa todo lo que llevas dentro!",
                    "Tu bondad no tiene límites. Que la vida te devuelva cada gesto de amor multiplicado.",
                    "Fluye con la vida como el agua. ¡Feliz cumpleaños al alma más mística!",
                    "Que la magia que ves en el mundo se manifieste en tu propia vida hoy."
                ]
            ];

            // Seleccionar array de frases del signo, o uno genérico si falla algo
            $mis_frases = $frases_signos[$signo] ?? [
                "¡Feliz Cumpleaños! Que la alegría de hoy sea el combustible para todas tus metas.",
                "Tu esfuerzo y dedicación nos inspiran. ¡Felicidades en tu día!",
                "Un año más de vida significa 365 nuevas oportunidades para brillar.",
                "Que este día esté lleno de magia y momentos inolvidables."
            ];

            // Elegir una frase al azar de las 10 disponibles
            $frase_mostrar = $mis_frases[array_rand($mis_frases)];

            // Título dinámico
            if ($es_atrasado) {
                $titulo_modal = "¡Feliz Cumple Atrasado!";
                $frase_mostrar = "Aunque el saludo llegue tarde... " . lcfirst($frase_mostrar); 
            } else {
                $titulo_modal = "¡Feliz Cumple, $signo!";
            }
            ?>

            <style>
                .modal-backdrop.show { opacity: 0.85; background-color: #2c0b1e; }
                
                /* CONTENEDOR PRINCIPAL DEL MODAL (Transparente y permite desborde) */
                .modal-cumple-wrapper {
                    border: none;
                    background: transparent; /* Invisible */
                    box-shadow: none;
                    position: relative;
                    overflow: visible !important; /* CRUCIAL: Permite que la rosa se vea afuera */
                    animation: bounceInDown 1s both;
                }

                /* TARJETA VISIBLE (Blanca y recorta las esquinas) */
                .modal-cumple-card {
                    background: rgba(255, 255, 255, 0.98);
                    border-radius: 25px;
                    overflow: hidden; /* Recorta las rosas decorativas internas */
                    box-shadow: 0 25px 50px rgba(0,0,0,0.4);
                    position: relative;
                    z-index: 5;
                }

                /* --- ROSA DE ENTREGA (POSICIONADA RELATIVA AL MODAL) --- */
                @keyframes rosePopUpSide {
                    0% { transform: translate(50%, 100%) rotate(45deg); opacity: 0; }
                    100% { transform: translate(0, 0) rotate(0deg); opacity: 1; }
                }

                .rose-delivery-container {
                    position: absolute;
                    bottom: -40px;
                    right: -180px; /* TU POSICIÓN SAGRADA: NO TOCAR */
                    width: 250px;
                    height: auto;
                    z-index: 20; 
                    pointer-events: none; /* Contenedor transparente a clics */
                    overflow: visible;
                }
                
                .rose-delivery-img {
                    width: 100%;
                    opacity: 0;
                    animation: rosePopUpSide 1.5s cubic-bezier(0.34, 1.56, 0.64, 1) 1.5s forwards;
                    filter: drop-shadow(-5px 5px 15px rgba(0,0,0,0.3));
                    cursor: pointer;
                    pointer-events: auto; /* IMPORTANTE: Habilita el clic en la rosa */
                    transition: transform 0.3s;
                }
                .rose-delivery-img:hover {
                    transform: scale(1.05) rotate(-5deg);
                }

                /* --- GIF "HAZ CLIC AQUÍ" --- */
                .click-here-indicator {
                    position: absolute;
                    top: -10%;
                    left: -60px;
                    width: 120px;
                    opacity: 0;
                    pointer-events: none;
                    filter: drop-shadow(0 2px 5px rgba(0,0,0,0.2));
                    animation: fadeIn 0.5s ease 3s forwards, floatClick 1.5s infinite ease-in-out 3s;
                }
                @keyframes floatClick { 0%, 100% { transform: translateX(0); } 50% { transform: translateX(-10px); } }
                @keyframes fadeIn { to { opacity: 1; } }

                /* --- CARTELITO DE DECISIÓN --- */
                .rose-decision-modal {
                    position: absolute;
                    bottom: 100px;
                    right: 20px;
                    background: white;
                    padding: 15px;
                    border-radius: 15px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                    text-align: center;
                    width: 200px;
                    display: none;
                    z-index: 30;
                    border: 2px solid #d63384;
                    animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                    pointer-events: auto; /* Botones clickeables */
                }
                .rose-decision-modal::after {
                    content: ''; position: absolute; bottom: -10px; right: 80px;
                    border-width: 10px 10px 0; border-style: solid;
                    border-color: #d63384 transparent transparent transparent;
                }
                .btn-rose-opt {
                    display: block; width: 100%; margin: 5px 0;
                    padding: 5px 10px; border-radius: 20px; font-size: 0.9rem; border: none; font-weight: bold;
                }
                .btn-rose-yes { background: #198754; color: white; }
                .btn-rose-no { background: #dc3545; color: white; }
                @keyframes popIn { from { transform: scale(0.5); opacity: 0; } to { transform: scale(1); opacity: 1; } }

                /* Estilos Generales */
                .modal-cumple-header-custom {
                    background: linear-gradient(135deg, #FF9A9E 0%, #FECFEF 100%);
                    padding: 30px 20px; text-align: center;
                    position: relative; z-index: 2;
                }
                .modal-cumple-title {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    font-weight: 900; font-size: 2.8rem;
                    background: -webkit-linear-gradient(#d63384, #ff6b6b);
                    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
                    text-transform: uppercase; margin-bottom: 10px;
                }
                .modal-cumple-user { font-size: 1.8rem; color: #fff; font-weight: 700; text-shadow: 1px 1px 3px rgba(0,0,0,0.2); }

                .modal-cumple-body-custom { padding: 40px 30px; text-align: center; position: relative; z-index: 2; }

                /* Rosas Decorativas */
                .deco-rose-img { position: absolute; z-index: 0; opacity: 0.4; pointer-events: none; max-width: 250px; }
                .rose-tl { top: -40px; left: -40px; transform: rotate(-25deg); }
                .rose-br { bottom: -40px; right: -40px; transform: rotate(155deg); }

                /* Media */
                .cumple-media-img {
                    max-width: 100%; height: auto; border-radius: 20px;
                    box-shadow: 0 10px 25px rgba(0,0,0,0.15); max-height: 250px;
                    margin-bottom: 25px;
                }

                /* Frase */
                .quote-container {
                    position: relative; margin: 10px 0 40px; padding: 25px;
                    background: #fff0f5; border-radius: 15px; border-left: 5px solid #d63384;
                }
                .quote-text { font-family: 'Georgia', serif; font-style: italic; font-size: 1.3rem; color: #444; }
                .quote-icon {
                    position: absolute; top: -15px; left: 20px;
                    background: #d63384; color: white; width: 30px; height: 30px;
                    border-radius: 50%; display: flex; align-items: center; justify-content: center;
                }

                /* Botones */
                .btn-cumple-gracias {
                    background: linear-gradient(90deg, #d63384, #ff6b6b); border: none; color: white;
                    padding: 12px 35px; font-size: 1.1rem; font-weight: bold; border-radius: 50px;
                    box-shadow: 0 5px 15px rgba(214, 51, 132, 0.4); transition: transform 0.2s;
                }
                .btn-cumple-gracias:hover { transform: scale(1.05); color: white; }
                .btn-cumple-later {
                    background: transparent; border: 2px solid #ccc; color: #777;
                    padding: 10px 25px; border-radius: 50px; font-weight: 600;
                }
                .btn-cumple-later:hover { border-color: #999; color: #555; background: #f8f9fa; }

                @keyframes bounceInDown {
                    from, 60%, 75%, 90%, to { animation-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1); }
                    0% { opacity: 0; transform: translate3d(0, -3000px, 0) scaleY(3); }
                    60% { opacity: 1; transform: translate3d(0, 25px, 0) scaleY(0.9); }
                    75% { transform: translate3d(0, -10px, 0) scaleY(0.95); }
                    90% { transform: translate3d(0, 5px, 0) scaleY(0.985); }
                    to { transform: translate3d(0, 0, 0); }
                }
            </style>

            <div class="modal fade" id="modalCumpleanos" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content modal-cumple-wrapper">
                        
                        <div class="modal-cumple-card">
                            <img src="./assets/rosas2.png" class="deco-rose-img rose-tl" alt="Decoración">
                            <img src="./assets/rosas2.png" class="deco-rose-img rose-br" alt="Decoración">

                            <div class="modal-cumple-header-custom">
                                <h1 class="modal-cumple-title"><?php echo $titulo_modal; ?></h1>
                                <div class="modal-cumple-user"><?php echo $nombre_user; ?></div>
                            </div>

                            <div class="modal-cumple-body-custom">
                                <div class="cumple-media-container">
                                    <img src="./assets/cumple.gif" alt="Celebración" class="cumple-media-img">
                                </div>

                                <div class="quote-container">
                                    <div class="quote-icon"><i class="fas fa-quote-left"></i></div>
                                    <p class="quote-text mb-0"><?php echo $frase_mostrar; ?></p>
                                </div>

                                <div class="d-flex justify-content-center gap-3 align-items-center">
                                    <button id="btnVerMasTarde" class="btn btn-cumple-later">Ver más tarde</button>
                                    <button id="btnGraciasCumple" class="btn btn-cumple-gracias"><i class="fas fa-gift me-2"></i> ¡GRACIAS!</button>
                                </div>
                            </div>
                        </div>

                        <div class="rose-delivery-container">
                            <div class="rose-decision-modal" id="roseDecision">
                                <p class="small mb-2 fw-bold text-dark">¿Aceptas esta rosa?</p>
                                <button class="btn-rose-opt btn-rose-yes" onclick="responderRosa('aceptar')">¡Sí, gracias!</button>
                                <button class="btn-rose-opt btn-rose-no" onclick="responderRosa('rechazar')">No, gracias</button>
                            </div>

                            <img src="assets/click.gif" class="click-here-indicator" id="rosePointer" alt="Haz clic">

                            <img src="assets/rosas.png" class="rose-delivery-img" id="roseImage" alt="Una rosa para ti">
                        </div>

                    </div>
                </div>
            </div>

            <div class="modal fade" id="modalEsUnPlacer" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-sm">
                    <div class="modal-content border-0 shadow" style="border-radius: 20px;">
                        <div class="modal-header bg-success text-white justify-content-center border-0">
                            <h5 class="modal-title fw-bold">¡Genial!</h5>
                        </div>
                        <div class="modal-body text-center p-4">
                            <p class="fs-5 mb-0">Es un placer saludarte.</p>
                            <small class="text-muted">¡Que tengas una gran jornada!</small>
                        </div>
                        <div class="modal-footer justify-content-center border-0 pt-0 pb-3">
                            <button class="btn btn-success rounded-pill px-4" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
            <script>
                // Función global para manejar la respuesta
                function responderRosa(decision) {
                    const cartel = document.getElementById('roseDecision');
                    const pointer = document.getElementById('rosePointer');
                    
                    cartel.style.display = 'none';
                    if(pointer) pointer.style.display = 'none'; 

                    // Enviar notificación al admin SILENCIOSAMENTE
                    fetch('ajax_confirmar_cumple.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ accion: 'notificar_rosa', decision: decision })
                    });

                    // Feedback visual sutil solo si acepta
                    if(decision === 'aceptar') {
                        confetti({ particleCount: 50, spread: 60, origin: { x: 0.9, y: 0.9 }, colors: ['#ff0000'] });
                    }
                }

                document.addEventListener('DOMContentLoaded', function() {
                    var modalEl = document.getElementById('modalCumpleanos');
                    var modal = new bootstrap.Modal(modalEl);
                    var modalFin = new bootstrap.Modal(document.getElementById('modalEsUnPlacer'));
                    var anioObjetivo = <?php echo $anio_objetivo; ?>;

                    modal.show();

                    // --- LÓGICA ROSA INTERACTIVA ---
                    const roseImg = document.getElementById('roseImage');
                    const rosePointer = document.getElementById('rosePointer');
                    const roseDecision = document.getElementById('roseDecision');

                    roseImg.addEventListener('click', function() {
                        roseDecision.style.display = 'block';
                        rosePointer.style.display = 'none';
                    });

                    // --- MEGA FIESTA EXAGERADA (20 SEGUNDOS) ---
                    function lanzarFiesta() {
                        var duration = 10 * 1000; 
                        var animationEnd = Date.now() + duration;
                        var defaults = { startVelocity: 30, spread: 360, ticks: 100, zIndex: 2200 }; 

                        function randomInRange(min, max) { return Math.random() * (max - min) + min; }

                        var interval = setInterval(function() {
                            var timeLeft = animationEnd - Date.now();
                            if (timeLeft <= 0) { return clearInterval(interval); }
                            
                            confetti(Object.assign({}, defaults, { 
                                particleCount: 20, origin: { x: randomInRange(0.1, 0.9), y: Math.random() - 0.2 } 
                            }));
                        }, 200);

                        var fireworksInterval = setInterval(function() {
                            var timeLeft = animationEnd - Date.now();
                            if (timeLeft <= 0) { return clearInterval(fireworksInterval); }
                            confetti({ particleCount: 80, angle: 60, spread: 60, origin: { x: 0, y: 1 }, zIndex: 2200, colors: ['#FF0000', '#FFD700', '#FFFFFF'] });
                            confetti({ particleCount: 80, angle: 120, spread: 60, origin: { x: 1, y: 1 }, zIndex: 2200, colors: ['#00FF00', '#0000FF', '#FFFFFF'] });
                        }, 1000);

                        var starsInterval = setInterval(function() {
                            var timeLeft = animationEnd - Date.now();
                            if (timeLeft <= 0) { return clearInterval(starsInterval); }
                            confetti({ shapes: ['star'], colors: ['#FFD700'], scalar: 2, particleCount: 30, spread: 360, origin: { x: 0.5, y: 0.4 }, zIndex: 2200 });
                        }, 2000);
                    }
                    setTimeout(lanzarFiesta, 500);

                    // Botones Principales
                    document.getElementById('btnGraciasCumple').addEventListener('click', function() {
                        modal.hide();
                        // --- INICIO DEL CAMBIO DE FONDO INSTANTANEO ---
        document.body.style.backgroundColor = '#fff0f5';
        document.body.style.backgroundImage = 'linear-gradient(to bottom, #fff0f5, #ffe4e1)';
        document.body.style.backgroundRepeat = 'no-repeat';
        document.body.style.backgroundAttachment = 'fixed';

        // Crear la rosa fija si no existe
        if (!document.querySelector('.rose-fixed-added')) {
            var roseFixed = document.createElement('img');
            roseFixed.src = 'assets/rosas.png';
            roseFixed.style.position = 'fixed';
            roseFixed.style.bottom = '-20px';
            roseFixed.style.right = '10px';
            roseFixed.style.width = '200px';
            roseFixed.style.zIndex = '0';
            roseFixed.style.pointerEvents = 'none';
            roseFixed.style.opacity = '0.8';
            roseFixed.classList.add('rose-fixed-added'); // Clase para identificarla
            document.body.appendChild(roseFixed);
        }
        // --- FIN DEL CAMBIO ---
                        
                        fetch('ajax_confirmar_cumple.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({ accion: 'confirmar', anio: anioObjetivo })
                        });
                        setTimeout(() => { modalFin.show(); }, 300);
                        confetti({ particleCount: 500, spread: 360, startVelocity: 60, origin: { y: 0.5 }, zIndex: 2200 });
                    });

                    document.getElementById('btnVerMasTarde').addEventListener('click', function() {
                        modal.hide();
                        fetch('ajax_confirmar_cumple.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({ accion: 'posponer' })
                        });
                    });
                });
            </script>
            <?php
        }
    }
}
?>

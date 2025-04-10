<?php
require 'db.php';

header('Content-Type: application/json');
ini_set('memory_limit', '128M');

// Validar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['respuesta' => 'Método no permitido']));
}

// Obtener pregunta
$pregunta = trim(strtolower(file_get_contents('php://input')));

// Si la pregunta está vacía, respondemos con un mensaje de bienvenida y sugerencias
if (empty($pregunta)) {
    $respuesta = "¡Hola! Puedo ayudarte con:\n\n";
    $respuesta .= "- Disponibilidad de citas: '¿Hay citas el 20/11/2023 a las 10:00?'\n";
    $respuesta .= "- Información sobre doctores\n";
    $respuesta .= "- Horario de atención: Lunes a Viernes de 8:00 AM a 6:00 PM.\n";
    $respuesta .= "Si necesitas algo más, solo pregúntame.";
    exit(json_encode(['respuesta' => $respuesta]));
}

try {
    // Base de respuestas
    $respuestas = [
        'hola' => '¡Hola! Soy tu asistente médico. ¿En qué puedo ayudarte?',
        'gracias' => '¡De nada! ¿Necesitas algo más?',
        'horarios' => 'Horario de atención: Lunes a Viernes de 8:00 AM a 6:00 PM.',
        'urgente' => 'Para emergencias llama al 911 o acude a urgencias.',
        'cita' => 'Para agendar una cita, dime la fecha y hora que deseas.'
    ];

    // Buscar coincidencia exacta
    if (isset($respuestas[$pregunta])) {
        exit(json_encode(['respuesta' => $respuestas[$pregunta]]));
    }

// Consultar disponibilidad de citas por fecha
if (preg_match('/hay citas el (\d{2}\/\d{2}\/\d{4})/', $pregunta, $matches)) {
    $fecha = $matches[1];  // Extrae la fecha (DD/MM/YYYY)

    // Convertir la fecha a formato SQL (Y-m-d)
    $fecha_sql = date('Y-m-d', strtotime($fecha));

    // Consultar las horas ocupadas en esa fecha
    $stmt = $pdo->prepare("SELECT hora FROM citas WHERE fecha = ?");
    $stmt->execute([$fecha_sql]);
    $horas_ocupadas = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($horas_ocupadas)) {
        // Si hay citas programadas en esa fecha, mostramos las horas ocupadas
        $respuesta = "¡Sí! Hay citas programadas para el {$fecha}. Las siguientes horas ya están ocupadas:\n";
        foreach ($horas_ocupadas as $hora) {
            $respuesta .= "🕓 {$hora}\n";
        }
    } else {
        // Si no hay citas programadas en esa fecha
        $respuesta = "No hay citas programadas para el {$fecha}.";
    }

    exit(json_encode(['respuesta' => $respuesta]));
}




    // Consultar citas del usuario si proporciona su carnet
    if (preg_match('/mi cita|cuando tengo cita|¿qué número de cita tengo\?/', $pregunta)) {
        $respuesta = "Por favor, proporcióname tu número de carnet para buscar tu cita.";
        exit(json_encode(['respuesta' => $respuesta]));
    }

    if (preg_match('/\d{4,10}/', $pregunta, $matches)) {
        $carnet = $matches[0];
    
        // Buscar la cita más próxima del usuario
        $stmt = $pdo->prepare("SELECT nombre_paciente, doctor_id, fecha, hora, id 
                               FROM citas 
                               WHERE carnet_paciente = ? 
                               ORDER BY fecha ASC, hora ASC 
                               LIMIT 1");
        $stmt->execute([$carnet]);
        $cita = $stmt->fetch();
    
        if ($cita) {
            // Obtener el nombre del doctor
            $doctor_id = $cita['doctor_id'];
            $stmt_doctor = $pdo->prepare("SELECT nombre FROM doctores WHERE id = ?");
            $stmt_doctor->execute([$doctor_id]);
            $doctor = $stmt_doctor->fetch();
    
            // Convertir la fecha al formato D/M/A
            $fecha_formateada = date('d/m/Y', strtotime($cita['fecha']));
    
            // Guardamos el ID de la cita en una variable global de sesión para la eliminación posterior
            session_start();
            $_SESSION['cita_id'] = $cita['id'];
    
            // Respuesta con el nombre del doctor en lugar del ID
            $respuesta = "Tu cita está programada con {$doctor['nombre']} para el {$fecha_formateada} a las {$cita['hora']}. El ID de tu cita es {$cita['id']}. ¿Te gustaría eliminarla?";
            exit(json_encode(['respuesta' => $respuesta]));
        } else {
            $respuesta = "No encontré ninguna cita registrada con el carnet: $carnet.";
            exit(json_encode(['respuesta' => $respuesta]));
        }
    }
    

    // Si el usuario confirma eliminar cita
    if (preg_match('/eliminar cita|borrar cita|cancelar cita/', $pregunta)) {
        $respuesta = "¿Estás seguro que deseas eliminar esta cita? Responde 'Sí' para confirmar o 'No' para cancelar.";
        exit(json_encode(['respuesta' => $respuesta]));
    }

    // Si el usuario confirma con "Sí"
    if (preg_match('/\b(sí|si|sii|siempre|claro|por supuesto|correcto)\b/i', $pregunta)) {
        session_start();
        
        if (isset($_SESSION['cita_id'])) {
            $cita_id = $_SESSION['cita_id'];

            // Eliminar la cita de la base de datos usando el campo 'id' de la cita
            $stmt = $pdo->prepare("DELETE FROM citas WHERE id = ?");
            $stmt->execute([$cita_id]);  // Usamos solo el campo 'id'
            unset($_SESSION['cita_id']);  // Limpiar la cita de la sesión
            
            $respuesta = "✅ Tu cita ha sido eliminada exitosamente.";
        } else {
            $respuesta = "No se ha encontrado ninguna cita registrada para eliminar. Asegúrate de haber solicitado correctamente la eliminación.";
        }
        exit(json_encode(['respuesta' => $respuesta]));
    }

    // Si el usuario responde "No"
    if (preg_match('/no/', $pregunta)) {
        $respuesta = "La cita no ha sido eliminada. Si necesitas otra cosa, pregúntame.";
        exit(json_encode(['respuesta' => $respuesta]));
    }

    // Consultar sobre doctores
    if (preg_match('/doctor|especialista|médico/', $pregunta)) {
        $especialidad = null;
        
        if (preg_match('/cardio|corazón|corazón/', $pregunta)) $especialidad = 'Cardiología';
        if (preg_match('/pediatra|niño|infantil/', $pregunta)) $especialidad = 'Pediatría';
        if (preg_match('/ginecólogo|gineco/', $pregunta)) $especialidad = 'Ginecología';
        if (preg_match('/dermatólogo|piel/', $pregunta)) $especialidad = 'Dermatología';

        $sql = "SELECT nombre, especialidad FROM doctores WHERE activo = 1" . 
               ($especialidad ? " AND especialidad = ?" : "") . " LIMIT 5";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($especialidad ? [$especialidad] : []);
        
        $doctores = $stmt->fetchAll();
        
        if (empty($doctores)) {
            $respuesta = "No hay doctores disponibles en este momento.";
        } else {
            $respuesta = "Doctores disponibles:\n";
            foreach ($doctores as $doc) {
                $respuesta .= "👨‍⚕️ {$doc['nombre']}\n";  // Solo el nombre
            }
        }
        
        exit(json_encode(['respuesta' => $respuesta]));
    }

    // Consultar horario de un doctor
    if (preg_match('/horarios|horario/', $pregunta)) {
        // Obtener todos los horarios de los doctores
        $stmt = $pdo->prepare("SELECT nombre, especialidad, horario_disponible FROM doctores WHERE activo = 1");
        $stmt->execute();
        $doctores = $stmt->fetchAll();

        if (empty($doctores)) {
            $respuesta = "No hay doctores disponibles en este momento.";
        } else {
            $respuesta = "Horarios de atención:\n";
            foreach ($doctores as $doctor) {
                $horarios = json_decode($doctor['horario_disponible'], true);
                $respuesta .= "👨‍⚕️ {$doctor['nombre']} ({$doctor['especialidad']}):\n";
                foreach ($horarios as $dia => $horas) {
                    $respuesta .= "{$dia}: {$horas[0]} - {$horas[1]}\n";
                }
            }
        }

        exit(json_encode(['respuesta' => $respuesta]));
    }

    // Si no se encontró una respuesta adecuada
    $defaultRespuesta = "¡Hola! Puedo ayudarte con:\n- Disponibilidad de citas: '¿Hay citas el 20/11/2023 a las 10:00?'\n- Información sobre doctores \n- Horarios de atención.\n\nSi necesitas algo más, solo pregúntame.";
    
    exit(json_encode(['respuesta' => $defaultRespuesta]));
    
} catch (PDOException $e) {
    error_log("Error en chatbot: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['respuesta' => 'Error al procesar tu pregunta']);
}
?>

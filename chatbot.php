<?php
require 'db.php';

header('Content-Type: application/json');
ini_set('memory_limit', '128M');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['respuesta' => 'Método no permitido']));
}

$data = json_decode(file_get_contents('php://input'), true);
$pregunta = trim(strtolower($data['pregunta'] ?? ''));

if (empty($pregunta)) {
    $respuesta = "¡Hola! Puedo ayudarte con:\n\n";
    $respuesta .= "- Disponibilidad de citas: '¿Hay citas el 20/11/2023 a las 10:00?'\n";
    $respuesta .= "- Información sobre doctores\n";
    $respuesta .= "- Horario de atención: Lunes a Viernes de 8:00 AM a 6:00 PM.\n";
    $respuesta .= "Si necesitas algo más, solo pregúntame.";
    exit(json_encode(['respuesta' => $respuesta]));
}

try {
    $respuestas = [
        'hola' => '¡Hola! Soy tu asistente médico. ¿En qué puedo ayudarte?',
        'gracias' => '¡De nada! ¿Necesitas algo más?',
        'horarios' => 'Horario de atención: Lunes a Viernes de 8:00 AM a 6:00 PM.',
        'urgente' => 'Para emergencias llama al 911 o acude a urgencias.',
        'cita' => 'Para agendar una cita, dime la fecha y hora que deseas.'
    ];

    if (isset($respuestas[$pregunta])) {
        exit(json_encode(['respuesta' => $respuestas[$pregunta]]));
    }

// Patrón mejorado para reconocer preguntas sobre disponibilidad
if (preg_match('/¿?(hay|tienen)\s+citas?\s+(el|para el)\s+(\d{1,2})\/(\d{1,2})\/(\d{4})(\s+a\s+las\s+(\d{1,2}):(\d{2}))?/i', $pregunta, $matches)) {
    // Validar fecha
    if (!checkdate($matches[4], $matches[3], $matches[5])) {
        $respuesta = "❌ La fecha {$matches[3]}/{$matches[4]}/{$matches[5]} no es válida.";
    } else {
        $fecha_sql = "{$matches[5]}-{$matches[4]}-{$matches[3]}";
        $hora_solicitada = isset($matches[7]) ? "{$matches[7]}:{$matches[8]}:00" : null;
        
        if ($hora_solicitada) {
            // Consulta optimizada para mostrar información detallada
            $stmt = $pdo->prepare("
                SELECT d.nombre as doctor_nombre, 
                       c.nombre_paciente,
                       TIME_FORMAT(c.hora, '%H:%i') as hora_format
                FROM citas c
                JOIN doctores d ON c.doctor_id = d.id
                WHERE c.fecha = ? 
                AND c.hora = ?
            ");
            $stmt->execute([$fecha_sql, $hora_solicitada]);
            $citas_existentes = $stmt->fetchAll();
            
            if (!empty($citas_existentes)) {
                $respuesta = "📅 Citas existentes el {$matches[3]}/{$matches[4]}/{$matches[5]} a las {$matches[7]}:{$matches[8]}:\n\n";
                foreach ($citas_existentes as $cita) {
                    $respuesta .= "👨‍⚕️ Doctor: {$cita['doctor_nombre']}\n";
                    $respuesta .= "⏰ Hora: {$cita['hora_format']}\n\n";
                }
                
                // Sugerir horas cercanas disponibles
                $stmt = $pdo->prepare("
                    SELECT TIME_FORMAT(hora, '%H:%i') as hora_format
                    FROM citas
                    WHERE fecha = ?
                    AND hora BETWEEN ? AND ?
                    ORDER BY ABS(TIME_TO_SEC(TIMEDIFF(hora, ?)))
                    LIMIT 3
                ");
                $hora_min = date('H:i:s', strtotime($hora_solicitada) - 10800); // 3 horas antes
                $hora_max = date('H:i:s', strtotime($hora_solicitada) + 10800); // 3 horas después
                $stmt->execute([$fecha_sql, $hora_min, $hora_max, $hora_solicitada]);
                $horas_cercanas = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($horas_cercanas)) {
                    $respuesta .= "🕒 Horarios ocupados cercanos:\n";
                    $respuesta .= "• " . implode("\n• ", $horas_cercanas);
                }
            } else {
                // Verificar horario de atención
                $hora_num = (int)$matches[7];
                if ($hora_num < 8 || $hora_num >= 18) {
                    $respuesta = "⚠️ La hora {$matches[7]}:{$matches[8]} está fuera del horario de atención (8:00-18:00).";
                } else {
                    // Mostrar disponibilidad con doctores libres
                    $stmt = $pdo->prepare("
                        SELECT d.id, d.nombre, d.especialidad
                        FROM doctores d
                        WHERE d.activo = 1
                        AND NOT EXISTS (
                            SELECT 1 FROM citas c 
                            WHERE c.doctor_id = d.id 
                            AND c.fecha = ? 
                            AND c.hora = ?
                        )
                    ");
                    $stmt->execute([$fecha_sql, $hora_solicitada]);
                    $doctores_disponibles = $stmt->fetchAll();
                    
                    if (empty($doctores_disponibles)) {
                        $respuesta = "❌ No hay disponibilidad el {$matches[3]}/{$matches[4]}/{$matches[5]} a las {$matches[7]}:{$matches[8]}.";
                    } else {
                        $respuesta = "✅ Disponibilidad el {$matches[3]}/{$matches[4]}/{$matches[5]} a las {$matches[7]}:{$matches[8]}:\n\n";
                        foreach ($doctores_disponibles as $doctor) {
                            $respuesta .= "👨‍⚕️ {$doctor['nombre']} ({$doctor['especialidad']})\n";
                        }
                    }
                }
            }
        } else {
            // CONSULTA PARA DISPONIBILIDAD DEL DÍA COMPLETO
            $stmt = $pdo->prepare("
                SELECT 
                    TIME_FORMAT(hora, '%H:%i') as hora_format,
                    COUNT(*) as total_citas,
                    GROUP_CONCAT(d.nombre SEPARATOR ', ') as doctores_ocupados
                FROM citas c
                JOIN doctores d ON c.doctor_id = d.id
                WHERE c.fecha = ?
                GROUP BY hora
                ORDER BY hora
            ");
            $stmt->execute([$fecha_sql]);
            $horas_ocupadas = $stmt->fetchAll();

            // Obtener todos los doctores activos
            $stmt_doctores = $pdo->prepare("SELECT id, nombre, especialidad FROM doctores WHERE activo = 1");
            $stmt_doctores->execute();
            $todos_doctores = $stmt_doctores->fetchAll();

            if (empty($horas_ocupadas)) {
                $respuesta = "✅ Disponibilidad completa el {$matches[3]}/{$matches[4]}/{$matches[5]} (8:00-18:00).\n\n";
                $respuesta .= "👨‍⚕️ Doctores disponibles todo el día:\n";
                foreach ($todos_doctores as $doctor) {
                    $respuesta .= "- {$doctor['nombre']} ({$doctor['especialidad']})\n";
                }
            } else {
                $respuesta = "📅 Disponibilidad el {$matches[3]}/{$matches[4]}/{$matches[5]}:\n\n";
                $respuesta .= "⏰ Horarios ocupados:\n";
                
                foreach ($horas_ocupadas as $hora) {
                    $respuesta .= "- {$hora['hora_format']}: {$hora['total_citas']} cita(s) con {$hora['doctores_ocupados']}\n";
                }
                
                // Calcular horas disponibles
                $horas_disponibles = [];
                for ($h = 8; $h < 18; $h++) {
                    for ($m = 0; $m < 60; $m += 30) { // Cada media hora
                        $hora_actual = sprintf("%02d:%02d", $h, $m);
                        $hora_ocupada = false;
                        
                        foreach ($horas_ocupadas as $hora) {
                            if ($hora['hora_format'] == $hora_actual) {
                                $hora_ocupada = true;
                                break;
                            }
                        }
                        
                        if (!$hora_ocupada) {
                            $horas_disponibles[] = $hora_actual;
                        }
                    }
                }
                
                $respuesta .= "\n🟢 Horarios disponibles:\n";
                if (count($horas_disponibles) > 10) {
                    $respuesta .= implode(", ", array_slice($horas_disponibles, 0, 10)) . "...\n";
                    $respuesta .= "ℹ️ Más de 10 horarios disponibles. Especifica una hora para más detalles.";
                } else {
                    $respuesta .= implode(", ", $horas_disponibles) . "\n";
                }
                
                // Mostrar doctores con disponibilidad
                $respuesta .= "\n👨‍⚕️ Doctores con horarios disponibles:\n";
                foreach ($todos_doctores as $doctor) {
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as citas
                        FROM citas
                        WHERE doctor_id = ? AND fecha = ?
                    ");
                    $stmt->execute([$doctor['id'], $fecha_sql]);
                    $citas_doctor = $stmt->fetch();
                    
                    if ($citas_doctor['citas'] < 8) {
                        $respuesta .= "- {$doctor['nombre']} ({$doctor['especialidad']}) - " . (8 - $citas_doctor['citas']) . " cupos\n";
                    }
                }
            }
        }
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
            $respuesta = "Tu cita está programada con {$doctor['nombre']} para el {$fecha_formateada} a las {$cita['hora']}. El ID de tu cita es {$cita['id']}. ¿Te gustaría cancelarla?";
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
            $stmt->execute([$cita_id]);
            unset($_SESSION['cita_id']);
            
            $respuesta = "✅ Tu cita ha sido cancelada exitosamente.";
        } else {
            $respuesta = "No se ha encontrado ninguna cita registrada para cancelar. Asegúrate de haber solicitado correctamente la cancelacion.";
        }
        exit(json_encode(['respuesta' => $respuesta]));
    }

    // Si el usuario responde "No"
    if (preg_match('/no/', $pregunta)) {
        $respuesta = "La cita no ha sido cancelada. Si necesitas otra cosa, pregúntame.";
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
                $respuesta .= "👨‍⚕️ {$doc['nombre']}\n";
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
  $defaultRespuesta = "¡Hola! Soy MediBot, tu asistente médico virtual. 😊\n\n"
  . "Puedo ayudarte con:\n\n"
  . "• 📅 Disponibilidad de citas (ej: '¿Hay citas el 20/11/2023 a las 10:00?')\n"
  . "• 🕒 Horarios de atención (ej: '¿Cuál es el horario del Dr. Pérez?')\n"
  . "• ❌ Cancelación de citas (ej: 'Quiero cancelar mi cita')\n\n"
  . "Por favor, dime en qué necesitas ayuda o hazme una pregunta más específica.\n"
  . "¡Estoy aquí para asistirte!";
  exit(json_encode(['respuesta' => $defaultRespuesta]));

} catch (PDOException $e) {
  error_log("Error en chatbot: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['respuesta' => 'Error al procesar tu pregunta']);
}
?>
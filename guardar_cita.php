<?php
require 'db.php';

$datos = json_decode(file_get_contents('php://input'), true);

// Validar campos
$errores = [];

if (empty($datos['nombre'])) {
    $errores[] = "El nombre es obligatorio.";
}

if (empty($datos['doctor_id'])) {
    $errores[] = "Debe seleccionar un doctor.";
}

if (empty($datos['fecha'])) {
    $errores[] = "La fecha es obligatoria.";
} else {
    // Validar la fecha
    if (!empty($datos['fecha']) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $datos['fecha'], $matches)) {
        if (!checkdate($matches[2], $matches[3], $matches[1])) {
            $errores[] = "La fecha ingresada no es válida";
        } else {
            // Verificar si la fecha es en el pasado
            $fechaMySQL = "{$matches[1]}-{$matches[2]}-{$matches[3]}";  // En formato 'YYYY-MM-DD'
            
            if ($fechaMySQL < date('Y-m-d')) {
                $errores[] = "No se pueden agendar citas en fechas pasadas";
            } elseif (date('N', strtotime($fechaMySQL)) >= 6) {  // Validar si es fin de semana (sábado o domingo)
                $errores[] = "No se agendan citas los fines de semana";
            } else {
                $datos['fecha'] = $fechaMySQL;
            }
        }
    } else {
        $errores[] = "Formato de fecha inválido. Use YYYY-MM-DD";
    }
}

// Validar que no haya citas duplicadas para el mismo doctor y hora
if (empty($errores)) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM citas WHERE doctor_id = ? AND fecha = ? AND hora = ?");
    $stmt->execute([$datos['doctor_id'], $datos['fecha'], $datos['hora']]);
    $existeCita = $stmt->fetchColumn();

    if ($existeCita > 0) {
        echo json_encode(['error' => 'Ya existe una cita agendada para este doctor en la misma fecha y hora.']);
        exit; // Detener el proceso si hay un traslape
    }

    // Guardar la cita en la base de datos
    $stmt = $pdo->prepare("INSERT INTO citas (nombre_paciente, carnet_paciente, doctor_id, fecha, hora) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $datos['nombre'],
        $datos['carnet'],
        $datos['doctor_id'],
        $datos['fecha'],
        $datos['hora']
    ]);

    // Obtener la cita recién insertada
    $cita_id = $pdo->lastInsertId();
    $cita = [
        'id' => $cita_id,
        'doctor_id' => $datos['doctor_id'], // Aquí estamos devolviendo el doctor_id
        'fecha' => $datos['fecha'],
        'hora' => $datos['hora'],
    ];

    // Obtener el nombre del doctor con el doctor_id
    $stmt_doctor = $pdo->prepare("SELECT nombre FROM doctores WHERE id = ?");
    $stmt_doctor->execute([$datos['doctor_id']]);
    $doctor = $stmt_doctor->fetch();

    // Agregar el nombre del doctor a la respuesta
    $cita['doctor'] = $doctor['nombre'];  // Añadimos el nombre del doctor

    echo json_encode(['data' => $cita]);
} else {
    echo json_encode(['error' => implode('<br>', $errores)]);
}
?>

<?php
require 'db.php';

$input = file_get_contents("php://input");
$datos = json_decode($input, true);

// Depuración: Si los datos no se reciben correctamente, registrar el error
if (!$datos) {
    echo json_encode(['error' => 'No se recibieron datos válidos.']);
    exit;
}

// Validar datos
$errores = [];

if (empty($datos['cita_id'])) {
    $errores[] = "El ID de la cita es obligatorio.";
}

if (empty($datos['nueva_fecha'])) {
    $errores[] = "La nueva fecha es obligatoria.";
} elseif (strtotime($datos['nueva_fecha']) < strtotime(date('Y-m-d'))) {
    $errores[] = "No se pueden agendar citas en fechas pasadas.";
}

if (empty($datos['nueva_hora'])) {
    $errores[] = "La nueva hora es obligatoria.";
}

// Si hay errores, devolverlos
if (!empty($errores)) {
    echo json_encode(['error' => implode('<br>', $errores)]);
    exit;
}

// Verificar si la cita existe
$stmt = $pdo->prepare("SELECT id, doctor_id FROM citas WHERE id = ?");
$stmt->execute([$datos['cita_id']]);
$cita = $stmt->fetch();

if (!$cita) {
    echo json_encode(['error' => "No se encontró la cita con ID {$datos['cita_id']}"]);
    exit;
}

// Obtener el doctor_id de la cita original
$doctor_id = $cita['doctor_id'];

// Validar que no haya citas duplicadas para el mismo doctor y hora
$stmt = $pdo->prepare("SELECT COUNT(*) FROM citas WHERE doctor_id = ? AND fecha = ? AND hora = ?");
$stmt->execute([$doctor_id, $datos['nueva_fecha'], $datos['nueva_hora']]);
$existeCita = $stmt->fetchColumn();

if ($existeCita > 0) {
    echo json_encode(['error' => 'Ya existe una cita agendada para este doctor en la misma fecha y hora.']);
    exit; // Detener el proceso si hay un traslape
}

// Actualizar la cita en la base de datos
$stmt = $pdo->prepare("UPDATE citas SET fecha = ?, hora = ? WHERE id = ?");
$stmt->execute([$datos['nueva_fecha'], $datos['nueva_hora'], $datos['cita_id']]);

echo json_encode([
    'data' => [
        'id' => $datos['cita_id'],
        'nueva_fecha' => $datos['nueva_fecha'],
        'nueva_hora' => $datos['nueva_hora']
    ]
]);
?>

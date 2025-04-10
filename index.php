<?php
require 'db.php';

// Obtener doctores
$doctores = $pdo->query("SELECT id, nombre, especialidad FROM doctores WHERE activo = 1 LIMIT 50")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda Médica</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Sistema de Agendamiento Médico</h1>
        <button id="toggleForm">Reasignar Cita</button>
        <div class="panel">
            <!-- Formulario de Agendar Cita -->
            <div id="formAgendar" class="form-container">
                <h2>Agendar Nueva Cita</h2>
                <form id="formCita">
                    <div class="form-group">
                        <label for="nombre">Nombre Completo</label>
                        <input type="text" id="nombre" name="nombre" required minlength="3" maxlength="100">
                    </div>

                    <div class="form-group">
                        <label for="carnet">Carnet/N° Identificación (Opcional)</label>
                        <input type="text" id="carnet" name="carnet" pattern="[0-9]{5,20}" title="Solo números (entre 5 y 20 caracteres)">
                    </div>

                    <div class="form-group">
                        <label for="doctor_id">Doctor</label>
                        <select id="doctor_id" name="doctor_id" required>
                            <option value="">Seleccione un doctor</option>
                            <?php foreach ($doctores as $doc): ?>
                                <option value="<?= htmlspecialchars($doc['id']) ?>">
                                    <?= htmlspecialchars($doc['nombre']) ?> - <?= htmlspecialchars($doc['especialidad']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fecha">Fecha</label>
                        <input type="date" id="fecha" name="fecha" required min="<?= date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="hora">Hora</label>
                        <input type="time" id="hora" name="hora" required min="08:00" max="18:30" step="1800">
                    </div>

                    <button type="submit">Agendar Cita</button>
                    <div id="resultadoCita"></div>
                </form>
            </div>

            <!-- Formulario de Reasignar Cita (Oculto inicialmente) -->
            <div id="formReasignar" class="form-container" style="display:none;">
                <h2>Reasignar Cita</h2>
                <form id="formReasignarCita">
                    <label for="cita_id">ID de la Cita</label>
                    <input type="text" id="cita_id" name="cita_id" required>
                    
                    <label for="nueva_fecha">Nueva Fecha</label>
                    <input type="date" id="nueva_fecha" name="nueva_fecha" required>
                    
                    <label for="nueva_hora">Nueva Hora</label>
                    <input type="time" id="nueva_hora" name="nueva_hora" required>
                    
                    <button type="submit">Reasignar Cita</button>
                    <div id="resultadoReasignar"></div>
                </form>
            </div>

            <!-- ASISTENTE VIRTUAL -->
            <div class="chat-container">
                <h2>Asistente Virtual</h2>
                <div id="chatMessages"></div>
                <div class="chat-input">
                    <input type="text" id="pregunta" placeholder="Pregunta sobre disponibilidad...">
                    <button id="btnEnviar">Enviar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('toggleForm').addEventListener('click', function() {
        const formAgendar = document.getElementById('formAgendar');
        const formReasignar = document.getElementById('formReasignar');
        if (formAgendar.style.display === 'none') {
            formAgendar.style.display = 'block';
            formReasignar.style.display = 'none';
            this.textContent = 'Reasignar Cita';
        } else {
            formAgendar.style.display = 'none';
            formReasignar.style.display = 'block';
            this.textContent = 'Agendar Nueva Cita';
        }
    });

        document.addEventListener('DOMContentLoaded', function() {
            // Manejo del formulario de citas
            const formCita = document.getElementById('formCita');
            if (formCita) {
                formCita.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const resultadoDiv = document.getElementById('resultadoCita');
                    resultadoDiv.innerHTML = '<div class="loading">Procesando...</div>';

                    const hora = formCita.hora.value;
                    const minutos = parseInt(hora.split(":")[1]);

                    // Verificar que la hora sea en formato 00 o 30 minutos
            if (minutos !== 0 && minutos !== 30) {
                const horaMessage = document.getElementById('horaMessage');
                if (!horaMessage) {
                    const newMessage = document.createElement('p');
                    newMessage.id = 'horaMessage';
                    newMessage.style.color = 'red';
                    newMessage.innerText = 'Por favor, ingrese una hora válida: debe ser 00 o 30 minutos.';
                    formCita.hora.parentElement.appendChild(newMessage);
                }
                return;
            }

            try {
    const response = await fetch('guardar_cita.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            nombre: formCita.nombre.value,
            carnet: formCita.carnet.value,
            doctor_id: formCita.doctor_id.value,
            fecha: formCita.fecha.value,
            hora: formCita.hora.value
        })
    });

    const data = await response.json();

    if (!response.ok || data.error) {
        throw new Error(data.error || 'Error al agendar la cita.');
    }
    resultadoDiv.innerHTML = `
    <div class="success">
        <p>✅ Cita agendada exitosamente</p>
        <p>Doctor: ${data.data.doctor}</p>  <!-- Aquí cambiamos doctor_id por doctor -->
        <p>Fecha: ${data.data.fecha} a las ${data.data.hora}</p>
        <p>NO. de cita: ${data.data.id}</p>
    </div>
`;

    formCita.reset();
    // Eliminar mensaje de error de la hora
    const horaMessage = document.getElementById('horaMessage');
    if (horaMessage) {
        horaMessage.remove();
    }
} catch (error) {
    resultadoDiv.innerHTML = `<div class="error">❌ Error: ${error.message}</div>`;
}

        });

        // Eliminar el mensaje de error cuando se cambia la hora
        const horaInput = formCita.hora;
        if (horaInput) {
            horaInput.addEventListener('input', function() {
                const horaMessage = document.getElementById('horaMessage');
                if (horaMessage) {
                    horaMessage.remove(); // Eliminar el mensaje de error cuando se cambia el campo
                }
            });
        }
    }

        // Manejo del formulario de reasignación de citas
        const formReasignarCita = document.getElementById('formReasignarCita');
        if (formReasignarCita) {
            formReasignarCita.addEventListener('submit', async function(e) {
                e.preventDefault();
                const resultadoDiv = document.getElementById('resultadoReasignar');
                resultadoDiv.innerHTML = '<div class="loading">Procesando...</div>';

                const formData = {
                    cita_id: document.getElementById('cita_id').value,
                    nueva_fecha: document.getElementById('nueva_fecha').value,
                    nueva_hora: document.getElementById('nueva_hora').value
                };

                try {
                    const response = await fetch('actualizar_cita.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(formData)
                    });

                    const data = await response.json();

                    if (!response.ok || data.error) {
                        throw new Error(data.error || 'Error desconocido');
                    }

                    resultadoDiv.innerHTML = ` 
                        <div class="success">
                            <p>✅ Cita reasignada exitosamente</p>
                            <p>Fecha: ${data.data.nueva_fecha} a las ${data.data.nueva_hora}</p>
                            <p>ID de cita: ${data.data.id}</p>
                        </div>
                    `;

                    formReasignarCita.reset();
                } catch (error) {
                    resultadoDiv.innerHTML = `<div class="error">❌ Error: ${error.message}</div>`;
                }
            });
        }

        // Manejo del Chatbot
        const btnEnviar = document.getElementById('btnEnviar');
        const preguntaInput = document.getElementById('pregunta');
        const chatMessages = document.getElementById('chatMessages');

        btnEnviar.addEventListener('click', async function() {
            const pregunta = preguntaInput.value.trim();

            if (pregunta) {
                // Agregar pregunta al chat
                chatMessages.innerHTML += ` 
                    <div class="user-message">
                        <strong>Tú:</strong> ${pregunta}
                    </div>
                `;
                preguntaInput.value = '';

                // Obtener respuesta del chatbot
                const response = await fetch('chatbot.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ pregunta })
                });

                const data = await response.json();

                // Agregar respuesta del chatbot
                chatMessages.innerHTML += ` 
                    <div class="bot-message">
                        <strong>MediBot:</strong> ${data.respuesta}
                    </div>
                `;
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        });

        // Agregar funcionalidad para presionar Enter
        preguntaInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault(); // Evitar salto de línea
                btnEnviar.click(); // Simular clic del botón
            }
        });
    });
    </script>
</body>
</html>

-- Creación de la base de datos
CREATE DATABASE IF NOT EXISTS `agenda_medica` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `agenda_medica`;

-- Tabla de doctores con IDs aleatorios de 4 dígitos
CREATE TABLE `doctores` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `especialidad` varchar(100) NOT NULL,
  `horario_disponible` longtext CHECK (json_valid(`horario_disponible`)),
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Datos de doctores con IDs aleatorios
INSERT INTO `doctores` (`id`, `nombre`, `especialidad`, `horario_disponible`, `activo`) VALUES
(1425, 'Dr. Carlos Fernández', 'Cardiología', '{"Lunes": ["08:00", "16:00"], "Miércoles": ["09:00", "17:00"]}', 1),
(3568, 'Dra. Ana García', 'Pediatría', '{"Martes": ["08:00", "15:00"], "Jueves": ["10:00", "18:00"]}', 1),
(4789, 'Dra. Laura Méndez', 'Dermatología', '{"Lunes": ["08:00", "12:00"], "Miércoles": ["14:00", "18:00"], "Viernes": ["09:00", "13:00"]}', 1),
(5123, 'Dr. Roberto Jiménez', 'Ortopedia', '{"Martes": ["07:30", "15:30"], "Jueves": ["08:00", "16:00"]}', 1),
(6254, 'Dra. Sofía Ramírez', 'Ginecología', '{"Lunes": ["09:00", "17:00"], "Miércoles": ["08:00", "16:00"]}', 1);

-- Tabla de citas con IDs aleatorios de 4 dígitos y carnets solo numéricos
CREATE TABLE `citas` (
  `id` int(11) NOT NULL,
  `nombre_paciente` varchar(100) NOT NULL,
  `carnet_paciente` varchar(20) DEFAULT NULL CHECK (`carnet_paciente` REGEXP '^[0-9]+$'),
  `doctor_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  PRIMARY KEY (`id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `citas_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `doctores` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Datos de citas con IDs aleatorios y carnets numéricos
INSERT INTO `citas` (`id`, `nombre_paciente`, `carnet_paciente`, `doctor_id`, `fecha`, `hora`) VALUES
(1001, 'María González', '12345678', 1425, '2025-04-15', '09:00:00'),
(2002, 'Juan Pérez', '87654321', 3568, '2025-04-16', '10:30:00'),
(3003, 'Ana López', '11223344', 4789, '2025-04-17', '11:00:00'),
(4004, 'Carlos Sánchez', '55667788', 5123, '2025-04-18', '14:00:00'),
(5005, 'Laura Martínez', '99887766', 6254, '2025-04-19', '15:30:00'),
(6006, 'Pedro Ramírez', '33445566', 1425, '2025-04-20', '16:00:00'),
(7007, 'Sofía Herrera', '77889900', 3568, '2025-04-21', '08:30:00'),
(8008, 'Miguel Díaz', '12121212', 4789, '2025-04-22', '09:45:00'),
(9009, 'Elena Castro', '34343434', 5123, '2025-04-23', '10:15:00'),
(1010, 'Jorge Navarro', '56565656', 6254, '2025-04-24', '13:30:00');

-- Configuración para futuras inserciones
ALTER TABLE `doctores` AUTO_INCREMENT = 9999;
ALTER TABLE `citas` AUTO_INCREMENT = 9999;
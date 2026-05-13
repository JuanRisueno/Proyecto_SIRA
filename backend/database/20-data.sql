/*

CASUÍSTICAS CUBIERTAS:
1. Cliente de entrevista con datos específicos (Cliente 1 - ANTONIO)
2. Cliente con 1 parcela y 1 invernadero con cultivo (Cliente 2 - DAVID MARTÍN)
3. Cliente con múltiples parcelas e invernaderos (algunos vacíos) (Cliente 3 - SERGIO PÉREZ)
4. Cliente con máximo de parcelas e invernaderos todos con cultivo (Cliente 4 - ANA LÓPEZ)
5. Cliente con parcela pero sin invernaderos (parcela sin construir) (Cliente 5 - LAURA GARCÍA)
6. Distribución geográfica en Almería y Murcia
7. Referencias catastrales únicas por parcela

DATOS DE LOS 5 CLIENTES:

CLIENTE 1: ANTONIO (cliente de entrevista)
- Empresa: Invernaderos El Sol de Almería S.L.
- CIF: B04XXXXXX
- Contacto: Antonio (600112233)
- Email: antonio.sol@gmail.com
- Contraseña: sol1234
- 2 parcelas:
  * "La Finca Grande": Polígono 12, Parcela 45 - Níjar, Almería (CP: 04100)
  * "Los Pinos": Camino de la Loma - Águilas, Murcia (CP: 30880)
- 6 invernaderos (según entrevista):
  * Níjar: 4 invernaderos
    - Invernadero 1:  11m - 50m
    - Invernadero 2:  11m - 50m
    - Invernadero 3:  11m - 50m
    - Invernadero 4:  11m - 50m
  * Águilas: 2 invernaderos
    - Invernadero 1:  11m - 50m
    - Invernadero 2:  11m - 50m

CLIENTE 2: DAVID MARTÍN
- Empresa: Cultivos David Martín
- CIF: A12345678
- Contacto: David Martín (950111222)
- Email: david@cultivosdm.com
- Contraseña: sol1234
- 1 parcela en El Ejido, Almería: Polígono 10, Parcela 50
* El Ejido 1 invernderos  
  - Invernadero 1: 9m - 30m

CLIENTE 3: SERGIO PÉREZ
- Empresa: AgroSergio Pérez  
- CIF: B87654321
- Contacto: Sergio Pérez (968333444)
- Email: sergio@agrosergio.com
- Contraseña: sol1234
- 2 parcelas en Murcia:
  * Ctra. Molina de Segura, Km 5 - Molina de Segura
  * Polígono Industrial - Alcantarilla
- 4 invernaderos: 
  * Molina de Segura invernaderos
    - Invernadero 1:  6m - 12m 
    - Invernadero 2: 	6m - 12m 
  * Polígono Industrial - Alcantarilla invernaderos
    - Invernadero 3: 	6m - 12m 
    - Invernadero 4:  6m - 12m 

CLIENTE 4: ANA LÓPEZ
- Empresa: Invernaderos Ana López
- CIF: C11222333
- Contacto: Ana López (950555666)
- Email: ana@invernaderosal.com
- Contraseña: sol1234
- 5 parcelas (2 Almería + 3 Murcia):
  * Paraje Los Llanos - El Ejido, Almería
  * Avda. de Roquetas - Roquetas de Mar, Almería
  * Ctra. Cartagena - Torre Pacheco, Murcia
  * Sangonera la Verde - Murcia (Parcela 1)
  * Sangonera la Verde - Murcia (Parcela 2)
- 10 invernaderos (2 por parcela):
  * Paraje Los Llanos - El Ejido
    - Invernadero 1: 	8m - 25m
    - Invernadero 2: 	8m - 25m
  *  Avda. de Roquetas - Roquetas de Mar 
    - Invernadero 3: 	8m - 25m
    - Invernadero 4:  8m - 25m
  * Ctra. Cartagena - Torre Pacheco 
    - Invernadero 1: 	9m - 30m
    - Invernadero 2: 	9m - 30m
  * Sangonera la Verde (Parcela 1)
    - Invernadero 7: 	6m - 12m 
    - Invernadero 8:  6m - 12m 
  * Sangonera la Verde (Parcela 2)
    - Invernadero 9: 	6m - 12m 
    - Invernadero 10: 6m - 12m 

CLIENTE 5: LAURA GARCÍA
- Empresa: Cultivos Laura García
- CIF: D44333444
- Contacto: Laura García (968777888)
- Email: laura@cultivoslg.es
- Contraseña: sol1234
- 1 parcela en Águilas, Murcia: Ctra. de Águilas, Km 12
- 0 invernaderos (parcela sin construir)

TOTALES FINALES:
- 7 clientes (incluyendo Admin/Root)
- 11 parcelas  
- 21 invernaderos
- 1 parcela sin invernaderos (Cliente 5 - Laura García)
*/

-- ---------------------------------------------------------
-- 2. INSERCIÓN DE DATOS (SEMILLA)
-- ---------------------------------------------------------

-- 1. LOCALIDADES
-- Insertamos todas las localidades necesarias para las parcelas
INSERT INTO LOCALIDAD (codigo_postal, municipio, provincia) VALUES
('04700', 'El Ejido', 'Almería'),
('04600', 'Huércal-Overa', 'Almería'),
('04001', 'Almería', 'Almería'),
('04740', 'Roquetas de Mar', 'Almería'),
('30500', 'Molina de Segura', 'Murcia'),
('30820', 'Alcantarilla', 'Murcia'),
('30700', 'Torre Pacheco', 'Murcia'),
('04100', 'Níjar', 'Almería'),      
('30880', 'Águilas', 'Murcia'),
('30833', 'Sangonera la Verde', 'Murcia');     

-- 2. CULTIVOS (LKB - Local Knowledge Base) - ACTUALIZADO V5.0
-- Predefinimos los cultivos regionales para el Robust MVP
-- Nota: cliente_id = NULL indica que es un cultivo oficial del sistema (inmutable para clientes).
INSERT INTO CULTIVO (nombre_cultivo, cliente_id, activa) VALUES
('Tomate', NULL, TRUE),
('Pimiento', NULL, TRUE),
('Sandía', NULL, TRUE),
('Pepino', NULL, TRUE),
('Melón', NULL, TRUE),
('Calabacín', NULL, TRUE),
('Berenjena', NULL, TRUE),
('Judía verde', NULL, TRUE);

-- 3. PARÁMETROS ÓPTIMOS (Configuraciones para Almería y Murcia)
INSERT INTO PARAMETROS_OPTIMOS (cultivo_id, fase_crecimiento, temp_optima_min, temp_optima_max, humedad_optima_min, humedad_optima_max, necesidad_hidrica, ph_ideal) VALUES
(1, 'General', 15.00, 30.00, 60.00, 80.00, 5.00, 6.4),
(2, 'General', 18.00, 26.00, 65.00, 85.00, 4.50, 6.3),
(3, 'General', 15.00, 30.00, 60.00, 70.00, 3.80, 6.4),
(4, 'General', 17.00, 30.00, 70.00, 90.00, 4.20, 6.3),
(5, 'General', 15.00, 30.00, 60.00, 70.00, 3.80, 6.4),
(6, 'General', 10.00, 35.00, 65.00, 80.00, 4.00, 6.2),
(7, 'General', 15.00, 30.00, 60.00, 80.00, 5.00, 6.2),
(8, 'General', 15.00, 25.00, 65.00, 75.00, 3.20, 6.8);

-- 4. TIPOS DE DISPOSITIVOS (IoT)
INSERT INTO TIPO_SENSOR (nombre_tipo, unidad_medida) VALUES
('Temperatura Aire', 'ºC'),
('Humedad Relativa', '%'),
('Viento', 'km/h'),
('Radiación Solar', 'W/m2'),
('Humedad Suelo', '%');

INSERT INTO TIPO_ACTUADOR (nombre_tipo) VALUES
('Electroválvula Riego'),
('Motor Ventana'),
('Iluminación LED'),
('Ventilador Extractor'),
('Calefacción');

-- 5. CLIENTES
-- Una vez arranques, puedes entrar con:
-- User: root / Pass: root1234
-- User: admin / Pass: admin1234
-- User: B04XXXXXX / Pass: sol1234
INSERT INTO CLIENTE (nombre_empresa, cif, email_admin, telefono, persona_contacto, hash_contrasena, rol, debe_cambiar_pw) VALUES
('SIRA Root', 'root', 'admin@sira.com', '000000000', 'Root', '$2b$12$EEavMHUnjerHr0xWz9GeReqhapgmTqksP3ZR76pV05xHiE4a7YtVW', 'root', TRUE),
('SIRA Administración', 'admin', 'admin@sira.com', '000000000', 'Admin', '$2b$12$Sk4MaDiCEMNeME/dVGdb5eKDw1hlIhCPFRSDdS46vaj1Ndc6XFM3S', 'admin', TRUE),
('Invernaderos El Sol de Almería S.L.', 'B04XXXXXX', 'antonio.sol@gmail.com', '600112233', 'Antonio', '$2b$12$FMW2W10Fwj6ms1XitDc01.sM94ITA3AOQc8Qc1u9t.3M2Sfy3K3c.', 'cliente', TRUE),
('Cultivos David Martín', 'A12345678', 'david@cultivosdm.com', '950111222', 'David Martín', '$2b$12$FMW2W10Fwj6ms1XitDc01.sM94ITA3AOQc8Qc1u9t.3M2Sfy3K3c.', 'cliente', TRUE),
('AgroSergio Pérez', 'B87654321', 'sergio@agrosergio.com', '968333444', 'Sergio Pérez', '$2b$12$FMW2W10Fwj6ms1XitDc01.sM94ITA3AOQc8Qc1u9t.3M2Sfy3K3c.', 'cliente', TRUE),
('Invernaderos Ana López', 'C11222333', 'ana@invernaderosal.com', '950555666', 'Ana López', '$2b$12$FMW2W10Fwj6ms1XitDc01.sM94ITA3AOQc8Qc1u9t.3M2Sfy3K3c.', 'cliente', TRUE),
('Cultivos Laura García', 'D44333444', 'laura@cultivoslg.es', '968777888', 'Laura García', '$2b$12$FMW2W10Fwj6ms1XitDc01.sM94ITA3AOQc8Qc1u9t.3M2Sfy3K3c.', 'cliente', TRUE)
ON CONFLICT (cif) DO NOTHING;

-- 6. PARCELAS
-- Antonio = id=3, David=4, Sergio=5, Ana=6, Laura=7
INSERT INTO PARCELA (cliente_id, codigo_postal, ref_catastral, direccion) VALUES
(3, '04100', '99999999B00001', 'Polígono 12, Parcela 45 - Níjar, Almería'),
(3, '30880', '88888888B00001', 'Camino de la Loma - Águilas, Murcia'),
(4, '04700', '12345678A00001', 'Polígono 10, Parcela 50 - El Ejido, Almería'),
(5, '30500', '11111111B00001', 'Ctra. Molina de Segura, Km 5 - Molina de Segura, Murcia'),
(5, '30820', '22222222B00001', 'Polígono Industrial - Alcantarilla, Murcia'),
(6, '04700', '33333333C00001', 'Paraje Los Llanos - El Ejido, Almería'),
(6, '04740', '44444444C00001', 'Avda. de Roquetas - Roquetas de Mar, Almería'),
(6, '30700', '55555555C00001', 'Ctra. Cartagena - Torre Pacheco, Murcia'),
(6, '30833', '66666666C00001', 'Sangonera la Verde - Parcela A, Murcia'),
(6, '30833', '10101011C00001', 'Sangonera la Verde - Parcela B, Murcia'),
(7, '30880', '77777777D00001', 'Ctra. de Águilas, Km 12 - Águilas, Murcia');

-- 7. INVERNADEROS
INSERT INTO INVERNADERO (nombre, parcela_id, cultivo_id, fecha_plantacion, largo_m, ancho_m) VALUES
('Nave 1', 1, 1, '2026-03-01', 50, 11),
('Nave 2', 1, NULL, NULL, 50, 11),
('Nave 3', 1, NULL, NULL, 50, 11),
('Nave 4', 1, NULL, NULL, 50, 11),
('Nave A', 2, NULL, NULL, 50, 11),
('Nave B', 2, NULL, NULL, 50, 11),
('Invernadero 1', 3, NULL, NULL, 30, 9),
('Invernadero 1', 4, NULL, NULL, 12, 6),
('Invernadero 2', 4, NULL, NULL, 12, 6),
('Invernadero 3', 5, NULL, NULL, 12, 6),
('Invernadero 4', 5, NULL, NULL, 12, 6),
('Invernadero 1', 6, NULL, NULL, 25, 8),
('Invernadero 2', 6, NULL, NULL, 25, 8),
('Invernadero 3', 7, NULL, NULL, 25, 8),
('Invernadero 4', 7, NULL, NULL, 25, 8),
('Invernadero 5', 8, NULL, NULL, 30, 9),
('Invernadero 6', 8, NULL, NULL, 30, 9),
('Invernadero 7', 9, NULL, NULL, 12, 6),
('Invernadero 8', 9, NULL, NULL, 12, 6),
('Invernadero 9', 10, NULL, NULL, 12, 6),
('Invernadero 10', 10, NULL, NULL, 12, 6);

-- 8. SENSORES DE EJERCICIO (Para demostración - 5 Sensores)
INSERT INTO SENSOR (invernadero_id, tipo_sensor_id, ubicacion_sensor, estado_sensor) VALUES
(1, 1, 'Sector Norte', 'Activo'),
(1, 2, 'Sector Norte', 'Activo'),
(1, 3, 'Exterior Techo', 'Activo'),
(1, 4, 'Exterior Techo', 'Activo'),
(1, 5, 'Sustrato Radicular', 'Activo');

-- 9. ACTUADORES DE EJERCICIO (Para demostración - 5 Actuadores)
INSERT INTO ACTUADOR (invernadero_id, tipo_actuador_id, ubicacion_actuador, estado_actuador) VALUES
(1, 1, 'Cabezal Riego', 'Activo'),
(1, 2, 'Ventana Cenital', 'Activo'),
(1, 3, 'Interior Nave', 'Activo'),
(1, 4, 'Frontal Nave', 'Activo'),
(1, 5, 'Perímetro Interno', 'Activo');

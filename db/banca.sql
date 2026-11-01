CREATE DATABASE IF NOT EXISTS banca_linea CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE banca_linea;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS movimientos;
DROP TABLE IF EXISTS terceros;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS cuentas;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE cuentas (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  numero      VARCHAR(20)  NOT NULL UNIQUE,
  nombre      VARCHAR(100) NOT NULL,
  dpi         VARCHAR(13)  NOT NULL,
  saldo       DECIMAL(14,2) NOT NULL DEFAULT 0 CHECK (saldo >= 0),
  creado_por  INT NULL,
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE usuarios (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(100) NOT NULL,
  usuario     VARCHAR(100) NOT NULL UNIQUE,
  salt        CHAR(36)     NOT NULL,
  clave_hash  CHAR(64)     NOT NULL,
  rol         ENUM('ADMIN','CAJERO','CLIENTE') NOT NULL,
  cuenta_id   INT NULL UNIQUE,
  bloqueado   TINYINT(1) NOT NULL DEFAULT 0,
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_usr_cuenta FOREIGN KEY (cuenta_id) REFERENCES cuentas(id)
) ENGINE=InnoDB;

ALTER TABLE cuentas ADD CONSTRAINT fk_cta_cajero FOREIGN KEY (creado_por) REFERENCES usuarios(id);

CREATE TABLE terceros (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id         INT NOT NULL,
  cuenta_id          INT NOT NULL,
  alias              VARCHAR(50) NOT NULL,
  monto_maximo       DECIMAL(14,2) NOT NULL,
  max_trans_diarias  INT NOT NULL,
  creado_en          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tercero (usuario_id, cuenta_id),
  CONSTRAINT fk_ter_usr FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT fk_ter_cta FOREIGN KEY (cuenta_id)  REFERENCES cuentas(id)
) ENGINE=InnoDB;

CREATE TABLE movimientos (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  tipo               ENUM('DEPOSITO','RETIRO','TRANSFERENCIA') NOT NULL,
  cuenta_origen_id   INT NULL,
  cuenta_destino_id  INT NULL,
  tercero_id         INT NULL,
  monto              DECIMAL(14,2) NOT NULL,
  descripcion        VARCHAR(150) NULL,
  usuario_id         INT NOT NULL,
  fecha              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mov_ori FOREIGN KEY (cuenta_origen_id)  REFERENCES cuentas(id),
  CONSTRAINT fk_mov_des FOREIGN KEY (cuenta_destino_id) REFERENCES cuentas(id),
  CONSTRAINT fk_mov_ter FOREIGN KEY (tercero_id)        REFERENCES terceros(id),
  CONSTRAINT fk_mov_usr FOREIGN KEY (usuario_id)        REFERENCES usuarios(id),
  INDEX ix_mov_fecha (fecha)
) ENGINE=InnoDB;

SET @s = UUID();
INSERT INTO usuarios (nombre, usuario, salt, clave_hash, rol)
VALUES ('Administrador', 'admin', @s, SHA2(CONCAT(@s, 'Admin123!'), 256), 'ADMIN');


DELIMITER $$

DROP PROCEDURE IF EXISTS sp_login $$
CREATE PROCEDURE sp_login(IN p_usuario VARCHAR(100), IN p_clave VARCHAR(255), IN p_rol VARCHAR(10))
BEGIN
  DECLARE v_bloq TINYINT;
  SELECT bloqueado INTO v_bloq FROM usuarios
   WHERE usuario = p_usuario AND rol = p_rol
     AND clave_hash = SHA2(CONCAT(salt, p_clave), 256);
  IF v_bloq IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Usuario o clave incorrectos.';
  END IF;
  IF v_bloq = 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El usuario está bloqueado. Contacte al administrador.';
  END IF;
  SELECT id, nombre, usuario, rol, cuenta_id FROM usuarios
   WHERE usuario = p_usuario AND rol = p_rol;
END $$

DROP PROCEDURE IF EXISTS sp_usuario_activo $$
CREATE PROCEDURE sp_usuario_activo(IN p_id INT, IN p_rol VARCHAR(10))
BEGIN
  SELECT id FROM usuarios WHERE id = p_id AND rol = p_rol AND bloqueado = 0;
END $$

DROP PROCEDURE IF EXISTS sp_registrar_cliente $$
CREATE PROCEDURE sp_registrar_cliente(IN p_numero VARCHAR(20), IN p_correo VARCHAR(100),
                                      IN p_dpi VARCHAR(13), IN p_clave VARCHAR(255))
BEGIN
  DECLARE v_cta INT; DECLARE v_dpi VARCHAR(13); DECLARE v_nombre VARCHAR(100);
  DECLARE v_salt CHAR(36);
  SELECT id, dpi, nombre INTO v_cta, v_dpi, v_nombre FROM cuentas WHERE numero = p_numero;
  IF v_cta IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cuenta bancaria no existe.';
  END IF;
  IF v_dpi <> p_dpi THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El DPI no coincide con el registrado en la cuenta.';
  END IF;
  IF EXISTS (SELECT 1 FROM usuarios WHERE cuenta_id = v_cta) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ya existe un usuario registrado para esta cuenta.';
  END IF;
  IF EXISTS (SELECT 1 FROM usuarios WHERE usuario = p_correo) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El correo ya está registrado.';
  END IF;
  SET v_salt = UUID();
  INSERT INTO usuarios (nombre, usuario, salt, clave_hash, rol, cuenta_id)
  VALUES (v_nombre, p_correo, v_salt, SHA2(CONCAT(v_salt, p_clave), 256), 'CLIENTE', v_cta);
END $$

DROP PROCEDURE IF EXISTS sp_cajeros_listar $$
CREATE PROCEDURE sp_cajeros_listar()
BEGIN
  SELECT id, nombre, usuario, bloqueado, creado_en FROM usuarios WHERE rol = 'CAJERO' ORDER BY nombre;
END $$

DROP PROCEDURE IF EXISTS sp_cajero_crear $$
CREATE PROCEDURE sp_cajero_crear(IN p_nombre VARCHAR(100), IN p_usuario VARCHAR(100), IN p_clave VARCHAR(255))
BEGIN
  DECLARE v_salt CHAR(36);
  IF EXISTS (SELECT 1 FROM usuarios WHERE usuario = p_usuario) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El nombre de usuario ya existe.';
  END IF;
  SET v_salt = UUID();
  INSERT INTO usuarios (nombre, usuario, salt, clave_hash, rol)
  VALUES (p_nombre, p_usuario, v_salt, SHA2(CONCAT(v_salt, p_clave), 256), 'CAJERO');
END $$

DROP PROCEDURE IF EXISTS sp_cajero_toggle_bloqueo $$
CREATE PROCEDURE sp_cajero_toggle_bloqueo(IN p_id INT)
BEGIN
  UPDATE usuarios SET bloqueado = 1 - bloqueado WHERE id = p_id AND rol = 'CAJERO';
END $$

DROP PROCEDURE IF EXISTS sp_monitor_resumen $$
CREATE PROCEDURE sp_monitor_resumen()
BEGIN
  SELECT
    (SELECT COUNT(*) FROM cuentas  WHERE DATE(creado_en) = CURDATE())                     AS cuentas_hoy,
    (SELECT COUNT(*) FROM usuarios WHERE rol = 'CLIENTE' AND DATE(creado_en) = CURDATE()) AS clientes_hoy,
    (SELECT COUNT(*) FROM movimientos WHERE tipo = 'TRANSFERENCIA')                       AS transacciones,
    (SELECT COUNT(*) FROM movimientos WHERE tipo = 'DEPOSITO')                            AS depositos,
    (SELECT COUNT(*) FROM movimientos WHERE tipo = 'RETIRO')                              AS retiros,
    (SELECT IFNULL(SUM(monto),0) FROM movimientos WHERE tipo = 'DEPOSITO')                AS monto_depositos,
    (SELECT IFNULL(SUM(monto),0) FROM movimientos WHERE tipo = 'RETIRO')                  AS monto_retiros;
END $$

DROP PROCEDURE IF EXISTS sp_cuenta_crear $$
CREATE PROCEDURE sp_cuenta_crear(IN p_nombre VARCHAR(100), IN p_numero VARCHAR(20), IN p_dpi VARCHAR(13),
                                 IN p_monto DECIMAL(14,2), IN p_cajero INT)
BEGIN
  DECLARE v_id INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  IF p_monto < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El monto inicial no puede ser negativo.';
  END IF;
  IF EXISTS (SELECT 1 FROM cuentas WHERE numero = p_numero) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El número de cuenta ya existe.';
  END IF;
  START TRANSACTION;
    INSERT INTO cuentas (numero, nombre, dpi, saldo, creado_por) VALUES (p_numero, p_nombre, p_dpi, p_monto, p_cajero);
    SET v_id = LAST_INSERT_ID();
    IF p_monto > 0 THEN
      INSERT INTO movimientos (tipo, cuenta_destino_id, monto, descripcion, usuario_id)
      VALUES ('DEPOSITO', v_id, p_monto, 'Monto inicial de apertura', p_cajero);
    END IF;
  COMMIT;
END $$

DROP PROCEDURE IF EXISTS sp_deposito $$
CREATE PROCEDURE sp_deposito(IN p_numero VARCHAR(20), IN p_monto DECIMAL(14,2), IN p_cajero INT)
BEGIN
  DECLARE v_id INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  IF p_monto <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El monto debe ser mayor a cero.';
  END IF;
  START TRANSACTION;
    SELECT id INTO v_id FROM cuentas WHERE numero = p_numero FOR UPDATE;
    IF v_id IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cuenta no existe.';
    END IF;
    UPDATE cuentas SET saldo = saldo + p_monto WHERE id = v_id;
    INSERT INTO movimientos (tipo, cuenta_destino_id, monto, descripcion, usuario_id)
    VALUES ('DEPOSITO', v_id, p_monto, 'Depósito en ventanilla', p_cajero);
  COMMIT;
  SELECT numero, nombre, saldo FROM cuentas WHERE id = v_id;
END $$

DROP PROCEDURE IF EXISTS sp_retiro $$
CREATE PROCEDURE sp_retiro(IN p_numero VARCHAR(20), IN p_monto DECIMAL(14,2), IN p_cajero INT)
BEGIN
  DECLARE v_id INT; DECLARE v_saldo DECIMAL(14,2);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  IF p_monto <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El monto debe ser mayor a cero.';
  END IF;
  START TRANSACTION;
    SELECT id, saldo INTO v_id, v_saldo FROM cuentas WHERE numero = p_numero FOR UPDATE;
    IF v_id IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cuenta no existe.';
    END IF;
    IF v_saldo < p_monto THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Saldo insuficiente.';
    END IF;
    UPDATE cuentas SET saldo = saldo - p_monto WHERE id = v_id;
    INSERT INTO movimientos (tipo, cuenta_origen_id, monto, descripcion, usuario_id)
    VALUES ('RETIRO', v_id, p_monto, 'Retiro en ventanilla', p_cajero);
  COMMIT;
  SELECT numero, nombre, saldo FROM cuentas WHERE id = v_id;
END $$

DROP PROCEDURE IF EXISTS sp_cliente_info $$
CREATE PROCEDURE sp_cliente_info(IN p_usuario INT)
BEGIN
  SELECT c.numero, c.nombre, c.saldo FROM usuarios u JOIN cuentas c ON c.id = u.cuenta_id WHERE u.id = p_usuario;
END $$

DROP PROCEDURE IF EXISTS sp_tercero_agregar $$
CREATE PROCEDURE sp_tercero_agregar(IN p_usuario INT, IN p_numero VARCHAR(20), IN p_monto_max DECIMAL(14,2),
                                    IN p_max_trans INT, IN p_alias VARCHAR(50))
BEGIN
  DECLARE v_cta INT; DECLARE v_propia INT;
  SELECT id INTO v_cta FROM cuentas WHERE numero = p_numero;
  SELECT cuenta_id INTO v_propia FROM usuarios WHERE id = p_usuario;
  IF v_cta IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cuenta bancaria no existe.';
  END IF;
  IF v_cta = v_propia THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No puede agregar su propia cuenta como tercero.';
  END IF;
  IF p_monto_max <= 0 OR p_max_trans <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El monto máximo y la cantidad de transacciones deben ser mayores a cero.';
  END IF;
  IF EXISTS (SELECT 1 FROM terceros WHERE usuario_id = p_usuario AND cuenta_id = v_cta) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Esta cuenta ya está registrada como tercero.';
  END IF;
  INSERT INTO terceros (usuario_id, cuenta_id, alias, monto_maximo, max_trans_diarias)
  VALUES (p_usuario, v_cta, p_alias, p_monto_max, p_max_trans);
END $$

DROP PROCEDURE IF EXISTS sp_terceros_listar $$
CREATE PROCEDURE sp_terceros_listar(IN p_usuario INT)
BEGIN
  SELECT t.id, t.alias, c.numero, c.nombre, t.monto_maximo, t.max_trans_diarias,
         (SELECT COUNT(*) FROM movimientos m WHERE m.tercero_id = t.id AND DATE(m.fecha) = CURDATE()) AS usadas_hoy
    FROM terceros t JOIN cuentas c ON c.id = t.cuenta_id
   WHERE t.usuario_id = p_usuario ORDER BY t.alias;
END $$

DROP PROCEDURE IF EXISTS sp_transferir $$
CREATE PROCEDURE sp_transferir(IN p_usuario INT, IN p_tercero INT, IN p_monto DECIMAL(14,2))
BEGIN
  DECLARE v_origen INT; DECLARE v_destino INT; DECLARE v_max DECIMAL(14,2);
  DECLARE v_max_trans INT; DECLARE v_hoy INT; DECLARE v_saldo DECIMAL(14,2);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  IF p_monto <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El monto debe ser mayor a cero.';
  END IF;
  START TRANSACTION;
    SELECT cuenta_id INTO v_origen FROM usuarios WHERE id = p_usuario;
    SELECT cuenta_id, monto_maximo, max_trans_diarias INTO v_destino, v_max, v_max_trans
      FROM terceros WHERE id = p_tercero AND usuario_id = p_usuario FOR UPDATE;
    IF v_destino IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cuenta de tercero no válida.';
    END IF;
    IF p_monto > v_max THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El monto supera el máximo permitido para esta cuenta.';
    END IF;
    SELECT COUNT(*) INTO v_hoy FROM movimientos WHERE tercero_id = p_tercero AND DATE(fecha) = CURDATE();
    IF v_hoy >= v_max_trans THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Se alcanzó la cantidad máxima de transferencias diarias para esta cuenta.';
    END IF;
    SELECT id INTO @l1 FROM cuentas WHERE id = LEAST(v_origen, v_destino) FOR UPDATE;
    SELECT id INTO @l2 FROM cuentas WHERE id = GREATEST(v_origen, v_destino) FOR UPDATE;
    SELECT saldo INTO v_saldo FROM cuentas WHERE id = v_origen;
    IF v_saldo < p_monto THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Saldo insuficiente.';
    END IF;
    UPDATE cuentas SET saldo = saldo - p_monto WHERE id = v_origen;
    UPDATE cuentas SET saldo = saldo + p_monto WHERE id = v_destino;
    INSERT INTO movimientos (tipo, cuenta_origen_id, cuenta_destino_id, tercero_id, monto, descripcion, usuario_id)
    VALUES ('TRANSFERENCIA', v_origen, v_destino, p_tercero, p_monto, 'Transferencia en línea', p_usuario);
  COMMIT;
END $$

DROP PROCEDURE IF EXISTS sp_estado_cuenta $$
CREATE PROCEDURE sp_estado_cuenta(IN p_usuario INT)
BEGIN
  DECLARE v_cta INT;
  SELECT cuenta_id INTO v_cta FROM usuarios WHERE id = p_usuario;
  SELECT m.fecha, m.tipo, m.descripcion,
         CASE WHEN m.cuenta_destino_id = v_cta THEN m.monto ELSE 0 END AS credito,
         CASE WHEN m.cuenta_origen_id  = v_cta THEN m.monto ELSE 0 END AS debito,
         CASE WHEN m.tipo = 'TRANSFERENCIA' THEN
              IF(m.cuenta_origen_id = v_cta, co_d.numero, co_o.numero) END AS contraparte
    FROM movimientos m
    LEFT JOIN cuentas co_o ON co_o.id = m.cuenta_origen_id
    LEFT JOIN cuentas co_d ON co_d.id = m.cuenta_destino_id
   WHERE m.cuenta_origen_id = v_cta OR m.cuenta_destino_id = v_cta
   ORDER BY m.fecha, m.id;
END $$

DELIMITER ;

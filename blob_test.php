<?php
$mysqli = new mysqli('localhost', 'root', '', 'recrutamento');
$mysqli->set_charset('utf8mb4');
$mysqli->query('ALTER TABLE candidato MODIFY COLUMN curriculo LONGBLOB NULL');
$nome = 'teste_blob';
$email = 'blob@test.com';
$data = str_repeat('A', 2048);
$stmt = $mysqli->prepare('INSERT INTO candidato (nome, email, curriculo) VALUES (?, ?, ?)');
$blob = null;
$stmt->bind_param('ssb', $nome, $email, $blob);
$stmt->send_long_data(2, $data);
$stmt->execute();
$id = $mysqli->insert_id;
$res = $mysqli->query("SELECT id_candidato, LENGTH(curriculo) as len FROM candidato WHERE id_candidato = $id");
$row = $res->fetch_assoc();
var_export($row);
$stmt->close();
$mysqli->close();

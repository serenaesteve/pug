<?php
declare(strict_types=1);

require __DIR__ . "/jvpug.php";


const DB_HOST = "127.0.0.1";
const DB_NAME = "serenaweb";
const DB_USER = "web";
const DB_PASS = "webpass";



mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $c = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  $c->set_charset("utf8mb4");
} catch (Throwable $e) {
  http_response_code(500);
  header("Content-Type: text/plain; charset=utf-8");
  echo "Error conectando a la base de datos.";
  exit;
}

$p = $_GET["p"] ?? null;
if ($p !== null) {
  $p = trim((string)$p);
  if ($p === "") $p = null;
}


$paginas = [];
try {
  $r = $c->query("SELECT * FROM paginas ORDER BY titulo ASC;");
  while ($f = $r->fetch_assoc()) $paginas[] = $f;
} catch (Throwable $e) {
  $paginas = [];
}


$entradas = [];
$pagina = [];

try {
  if ($p === "blog") {
    $r = $c->query("SELECT * FROM entradas ORDER BY id DESC;");
    while ($f = $r->fetch_assoc()) $entradas[] = $f;

  } elseif ($p !== null) {
    $stmt = $c->prepare("SELECT * FROM paginas WHERE titulo = ? LIMIT 1");
    $stmt->bind_param("s", $p);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($f = $res->fetch_assoc()) $pagina[] = $f;
    $stmt->close();
  }
} catch (Throwable $e) {
  $entradas = [];
  $pagina = [];
}


echo JVpug::renderFile(__DIR__ . "/miweb.jvpug", [
  "nombre_site" => "Serena Sania Esteve",
  "titulo_site" => "Técnica administrativa · Estudiante de DAM",
  "p" => $p,
  "paginas" => $paginas,
  "entradas" => $entradas,
  "pagina" => $pagina,
  "year" => date("Y"),
]);


<?php
header("Content-Type: text/css; charset=utf-8");

include __DIR__ . "/colores.php";
foreach ($colores as $color) {
  $c = strtolower($color);
  echo ".b-{$c}{background:{$c};}";
  echo ".c-{$c}{color:{$c};}";
}

for ($i = 0; $i < 2000; $i++) {
  echo ".p-{$i}{padding:{$i}px;}";
  echo ".m-{$i}{margin:{$i}px;}";
  echo ".w-{$i}{width:{$i}px;}";
  echo ".h-{$i}{height:{$i}px;}";
  echo ".fs-{$i}{font-size:{$i}px;}";
  echo ".g-{$i}{gap:{$i}px;}";
}
?>
.flex{display:flex;}
.fd-row{flex-direction:row;}
.fd-column{flex-direction:column;}
.fj-center{justify-content:center;}
.fa-center{align-items:center;}
.fw-wrap{flex-wrap:wrap;}
<?php
include __DIR__ . "/familiasfuentes.php";
foreach ($familias as $familia) {
  $f = strtolower($familia);
  echo ".ff-{$f}{font-family:{$familia};}";
}
?>
.grid{display:grid;}
<?php
for ($i = 1; $i <= 20; $i++) {
  echo ".gc-{$i}{grid-template-columns:repeat({$i},1fr);}";
}
$alineaciones = ['left','right','center','justify'];
foreach ($alineaciones as $a) {
  echo ".ta-{$a}{text-align:{$a};}";
}
?>
.td-none{text-decoration:none;}


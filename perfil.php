<?php
require __DIR__ . '/jvpug.php';

echo JVpug::renderFile(__DIR__ . '/perfil.jvpug', [
  'nombre' => 'Serena Sania Esteve',
  'titulo' => 'Técnica administrativa · Estudiante de Desarrollo de Aplicaciones Multiplataforma',
  'descripcion' =>
    'Técnica administrativa con experiencia en atención al cliente, gestión documental y organización de procesos. '
  . 'Actualmente cursando DAM, con interés en tecnología aplicada a la empresa, IA y automatización.',
  'areas' => [
    'Gestión administrativa y documental',
    'Atención al cliente y comunicación',
    'Organización y planificación',
    'Marketing y redes sociales',
    'CRM y herramientas digitales',
    'IA aplicada a la empresa',
    'Automatización de procesos',
    'Desarrollo de aplicaciones (DAM)',
  ],
  'enfoque_html' =>
    '<strong>Aprender continuamente</strong>, trabajar con organización y comunicación clara, '
  . 'y usar herramientas digitales para aportar soluciones prácticas y eficientes.',
  'datos' => [
    'Email' => 'serenaestevee@gmail.com',
    'Ubicación' => 'Mislata, Valencia',
    'Idiomas' => 'Español (nativo), Valenciano (alto), Inglés (A2)',
    'Vehículo' => 'Sí',
  ],
  'year' => date('Y'),
]);


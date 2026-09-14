<?php

function deploymentTest(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$root = dirname(__DIR__);
$deployment = file_get_contents($root . '/.cpanel.yml');
$ignore = file_get_contents($root . '/.gitignore');
$schema = file_get_contents($root . '/database/schema.sql');
$template = json_decode(
    file_get_contents($root . '/data/delegations.example.json'),
    true,
    512,
    JSON_THROW_ON_ERROR
);

deploymentTest(!preg_match('/cp\s+-R[^\n]*\bdata\b/', $deployment), 'El despliegue no debe copiar data/ de forma recursiva.');
deploymentTest(str_contains($deployment, 'data/delegations.example.json'), 'La plantilla inicial debe desplegarse.');
deploymentTest(str_contains($ignore, '/data/delegations.json'), 'El JSON vivo debe estar ignorado por Git.');
deploymentTest(str_contains($ignore, '/data/delegations.backup.json'), 'El respaldo debe estar ignorado por Git.');
deploymentTest(!preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?delegations`?/i', $schema), 'El módulo no debe crear tablas SQL.');
deploymentTest(isset($template['next_id'], $template['delegations']) && is_array($template['delegations']), 'La plantilla JSON es inválida.');

echo "Delegations deployment persistence tests: OK\n";

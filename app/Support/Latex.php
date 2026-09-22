<?php
namespace App\Support;
final class Latex { public static function escape(?string $value): string { return strtr((string) $value, ['\\' => '\\textbackslash{}', '&' => '\\&', '%' => '\\%', '$' => '\\$', '#' => '\\#', '_' => '\\_', '{' => '\\{', '}' => '\\}', '~' => '\\textasciitilde{}', '^' => '\\textasciicircum{}']); } }

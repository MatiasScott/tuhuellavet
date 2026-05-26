<?php

declare(strict_types=1);

namespace App\Services;

final class FormulaEngineService
{
    private object $engine;

    public function __construct()
    {
        if (!class_exists('Symfony\\Component\\ExpressionLanguage\\ExpressionLanguage')) {
            throw new \RuntimeException('Instala symfony/expression-language para habilitar formulas dinamicas.');
        }

        $engineClass = 'Symfony\\Component\\ExpressionLanguage\\ExpressionLanguage';
        $this->engine = new $engineClass();
    }

    public function detectVariables(string $expression): array
    {
        preg_match_all('/\b[a-zA-Z_][a-zA-Z0-9_]*\b/', $expression, $matches);

        $reserved = ['and', 'or', 'not', 'if', 'in', 'matches'];
        $variables = array_filter(array_unique($matches[0]), static fn (string $name): bool => !in_array(strtolower($name), $reserved, true));

        sort($variables);

        return array_values($variables);
    }

    public function evaluate(string $expression, array $variables): float|int
    {
        $value = $this->engine->evaluate($expression, $variables);

        if (!is_numeric($value)) {
            throw new \RuntimeException('La formula no retorno un valor numerico.');
        }

        return $value + 0;
    }
}

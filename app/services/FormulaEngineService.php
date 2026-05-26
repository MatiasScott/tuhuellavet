<?php

declare(strict_types=1);

namespace App\Services;

final class FormulaEngineService
{
    private const OPERATORS = ['+', '-', '*', '/', '^'];
    private const PRECEDENCE = ['+' => 1, '-' => 1, '*' => 2, '/' => 2, '^' => 3];

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
        $tokens = $this->tokenize($expression);
        $rpn = $this->toRpn($tokens);
        $value = $this->evaluateRpn($rpn, $variables);

        if (!is_numeric($value)) {
            throw new \RuntimeException('La formula no retorno un valor numerico.');
        }

        return (float) $value + 0;
    }

    private function tokenize(string $expression): array
    {
        $tokens = [];
        $length = strlen($expression);
        $i = 0;

        while ($i < $length) {
            $char = $expression[$i];

            if (ctype_space($char)) {
                $i++;
                continue;
            }

            if (ctype_digit($char) || $char === '.') {
                $number = '';
                while ($i < $length && (ctype_digit($expression[$i]) || $expression[$i] === '.')) {
                    $number .= $expression[$i];
                    $i++;
                }

                if (!is_numeric($number)) {
                    throw new \RuntimeException('Numero invalido en la expresion.');
                }

                $tokens[] = ['type' => 'number', 'value' => (float) $number];
                continue;
            }

            if (ctype_alpha($char) || $char === '_') {
                $name = '';
                while ($i < $length && (ctype_alnum($expression[$i]) || $expression[$i] === '_')) {
                    $name .= $expression[$i];
                    $i++;
                }
                $tokens[] = ['type' => 'variable', 'value' => $name];
                continue;
            }

            if (in_array($char, self::OPERATORS, true)) {
                $tokens[] = ['type' => 'operator', 'value' => $char];
                $i++;
                continue;
            }

            if ($char === '(' || $char === ')') {
                $tokens[] = ['type' => 'paren', 'value' => $char];
                $i++;
                continue;
            }

            throw new \RuntimeException('Caracter no permitido en la expresion: ' . $char);
        }

        return $this->normalizeUnaryMinus($tokens);
    }

    private function normalizeUnaryMinus(array $tokens): array
    {
        $normalized = [];

        foreach ($tokens as $index => $token) {
            if (
                $token['type'] === 'operator'
                && $token['value'] === '-'
                && (
                    $index === 0
                    || ($tokens[$index - 1]['type'] === 'operator')
                    || ($tokens[$index - 1]['type'] === 'paren' && $tokens[$index - 1]['value'] === '(')
                )
            ) {
                $normalized[] = ['type' => 'number', 'value' => 0.0];
            }

            $normalized[] = $token;
        }

        return $normalized;
    }

    private function toRpn(array $tokens): array
    {
        $output = [];
        $operators = [];

        foreach ($tokens as $token) {
            if ($token['type'] === 'number' || $token['type'] === 'variable') {
                $output[] = $token;
                continue;
            }

            if ($token['type'] === 'operator') {
                while ($operators !== []) {
                    $top = end($operators);
                    if (!is_array($top) || ($top['type'] ?? null) !== 'operator') {
                        break;
                    }

                    $current = $token['value'];
                    $topOp = $top['value'];

                    $isRightAssociative = $current === '^';
                    $shouldPop = $isRightAssociative
                        ? self::PRECEDENCE[$current] < self::PRECEDENCE[$topOp]
                        : self::PRECEDENCE[$current] <= self::PRECEDENCE[$topOp];

                    if (!$shouldPop) {
                        break;
                    }

                    $output[] = array_pop($operators);
                }

                $operators[] = $token;
                continue;
            }

            if ($token['type'] === 'paren' && $token['value'] === '(') {
                $operators[] = $token;
                continue;
            }

            if ($token['type'] === 'paren' && $token['value'] === ')') {
                $foundOpening = false;

                while ($operators !== []) {
                    $top = array_pop($operators);
                    if (($top['type'] ?? null) === 'paren' && ($top['value'] ?? null) === '(') {
                        $foundOpening = true;
                        break;
                    }
                    $output[] = $top;
                }

                if (!$foundOpening) {
                    throw new \RuntimeException('Parentesis desbalanceados en la formula.');
                }
            }
        }

        while ($operators !== []) {
            $top = array_pop($operators);
            if (($top['type'] ?? null) === 'paren') {
                throw new \RuntimeException('Parentesis desbalanceados en la formula.');
            }
            $output[] = $top;
        }

        return $output;
    }

    private function evaluateRpn(array $tokens, array $variables): float
    {
        $stack = [];

        foreach ($tokens as $token) {
            if ($token['type'] === 'number') {
                $stack[] = (float) $token['value'];
                continue;
            }

            if ($token['type'] === 'variable') {
                $name = (string) $token['value'];
                if (!array_key_exists($name, $variables) || !is_numeric($variables[$name])) {
                    throw new \RuntimeException('Valor invalido o faltante para variable: ' . $name);
                }
                $stack[] = (float) $variables[$name];
                continue;
            }

            if ($token['type'] === 'operator') {
                if (count($stack) < 2) {
                    throw new \RuntimeException('Expresion invalida.');
                }

                $right = (float) array_pop($stack);
                $left = (float) array_pop($stack);

                $result = match ($token['value']) {
                    '+' => $left + $right,
                    '-' => $left - $right,
                    '*' => $left * $right,
                    '/' => $this->safeDivide($left, $right),
                    '^' => $left ** $right,
                    default => throw new \RuntimeException('Operador no soportado.'),
                };

                $stack[] = $result;
            }
        }

        if (count($stack) !== 1) {
            throw new \RuntimeException('Expresion invalida para evaluacion.');
        }

        return (float) $stack[0];
    }

    private function safeDivide(float $left, float $right): float
    {
        if (abs($right) < 0.000000001) {
            throw new \RuntimeException('Division por cero en la formula.');
        }

        return $left / $right;
    }
}

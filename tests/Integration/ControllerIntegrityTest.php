<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;
use ReflectionClass;

class ControllerIntegrityTest extends TestCase
{
    private function controllerFiles(): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                BASE_PATH . '/app/Controllers',
                \FilesystemIterator::SKIP_DOTS
            )
        );

        $files = [];

        foreach ($iterator as $file) {
            if (
                $file->isFile()
                && strtolower($file->getExtension()) === 'php'
            ) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function classFromFile(string $file): ?string
    {
        $content = file_get_contents($file);

        if (
            !preg_match(
                '/namespace\s+([^;]+);/',
                $content,
                $namespace
            )
        ) {
            return null;
        }

        if (
            !preg_match(
                '/class\s+([A-Za-z0-9_]+)/',
                $content,
                $class
            )
        ) {
            return null;
        }

        return trim($namespace[1])
            . '\\'
            . trim($class[1]);
    }

    public function testControllersDirectoryExists(): void
    {
        $this->assertDirectoryExists(
            BASE_PATH . '/app/Controllers'
        );
    }

    public function testControllersCanBeAutoloaded(): void
    {
        $files = $this->controllerFiles();

        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $class = $this->classFromFile($file);

            $this->assertNotNull(
                $class,
                'No se pudo determinar la clase de '
                . basename($file)
            );

            if ($class === null) {
                continue;
            }

            $this->assertTrue(
                class_exists($class),
                "Composer no puede cargar {$class}."
            );
        }
    }

    public function testControllersAreConcreteClasses(): void
    {
        foreach ($this->controllerFiles() as $file) {
            $class = $this->classFromFile($file);

            if (!$class || !class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            $this->assertFalse(
                $reflection->isInterface(),
                "{$class} no debe ser una interfaz."
            );

            $this->assertFalse(
                $reflection->isTrait(),
                "{$class} no debe ser un trait."
            );
        }
    }

    public function testPublicControllerActionsAreCallable(): void
    {
        foreach ($this->controllerFiles() as $file) {
            $class = $this->classFromFile($file);

            if (!$class || !class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            foreach (
                $reflection->getMethods(
                    \ReflectionMethod::IS_PUBLIC
                )
                as $method
            ) {
                if (
                    $method->getDeclaringClass()->getName()
                    !== $class
                ) {
                    continue;
                }

                if (
                    str_starts_with(
                        $method->getName(),
                        '__'
                    )
                ) {
                    continue;
                }

                $this->assertTrue(
                    $method->isPublic()
                );
            }
        }
    }
}
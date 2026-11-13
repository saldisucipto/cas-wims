<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use ReflectionProperty;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $basePath = $this->normalizePath(base_path());

        $this->app->afterResolving('blade.compiler', function (BladeCompiler $compiler) use ($basePath): void {
            $basePathProperty = new ReflectionProperty($compiler, 'basePath');
            $basePathProperty->setAccessible(true);
            $basePathProperty->setValue($compiler, $basePath);
        });
    }

    /**
     * Blade hashes the compiled view file from the path relative to the base path.
     * View paths resolved through the view finder mix separators ("views/rf/x.blade.php")
     * while base_path() uses "\" on Windows, so the same view maps to two compiled files
     * and edits can keep serving a stale template. Normalizing the separators keeps a
     * single compiled file per view.
     */
    private function normalizePath(string $path): string
    {
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }
}

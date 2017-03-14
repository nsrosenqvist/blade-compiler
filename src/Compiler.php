<?php namespace NSRosenqvist\Blade;

// Blade
use Illuminate\View\FileViewFinder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Illuminate\View\Factory;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\View;

class Compiler
{
    function __construct($cacheDir = null, array $paths = [])
    {
        $this->paths = $paths;
        $this->cacheDir = $cacheDir ?? sys_get_temp_dir().'/blade/views';

        if ( ! file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }

        // Register system that Blade depends on
        $this->filesystem = new Filesystem();
        $this->viewFinder = new FileViewFinder($this->filesystem, $paths);
        $this->resolver = new EngineResolver;

        // Next, we will register the various view engines with the resolver so that the
        // environment will resolve the engines needed for various views based on the
        // extension of view file.
        $this->resolver->register('file', function () {
            return new FileEngine;
        });

        $this->resolver->register('php', function () {
            return new PhpEngine;
        });

        // Blade resolver
        $this->compiler = new BladeCompiler($this->filesystem, $this->cacheDir);
        $this->blade = new CompilerEngine($this->compiler);

        $this->resolver->register('blade', function () {
            return $this->blade;
        });

        // create a dispatcher
        $this->dispatcher = new Dispatcher(new Container);

        // build the factory
        $this->factory = new Factory(
            $this->resolver,
            $this->viewFinder,
            $this->dispatcher
        );
    }

    function compile($path, $data = [])
    {
        // If the file can't be found it's probably supplied as a template within
        // one of the base directories
        if ( ! file_exists($path)) {
            $path = $this->viewFinder->find($path);
        }

        // Make sure that we use the right resolver for the initial file
        $engine = $this->factory->getEngineFromPath($path);

        // this path needs to be string
        return $view = new View(
            $this->factory,
            $engine,
            $path, // view (not sure what it does)
            $path,
            $data
        );
    }

    function render($path, $data = [])
    {
        return $this->compile($path, $data)->render();
    }
}
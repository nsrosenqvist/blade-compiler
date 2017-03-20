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
use Illuminate\View\Engines\FileEngine;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\ViewFinderInterface;

class Compiler
{
    function __construct($cacheDir = null, $paths = [])
    {
        // Make sure cache directory exists
        $this->cacheDir = $cacheDir ?? sys_get_temp_dir().'/blade/views';

        if ( ! file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }

        // Register system that Blade depends on, starting with the view finder
        $this->filesystem = new Filesystem();

        if ($paths instanceof ViewFinderInterface) {
            $this->paths = [];
            $this->viewFinder = $paths;
        }
        else {
            $this->paths = $paths;
            $this->viewFinder = new FileViewFinder($this->filesystem, $this->paths);
        }

        // Next, we will register the various view engines with the resolver so that the
        // environment will resolve the engines needed for various views based on the
        // extension of view file.
        $this->resolver = new EngineResolver;

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

    function extend($name, callable $handler) {
        $this->directive($name, $handler);
    }

    function directive($name, callable $handler) {
        $this->compiler->directive($name, $handler);
    }

    function compile($path, array $data = [])
    {
        // If the file can't be found it's probably supplied as a template within
        // one of the base directories
        $path = $this->find($path);

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

    function find($path) {
        if ( ! file_exists($path)) {
            $path = $this->viewFinder->find($path);
        }

        return $path;
    }

    function compiledPath($path)
    {
        return $this->compiler->getCompiledPath($this->find($path));
    }

    function render($path, array $data = [])
    {
        return $this->compile($path, $data)->render();
    }

    public function __call($method, $params)
    {
        return call_user_func_array([$this->factory, $method], $params);
    }
}

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
    /**
     * Create a Blade compiler
     * @param string|null    $cacheDir  The filesystem directory where templates will be cached
     * @param string[]|array $paths     An array of strings to directories that should be available for the compiler
     */
    public function __construct($cacheDir = null, $paths = [])
    {
        // Make sure cache directory exists
        $this->cacheDir = $cacheDir ?? sys_get_temp_dir().'/blade/views';

        if (! file_exists($this->cacheDir)) {
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

    /**
     * Gives full access to edit the blade environment
     * @param  callable $handler  A function that has the compiler as the first argument
     */
    public function modify(callable $handler)
    {
        $handler($this);
    }

    /**
     * Add a blade directive to the compiler
     * @param  string   $name     Name of blade directive
     * @param  callable $handler  The function that handles the directive
     */
    public function directive(string $name, callable $handler)
    {
        $this->compiler->directive($name, $handler);
    }

    /**
    * Shorthand for directive
    * @param  string   $name     Name of blade directive
    * @param  callable $handler  The function that handles the directive
    */
    public function extend(string $name, callable $handler)
    {
        $this->directive($name, $handler);
    }

    /**
    * Shorthand for factory()->setContainer() or getContainer()
    * @param  string|null  $container                         Illuminate Container object
    * @param  \Illuminate\Contracts\Container\Container|null  The container or null
    */
    public function container($container = null)
    {
        if (is_null($container)) {
            return $this->factory->getContainer();
        }
        else {
            $this->factory->share($name, $handler);
            return null;
        }
    }

    /**
     * Compile a specific Blade file
     * @param  string $path           Path to file to compile
     * @param  array  $data           An associative array of data to be passed to the view
     * @return \Illuminate\View\View  Returns the blade view
     */
    public function compile(string $path, array $data = [])
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

    /**
     * Compiles a string with blade directives
     * @param  string $str  String with directives
     * @return string       Compiled string
     */
    public function compileString(string $str)
    {
        return $this->compiler->compileString($str);
    }

    /**
     * Searches the viewFinder for the path provided
     * @param  string $path  Path to search for
     * @return string        If absolute provided and exists, return it, otherwise return result from viewFinder
     */
    public function find(string $path)
    {
        if (! file_exists($path)) {
            $path = $this->viewFinder->find($path);
        }

        return $path;
    }

    /**
     * Get the Blade files compiled version
     * @param  string $path  Path to uncompiled file
     * @return string        Path to cached file
     */
    public function compiledPath(string $path)
    {
        return $this->compiler->getCompiledPath($this->find($path));
    }

    /**
     * Shorthand for compile()->render() to get the rendered view
     * @param  string $path  Path to blade file
     * @param  array  $data  Associative array with data for the view
     * @return string        The compiled view
     */
    public function render(string $path, array $data = [])
    {
        return $this->compile($path, $data)->render();
    }

    /**
     * Every method that is not defined is run against the factory object (for example share)
     * @param  string         $method  Method name
     * @param  string[]|array $params  Associative array with parameters
     * @return mixed                   Whatever factory's method returns
     */
    public function __call($method, $params)
    {
        return call_user_func_array([$this->factory, $method], $params);
    }
}

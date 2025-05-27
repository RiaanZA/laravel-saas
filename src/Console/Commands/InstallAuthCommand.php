<?php

namespace RiaanZA\LaravelSubscription\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class InstallAuthCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'subscription:install-auth
                            {--force : Overwrite existing files}
                            {--skip-npm : Skip NPM dependency installation}';

    /**
     * The console command description.
     */
    protected $description = 'Install complete frontend scaffolding for Laravel Subscription package';

    /**
     * The filesystem instance.
     */
    protected $files;

    /**
     * Create a new command instance.
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Installing Laravel Subscription Frontend...');

        // Setup Inertia.js infrastructure
        $this->setupInertiaInfrastructure();

        // Publish all frontend assets
        $this->publishFrontendAssets();

        // Update app.js to include auth pages
        $this->updateAppJs();

        // Create User model trait if needed
        $this->updateUserModel();

        // Publish configuration if not exists
        $this->publishConfig();

        // Create basic CSS file if needed
        $this->createAppCss();

        // Publish frontend configuration files
        $this->publishFrontendConfig();

        // Install NPM dependencies
        if (!$this->option('skip-npm')) {
            $this->installNpmDependencies();
        } else {
            $this->info('Skipping NPM installation. Run "npm install && npm run build" manually.');
        }

        $this->info('');
        $this->info('Frontend scaffolding installed successfully!');
        $this->info('');
        $this->info('Next steps:');
        if ($this->option('skip-npm')) {
            $this->info('1. Run: npm install && npm run build');
            $this->info('2. Run: php artisan migrate');
        } else {
            $this->info('1. Run: php artisan migrate');
        }
        $this->info('2. Visit /login to test authentication');
        $this->info('3. Visit /subscription/plans to test subscription system');
        $this->info('');

        return 0;
    }

    /**
     * Publish all frontend assets.
     */
    protected function publishFrontendAssets(): void
    {
        $this->info('Publishing frontend views and components...');

        // Define source and destination paths
        $sourcePath = __DIR__ . '/../../../resources/js';

        // Copy all pages to main Pages directory (for Vite manifest)
        $pagesDestination = resource_path('js/Pages');
        if (!$this->files->isDirectory($pagesDestination)) {
            $this->files->makeDirectory($pagesDestination, 0755, true);
        }
        $this->copyDirectory($sourcePath . '/Pages', $pagesDestination);

        // Copy all components to main Components directory
        $componentsDestination = resource_path('js/Components');
        if (!$this->files->isDirectory($componentsDestination)) {
            $this->files->makeDirectory($componentsDestination, 0755, true);
        }
        $this->copyDirectory($sourcePath . '/Components', $componentsDestination);

        // Copy lowercase components directory (used by subscription.js)
        $lowercaseComponentsDestination = resource_path('js/components');
        if (!$this->files->isDirectory($lowercaseComponentsDestination)) {
            $this->files->makeDirectory($lowercaseComponentsDestination, 0755, true);
        }
        $this->copyDirectory($sourcePath . '/components', $lowercaseComponentsDestination);

        // Copy composables directory
        $composablesDestination = resource_path('js/composables');
        if (!$this->files->isDirectory($composablesDestination)) {
            $this->files->makeDirectory($composablesDestination, 0755, true);
        }
        $this->copyDirectory($sourcePath . '/composables', $composablesDestination);

        // Copy any additional JS files (like subscription.js)
        $additionalFiles = ['subscription.js'];
        foreach ($additionalFiles as $file) {
            $sourceFile = $sourcePath . '/' . $file;
            $destinationFile = resource_path('js/' . $file);
            if ($this->files->exists($sourceFile)) {
                $this->files->copy($sourceFile, $destinationFile);
                $this->info("✓ Published {$file}");
            }
        }

        $this->info('✓ All frontend assets published to main directories');
    }

    /**
     * Update app.js to include authentication pages.
     */
    protected function updateAppJs(): void
    {
        $appJsPath = resource_path('js/app.js');

        if (!$this->files->exists($appJsPath)) {
            $this->warn('app.js not found. Creating basic Inertia setup...');
            $this->createAppJs();
            return;
        }

        $content = $this->files->get($appJsPath);

        // Check if already configured for standard page resolution
        if (Str::contains($content, 'import.meta.glob(\'./Pages/**/*.vue\')')) {
            // Check if Ziggy is configured
            if (!Str::contains($content, 'ziggy-js') || !Str::contains($content, 'ZiggyVue')) {
                $this->warn('app.js needs Ziggy configuration. Updating...');
                $this->addZiggyToAppJs($content, $appJsPath);
            } else {
                $this->info('✓ app.js already configured for page resolution and Ziggy');
            }
            return;
        }

        // If app.js exists but doesn't have proper page resolution, update it
        if (!Str::contains($content, 'resolvePageComponent')) {
            $this->warn('app.js exists but needs Inertia setup. Recreating...');
            $this->createAppJs();
            return;
        }

        $this->info('✓ app.js configuration verified');
    }

    /**
     * Create basic app.js if it doesn't exist.
     */
    protected function createAppJs(): void
    {
        $appJsContent = "import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { ZiggyVue } from 'ziggy-js'

createInertiaApp({
  title: (title) => `\${title} - Laravel`,
  resolve: (name) => resolvePageComponent(`./Pages/\${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
  setup({ el, App, props, plugin }) {
    return createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(ZiggyVue)
      .mount(el)
  },
  progress: {
    color: '#4F46E5',
  },
})";

        $this->files->put(resource_path('js/app.js'), $appJsContent);
        $this->info('✓ Created app.js with standard page resolution');
    }

    /**
     * Add Ziggy configuration to existing app.js.
     */
    protected function addZiggyToAppJs(string $content, string $appJsPath): void
    {
        // Add Ziggy import if not present
        if (!Str::contains($content, 'ziggy-js')) {
            $content = str_replace(
                "import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'",
                "import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'\nimport { ZiggyVue } from 'ziggy-js'",
                $content
            );
        }

        // Add ZiggyVue plugin if not present
        if (!Str::contains($content, '.use(ZiggyVue)')) {
            $content = str_replace(
                '.use(plugin)',
                '.use(plugin)\n      .use(ZiggyVue)',
                $content
            );
        }

        $this->files->put($appJsPath, $content);
        $this->info('✓ Added Ziggy configuration to app.js');
    }

    /**
     * Update User model to include subscription traits.
     */
    protected function updateUserModel(): void
    {
        $userModelPath = app_path('Models/User.php');

        if (!$this->files->exists($userModelPath)) {
            $this->warn('User model not found. Please ensure you have a User model.');
            return;
        }

        $content = $this->files->get($userModelPath);

        // Check if trait is already included
        if (Str::contains($content, 'HasSubscriptions')) {
            $this->info('✓ User model already includes subscription traits');
            return;
        }

        // Add use statement
        $useStatement = "use RiaanZA\\LaravelSubscription\\Traits\\HasSubscriptions;";

        if (!Str::contains($content, $useStatement)) {
            $content = str_replace(
                "use Illuminate\\Foundation\\Auth\\User as Authenticatable;",
                "use Illuminate\\Foundation\\Auth\\User as Authenticatable;\n" . $useStatement,
                $content
            );
        }

        // Add trait to class
        if (!Str::contains($content, 'use HasSubscriptions;')) {
            $content = str_replace(
                "use HasApiTokens, HasFactory, Notifiable;",
                "use HasApiTokens, HasFactory, Notifiable, HasSubscriptions;",
                $content
            );
        }

        $this->files->put($userModelPath, $content);
        $this->info('✓ User model updated with subscription traits');
    }

    /**
     * Publish configuration if it doesn't exist.
     */
    protected function publishConfig(): void
    {
        $configPath = config_path('laravel-subscription.php');

        if (!$this->files->exists($configPath)) {
            $this->call('vendor:publish', [
                '--tag' => 'laravel-subscription-config',
                '--force' => $this->option('force'),
            ]);
            $this->info('✓ Configuration file published');
        } else {
            $this->info('✓ Configuration file already exists');
        }
    }

    /**
     * Setup Inertia.js infrastructure.
     */
    protected function setupInertiaInfrastructure(): void
    {
        $this->info('Setting up Inertia.js infrastructure...');

        // Create app.blade.php layout
        $this->createAppLayout();

        // Create Inertia middleware if it doesn't exist
        $this->createInertiaMiddleware();

        // Update Kernel.php to include Inertia middleware
        $this->updateKernel();

        $this->info('✓ Inertia.js infrastructure setup complete');
    }

    /**
     * Create the app.blade.php layout file.
     */
    protected function createAppLayout(): void
    {
        $layoutPath = resource_path('views/app.blade.php');

        if (!$this->files->exists($layoutPath) || $this->option('force')) {
            // Create views directory if it doesn't exist
            if (!$this->files->isDirectory(resource_path('views'))) {
                $this->files->makeDirectory(resource_path('views'), 0755, true);
            }

            $layoutContent = $this->getAppLayoutContent();
            $this->files->put($layoutPath, $layoutContent);
            $this->info('✓ Created app.blade.php layout');
        } else {
            $this->info('✓ app.blade.php layout already exists');
        }
    }

    /**
     * Get the content for app.blade.php layout.
     */
    protected function getAppLayoutContent(): string
    {
        return '<!DOCTYPE html>
<html lang="{{ str_replace(\'_\', \'-\', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config(\'app.name\', \'Laravel\') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite([\'resources/css/app.css\', \'resources/js/app.js\'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
';
    }

    /**
     * Create Inertia middleware if it doesn't exist.
     */
    protected function createInertiaMiddleware(): void
    {
        $middlewarePath = app_path('Http/Middleware/HandleInertiaRequests.php');

        if (!$this->files->exists($middlewarePath) || $this->option('force')) {
            // Create middleware directory if it doesn't exist
            if (!$this->files->isDirectory(app_path('Http/Middleware'))) {
                $this->files->makeDirectory(app_path('Http/Middleware'), 0755, true);
            }

            $middlewareContent = $this->getInertiaMiddlewareContent();
            $this->files->put($middlewarePath, $middlewareContent);
            $this->info('✓ Created HandleInertiaRequests middleware');
        } else {
            $this->info('✓ HandleInertiaRequests middleware already exists');
        }
    }

    /**
     * Get the content for HandleInertiaRequests middleware.
     */
    protected function getInertiaMiddlewareContent(): string
    {
        return '<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = \'app\';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): string|null
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            \'auth\' => [
                \'user\' => $request->user(),
            ],
            \'ziggy\' => fn () => [
                ...(
new Ziggy)->toArray(),
                \'location\' => $request->url(),
            ],
        ];
    }
}
';
    }

    /**
     * Update Kernel.php to include Inertia middleware.
     */
    protected function updateKernel(): void
    {
        $kernelPath = app_path('Http/Kernel.php');

        if (!$this->files->exists($kernelPath)) {
            $this->warn('Kernel.php not found. Please manually add HandleInertiaRequests to your middleware.');
            return;
        }

        $content = $this->files->get($kernelPath);

        // Check if middleware is already registered
        if (Str::contains($content, 'HandleInertiaRequests')) {
            $this->info('✓ HandleInertiaRequests middleware already registered');
            return;
        }

        // Add the middleware to the web group
        $middlewareEntry = "            \App\Http\Middleware\HandleInertiaRequests::class,";

        if (Str::contains($content, "'web' => [")) {
            $content = str_replace(
                "'web' => [",
                "'web' => [\n" . $middlewareEntry,
                $content
            );

            $this->files->put($kernelPath, $content);
            $this->info('✓ Added HandleInertiaRequests to web middleware group');
        } else {
            $this->warn('Could not automatically add middleware. Please manually add HandleInertiaRequests to your web middleware group.');
        }
    }

    /**
     * Create basic app.css file if needed.
     */
    protected function createAppCss(): void
    {
        $cssPath = resource_path('css/app.css');

        if (!$this->files->exists($cssPath) || $this->option('force')) {
            // Create css directory if it doesn't exist
            if (!$this->files->isDirectory(resource_path('css'))) {
                $this->files->makeDirectory(resource_path('css'), 0755, true);
            }

            $cssContent = $this->getAppCssContent();
            $this->files->put($cssPath, $cssContent);
            $this->info('✓ Created app.css with Tailwind CSS');
        } else {
            $this->info('✓ app.css already exists');
        }
    }

    /**
     * Get the content for app.css.
     */
    protected function getAppCssContent(): string
    {
        return '@import "tailwindcss";';
    }

    /**
     * Publish frontend configuration files.
     */
    protected function publishFrontendConfig(): void
    {
        $this->info('Publishing frontend configuration files...');

        $stubsPath = __DIR__ . '/../../../stubs';

        // Handle package.json - merge dependencies if exists, create if not
        $this->handlePackageJson($stubsPath . '/package.json');

        // Publish vite.config.js if it doesn't exist
        $this->publishStubFile($stubsPath . '/vite.config.js', base_path('vite.config.js'), 'vite.config.js');

        // Publish tailwind.config.js if it doesn't exist
        $this->publishStubFile($stubsPath . '/tailwind.config.js', base_path('tailwind.config.js'), 'tailwind.config.js');

        // Publish postcss.config.js if it doesn't exist
        $this->publishStubFile($stubsPath . '/postcss.config.js', base_path('postcss.config.js'), 'postcss.config.js');

        $this->info('✓ Frontend configuration files published');
    }

    /**
     * Publish a stub file if it doesn't exist.
     */
    protected function publishStubFile(string $source, string $destination, string $name): void
    {
        if (!$this->files->exists($destination) || $this->option('force')) {
            if ($this->files->exists($source)) {
                $this->files->copy($source, $destination);
                $this->info("✓ Published {$name}");
            } else {
                $this->warn("Stub file {$name} not found");
            }
        } else {
            $this->info("✓ {$name} already exists");
        }
    }

    /**
     * Handle package.json - merge dependencies or create new.
     */
    protected function handlePackageJson(string $stubPath): void
    {
        $packageJsonPath = base_path('package.json');

        if (!$this->files->exists($stubPath)) {
            $this->warn('package.json stub not found');
            return;
        }

        $stubContent = json_decode($this->files->get($stubPath), true);

        if ($this->files->exists($packageJsonPath) && !$this->option('force')) {
            // Merge with existing package.json
            $existingContent = json_decode($this->files->get($packageJsonPath), true);

            // Merge dependencies
            if (isset($stubContent['dependencies'])) {
                $existingContent['dependencies'] = array_merge(
                    $existingContent['dependencies'] ?? [],
                    $stubContent['dependencies']
                );
            }

            // Merge devDependencies
            if (isset($stubContent['devDependencies'])) {
                $existingContent['devDependencies'] = array_merge(
                    $existingContent['devDependencies'] ?? [],
                    $stubContent['devDependencies']
                );
            }

            // Merge scripts
            if (isset($stubContent['scripts'])) {
                $existingContent['scripts'] = array_merge(
                    $existingContent['scripts'] ?? [],
                    $stubContent['scripts']
                );
            }

            // Set type if not exists
            if (isset($stubContent['type']) && !isset($existingContent['type'])) {
                $existingContent['type'] = $stubContent['type'];
            }

            $this->files->put($packageJsonPath, json_encode($existingContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info('✓ Merged dependencies into existing package.json');
        } else {
            // Create new package.json
            $this->files->copy($stubPath, $packageJsonPath);
            $this->info('✓ Published package.json');
        }
    }

    /**
     * Install NPM dependencies.
     */
    protected function installNpmDependencies(): void
    {
        $this->info('Installing NPM dependencies...');

        // Check if npm is available
        if (!$this->isCommandAvailable('npm')) {
            $this->warn('NPM is not available. Please install Node.js and NPM, then run: npm install');
            return;
        }

        // Check if package.json exists
        if (!$this->files->exists(base_path('package.json'))) {
            $this->warn('package.json not found. Please ensure frontend configuration was published.');
            return;
        }

        // Install dependencies
        $this->info('Running npm install...');
        $process = $this->executeShellCommand('npm install');

        if ($process['success']) {
            $this->info('✓ NPM dependencies installed successfully');

            // Optionally run npm run build
            if ($this->confirm('Would you like to build the assets now?', true)) {
                $this->info('Building assets...');
                $buildProcess = $this->executeShellCommand('npm run build');

                if ($buildProcess['success']) {
                    $this->info('✓ Assets built successfully');
                } else {
                    $this->warn('Asset build failed. You can run "npm run build" manually later.');
                    $this->line('Error: ' . $buildProcess['output']);
                }
            }
        } else {
            $this->warn('NPM install failed. Please run "npm install" manually.');
            $this->line('Error: ' . $process['output']);
        }
    }

    /**
     * Check if a command is available.
     */
    protected function isCommandAvailable(string $command): bool
    {
        $process = $this->executeShellCommand("which {$command}");
        return $process['success'];
    }

    /**
     * Execute a shell command.
     */
    protected function executeShellCommand(string $command): array
    {
        $descriptorspec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open($command, $descriptorspec, $pipes, base_path());

        if (is_resource($process)) {
            fclose($pipes[0]);

            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);

            fclose($pipes[1]);
            fclose($pipes[2]);

            $returnCode = proc_close($process);

            return [
                'success' => $returnCode === 0,
                'output' => $output ?: $error,
                'return_code' => $returnCode,
            ];
        }

        return [
            'success' => false,
            'output' => 'Failed to execute command',
            'return_code' => -1,
        ];
    }

    /**
     * Copy a directory recursively.
     */
    protected function copyDirectory(string $source, string $destination): void
    {
        if (!$this->files->isDirectory($source)) {
            return;
        }

        if (!$this->files->isDirectory($destination)) {
            $this->files->makeDirectory($destination, 0755, true);
        }

        $files = $this->files->allFiles($source);

        foreach ($files as $file) {
            $relativePath = $file->getRelativePathname();
            $destinationFile = $destination . '/' . $relativePath;

            // Create directory if needed
            $destinationDir = dirname($destinationFile);
            if (!$this->files->isDirectory($destinationDir)) {
                $this->files->makeDirectory($destinationDir, 0755, true);
            }

            // Copy file if it doesn't exist or force is enabled
            if (!$this->files->exists($destinationFile) || $this->option('force')) {
                $this->files->copy($file->getPathname(), $destinationFile);
            }
        }
    }
}

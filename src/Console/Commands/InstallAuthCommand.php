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
                            {--force : Overwrite existing files}';

    /**
     * The console command description.
     */
    protected $description = 'Install authentication scaffolding for Laravel Subscription package';

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
        $this->info('Installing Laravel Subscription Authentication...');

        // Publish authentication assets
        $this->publishAuthAssets();

        // Update app.js to include auth pages
        $this->updateAppJs();

        // Create User model trait if needed
        $this->updateUserModel();

        // Publish configuration if not exists
        $this->publishConfig();

        $this->info('');
        $this->info('Authentication scaffolding installed successfully!');
        $this->info('');
        $this->info('Next steps:');
        $this->info('1. Run: npm install && npm run build');
        $this->info('2. Run: php artisan migrate');
        $this->info('3. Visit /login to test authentication');
        $this->info('');

        return 0;
    }

    /**
     * Publish authentication assets.
     */
    protected function publishAuthAssets(): void
    {
        $this->info('Publishing authentication views and components...');

        // Define source and destination paths
        $sourcePath = __DIR__ . '/../../../resources/js';
        $destinationPath = resource_path('js/vendor/laravel-subscription');

        // Create destination directory if it doesn't exist
        if (!$this->files->isDirectory($destinationPath)) {
            $this->files->makeDirectory($destinationPath, 0755, true);
        }

        // Copy auth pages and components
        $this->copyDirectory($sourcePath . '/Pages/Auth', $destinationPath . '/Pages/Auth');
        $this->copyDirectory($sourcePath . '/Components/Auth', $destinationPath . '/Components/Auth');

        $this->info('✓ Authentication views published');
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

        // Check if already configured
        if (Str::contains($content, 'laravel-subscription')) {
            $this->info('✓ app.js already configured for authentication');
            return;
        }

        // Add auth page resolution
        $authPageResolution = "
// Laravel Subscription Auth Pages
const authPages = import.meta.glob('./vendor/laravel-subscription/Pages/Auth/*.vue');
";

        // Update resolve function to include auth pages
        $resolveFunction = "resolve: (name) => {
    // Check for auth pages first
    if (name.startsWith('Auth/')) {
      const authPagePath = `./vendor/laravel-subscription/Pages/\${name}.vue`;
      if (authPages[authPagePath]) {
        return authPages[authPagePath]();
      }
    }
    
    // Default page resolution
    return resolvePageComponent(`./Pages/\${name}.vue`, import.meta.glob('./Pages/**/*.vue'));
  },";

        $updatedContent = str_replace(
            "resolve: (name) => resolvePageComponent(`./Pages/\${name}.vue`, import.meta.glob('./Pages/**/*.vue')),",
            $resolveFunction,
            $content
        );

        // Add auth pages import
        $updatedContent = str_replace(
            "import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'",
            "import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'\n" . $authPageResolution,
            $updatedContent
        );

        $this->files->put($appJsPath, $updatedContent);
        $this->info('✓ app.js updated to include authentication pages');
    }

    /**
     * Create basic app.js if it doesn't exist.
     */
    protected function createAppJs(): void
    {
        $appJsContent = "import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'

// Laravel Subscription Auth Pages
const authPages = import.meta.glob('./vendor/laravel-subscription/Pages/Auth/*.vue');

createInertiaApp({
  title: (title) => `\${title} - Laravel`,
  resolve: (name) => {
    // Check for auth pages first
    if (name.startsWith('Auth/')) {
      const authPagePath = `./vendor/laravel-subscription/Pages/\${name}.vue`;
      if (authPages[authPagePath]) {
        return authPages[authPagePath]();
      }
    }
    
    // Default page resolution
    return resolvePageComponent(`./Pages/\${name}.vue`, import.meta.glob('./Pages/**/*.vue'));
  },
  setup({ el, App, props, plugin }) {
    return createApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el)
  },
  progress: {
    color: '#4F46E5',
  },
})";

        $this->files->put(resource_path('js/app.js'), $appJsContent);
        $this->info('✓ Created app.js with authentication support');
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
